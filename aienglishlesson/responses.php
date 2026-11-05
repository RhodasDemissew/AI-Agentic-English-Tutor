<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($id);
require_capability('mod/aienglishlesson:manage', $context);
$PAGE->set_url('/mod/aienglishlesson/responses.php', ['id' => $id]);
$PAGE->set_title(get_string('reviewresponses', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewresponses', 'aienglishlesson'));
echo html_writer::link(new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id]), get_string('backtolesson', 'aienglishlesson'));
$total = $DB->count_records('aiel_responses', ['lessonid' => $cm->instance]);
echo $OUTPUT->paging_bar($total, $page, 25, $PAGE->url);
foreach ($DB->get_records('aiel_responses', ['lessonid' => $cm->instance], 'timemodified DESC, id DESC', '*', $page * 25, 25) as $r) {
    $user = $DB->get_record('user', ['id' => $r->userid], '*', MUST_EXIST);
    $version = $DB->get_record('aiel_versions', ['id' => $r->versionid], '*', MUST_EXIST);
    $lesson = \mod_aienglishlesson\local\lesson::validate($version->content);
    echo html_writer::start_tag('section', ['class' => 'aiel-practice']);
    echo html_writer::tag('h3', s(fullname($user) . ' — ' . $lesson['sections'][$r->sectionno]['title']));
    echo html_writer::div(s(get_string('versionlabel', 'aienglishlesson', (object)['id' => $version->id, 'status' => $version->status]) . ' · ' . userdate($r->timemodified)));
    echo html_writer::div(s($r->response), 'aiel-text');
    foreach (get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'recording', $r->id, 'id', false) as $file) {
        echo html_writer::tag('audio', get_string('recording', 'aienglishlesson'), ['controls' => 'controls', 'preload' => 'none',
            'src' => moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'recording', $r->id, '/', $file->get_filename())->out(false)]);
    }
    echo html_writer::end_tag('section');
}
echo $OUTPUT->footer();
