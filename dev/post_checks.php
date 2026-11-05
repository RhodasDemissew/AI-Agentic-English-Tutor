<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/mod/aienglishlesson/lib.php');
require_once($CFG->libdir . '/adminlib.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local development only'); }
\core\session\manager::set_user(get_admin());
$fixture = json_decode(file_get_contents('/var/moodledata/aiel-test.json'));
function check($ok, $message) { if (!$ok) { throw new Exception('FAIL: ' . $message); } echo 'PASS: ' . $message . "\n"; }
check($DB->count_records('aiel_usage') === 0, 'Saved lesson reads, edits and publishing make no AI calls');
$schema = new xmldb_file($CFG->dirroot . '/mod/aienglishlesson/db/install.xml');
check($schema->loadXMLStructure(), 'Final XMLDB install schema parses');
$context = context_module::instance($fixture->cmid);
$student = $DB->get_record('user', ['username' => 'aielstudent']);
$contexts = \mod_aienglishlesson\privacy\provider::get_contexts_for_userid($student->id);
check(in_array($context->id, $contexts->get_contextids()), 'Privacy API discovers learner progress context');
$users = new \core_privacy\local\request\userlist($context, 'mod_aienglishlesson');
\mod_aienglishlesson\privacy\provider::get_users_in_context($users);
check(in_array($student->id, $users->get_userids()), 'Privacy API discovers learner in context');
$setting = new \mod_aienglishlesson\admin_setting_apikey('aienglishlesson/apikey', 'Key', '', '');
$html = $setting->output_html('FICTIONAL-SECRET-DO-NOT-RENDER');
check(strpos($html, 'FICTIONAL-SECRET-DO-NOT-RENDER') === false, 'Saved credential never rendered in settings HTML');
$bc = new backup_controller(backup::TYPE_1ACTIVITY, $fixture->cmid, backup::FORMAT_MOODLE,
    backup::INTERACTIVE_NO, backup::MODE_GENERAL, get_admin()->id);
$bc->get_plan()->get_setting('users')->set_value(true);
$bc->execute_plan();
$results = $bc->get_results();
check(!empty($results['backup_destination']), 'Activity backup creates Moodle archive');
$file = $results['backup_destination'];
$folder = restore_controller::get_tempdir_name($fixture->courseid, get_admin()->id);
$path = $CFG->tempdir . '/backup/' . $folder;
get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($file, $path);
$rc = new restore_controller($folder, $fixture->courseid, backup::INTERACTIVE_NO,
    backup::MODE_GENERAL, get_admin()->id, backup::TARGET_CURRENT_ADDING);
$precheck = $rc->execute_precheck();
check($precheck, 'Restore precheck succeeds');
$before = $DB->count_records('aienglishlesson');
$rc->execute_plan();
check($DB->count_records('aienglishlesson') === $before + 1, 'Activity restore recreates plugin record');
$restored = $DB->get_record_sql('SELECT * FROM {aienglishlesson} ORDER BY id DESC', [], IGNORE_MULTIPLE);
check($DB->count_records('aiel_versions', ['lessonid' => $restored->id]) === 2, 'Backup restores both lesson versions');
$restoredcm = get_coursemodule_from_instance('aienglishlesson', $restored->id);
$restoredcontext = context_module::instance($restoredcm->id);
check(count(get_file_storage()->get_area_files($restoredcontext->id, 'mod_aienglishlesson', 'source', 0, 'id', false)) === 1,
    'Backup restores source PDF');
check($DB->count_records('aiel_jobs', ['lessonid' => $restored->id]) === 0, 'Restore does not replay generation jobs');
$originalcm = get_coursemodule_from_id('aienglishlesson', $fixture->cmid);
$originalresponses = $DB->get_records('aiel_responses', ['lessonid' => $originalcm->instance]);
if ($originalresponses) {
    check($DB->count_records('aiel_responses', ['lessonid' => $restored->id]) === count($originalresponses), 'Backup restores learner responses');
    check(count(get_file_storage()->get_area_files($restoredcontext->id, 'mod_aienglishlesson', 'recording', false, 'id', false)) > 0,
        'Backup restores speaking recordings with mapped response IDs');
    check(count(get_file_storage()->get_area_files($restoredcontext->id, 'mod_aienglishlesson', 'audio', false, 'id', false)) === 4,
        'Backup restores cached audio for both versions');
    \core_privacy\local\request\writer::reset();
    \mod_aienglishlesson\privacy\provider::export_user_data(new \core_privacy\local\request\approved_contextlist(
        $student, 'mod_aienglishlesson', [$context->id]));
    $archive = \core_privacy\local\request\writer::with_context($context)->finalise_content();
    $zip = new ZipArchive(); $zip->open($archive); $hastext = false; $hasaudio = false;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $bytes = $zip->getFromIndex($i);
        $hastext = $hastext || strpos($bytes, 'FICTIONAL-WRITING-RESPONSE') !== false;
        $hasaudio = $hasaudio || (substr($bytes, 0, 4) === 'RIFF');
    }
    $zip->close();
    check($hastext && $hasaudio, 'Privacy export archive includes own written response and recording');
}
$rc->destroy(); $bc->destroy();
\mod_aienglishlesson\privacy\provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
    $student, 'mod_aienglishlesson', [$context->id]));
$cm = get_coursemodule_from_id('aienglishlesson', $fixture->cmid);
check(!$DB->record_exists('aiel_progress', ['lessonid' => $cm->instance, 'userid' => $student->id]), 'Privacy deletion removes only approved learner progress');
check(!$DB->record_exists('aiel_responses', ['lessonid' => $cm->instance, 'userid' => $student->id]), 'Privacy deletion removes learner responses');
foreach ($originalresponses as $response) {
    check(!get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'recording', $response->id, 'id', false),
        'Privacy deletion removes the associated recording');
}
// Remove restored test activity through Moodle lifecycle; keep the original demo.
require_once($CFG->dirroot . '/course/lib.php');
course_delete_module($restoredcm->id);
check(!$DB->record_exists('aienglishlesson', ['id' => $restored->id]), 'Activity deletion callback succeeds');
check(!$DB->record_exists('aiel_versions', ['lessonid' => $restored->id]) &&
    !get_file_storage()->get_area_files($restoredcontext->id, 'mod_aienglishlesson', 'source', 0, 'id', false),
    'Activity deletion removes versions and source files');
echo "Post checks complete.\n";
