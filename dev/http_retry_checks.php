<?php
namespace core\lock {
    class lock_config { public static function get_lock_factory(string $name): object { return new class {
        public function get_lock(string $name, int $timeout): object { return new class { public function release(): void {} }; }
    }; } }
}
namespace {
define('MOODLE_INTERNAL', true);
define('DAYSECS', 86400);
class moodle_exception extends \Exception {}
class stored_file {}
class curl {
    public static array $responses = [];
    private int $status = 0;
    public function setHeader(array $headers): void {}
    public function post(string $url, string $body, array $options): string {
        [$this->status, $raw] = array_shift(self::$responses);
        return $raw;
    }
    public function get_info(): array { return ['http_code' => $this->status]; }
    public function get_errno(): int { return 0; }
}
class usage_db {
    public array $records = [];
    public function count_records_select(string $table, string $where, array $params): int { return count($this->records); }
    public function insert_record(string $table, object $record): void { $this->records[] = $record; }
    public function delete_records_select(string $table, string $where, array $params): void {}
}
function get_config(string $component, ?string $name = null): mixed {
    $settings = (object)['provider' => 'gemini', 'eligible' => 1, 'freeonly' => 1,
        'apikey' => 'secret-test-key', 'model' => 'gemini-test', 'timeout' => 10, 'dailycalls' => 2];
    return $name === null ? $settings : ($settings->$name ?? false);
}
$CFG = (object)['libdir' => __DIR__];
require_once __DIR__ . '/../aienglishlesson/classes/local/provider.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/provider_error.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/gemini.php';
class test_gemini extends \mod_aienglishlesson\local\gemini {
    public function call(): array { return $this->request('POST', 'models/gemini-test:generateContent', ['contents' => []]); }
}
function check(bool $ok, string $name): void { if (!$ok) { throw new \RuntimeException($name); } echo "PASS $name\n"; }
$DB = new usage_db();
curl::$responses = [[503, '{}'], [200, '{"ok":true}']];
check((new test_gemini())->call()['ok'] === true && count($DB->records) === 2, '503 retry succeeds and consumes two quota slots');
try { (new test_gemini())->call(); throw new \RuntimeException('Quota bypassed'); }
catch (\mod_aienglishlesson\local\provider_error $e) {
    check($e->getMessage() === 'limit' && !curl::$responses, 'quota exhaustion blocks request');
}
foreach ([400, 401] as $status) {
    $DB = new usage_db(); curl::$responses = [[$status, '{}']];
    try { (new test_gemini())->call(); throw new \RuntimeException('Permanent error accepted'); }
    catch (\mod_aienglishlesson\local\provider_error $e) {
        check($e->getMessage() === 'http' . $status && !$e->transient && count($DB->records) === 1,
            "HTTP $status does not retry");
    }
}
}
