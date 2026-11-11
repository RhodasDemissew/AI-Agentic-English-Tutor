<?php
namespace {
    define('MOODLE_INTERNAL', true);
    function get_config($component, $name = null) {
        return (object)['provider' => 'gemini', 'eligible' => 1, 'freeonly' => 1,
            'apikey' => 'FICTIONAL', 'model' => 'gemini-3.5-flash-lite'];
    }
}
namespace mod_aienglishlesson\local {
    class source_files {
        public static function parts(array $files, string $pagerange): array {
            return [['text' => 'Test source']];
        }
    }
}
namespace {
    require_once __DIR__ . '/../aienglishlesson/classes/local/provider.php';
    require_once __DIR__ . '/../aienglishlesson/classes/local/provider_error.php';
    require_once __DIR__ . '/../aienglishlesson/classes/local/gemini.php';
    require_once __DIR__ . '/../aienglishlesson/classes/local/lesson.php';

    $section = static function(string $kind, string $script = ''): array {
        return ['kind' => $kind, 'title' => ucfirst($kind), 'body' => 'Lesson content.', 'example' => '',
            'practice' => 'Answer this.', 'answer' => 'Answer.', 'feedback' => '', 'hint' => '',
            'simpler' => '', 'extraexample' => '', 'audioscript' => $script, 'origin' => 'generated', 'cards' => []];
    };
    $short = str_repeat('spoken word ', 75);
    $sections = [$section('reading'), $section('listening', $short), $section('speaking', $short),
        $section('writing'), $section('recap')];
    $partial = ['sections' => array_map($section, ['objectives', 'warmup', 'vocabulary', 'explanation', 'workedexample'])];
    $partial['sections'][2]['cards'] = [['term' => 'nature', 'meaning' => 'the living world',
        'example' => 'Nature includes trees.']];
    $activity = (object)['gradeband' => 'G6', 'pagerange' => '', 'name' => 'Test', 'proficiency' => 'B1',
        'topic' => 'Nature', 'duration' => 60, 'instructions' => ''];
    $mock = new class($sections) extends \mod_aienglishlesson\local\gemini {
        public array $sections;
        public array $quiz = [];
        public array $requestbody = [];
        public function __construct(array $sections) { $this->sections = $sections; }
        protected function request(string $method, string $path, ?array $body = null, int $maxbytes = 1000000,
                int $maxattempts = 3, int $timeout = 0): array {
            $this->requestbody = $body;
            if ($path !== 'models/gemini-3.5-flash-lite:generateContent') { throw new \RuntimeException('Wrong model'); }
            $payload = $body['generationConfig']['maxOutputTokens'] === 4096 ?
                ['quiz' => $this->quiz] : ['readable' => true, 'sections' => $this->sections];
            return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' =>
                [['text' => json_encode($payload)]]]]]];
        }
    };
    $result = $mock->generate_stage($activity, [], 1, $partial);
    if (count($result['sections']) !== 5 || $mock->requestbody['generationConfig']['maxOutputTokens'] !== 16384 ||
            str_word_count($result['sections'][1]['audioscript']) >= 200) {
        throw new \RuntimeException('Short reviewable scripts were not retained');
    }
    $mock->sections[1]['audioscript'] = '';
    try {
        $mock->generate_stage($activity, [], 1, $partial);
        throw new \RuntimeException('Missing script accepted');
    } catch (\mod_aienglishlesson\local\provider_error $e) {
        if ($e->getMessage() !== 'shortaudio') { throw new \RuntimeException('Wrong missing-script diagnostic'); }
    }
    $mock->sections[1]['audioscript'] = $short;
    $mock->sections[0]['practice'] = 'Find the answer in the textbook glossary.';
    try {
        $mock->generate_stage($activity, [], 1, $partial);
        throw new \RuntimeException('External textbook reference accepted');
    } catch (\mod_aienglishlesson\local\provider_error $e) {
        if ($e->getMessage() !== 'externalreference') { throw new \RuntimeException('Wrong external-reference diagnostic'); }
    }
    $mock->sections[0]['practice'] = 'Answer this.';
    $mock->quiz = array_map(static function($i) { return ['question' => 'Question ' . $i,
        'choices' => ['A', 'B', 'C', 'D'], 'correct' => $i % 4,
        'explanation' => 'The lesson explains this.']; }, range(1, 10));
    $quiz = $mock->generate_stage($activity, [], 2, ['sections' => array_merge($partial['sections'], $mock->sections)]);
    $parts = $mock->requestbody['contents'][0]['parts'];
    if (count($quiz['quiz']) !== 10 || count($parts) !== 1 ||
            !str_contains($parts[0]['text'], 'Use only this generated lesson text')) {
        throw new \RuntimeException('Quiz did not use the generated lesson alone');
    }
    echo "Short audio checks passed\n";
}
