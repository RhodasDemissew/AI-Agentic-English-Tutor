<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$versionid = required_param('version', PARAM_INT);
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aienglishlesson:manage', $context);
$version = $DB->get_record('aiel_versions', ['id' => $versionid, 'lessonid' => $cm->instance], '*', MUST_EXIST);
$url = new moodle_url('/mod/aienglishlesson/rubrics.php', ['id' => $id, 'version' => $versionid]);
if (data_submitted()) {
    require_sesskey();
    $rubrics = [];
    foreach (\mod_aienglishlesson\local\assessment::SKILLS as $skill) {
        $value = required_param('rubric_' . $skill, PARAM_RAW_TRIMMED);
        if ($value === '' || strlen($value) > 3000) { throw new moodle_exception('invalidrequest'); }
        $rubrics[$skill] = $value;
    }
    $DB->set_field('aiel_versions', 'rubrics', json_encode($rubrics), ['id' => $versionid]);
    redirect($url);
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('rubrics', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rubrics', 'aienglishlesson'));
echo html_writer::tag('p', get_string('rubricnotice', 'aienglishlesson'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
foreach (\mod_aienglishlesson\local\assessment::rubrics($version) as $skill => $text) {
    echo html_writer::tag('label', ucfirst($skill), ['for' => 'rubric_' . $skill]);
    echo html_writer::tag('textarea', s($text), ['id' => 'rubric_' . $skill,
        'name' => 'rubric_' . $skill, 'rows' => 4, 'maxlength' => 3000, 'class' => 'form-control mb-3']);
}
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo html_writer::link(new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $versionid]),
    get_string('backtolesson', 'aienglishlesson'));
echo $OUTPUT->footer();
