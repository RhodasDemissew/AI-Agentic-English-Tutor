<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$studentid = max(0, optional_param('student', 0, PARAM_INT));
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aienglishlesson:manage', $context);
$versions = $DB->get_records('aiel_versions', ['lessonid' => $cm->instance, 'status' => 'published'], 'id DESC', '*', 0, 1);
$currentversion = $versions ? reset($versions) : false;
if (data_submitted()) {
    require_sesskey();
    if (!$currentversion || !$studentid) { throw new moodle_exception('invalidrequest'); }
    $attemptid = required_param('attempt', PARAM_INT);
    $attempt = $DB->get_record('aiel_responses', ['id' => $attemptid, 'lessonid' => $cm->instance,
        'versionid' => $currentversion->id, 'userid' => $studentid], '*', MUST_EXIST);
    $score = required_param('score', PARAM_INT);
    $feedback = required_param('teacherfeedback', PARAM_RAW_TRIMMED);
    \mod_aienglishlesson\local\assessment::approve($attempt, $score, $feedback, $USER->id);
    $return = new moodle_url('/mod/aienglishlesson/responses.php',
        ['id' => $id, 'page' => $page, 'student' => $studentid]);
    $return->set_anchor('response-' . $attemptid);
    redirect($return);
}
$PAGE->set_url('/mod/aienglishlesson/responses.php', ['id' => $id, 'page' => $page, 'student' => $studentid]);
$PAGE->set_title(get_string('reviewresponses', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewresponses', 'aienglishlesson'));
if (!$currentversion) {
    echo $OUTPUT->notification(get_string('nopublished', 'aienglishlesson'), 'info');
    echo $OUTPUT->footer();
    exit;
}
if (!$studentid) {
    echo html_writer::link(new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id]),
        get_string('backtolesson', 'aienglishlesson'));
    echo html_writer::tag('p', get_string('selectstudentreview', 'aienglishlesson'));
    $total = $DB->count_records_sql('SELECT COUNT(DISTINCT userid) FROM {aiel_responses} WHERE versionid = :versionid',
        ['versionid' => $currentversion->id]);
    $listurl = new moodle_url('/mod/aienglishlesson/responses.php', ['id' => $id]);
    echo $OUTPUT->paging_bar($total, $page, 25, $listurl);
    $students = $DB->get_records_sql('SELECT u.* FROM {user} u
        WHERE u.id IN (SELECT r.userid FROM {aiel_responses} r WHERE r.versionid = :versionid)
        ORDER BY u.lastname, u.firstname, u.id', ['versionid' => $currentversion->id], $page * 25, 25);
    if (!$students) { echo $OUTPUT->notification(get_string('noresponses', 'aienglishlesson'), 'info'); }
    echo html_writer::start_tag('ul', ['class' => 'aiel-student-list']);
    foreach ($students as $student) {
        $studenturl = new moodle_url('/mod/aienglishlesson/responses.php',
            ['id' => $id, 'page' => $page, 'student' => $student->id]);
        echo html_writer::tag('li', html_writer::link($studenturl, s(fullname($student))));
    }
    echo html_writer::end_tag('ul');
    echo $OUTPUT->footer();
    exit;
}
$user = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
$hasresponses = $DB->record_exists('aiel_responses', ['versionid' => $currentversion->id, 'userid' => $studentid]);
if (!$hasresponses) { throw new moodle_exception('invalidrequest'); }
echo html_writer::link(new moodle_url('/mod/aienglishlesson/responses.php', ['id' => $id, 'page' => $page]),
    get_string('backtostudents', 'aienglishlesson'), ['class' => 'btn btn-secondary mb-3']);
echo $OUTPUT->heading(fullname($user), 3);
$where = 'r.versionid = :versionid AND r.userid = :userid AND r.id =
    (SELECT MAX(newest.id) FROM {aiel_responses} newest WHERE newest.versionid = r.versionid
        AND newest.userid = r.userid AND newest.sectionno = r.sectionno)';
$actionurl = new moodle_url('/mod/aienglishlesson/responses.php',
    ['id' => $id, 'page' => $page, 'student' => $studentid]);
foreach ($DB->get_records_sql('SELECT r.* FROM {aiel_responses} r WHERE ' . $where .
        ' ORDER BY r.sectionno ASC', ['versionid' => $currentversion->id, 'userid' => $studentid]) as $r) {
    \mod_aienglishlesson\local\assessment::recover_stale($r->id);
    if ($r->aistatus === 'running' && $r->timemodified < time() - 180) { $r->aistatus = 'failed'; }
    $version = $currentversion;
    $lesson = \mod_aienglishlesson\local\lesson::validate($version->content);
    $speaking = $lesson['sections'][$r->sectionno]['kind'] === 'speaking';
    $recordings = get_file_storage()->get_area_files($context->id,
        'mod_aienglishlesson', 'recording', $r->id, 'id', false);
    echo html_writer::start_tag('section', ['class' => 'aiel-practice', 'id' => 'response-' . $r->id]);
    echo html_writer::tag('h3', html_writer::tag('span', get_string('kind_' . $lesson['sections'][$r->sectionno]['kind'],
        'aienglishlesson'), ['class' => 'aiel-chip mr-2']) . s($lesson['sections'][$r->sectionno]['title']));
    echo html_writer::div(s(get_string('versionlabel', 'aienglishlesson', (object)['id' => $version->id, 'status' => $version->status]) . ' · ' . userdate($r->timemodified)));
    if ($speaking) {
        if (trim($r->response) !== '') {
            echo html_writer::tag('h4', get_string('speakingnotes', 'aienglishlesson'));
            echo html_writer::div(s($r->response), 'aiel-text');
        }
        if (!$recordings) {
            echo $OUTPUT->notification(get_string('speakingrecordingmissing', 'aienglishlesson'), 'warning');
        }
    } else {
        echo html_writer::div(s($r->response), 'aiel-text');
    }
    $images = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'image', $r->id, 'id', false);
    foreach ($images as $file) {
        echo html_writer::empty_tag('img', ['src' => moodle_url::make_pluginfile_url($context->id,
            'mod_aienglishlesson', 'image', $r->id, '/', $file->get_filename())->out(false),
            'alt' => get_string('answerimage', 'aienglishlesson'), 'style' => 'max-width:100%']);
    }
    foreach ($recordings as $file) {
        echo html_writer::tag('audio', get_string('recording', 'aienglishlesson'), ['controls' => 'controls', 'preload' => 'none',
            'src' => moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'recording', $r->id, '/', $file->get_filename())->out(false)]);
    }
    $statuskey = 'aistatus_' . $r->aistatus;
    $badges = html_writer::tag('span', get_string_manager()->string_exists($statuskey, 'aienglishlesson') ?
        get_string($statuskey, 'aienglishlesson') : s($r->aistatus), ['class' => 'aiel-chip aiel-status-' . s($r->aistatus)]);
    if ($r->aistatus === 'succeeded') {
        $band = $r->aiscore >= 80 ? 'high' : ($r->aiscore >= 50 ? 'mid' : 'low');
        $badges .= html_writer::tag('span', get_string('aiscorebadge', 'aienglishlesson', $r->aiscore),
            ['class' => 'aiel-chip aiel-chip--score aiel-band-' . $band]);
        if ($r->aiconfidence !== null) {
            $badges .= html_writer::tag('span', get_string('aiconfidencebadge', 'aienglishlesson', $r->aiconfidence), ['class' => 'aiel-chip']);
        }
    }
    if ($r->timeapproved) {
        $badges .= html_writer::tag('span', '✓ ' . get_string('approvedscore', 'aienglishlesson', $r->teacherscore),
            ['class' => 'aiel-chip aiel-chip--good']);
    }
    echo html_writer::div($badges, 'aiel-badges');
    if ($r->aistatus === 'succeeded') {
        echo html_writer::div(s($r->aifeedback), 'aiel-text aiel-feedback');
        if (trim((string)($r->aimistake ?? '')) !== '') {
            echo html_writer::div(html_writer::tag('strong', get_string('focuson', 'aienglishlesson')) . ' ' . s($r->aimistake),
                'aiel-callout aiel-callout--focus');
        }
        if (trim((string)($r->aitranscript ?? '')) !== '') {
            echo html_writer::div(html_writer::tag('strong', get_string('whatweheard', 'aienglishlesson')) .
                html_writer::div(s($r->aitranscript), 'aiel-text'), 'aiel-callout aiel-callout--heard');
        }
    }
    if ($r->timeapproved && trim((string)$r->teacherfeedback) !== '') {
        echo html_writer::div(html_writer::tag('strong', get_string('teacherfeedback', 'aienglishlesson')) . ' ' .
            s($r->teacherfeedback), 'aiel-callout aiel-callout--teacher');
    }
    $latest = $DB->get_records('aiel_responses', ['versionid' => $r->versionid,
        'userid' => $r->userid, 'sectionno' => $r->sectionno], 'id DESC', '*', 0, 1);
    if ($latest && (int)reset($latest)->id === (int)$r->id && $version->status === 'published' &&
            (!$speaking || $recordings) &&
            in_array($lesson['sections'][$r->sectionno]['kind'], \mod_aienglishlesson\local\assessment::SKILLS, true)) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl->out(false)]);
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
        echo html_writer::tag('button', get_string($r->timeapproved ? 'savescorechanges' : 'approvescore', 'aienglishlesson'),
            ['type' => 'submit', 'class' => 'btn btn-primary mt-2']);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_tag('section');
}
echo $OUTPUT->footer();
