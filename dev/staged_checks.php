<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local only'); }
$fixture = json_decode(file_get_contents('/var/moodledata/aiel-test.json'));
$cm = get_coursemodule_from_id('aienglishlesson', $fixture->cmid);
$activity = $DB->get_record('aienglishlesson', ['id' => $cm->instance]);
$files = get_file_storage()->get_area_files(context_module::instance($cm->id)->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
$pdf = reset($files);
$content = $DB->get_field('aiel_versions', 'content', ['id' => $fixture->draft]);
$settings = ['provider' => 'gemini', 'eligible' => 1, 'freeonly' => 1, 'apikey' => 'FICTIONAL', 'model' => 'gemini-3.5-flash-lite'];
$original = [];
foreach ($settings as $k => $v) { $original[$k] = get_config('aienglishlesson', $k); set_config($k, $v, 'aienglishlesson'); }
function verify($ok, $message) { if (!$ok) { throw new Exception($message); } echo "PASS: $message\n"; }
try {
    $mock = new class($content) extends \mod_aienglishlesson\local\gemini {
        public $body; public $calls = 0; public $fail = false; private $content;
        public function __construct($content) { $this->content = $content; }
        protected function request(string $method, string $path, ?array $body = null, int $maxbytes = 1000000): array {
            $this->calls++; $this->body = $body;
            verify($method === 'POST' && $path === 'models/gemini-3.5-flash-lite:generateContent', 'Same generation endpoint and model');
            if ($this->fail) { throw new \mod_aienglishlesson\local\provider_error('http503', true); }
            return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => isset($body['generationConfig']['responseSchema']) ? $this->content : 'Hello.']]]]]];
        }
    };
    $before = [$DB->count_records('aiel_versions'), $DB->count_records('aiel_jobs'), $DB->count_records('aiel_usage')];
    $mock->diagnose(1, $activity, $pdf);
    verify(count($mock->body['contents'][0]['parts']) === 1 && !isset($mock->body['generationConfig']['responseSchema']), 'Text test contains no PDF or schema');
    $mock->diagnose(2, $activity, $pdf);
    verify(isset($mock->body['contents'][0]['parts'][0]['inlineData']) && !isset($mock->body['generationConfig']['responseSchema']), 'PDF test includes file but no schema');
    $mock->diagnose(3, $activity, $pdf);
    verify(isset($mock->body['generationConfig']['responseSchema'], $mock->body['systemInstruction']), 'Full test exercises actual generation contract');
    verify($mock->calls === 3, 'One provider request per explicit test');
    $mock->fail = true;
    try { $mock->diagnose(1, $activity, $pdf); throw new Exception('Expected failure'); }
    catch (\mod_aienglishlesson\local\provider_error $e) { verify($e->getMessage() === 'http503', 'Exact safe error preserved without retry'); }
    verify($before === [$DB->count_records('aiel_versions'), $DB->count_records('aiel_jobs'), $DB->count_records('aiel_usage')], 'No drafts/jobs or real API attempts created');
} finally {
    foreach ($original as $k => $v) { if ($v === false) { unset_config($k, 'aienglishlesson'); } else { set_config($k, $v, 'aienglishlesson'); } }
}
