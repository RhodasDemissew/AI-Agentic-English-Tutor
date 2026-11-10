<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($id);
require_capability('mod/aienglishlesson:manage', $context);
if (data_submitted()) {
    require_sesskey();
    $attemptid = required_param('attempt', PARAM_INT);
    $attempt = $DB->get_record('aiel_responses', ['id' => $attemptid, 'lessonid' => $cm->instance], '*', MUST_EXIST);
    $score = required_param('score', PARAM_INT);
    $feedback = required_param('teacherfeedback', PARAM_RAW_TRIMMED);
    \mod_aienglishlesson\local\assessment::approve($attempt, $score, $feedback, $USER->id);
    redirect(new moodle_url('/mod/aienglishlesson/responses.php', ['id' => $id, 'page' => $page]));
}
$PAGE->set_url('/mod/aienglishlesson/responses.php', ['id' => $id]);
$PAGE->set_title(get_string('reviewresponses', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewresponses', 'aienglishlesson'));
echo html_writer::link(new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id]), get_string('backtolesson', 'aienglishlesson'));
$total = $DB->count_records('aiel_responses', ['lessonid' => $cm->instance]);
echo $OUTPUT->paging_bar($total, $page, 25, $PAGE->url);
foreach ($DB->get_records('aiel_responses', ['lessonid' => $cm->instance], 'timemodified DESC, id DESC', '*', $page * 25, 25) as $r) {
    \mod_aienglishlesson\local\assessment::recover_stale($r->id);
    if ($r->aistatus === 'running' && $r->timemodified < time() - 180) { $r->aistatus = 'failed'; }
    $user = $DB->get_record('user', ['id' => $r->userid], '*', MUST_EXIST);
    $version = $DB->get_record('aiel_versions', ['id' => $r->versionid], '*', MUST_EXIST);
    $lesson = \mod_aienglishlesson\local\lesson::validate($version->content);
    echo html_writer::start_tag('section', ['class' => 'aiel-practice']);
    echo html_writer::tag('h3', s(fullname($user) . ' — ' . $lesson['sections'][$r->sectionno]['title']));
    echo html_writer::div(s(get_string('versionlabel', 'aienglishlesson', (object)['id' => $version->id, 'status' => $version->status]) . ' · ' . userdate($r->timemodified)));
    echo html_writer::div(s($r->response), 'aiel-text');
    $images = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'image', $r->id, 'id', false);
    foreach ($images as $file) {
        echo html_writer::empty_tag('img', ['src' => moodle_url::make_pluginfile_url($context->id,
            'mod_aienglishlesson', 'image', $r->id, '/', $file->get_filename())->out(false),
            'alt' => get_string('answerimage', 'aienglishlesson'), 'style' => 'max-width:100%']);
    }
    foreach (get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'recording', $r->id, 'id', false) as $file) {
        echo html_writer::tag('audio', get_string('recording', 'aienglishlesson'), ['controls' => 'controls', 'preload' => 'none',
            'src' => moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'recording', $r->id, '/', $file->get_filename())->out(false)]);
    }
    echo html_writer::div(get_string('aistatus', 'aienglishlesson', s($r->aistatus)));
    if ($r->aistatus === 'succeeded') {
        echo html_writer::div(get_string('provisionalscore', 'aienglishlesson', $r->aiscore));
        echo html_writer::div(s($r->aifeedback), 'aiel-text');
    }
    if ($r->timeapproved) {
        echo html_writer::div(get_string('approvedscore', 'aienglishlesson', $r->teacherscore));
        echo html_writer::div(s($r->teacherfeedback));
    }
    $latest = $DB->get_records('aiel_responses', ['versionid' => $r->versionid,
        'userid' => $r->userid, 'sectionno' => $r->sectionno], 'id DESC', '*', 0, 1);
    if ($latest && (int)reset($latest)->id === (int)$r->id && $version->status === 'published' &&
            in_array($lesson['sections'][$r->sectionno]['kind'], \mod_aienglishlesson\local\assessment::SKILLS, true)) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
        foreach (['sesskey' => sesskey(), 'attempt' => $r->id] as $name => $value) {
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        echo html_writer::tag('label', get_string('teacherscore', 'aienglishlesson'), ['for' => 'score' . $r->id]);
        echo html_writer::empty_tag('input', ['type' => 'number', 'id' => 'score' . $r->id,
            'name' => 'score', 'min' => 0, 'max' => 100, 'required' => 'required',
            'value' => $r->teacherscore ?? $r->aiscore ?? '', 'class' => 'form-control']);
        echo html_writer::tag('label', get_string('teacherfeedback', 'aienglishlesson'), ['for' => 'feedback' . $r->id]);
        echo html_writer::tag('textarea', s($r->teacherfeedback ?? $r->aifeedback ?? ''),
            ['id' => 'feedback' . $r->id, 'name' => 'teacherfeedback', 'rows' => 3,
                'maxlength' => 4000, 'class' => 'form-control']);
        echo html_writer::tag('button', get_string('approvescore', 'aienglishlesson'),
            ['type' => 'submit', 'class' => 'btn btn-primary mt-2']);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_tag('section');
}
echo $OUTPUT->footer();
