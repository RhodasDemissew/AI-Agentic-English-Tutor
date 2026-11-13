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
        $question = ['type' => 'object', 'properties' => ['question' => $text,
            'choices' => ['type' => 'array', 'items' => $text], 'correct' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 3],
            'explanation' => $text], 'required' => ['question', 'choices', 'correct', 'explanation']];
        return ['type' => 'object', 'properties' => ['title' => $text, 'activities' => ['type' => 'array', 'items' => self::activity_schema()],
            'focus' => ['type' => 'string', 'enum' => ['reading', 'listening', 'speaking', 'writing']],
            'readable' => ['type' => 'boolean'], 'sections' => ['type' => 'array', 'items' => $section],
            'quiz' => ['type' => 'array', 'items' => $question]],
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
        if (!is_array($data) || array_diff(array_keys($data), ['title', 'focus', 'readable', 'sections', 'quiz', 'activities'])) { $fail(); }
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
        if (array_key_exists('quiz', $data)) {
            if (!is_array($data['quiz']) || !array_is_list($data['quiz']) || count($data['quiz']) !== 10) { $fail(); }
            foreach ($data['quiz'] as $question) {
                if (!is_array($question) || array_diff(array_keys($question), ['question', 'choices', 'correct', 'explanation']) ||
                        !is_int($question['correct'] ?? null) || $question['correct'] < 0 || $question['correct'] > 3 ||
                        !is_array($question['choices'] ?? null) || !array_is_list($question['choices']) ||
                        count($question['choices']) !== 4) { $fail(); }
                $checktext($question['question'] ?? null, 1000);
                $checktext($question['explanation'] ?? null, 1500);
                if (trim($question['question']) === '' || trim($question['explanation']) === '') { $fail(); }
                foreach ($question['choices'] as $choice) {
                    $checktext($choice, 500);
                    if (trim($choice) === '') { $fail(); }
                }
                if (count(array_unique(array_map(static function($choice) {
                    return mb_strtolower(trim($choice));
                }, $question['choices']))) !== 4) { $fail(); }
            }
        }
        if (array_key_exists('activities', $data)) {
            $clean = self::clean_activities($data['activities']);
            if ($clean === null || count($clean) !== count($data['activities'])) { $fail(); }
        }
        return $data;
    }

    /** Practice game types generated with the quiz and scored by PHP. */
    public const ACTIVITY_TYPES = ['order', 'match', 'gap'];

    public static function activity_schema(): array {
        $text = ['type' => 'string'];
        return ['type' => 'object', 'properties' => [
            'type' => ['type' => 'string', 'enum' => self::ACTIVITY_TYPES], 'prompt' => $text,
            'items' => ['type' => 'array', 'items' => $text],
            'pairs' => ['type' => 'array', 'items' => ['type' => 'object',
                'properties' => ['left' => $text, 'right' => $text], 'required' => ['left', 'right']]],
            'sentence' => $text, 'choices' => ['type' => 'array', 'items' => $text],
            'correct' => ['type' => 'integer'],
        ], 'required' => ['type', 'prompt', 'items', 'pairs', 'sentence', 'choices', 'correct']];
    }

    /**
     * Normalise practice games to their type-specific fields. Invalid items are dropped.
     * Returns null when the value is not a usable list (fewer than 3 valid games).
     */
    public static function clean_activities($activities): ?array {
        if (!is_array($activities) || !array_is_list($activities) || count($activities) > 10) { return null; }
        $str = static function($value, int $max): ?string {
            if (!is_string($value) || strpos($value, "\0") !== false) { return null; }
            $value = trim(preg_replace('/\s+/u', ' ', $value));
            return $value === '' || \core_text::strlen($value) > $max ? null : $value;
        };
        $unique = static function(array $values): bool {
            return count(array_unique(array_map('mb_strtolower', $values))) === count($values);
        };
        $out = [];
        foreach ($activities as $item) {
            if (!is_array($item) || !in_array($item['type'] ?? '', self::ACTIVITY_TYPES, true)) { continue; }
            $prompt = $str($item['prompt'] ?? null, 300);
            if ($prompt === null) { continue; }
            if ($item['type'] === 'order') {
                $words = [];
                foreach ((array)($item['items'] ?? []) as $word) {
                    $word = $str($word, 60);
                    if ($word === null) { continue 2; }
                    $words[] = $word;
                }
                if (count($words) < 3 || count($words) > 10 || !$unique($words)) { continue; }
                $out[] = ['type' => 'order', 'prompt' => $prompt, 'items' => $words];
            } else if ($item['type'] === 'match') {
                $pairs = [];
                foreach ((array)($item['pairs'] ?? []) as $pair) {
                    $left = is_array($pair) ? $str($pair['left'] ?? null, 200) : null;
                    $right = is_array($pair) ? $str($pair['right'] ?? null, 200) : null;
                    if ($left === null || $right === null) { continue 2; }
                    $pairs[] = ['left' => $left, 'right' => $right];
                }
                if (count($pairs) < 3 || count($pairs) > 5 || !$unique(array_column($pairs, 'left')) ||
                        !$unique(array_column($pairs, 'right'))) { continue; }
                $out[] = ['type' => 'match', 'prompt' => $prompt, 'pairs' => $pairs];
            } else {
                $sentence = $str($item['sentence'] ?? null, 400);
                $choices = [];
                foreach ((array)($item['choices'] ?? []) as $choice) {
                    $choice = $str($choice, 100);
                    if ($choice === null) { continue 2; }
                    $choices[] = $choice;
                }
                $correct = $item['correct'] ?? null;
                if ($sentence === null || substr_count($sentence, '___') !== 1 || count($choices) < 3 ||
                        count($choices) > 4 || !$unique($choices) || !is_int($correct) || $correct < 0 ||
                        $correct >= count($choices)) { continue; }
                $out[] = ['type' => 'gap', 'prompt' => $prompt, 'sentence' => $sentence, 'choices' => $choices,
                    'correct' => $correct];
            }
        }
        return count($out) >= 3 ? $out : null;
    }

    public static function student_data(array $data): array {
        foreach ($data['sections'] as &$section) {
            unset($section['answer'], $section['feedback']);
            if (!in_array($section['kind'], ['reading', 'listening', 'speaking', 'writing'], true)) {
                unset($section['practice'], $section['hint']);
            }
        }
        if (isset($data['quiz'])) {
            foreach ($data['quiz'] as &$question) { unset($question['correct'], $question['explanation']); }
            unset($question);
        }
        return $data;
    }

    /** Identify assessed questions that may rely on a visual the lesson does not display. Review only. */
    public static function visual_question_references(array $data): array {
        $references = [];
        $visualword = '/\b(picture|pictures|pictured|image|images|photo|photos|photograph|photographs|diagram|diagrams|figure|figures|illustration|illustrations|drawing|drawings|chart|charts|graph|graphs|map|maps)\b/iu';
        foreach ($data['sections'] ?? [] as $i => $section) {
            if (in_array($section['kind'] ?? '', ['reading', 'listening', 'speaking', 'writing'], true) &&
                    preg_match($visualword, $section['practice'] ?? '')) {
                $references[] = ['type' => 'section', 'index' => $i];
            }
        }
        foreach ($data['quiz'] ?? [] as $i => $question) {
            if (preg_match($visualword, implode(' ', array_merge([$question['question'] ?? ''], $question['choices'] ?? [])))) {
                $references[] = ['type' => 'quiz', 'index' => $i];
            }
        }
        return $references;
    }
}
