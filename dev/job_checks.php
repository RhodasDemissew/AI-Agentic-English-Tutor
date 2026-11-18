<?php
namespace core\lock {
    class lock_config {
        public static function get_lock_factory(string $name): object {
            return new class {
                public function get_lock(string $name, int $timeout): object {
                    return new class { public function release(): void {} };
                }
            };
        }
    }
}
namespace {
define('MOODLE_INTERNAL', true);
define('MUST_EXIST', 1);
class moodle_exception extends \Exception { public string $errorcode;
    public function __construct(string $code, string $component = '') { $this->errorcode = $code; parent::__construct($code); }
}
class stored_file {}
class context_module { public int $id = 1; public static function instance(int $id): self { return new self(); } }
function get_config(string $component, ?string $name = null): mixed { return $name === 'executionmode' ? 'direct' : false; }
function get_coursemodule_from_instance(string $component, int $id, int $course = 0, bool $strict = false,
        int $mustexist = 0): object { return (object)['id' => 1]; }
function get_file_storage(): object { return new class {
    public function get_area_files(): array { return [new stored_file()]; }
}; }
class fake_db {
    public array $jobs = []; public array $versions = []; private int $next = 1;
    public function get_record(string $table, array $where, string $fields = '*', int $must = 0): object|false {
        if ($table === 'aienglishlesson') { return (object)['id' => $where['id']]; }
        $set = $table === 'aiel_jobs' ? $this->jobs : $this->versions;
        if (!isset($set[$where['id']])) { return false; }
        $record = clone $set[$where['id']];
        if ($table === 'aiel_jobs') { $record->attempts = (string)$record->attempts; }
        return $record;
    }
    public function get_record_select(string $table, string $where, array $params): object|false {
        foreach ($this->jobs as $job) {
            if ($job->lessonid === $params[0] && in_array($job->status, ['queued', 'running'], true)) { return clone $job; }
        }
        return false;
    }
    public function insert_record(string $table, object $record): int {
        $id = $this->next++; $record->id = $id;
        if ($table === 'aiel_jobs') { $this->jobs[$id] = clone $record; }
        else { $this->versions[$id] = clone $record; }
        return $id;
    }
    public function update_record(string $table, object $record): void { $this->jobs[$record->id] = clone $record; }
    public function set_field(string $table, string $field, mixed $value, array $where): void {
        $this->versions[$where['id']]->$field = $value;
    }
    public function start_delegated_transaction(): object { return new class { public function allow_commit(): void {} }; }
}
require_once __DIR__ . '/../aienglishlesson/classes/local/provider.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/provider_error.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/lesson.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/audio.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/jobs.php';
class fake_provider implements \mod_aienglishlesson\local\provider {
    public int $calls = 0;
    public function __construct(private array $content, private bool $failfirst = false) {}
    public function test(): void {}
    public function generate(\stdClass $activity, \stored_file $pdf): array {
        $this->calls++;
        if ($this->failfirst && $this->calls === 1) { throw new \mod_aienglishlesson\local\provider_error('http503', true); }
        return $this->content;
    }
}
function check(bool $ok, string $name): void { if (!$ok) { throw new \RuntimeException($name); } echo "PASS $name\n"; }
$content = ['title' => 'Market', 'focus' => 'reading', 'readable' => true, 'sections' => []];
foreach (\mod_aienglishlesson\local\lesson::KINDS as $kind) {
    $content['sections'][] = ['kind' => $kind, 'title' => ucfirst($kind), 'body' => 'Ana visits a market.',
        'origin' => 'source', 'example' => '', 'practice' => 'What happens?', 'answer' => 'Ana shops.',
        'feedback' => '', 'hint' => '', 'simpler' => '', 'extraexample' => '',
        'audioscript' => $kind === 'listening' ? 'Ana shops.' : '',
        'cards' => $kind === 'vocabulary' ? [['term' => 'market', 'meaning' => 'shop', 'example' => 'A market opens.']] : []];
}
$DB = new fake_db();
$provider = new fake_provider($content, true);
$id = \mod_aienglishlesson\local\jobs::queue(7);
\mod_aienglishlesson\local\jobs::run($id, $provider);
check($DB->jobs[$id]->status === 'queued' && (int)$DB->jobs[$id]->attempts === 1, 'direct 503 remains recoverable');
check(\mod_aienglishlesson\local\jobs::queue(7) === $id, 'queued job reused');
\mod_aienglishlesson\local\jobs::run($id, $provider);
check($DB->jobs[$id]->status === 'succeeded' && count($DB->versions) === 1, 'retry saves one draft');
\mod_aienglishlesson\local\jobs::run($id, $provider);
check(count($DB->versions) === 1 && $provider->calls === 2, 'completed job cannot duplicate draft');
foreach (['http400', 'http401', 'limit'] as $code) {
    $bad = new class($code) implements \mod_aienglishlesson\local\provider {
        public function __construct(private string $code) {}
        public function test(): void {}
        public function generate(\stdClass $activity, \stored_file $pdf): array {
            throw new \mod_aienglishlesson\local\provider_error($this->code);
        }
    };
    $badid = \mod_aienglishlesson\local\jobs::queue(8 + count($DB->jobs));
    \mod_aienglishlesson\local\jobs::run($badid, $bad);
    check($DB->jobs[$badid]->status === 'failed' && $DB->jobs[$badid]->diagnostic === $code,
        "$code is permanent and sanitized");
}
}
