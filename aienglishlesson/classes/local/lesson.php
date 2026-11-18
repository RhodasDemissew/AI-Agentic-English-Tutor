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
    /** Reason code of the last failed validate() call, for diagnostics and automatic retries. */
    public static string $failure = '';

    /** Byte limits per section field (the database and pages rely on these). */
    public const SECTION_LIMITS = ['title' => 255, 'body' => 8000, 'example' => 4000, 'practice' => 4000, 'answer' => 4000,
        'feedback' => 4000, 'hint' => 4000, 'simpler' => 4000, 'extraexample' => 4000, 'audioscript' => 4000];

    private static function text_ok($value, int $max): bool {
        return is_string($value) && strlen($value) <= $max && strpos($value, "\0") === false;
    }

    /** The first rule one section breaks, as a reason code, or null when the section is valid. */
    public static function section_problem($section): ?string {
        if (!is_array($section) || array_diff(array_keys($section), ['kind', 'title', 'body', 'example', 'practice', 'answer',
                'origin', 'cards', 'feedback', 'hint', 'simpler', 'extraexample', 'audioscript']) ||
                !in_array($section['kind'] ?? '', self::KINDS, true) ||
                !in_array($section['origin'] ?? '', ['source', 'generated', 'mixed'], true)) { return 'lessonshape'; }
        foreach (self::SECTION_LIMITS as $key => $max) {
            $optional = in_array($key, ['feedback', 'hint', 'simpler', 'extraexample', 'audioscript'], true);
            if (($optional && !isset($section[$key])) ) { continue; }
            if (!self::text_ok($section[$key] ?? null, $max)) { return 'lessontext'; }
        }
        if (trim($section['title']) === '' || trim($section['body']) === '') { return 'lessonempty'; }
        if (in_array($section['kind'], ['reading', 'listening', 'speaking', 'writing'], true) &&
                trim($section['practice']) === '') { return 'lessontask'; }
        if (in_array($section['kind'], ['reading', 'listening', 'writing'], true) &&
                trim($section['answer']) === '') { return 'lessonanswer'; }
        if ($section['kind'] === 'listening' && trim($section['audioscript'] ?? '') === '') { return 'shortaudio'; }
        if (!is_array($section['cards'] ?? null) || !array_is_list($section['cards']) || count($section['cards']) > 20) { return 'lessoncards'; }
        foreach ($section['cards'] as $card) {
            if (!is_array($card) || array_diff(array_keys($card), ['term', 'meaning', 'example'])) { return 'lessoncards'; }
            foreach (['term', 'meaning', 'example'] as $key) {
                if (!self::text_ok($card[$key] ?? null, 1000) || trim($card[$key]) === '') { return 'lessoncards'; }
            }
        }
        if ($section['kind'] === 'vocabulary' && !$section['cards']) { return 'lessoncards'; }
        return null;
    }

    /** The first rule the quiz breaks, as a reason code, or null when it is valid. */
    public static function quiz_problem($quiz): ?string {
        if (!is_array($quiz) || !array_is_list($quiz) || count($quiz) !== 10) { return 'quizcount'; }
        foreach ($quiz as $question) {
            if (!is_array($question) || array_diff(array_keys($question), ['question', 'choices', 'correct', 'explanation']) ||
                    !is_int($question['correct'] ?? null) || $question['correct'] < 0 || $question['correct'] > 3 ||
                    !is_array($question['choices'] ?? null) || !array_is_list($question['choices']) ||
                    count($question['choices']) !== 4) { return 'quizchoices'; }
            if (!self::text_ok($question['question'] ?? null, 1000) || !self::text_ok($question['explanation'] ?? null, 1500) ||
                    trim($question['question']) === '' || trim($question['explanation']) === '') { return 'quiztext'; }
            foreach ($question['choices'] as $choice) {
                if (!self::text_ok($choice, 500) || trim($choice) === '') { return 'quizchoices'; }
            }
            if (count(array_unique(array_map(static function($choice) {
                return mb_strtolower(trim($choice));
            }, $question['choices']))) !== 4) { return 'quizchoices'; }
        }
        return null;
    }

    /** Shorten text to a byte limit without breaking a character, preferring a sentence or word end. */
    public static function fit(string $text, int $max): string {
        $text = str_replace("\0", '', $text);
        if (strlen($text) <= $max) { return $text; }
        $cut = mb_strcut($text, 0, $max - 3, 'UTF-8');
        $sentence = max(strrpos($cut, '. ') ?: 0, strrpos($cut, ".\n") ?: 0);
        if ($sentence > $max * 0.8) { return substr($cut, 0, $sentence + 1); }
        $space = strrpos($cut, ' ');
        return rtrim($space > $max * 0.8 ? substr($cut, 0, $space) : $cut) . '…';
    }

    /** Fix small, safe problems in one generated section: over-long text and incomplete vocabulary cards. */
    public static function repair_section(array $section): array {
        foreach (self::SECTION_LIMITS as $key => $max) {
            if (isset($section[$key]) && is_string($section[$key])) { $section[$key] = self::fit($section[$key], $max); }
        }
        if (isset($section['cards']) && is_array($section['cards'])) {
            $cards = [];
            foreach ($section['cards'] as $card) {
                if (!is_array($card)) { continue; }
                $clean = [];
                foreach (['term', 'meaning', 'example'] as $key) {
                    $clean[$key] = is_string($card[$key] ?? null) ? self::fit(trim($card[$key]), 1000) : '';
                }
                if ($clean['term'] !== '' && $clean['meaning'] !== '') {
                    if ($clean['example'] === '') { $clean['example'] = $clean['term']; }
                    $cards[] = $clean;
                }
            }
            $section['cards'] = array_slice($cards, 0, 20);
        }
        return $section;
    }

    /**
     * Keep every usable quiz question, fixing what can be fixed safely: long text, missing explanations,
     * duplicate choices (question dropped) and extra questions (first ten kept). May return fewer than ten.
     */
    public static function repair_quiz($quiz): array {
        $out = [];
        foreach (is_array($quiz) ? $quiz : [] as $question) {
            if (!is_array($question) || !is_array($question['choices'] ?? null) || !is_int($question['correct'] ?? null)) { continue; }
            $text = is_string($question['question'] ?? null) ? self::fit(trim($question['question']), 1000) : '';
            $choices = array_values(array_map(static function($choice) {
                return is_string($choice) ? self::fit(trim($choice), 500) : '';
            }, $question['choices']));
            $correct = $question['correct'];
            if ($text === '' || count($choices) !== 4 || $correct < 0 || $correct > 3 || in_array('', $choices, true) ||
                    count(array_unique(array_map('mb_strtolower', $choices))) !== 4) { continue; }
            $explanation = is_string($question['explanation'] ?? null) ? self::fit(trim($question['explanation']), 1500) : '';
            if ($explanation === '') { $explanation = $choices[$correct]; }
            $out[] = ['question' => $text, 'choices' => $choices, 'correct' => $correct, 'explanation' => $explanation];
            if (count($out) === 10) { break; }
        }
        return $out;
    }

    public static function validate(string $json): array {
        self::$failure = '';
        $fail = function(string $reason = 'invalidlesson') {
            self::$failure = $reason;
            throw new \moodle_exception('invalidlesson', 'aienglishlesson');
        };
        if (strlen($json) > 150000) { $fail('lessonsize'); }
        try { $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { $fail('lessonshape'); }
        if (!is_array($data) || array_diff(array_keys($data), ['title', 'focus', 'readable', 'sections', 'quiz', 'activities'])) { $fail('lessonshape'); }
        if (!self::text_ok($data['title'] ?? null, 255)) { $fail('lessontext'); }
        if (trim($data['title']) === '' || !in_array($data['focus'] ?? '', ['reading', 'listening', 'speaking', 'writing'], true) ||
                !is_bool($data['readable'] ?? null)) { $fail('lessonshape'); }
        if (!$data['readable']) { throw new \moodle_exception('unreadablepdf', 'aienglishlesson'); }
        if (!is_array($data['sections'] ?? null) || !array_is_list($data['sections']) || count($data['sections']) < 10 || count($data['sections']) > 20) { $fail('lessonshape'); }
        $kinds = [];
        foreach ($data['sections'] as $section) {
            $problem = self::section_problem($section);
            if ($problem !== null) { $fail($problem); }
            $kinds[] = $section['kind'];
        }
        if (count($kinds) !== count(self::KINDS) || $kinds !== self::KINDS) { $fail('lessonshape'); }
        if (array_key_exists('quiz', $data)) {
            $problem = self::quiz_problem($data['quiz']);
            if ($problem !== null) { $fail($problem); }
        }
        if (array_key_exists('activities', $data)) {
            $clean = self::clean_activities($data['activities']);
            if ($clean === null || count($clean) !== count($data['activities'])) { $fail('lessongames'); }
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
