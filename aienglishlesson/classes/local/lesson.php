<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
class lesson {
    public const KINDS = ['objectives', 'warmup', 'vocabulary', 'explanation', 'workedexample', 'reading', 'listening', 'speaking', 'writing', 'recap'];
    public static function schema(): array {
        $text = ['type' => 'string'];
        $section = ['type' => 'object', 'properties' => [
            'kind' => ['type' => 'string', 'enum' => self::KINDS], 'title' => $text, 'body' => $text,
            'example' => $text, 'practice' => $text, 'answer' => $text,
            'feedback' => $text, 'hint' => $text, 'simpler' => $text, 'extraexample' => $text, 'audioscript' => $text,
            'origin' => ['type' => 'string', 'enum' => ['source', 'generated', 'mixed']],
            'cards' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'term' => $text, 'meaning' => $text, 'example' => $text], 'required' => ['term', 'meaning', 'example']]],
        ], 'required' => ['kind', 'title', 'body', 'example', 'practice', 'answer', 'feedback', 'hint', 'simpler', 'extraexample', 'audioscript', 'origin', 'cards']];
        return ['type' => 'object', 'properties' => ['title' => $text,
            'focus' => ['type' => 'string', 'enum' => ['reading', 'listening', 'speaking', 'writing']],
            'readable' => ['type' => 'boolean'], 'sections' => ['type' => 'array', 'items' => $section]],
            'required' => ['title', 'focus', 'readable', 'sections']];
    }
    public static function validate(string $json): array {
        if (strlen($json) > 150000) { throw new \moodle_exception('invalidlesson', 'aienglishlesson'); }
        try { $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw new \moodle_exception('invalidlesson', 'aienglishlesson'); }
        $fail = function() { throw new \moodle_exception('invalidlesson', 'aienglishlesson'); };
        $checktext = function($value, $max) use ($fail) {
            if (!is_string($value) || strlen($value) > $max || strpos($value, "\0") !== false) { $fail(); }
        };
        if (!is_array($data) || array_diff(array_keys($data), ['title', 'focus', 'readable', 'sections'])) { $fail(); }
        $checktext($data['title'] ?? null, 255);
        if (trim($data['title']) === '' || !in_array($data['focus'] ?? '', ['reading', 'listening', 'speaking', 'writing'], true) ||
                !is_bool($data['readable'] ?? null)) { $fail(); }
        if (!$data['readable']) { throw new \moodle_exception('unreadablepdf', 'aienglishlesson'); }
        if (!is_array($data['sections'] ?? null) || !array_is_list($data['sections']) || count($data['sections']) < 10 || count($data['sections']) > 20) { $fail(); }
        $kinds = [];
        foreach ($data['sections'] as $section) {
            if (!is_array($section) || array_diff(array_keys($section), ['kind', 'title', 'body', 'example', 'practice', 'answer', 'origin', 'cards', 'feedback', 'hint', 'simpler', 'extraexample', 'audioscript'])) { $fail(); }
            foreach (['feedback', 'hint', 'simpler', 'extraexample', 'audioscript'] as $key) {
                if (isset($section[$key])) { $checktext($section[$key], 4000); }
            }
            if (!in_array($section['kind'] ?? '', self::KINDS, true) ||
                    !in_array($section['origin'] ?? '', ['source', 'generated', 'mixed'], true)) { $fail(); }
            $kinds[] = $section['kind'];
            foreach (['title' => 255, 'body' => 8000, 'example' => 4000, 'practice' => 4000, 'answer' => 4000] as $key => $max) {
                $checktext($section[$key] ?? null, $max);
            }
            if (trim($section['title']) === '' || trim($section['body']) === '') { $fail(); }
            if (in_array($section['kind'], ['reading', 'listening', 'speaking', 'writing'], true) &&
                    trim($section['practice']) === '') { $fail(); }
            if (in_array($section['kind'], ['reading', 'listening', 'writing'], true) &&
                    trim($section['answer']) === '') { $fail(); }
            if ($section['kind'] === 'listening' && trim($section['audioscript'] ?? '') === '') { $fail(); }
            if (!is_array($section['cards'] ?? null) || !array_is_list($section['cards']) || count($section['cards']) > 20) { $fail(); }
            foreach ($section['cards'] as $card) {
                if (!is_array($card) || array_diff(array_keys($card), ['term', 'meaning', 'example'])) { $fail(); }
                foreach (['term', 'meaning', 'example'] as $key) {
                    $checktext($card[$key] ?? null, 1000);
                    if (trim($card[$key]) === '') { $fail(); }
                }
            }
            if ($section['kind'] === 'vocabulary' && !$section['cards']) { $fail(); }
        }
        if (count($kinds) !== count(self::KINDS) || $kinds !== self::KINDS) { $fail(); }
        return $data;
    }
    public static function student_data(array $data): array {
        foreach ($data['sections'] as &$section) { unset($section['answer'], $section['feedback']); }
        return $data;
    }
}
