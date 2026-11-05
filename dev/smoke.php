<?php
// Run only in the isolated development container. No external API calls.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/aienglishlesson/lib.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local development only'); }
// Exercise the original background/local paths. Portable checks cover direct/cloud mode separately.
set_config('executionmode', 'background', 'aienglishlesson');
set_config('audioprovider', 'local', 'aienglishlesson');
\core\session\manager::set_user(get_admin());
function check($condition, $message) {
    if (!$condition) { throw new Exception('FAIL: ' . $message); }
    echo 'PASS: ' . $message . "\n";
}
$course = create_course((object)['fullname' => 'AI English Lessons verification', 'shortname' => 'AIEL-' . time(),
    'category' => 1, 'enablecompletion' => 1]);
$module = add_moduleinfo((object)['modulename' => 'aienglishlesson',
    'module' => $DB->get_field('modules', 'id', ['name' => 'aienglishlesson']), 'name' => 'A day at the market',
    'intro' => 'Local fixture, not AI-generated.', 'introformat' => FORMAT_HTML, 'gradeband' => 'adult',
    'proficiency' => 'A2', 'topic' => 'Shopping', 'pagerange' => '', 'duration' => 30, 'instructions' => '',
    'section' => 0, 'visible' => 1, 'completion' => 2, 'completionview' => 1,
    'completiongradeitemnumber' => '', 'completionexpected' => 0], $course);
$lessonid = $module->instance;
$context = context_module::instance($module->coursemodule);
check($DB->record_exists('aienglishlesson', ['id' => $lessonid]), 'Activity creation through Moodle add_moduleinfo');
$student = $DB->get_record('user', ['username' => 'aielstudent']);
if (!$student) {
    $uid = user_create_user((object)['username' => 'aielstudent', 'password' => 'LocalStudent-42!', 'firstname' => 'Fictional',
        'lastname' => 'Student', 'email' => 'student@example.invalid', 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id]);
    $student = $DB->get_record('user', ['id' => $uid]);
}
$manual = enrol_get_plugin('manual');
$instances = enrol_get_instances($course->id, false);
$instance = reset($instances);
if (!$instance || $instance->enrol !== 'manual') {
    $instanceid = $manual->add_instance($course); $instance = $DB->get_record('enrol', ['id' => $instanceid]);
}
$manual->enrol_user($instance, $student->id, $DB->get_field('role', 'id', ['shortname' => 'student']));
check(has_capability('mod/aienglishlesson:view', $context, $student), 'Student can study');
check(!has_capability('mod/aienglishlesson:manage', $context, $student), 'Student cannot manage drafts or source PDFs');
$pdf = get_file_storage()->create_file_from_pathname((object)['contextid' => $context->id, 'component' => 'mod_aienglishlesson',
    'filearea' => 'source', 'itemid' => 0, 'filepath' => '/', 'filename' => 'sample.pdf', 'mimetype' => 'application/pdf'], '/opt/aiel-dev/sample.pdf');
check(\mod_aienglishlesson\local\pdf::inspect($pdf, '1') === 1, 'Real PDF inspection');
try { \mod_aienglishlesson\local\pdf::inspect($pdf, '2-3'); check(false, 'Invalid page range rejected'); }
catch (moodle_exception $e) { check($e->errorcode === 'rangeerror', 'Invalid page range rejected'); }
$content = ['title' => 'A day at the market', 'focus' => 'speaking', 'readable' => true, 'sections' => []];
foreach (\mod_aienglishlesson\local\lesson::KINDS as $kind) {
    $content['sections'][] = ['kind' => $kind, 'title' => ucfirst($kind), 'body' => 'Learn to ask politely for apples at a market. This is a local test fixture.',
        'example' => 'Customer: May I have two apples, please? Seller: Of course.', 'practice' => 'Ask your partner for three oranges.',
        'answer' => 'TEACHER-ONLY-SECRET: May I have three oranges, please?', 'origin' => 'generated',
        'feedback' => 'AFTER-SUBMISSION-MODEL: May I have three oranges, please? Check your polite request word.',
        'hint' => 'Begin with May I have...', 'simpler' => 'Ask politely. Say please.', 'extraexample' => 'May I have a banana, please?',
        'audioscript' => in_array($kind, ['listening', 'speaking']) ? 'At the market, Ana buys two apples. May I have two apples, please? Of course, says the seller.' : '',
        'cards' => [['term' => 'please', 'meaning' => 'A polite request word', 'example' => 'Two apples, please.']]];
}
$content = \mod_aienglishlesson\local\lesson::validate(json_encode($content));
$content['sections'][0]['body'] .= ' <script>alert(1)</script>';
check(strpos(json_encode(\mod_aienglishlesson\local\lesson::student_data($content)), 'TEACHER-ONLY-SECRET') === false, 'Answer keys removed before student rendering');
$bad = $content; $bad['sections'][0]['script'] = '<script>alert(1)</script>';
try { \mod_aienglishlesson\local\lesson::validate(json_encode($bad)); check(false, 'Unknown AI fields rejected'); }
catch (moodle_exception $e) { check($e->errorcode === 'invalidlesson', 'Unknown AI fields rejected'); }
$fixture = new class($content) implements \mod_aienglishlesson\local\provider {
    private array $content;
    public function __construct($content) { $this->content = $content; }
    public function test(): void {}
    public function generate(stdClass $activity, stored_file $pdf): array { return $this->content; }
};
$jobid = \mod_aienglishlesson\local\jobs::queue($lessonid);
check(\mod_aienglishlesson\local\jobs::queue($lessonid) === $jobid, 'Duplicate job prevented');
\mod_aienglishlesson\local\jobs::run($jobid, $fixture);
check($DB->get_field('aiel_jobs', 'status', ['id' => $jobid]) === 'succeeded', 'Background workflow saves validated draft with test double');
$version = $DB->get_record('aiel_versions', ['lessonid' => $lessonid], '*', MUST_EXIST);
$DB->set_field('aiel_versions', 'status', 'published', ['id' => $version->id]);
$newjob = \mod_aienglishlesson\local\jobs::queue($lessonid);
\mod_aienglishlesson\local\jobs::run($newjob, $fixture);
check($DB->get_field('aiel_versions', 'status', ['id' => $version->id]) === 'published', 'Regeneration preserves published version');
$fail = new class implements \mod_aienglishlesson\local\provider {
    public function test(): void {}
    public function generate(stdClass $activity, stored_file $pdf): array {
        throw new \mod_aienglishlesson\local\provider_error('transient', true);
    }
};
$failid = \mod_aienglishlesson\local\jobs::queue($lessonid);
for ($i = 0; $i < 3; $i++) { try { \mod_aienglishlesson\local\jobs::run($failid, $fail); } catch (moodle_exception $e) {} }
check($DB->get_field('aiel_jobs', 'status', ['id' => $failid]) === 'failed' &&
    $DB->get_field('aiel_jobs', 'attempts', ['id' => $failid]) == 3, 'Transient failures stop after three attempts');
$drafts = $DB->get_records('aiel_versions', ['lessonid' => $lessonid, 'status' => 'draft']);
$draft = reset($drafts);
file_put_contents('/var/moodledata/aiel-test.json', json_encode(['cmid' => $module->coursemodule,
    'published' => $version->id, 'draft' => $draft->id, 'contextid' => $context->id, 'courseid' => $course->id]));
echo 'FIXTURE ' . json_encode(['cmid' => $module->coursemodule, 'published' => $version->id, 'draft' => $draft->id, 'contextid' => $context->id]) . "\n";
