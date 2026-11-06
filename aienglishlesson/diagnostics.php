<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
require_capability('moodle/site:config', context_system::instance());
$context = context_module::instance($cm->id);
require_capability('mod/aienglishlesson:manage', $context);
$PAGE->set_url('/mod/aienglishlesson/diagnostics.php', ['id' => $id]);
$PAGE->set_title(get_string('generationdiagnostics', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
$notice = null;
if (data_submitted()) {
    require_sesskey();
    $stage = required_param('stage', PARAM_INT);
    if (!in_array($stage, [1, 2, 3, 4], true)) { throw new moodle_exception('invalidrequest'); }
    $activity = $DB->get_record('aienglishlesson', ['id' => $cm->instance], '*', MUST_EXIST);
    $files = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
    if (count($files) !== 1) { throw new moodle_exception('missingpdf', 'aienglishlesson'); }
    \core\session\manager::write_close();
    \core_php_time_limit::raise(300);
    $start = microtime(true);
    try {
        (new \mod_aienglishlesson\local\gemini())->diagnose($stage, $activity, reset($files));
        $notice = get_string('diagnosticstagepass', 'aienglishlesson', $stage);
        $type = 'success';
    } catch (\Throwable $e) {
        $code = $e instanceof \mod_aienglishlesson\local\provider_error ? $e->getMessage() :
            ($e instanceof moodle_exception ? $e->errorcode : 'internal');
        // Codes are selected locally. Never include provider bodies or exception debug info.
        $key = 'diagnostic_' . $code;
        $detail = get_string_manager()->string_exists($key, 'aienglishlesson') ? get_string($key, 'aienglishlesson') : $code;
        $notice = get_string('diagnosticstagefail', 'aienglishlesson', (object)['stage' => $stage, 'code' => $detail]);
        $type = 'error';
    }
    $notice .= ' ' . get_string('diagnosticelapsed', 'aienglishlesson', round(microtime(true) - $start, 1));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generationdiagnostics', 'aienglishlesson'));
echo $OUTPUT->notification(get_string('diagnosticinstructions', 'aienglishlesson'), 'info');
echo html_writer::div(s(get_string('model', 'aienglishlesson') . ': ' . get_config('aienglishlesson', 'model')));
if ($notice !== null) { echo $OUTPUT->notification(s($notice), $type); }
foreach ([1, 2, 3, 4] as $stage) {
    echo $OUTPUT->single_button(new moodle_url($PAGE->url, ['stage' => $stage]), get_string('diagnosticstage' . $stage, 'aienglishlesson'), 'post');
}
echo html_writer::link(new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id]), get_string('backtolesson', 'aienglishlesson'));
echo $OUTPUT->footer();
