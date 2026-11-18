<?php
// One teacher-authorised web request per generation stage, avoiding a long combined host request.
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$jobid = required_param('job', PARAM_INT);
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aienglishlesson:manage', $context);
$job = $DB->get_record('aiel_jobs', ['id' => $jobid, 'lessonid' => $cm->instance], '*', MUST_EXIST);
$back = new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id]);
if (optional_param('step', 0, PARAM_INT)) {
    if (!data_submitted()) { throw new moodle_exception('invalidrequest'); }
    require_sesskey();
    \core\session\manager::write_close();
    \core_php_time_limit::raise(300);
    \mod_aienglishlesson\local\jobs::run($jobid);
    $job = $DB->get_record('aiel_jobs', ['id' => $jobid], '*', MUST_EXIST);
    $versionid = 0;
    if ($job->status === 'succeeded') {
        $drafts = $DB->get_records('aiel_versions', ['lessonid' => $cm->instance, 'status' => 'draft'], 'id DESC', '*', 0, 1);
        if ($drafts) { $versionid = (int)reset($drafts)->id; }
    }
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['status' => $job->status, 'stage' => (int)$job->stage, 'version' => $versionid]);
    exit;
}
$PAGE->set_url('/mod/aienglishlesson/preparegeneration.php', ['id' => $id, 'job' => $jobid]);
$PAGE->set_title(get_string('generate', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('mod_aienglishlesson/preparegeneration', 'init', [[
    'url' => (new moodle_url('/mod/aienglishlesson/preparegeneration.php'))->out(false),
    'id' => $id, 'job' => $jobid, 'sesskey' => sesskey(),
    'lessonurl' => $back->out(false),
    'audiourl' => (new moodle_url('/mod/aienglishlesson/prepareaudio.php'))->out(false),
    'audioenabled' => \mod_aienglishlesson\local\audio::provider() !== 'upload',
    'working' => get_string('generationworking', 'aienglishlesson', '{percent}'),
    'failed' => get_string('generationstopped', 'aienglishlesson'),
]]);
echo $OUTPUT->header();
echo html_writer::start_div('aiel-progress-panel');
echo html_writer::tag('h2', get_string('generatingtitle', 'aienglishlesson'));
echo html_writer::div(html_writer::div('', 'progress-bar progress-bar-striped progress-bar-animated',
    ['id' => 'aiel-generation-bar', 'style' => 'width: 2%']), 'progress aiel-progress-bar', [
    'role' => 'progressbar', 'aria-valuemin' => 0, 'aria-valuemax' => 100, 'aria-valuenow' => 0,
    'aria-label' => get_string('generatingtitle', 'aienglishlesson')]);
echo html_writer::div(get_string('generationworking', 'aienglishlesson', 0), 'aiel-progress-text',
    ['id' => 'aiel-generation-progress', 'role' => 'status', 'aria-live' => 'polite']);
echo html_writer::div(get_string('keeppageopen', 'aienglishlesson'), 'aiel-note');
echo html_writer::div(html_writer::link($back, get_string('backtolesson', 'aienglishlesson'),
    ['class' => 'btn btn-secondary', 'id' => 'aiel-generation-back', 'hidden' => 'hidden']), 'mt-3');
echo html_writer::tag('noscript', get_string('generationjavascript', 'aienglishlesson'));
echo html_writer::end_div();
echo $OUTPUT->footer();
