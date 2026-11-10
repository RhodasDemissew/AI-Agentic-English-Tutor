<?php
// Teacher-only, one audio section per request. No cron or server executable required for cloud speech.
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$versionid = required_param('version', PARAM_INT);
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aienglishlesson:manage', $context);
$version = $DB->get_record('aiel_versions', ['id' => $versionid, 'lessonid' => $cm->instance, 'status' => 'draft'], '*', MUST_EXIST);
$back = new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $versionid]);
if (optional_param('step', 0, PARAM_INT)) {
    if (!data_submitted()) { throw new moodle_exception('invalidrequest'); }
    require_sesskey();
    \core\session\manager::write_close();
    \core_php_time_limit::raise(300);
    \mod_aienglishlesson\local\audio::render($versionid, true);
    $status = $DB->get_field('aiel_versions', 'audiostatus', ['id' => $versionid]);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['status' => $status, 'done' => $status === 'ready']);
    exit;
}
$PAGE->set_url('/mod/aienglishlesson/prepareaudio.php', ['id' => $id, 'version' => $versionid]);
$PAGE->set_title(get_string('buildaudio', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('mod_aienglishlesson/prepareaudio', 'init', [[
    'url' => (new moodle_url('/mod/aienglishlesson/prepareaudio.php'))->out(false),
    'id' => $id, 'version' => $versionid, 'sesskey' => sesskey(),
    'working' => get_string('audioworking', 'aienglishlesson'),
    'complete' => get_string('audiocomplete', 'aienglishlesson'),
    'failed' => get_string('audiostopped', 'aienglishlesson'),
]]);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('buildaudio', 'aienglishlesson'));
echo html_writer::div(get_string('audioworking', 'aienglishlesson'), 'alert alert-info', ['id' => 'aiel-audio-progress', 'role' => 'status']);
echo html_writer::link($back, get_string('backtolesson', 'aienglishlesson'), ['class' => 'btn btn-secondary']);
echo html_writer::tag('noscript', get_string('audiojavascript', 'aienglishlesson'));
echo $OUTPUT->footer();
