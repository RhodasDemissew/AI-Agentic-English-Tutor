<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/mod/aienglishlesson/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local only'); }
\core\session\manager::set_user(get_admin());
$fixture = json_decode(file_get_contents('/var/moodledata/aiel-test.json'));
$cm = get_coursemodule_from_id('aienglishlesson', $fixture->cmid);
$context = context_module::instance($cm->id);
function check($ok, $message) { if (!$ok) { throw new Exception('FAIL: ' . $message); } echo 'PASS: ' . $message . "\n"; }
$draftid = file_get_unused_draft_itemid();
get_file_storage()->create_file_from_pathname((object)['contextid' => context_user::instance(get_admin()->id)->id,
    'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
    'filename' => 'sample.pdf', 'mimetype' => 'application/pdf'], '/opt/aiel-dev/sample.pdf');
aienglishlesson_update_instance((object)['instance' => $cm->instance, 'coursemodule' => $cm->id, 'sourcepdf' => $draftid]);
$files = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
check(count($files) === 1 && reset($files)->get_contenthash() === sha1_file('/opt/aiel-dev/sample.pdf'),
    'Activity update saves uploaded draft PDF through Moodle File API');
$student = $DB->get_record('user', ['username' => 'aielstudent']);
$completion = (new completion_info($DB->get_record('course', ['id' => $cm->course])))->get_data($cm, false, $student->id);
check($completion && $completion->viewed == 1, 'Published view records Moodle completion');
check(get_config('aienglishlesson', 'provider') !== 'gemini', 'Provider remains disabled');
try { (new \mod_aienglishlesson\local\gemini())->test(); check(false, 'Disabled connection test blocked'); }
catch (\mod_aienglishlesson\local\provider_error $e) { check($e->getMessage() === 'configuration', 'Disabled connection test blocked before network'); }
$jobid = \mod_aienglishlesson\local\jobs::queue($cm->instance);
echo 'QUEUED_DISABLED_JOB=' . $jobid . "\n";
