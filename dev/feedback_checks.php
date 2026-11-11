<?php
namespace {
    define('MOODLE_INTERNAL', true);
    function get_config($component, $name = null) {
        return (object)['provider' => 'gemini', 'eligible' => 1, 'freeonly' => 1,
            'apikey' => 'FICTIONAL', 'model' => 'gemini-3.5-flash-lite'];
    }
}
namespace {
    require_once __DIR__ . '/../aienglishlesson/classes/local/provider.php';
    require_once __DIR__ . '/../aienglishlesson/classes/local/provider_error.php';
    require_once __DIR__ . '/../aienglishlesson/classes/local/gemini.php';

    $provider = new class extends \mod_aienglishlesson\local\gemini {
        public string $feedback = '';
        public array $body = [];
        protected function request(string $method, string $path, ?array $body = null, int $maxbytes = 1000000,
                int $maxattempts = 3, int $timeout = 0): array {
            $this->body = $body;
            return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' =>
                [['text' => json_encode(['score' => 25, 'confidence' => 80, 'feedback' => $this->feedback])]]]]]];
        }
    };
    $section = ['kind' => 'reading', 'practice' => 'Explain the design.', 'body' => 'A reading passage.',
        'answer' => 'The kingfisher beak inspired the train nose.'];
    $provider->feedback = 'Review the passage and explain how the example supports your claim.';
    $result = $provider->assess($section, 'I need to explain more.', 'Use evidence.', []);
    if ($result['score'] !== 25 || !str_contains($provider->body['contents'][0]['parts'][0]['text'],
            'Never reveal, quote, paraphrase')) {
        throw new \RuntimeException('Safe feedback guidance missing');
    }
    $provider->feedback = 'The kingfisher beak inspired the train nose.';
    try {
        $provider->assess($section, 'I need to explain more.', 'Use evidence.', []);
        throw new \RuntimeException('Verbatim reference answer leaked');
    } catch (\mod_aienglishlesson\local\provider_error $e) {
        if ($e->getMessage() !== 'response') { throw new \RuntimeException('Wrong unsafe feedback result'); }
    }
    echo "Feedback checks passed\n";
}
