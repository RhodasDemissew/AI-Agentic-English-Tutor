<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local development only'); }
\core\session\manager::set_user(get_admin());
function verify($ok, $message) { if (!$ok) { throw new Exception($message); } echo "PASS: $message\n"; }
$fixture = json_decode(file_get_contents('/var/moodledata/aiel-test.json'));
$cm = get_coursemodule_from_id('aienglishlesson', $fixture->cmid);
$context = context_module::instance($cm->id);
$files = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
$pdf = reset($files);
$keys = ['pdfinfo', 'pdfstrict', 'executionmode', 'audioprovider', 'provider', 'apikey', 'eligible', 'freeonly', 'model'];
$saved = [];
foreach ($keys as $key) { $saved[$key] = get_config('aienglishlesson', $key); }
try {
    set_config('pdfinfo', '/missing/pdfinfo', 'aienglishlesson');
    set_config('pdfstrict', 0, 'aienglishlesson');
    verify(\mod_aienglishlesson\local\pdf::inspect($pdf, '2-5') === null, 'Missing Poppler permits PDF and reports unknown page count');
    try { \mod_aienglishlesson\local\pdf::inspect($pdf, '5-2'); throw new Exception('Range accepted'); }
    catch (moodle_exception $e) { verify($e->errorcode === 'rangeerror', 'Portable mode rejects reversed range'); }
    set_config('pdfstrict', 1, 'aienglishlesson');
    try { \mod_aienglishlesson\local\pdf::inspect($pdf, ''); throw new Exception('Strict accepted'); }
    catch (moodle_exception $e) { verify($e->errorcode === 'nopdfinfo', 'Strict mode retains Poppler requirement'); }
    set_config('executionmode', 'direct', 'aienglishlesson');
    set_config('audioprovider', 'gemini', 'aienglishlesson');
    $source = $DB->get_record('aiel_versions', ['id' => $fixture->draft]);
    $provider = new class($source->content) implements \mod_aienglishlesson\local\provider {
        private $content;
        public function __construct($content) { $this->content = $content; }
        public function test(): void {}
        public function generate(stdClass $activity, stored_file $pdf): array { return json_decode($this->content, true); }
    };
    $before = $DB->count_records('task_adhoc', ['component' => 'mod_aienglishlesson']);
    $jobid = \mod_aienglishlesson\local\jobs::queue($cm->instance);
    verify($DB->count_records('task_adhoc', ['component' => 'mod_aienglishlesson']) === $before, 'Direct queue creates no cron task');
    \mod_aienglishlesson\local\jobs::run($jobid, $provider);
    verify($DB->get_field('aiel_jobs', 'status', ['id' => $jobid]) === 'succeeded', 'Direct generation saves draft without cron (fixture provider)');
    $version = $DB->get_record_sql('SELECT * FROM {aiel_versions} WHERE lessonid = ? ORDER BY id DESC', [$cm->instance], IGNORE_MULTIPLE);
    verify($version->audiostatus === 'none', 'Direct generation leaves audio for teacher preparation');
    // Exercise the exact cloud body and decoder without a provider request.
    foreach (['provider' => 'gemini', 'apikey' => 'FICTIONAL-TEST-KEY', 'eligible' => 1, 'freeonly' => 1, 'model' => 'gemini-3.5-flash-lite'] as $key => $value) {
        set_config($key, $value, 'aienglishlesson');
    }
    $wav = 'RIFF' . pack('V', 38) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 24000, 48000, 2, 16) . 'data' . pack('V', 2) . "\0\0";
    $cloud = new class($wav) extends \mod_aienglishlesson\local\gemini {
        public $body; public $bad = false; private $wav;
        public function __construct($wav) { $this->wav = $wav; }
        protected function request(string $method, string $path, ?array $body = null, int $maxbytes = 1000000): array {
            verify($path === 'interactions' && $method === 'POST', 'Speech uses official Interactions route');
            $this->body = $body;
            return ['steps' => [['type' => 'model_output', 'content' => [['type' => 'audio', 'data' => $this->bad ? 'invalid' : base64_encode($this->wav)]]]]];
        }
    };
    verify($cloud->speech('Hello learners.') === $wav, 'Cloud response decodes to WAV');
    verify($cloud->body['input'][0]['content'][0]['text'] === 'Hello learners.' &&
        $cloud->body['response_format']['mime_type'] === 'audio/wav', 'Cloud request transmits exact script and asks for saved WAV');
    $cloud->bad = true;
    try { $cloud->speech('Hello.'); throw new Exception('Invalid speech accepted'); }
    catch (\mod_aienglishlesson\local\provider_error $e) { verify($e->getMessage() === 'speechaudio', 'Invalid provider audio rejected'); }
    $calls = 0;
    $synthesize = function($script) use (&$calls, $wav) { $calls++; return $wav; };
    \mod_aienglishlesson\local\audio::render($version->id, true, $synthesize);
    verify($calls === 1 && $DB->get_field('aiel_versions', 'audiostatus', ['id' => $version->id]) === 'pending', 'Direct audio processes one section per request');
    \mod_aienglishlesson\local\audio::render($version->id, true, function($script) { throw new \mod_aienglishlesson\local\provider_error('http403'); });
    verify($DB->get_field('aiel_versions', 'audiostatus', ['id' => $version->id]) === 'failed:http403', 'Speech failure records safe diagnostic');
    verify(\mod_aienglishlesson\local\audio::file($context, $version->id, 6), 'Successful audio survives a later failure');
    \mod_aienglishlesson\local\audio::render($version->id, true, $synthesize);
    \mod_aienglishlesson\local\audio::render($version->id, true, $synthesize);
    verify($calls === 2 && $DB->get_field('aiel_versions', 'audiostatus', ['id' => $version->id]) === 'ready', 'Retry resumes missing audio; cached files avoid repeat calls');
    verify(\mod_aienglishlesson\local\audio::ready($version, $context), 'Prepared cloud media satisfies publication check');
    // Leave one missing file so the following HTTP worker test performs real local synthesis.
    \mod_aienglishlesson\local\audio::file($context, $version->id, 7)->delete();
    verify($DB->count_records('aiel_usage') === 0, 'All portable tests used zero real API attempts');
    // Keep the new draft for teacher upload and direct HTTP checks.
    $fixture->portableversion = $version->id;
    file_put_contents('/var/moodledata/aiel-test.json', json_encode($fixture));
} finally {
    foreach ($saved as $key => $value) {
        if ($value === false) { unset_config($key, 'aienglishlesson'); }
        else { set_config($key, $value, 'aienglishlesson'); }
    }
}
// HTTP tests deliberately use local speech and disabled AI, with cron stopped.
set_config('executionmode', 'direct', 'aienglishlesson');
set_config('audioprovider', 'local', 'aienglishlesson');
set_config('provider', 'disabled', 'aienglishlesson');
