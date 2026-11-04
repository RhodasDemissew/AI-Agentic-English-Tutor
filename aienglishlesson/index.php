<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);
$PAGE->set_url('/mod/aienglishlesson/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'aienglishlesson')); $PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'aienglishlesson'));
foreach (get_fast_modinfo($course)->get_instances_of('aienglishlesson') as $cm) {
    if ($cm->uservisible) { echo html_writer::div(html_writer::link($cm->url, format_string($cm->name))); }
}
echo $OUTPUT->footer();
