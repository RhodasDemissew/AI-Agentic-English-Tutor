<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local only'); }
$fixture = json_decode(file_get_contents('/var/moodledata/aiel-test.json'));
$cm = get_coursemodule_from_id('aienglishlesson', $fixture->cmid);
$activity = $DB->get_record('aienglishlesson', ['id' => $cm->instance]);
$files = get_file_storage()->get_area_files(context_module::instance($cm->id)->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
$files = array_values($files);
$content = $DB->get_field('aiel_versions', 'content', ['id' => $fixture->draft]);
$settings = ['provider' => 'gemini', 'eligible' => 1, 'freeonly' => 1, 'apikey' => 'FICTIONAL', 'model' => 'gemini-3.5-flash-lite'];
$original = [];
foreach ($settings as $k => $v) { $original[$k] = get_config('aienglishlesson', $k); set_config($k, $v, 'aienglishlesson'); }
function verify($ok, $message) { if (!$ok) { throw new Exception($message); } echo "PASS: $message\n"; }
try {
    $mock = new class($content) extends \mod_aienglishlesson\local\gemini {
        public $body; public $calls = 0; public $fail = false; public $invalid = false; private $content;
        public function __construct($content) { $this->content = $content; }
        protected function request(string $method, string $path, ?array $body = null, int $maxbytes = 1000000,
                int $maxattempts = 3, int $timeout = 0): array {
            $this->calls++; $this->body = $body;
            verify($method === 'POST' && $path === 'models/gemini-3.5-flash-lite:generateContent', 'Same generation endpoint and model');
            if ($this->fail) { throw new \mod_aienglishlesson\local\provider_error('http503', true); }
            $full = json_decode($this->content, true);
            $stageone = json_encode(['readable' => true, 'sections' => array_slice($full['sections'], 0, 5)]);
            return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $this->invalid ? '{"title":"incomplete"}' :
                (($body['generationConfig']['maxOutputTokens'] ?? 0) === 8192 ? $stageone : (isset($body['generationConfig']['responseMimeType']) ? '{"topic":"English"}' : 'Hello.'))]]]]]];
        }
    };
    $before = [$DB->count_records('aiel_versions'), $DB->count_records('aiel_jobs'), $DB->count_records('aiel_usage')];
    $mock->diagnose(1, $activity, $files);
    verify(count($mock->body['contents'][0]['parts']) === 1 && !isset($mock->body['generationConfig']['responseSchema']), 'Text test contains no PDF or schema');
    $mock->diagnose(2, $activity, $files);
    verify(count(array_filter($mock->body['contents'][0]['parts'], static function($part) { return isset($part['inlineData']); })) === 1 &&
        !isset($mock->body['generationConfig']['responseSchema']), 'PDF test includes file but no schema');
    $mock->diagnose(3, $activity, $files);
    $promptparts = $mock->body['contents'][0]['parts'];
    $stageprompt = end($promptparts);
    verify(!isset($mock->body['generationConfig']['responseSchema']) &&
        $mock->body['generationConfig']['responseMimeType'] === 'application/json' &&
        $mock->body['generationConfig']['maxOutputTokens'] === 8192 &&
        $mock->body['generationConfig']['thinkingConfig'] === ['thinkingLevel' => 'minimal'] &&
        strpos($stageprompt['text'], '"audioscript"') !== false,
        'Full test uses JSON mode, 8192 tokens, minimal thinking, and textual contract in contents');
    verify($mock->calls === 3, 'One provider request per explicit test');
    $mock->diagnose(4, $activity, $files);
    verify($mock->body['generationConfig'] === ['maxOutputTokens' => 1024, 'thinkingConfig' => ['thinkingLevel' => 'minimal'], 'responseMimeType' => 'application/json'] &&
        count(array_filter($mock->body['contents'][0]['parts'], static function($part) { return isset($part['inlineData']); })) === 1,
        'Tiny JSON test uses PDF and low output allowance');
    $mock->invalid = true;
    try { $mock->diagnose(4, $activity, $files); throw new Exception('Invalid diagnostic JSON accepted'); }
    catch (\mod_aienglishlesson\local\provider_error $e) { verify($e->getMessage() === 'response', 'Tiny JSON test rejects wrong structure'); }
    try { $mock->diagnose(3, $activity, $files); throw new Exception('Invalid lesson accepted'); }
    catch (moodle_exception $e) { verify($e->errorcode === 'invalidlesson', 'Local validator still rejects incomplete JSON lesson'); }
    $mock->invalid = false;
    $mock->fail = true;
    try { $mock->diagnose(1, $activity, $files); throw new Exception('Expected failure'); }
    catch (\mod_aienglishlesson\local\provider_error $e) { verify($e->getMessage() === 'http503', 'Exact safe error preserved without retry'); }
    verify($before === [$DB->count_records('aiel_versions'), $DB->count_records('aiel_jobs'), $DB->count_records('aiel_usage')], 'No drafts/jobs or real API attempts created');
} finally {
    foreach ($original as $k => $v) { if ($v === false) { unset_config($k, 'aienglishlesson'); } else { set_config($k, $v, 'aienglishlesson'); } }
}
