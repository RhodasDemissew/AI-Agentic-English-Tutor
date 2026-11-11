<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($id);
require_capability('mod/aienglishlesson:view', $context);
$manage = has_capability('mod/aienglishlesson:manage', $context);
$versionid = optional_param('version', 0, PARAM_INT);
$conditions = ['lessonid' => $cm->instance];
if (!$manage) { $conditions['status'] = 'published'; }
if ($versionid) { $conditions['id'] = $versionid; }
else { $conditions['status'] = 'published'; }
$versions = $DB->get_records('aiel_versions', $conditions, 'id DESC', '*', 0, 1);
$version = $versions ? reset($versions) : false;
if (!$version) { throw new moodle_exception('invalidrequest'); }
$versionid = (int)$version->id;
$content = \mod_aienglishlesson\local\lesson::validate($version->content);
if (!isset($content['quiz'])) { throw new moodle_exception('invalidquiz', 'aienglishlesson'); }
$back = new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $versionid]);
$url = new moodle_url('/mod/aienglishlesson/quiz.php', ['id' => $id, 'version' => $versionid]);
if (data_submitted()) {
    require_sesskey();
    if ($manage || isguestuser() || !is_enrolled($context, $USER, '', true)) { throw new moodle_exception('invalidrequest'); }
    $token = required_param('attempttoken', PARAM_ALPHANUM);
    $key = 'aiel_quiz_' . $versionid;
    if (empty($SESSION->$key) || !hash_equals($SESSION->$key, $token)) { throw new moodle_exception('invalidrequest'); }
    unset($SESSION->$key);
    $answers = [];
    foreach ($content['quiz'] as $i => $question) { $answers[] = required_param('answer_' . $i, PARAM_INT); }
    \mod_aienglishlesson\local\quiz::submit($version, $USER->id, $answers);
    redirect(new moodle_url('/mod/aienglishlesson/quiz.php', ['id' => $id, 'version' => $versionid, 'review' => 1]));
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('quiz', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quiz', 'aienglishlesson'));
echo html_writer::link($back, get_string('backtolesson', 'aienglishlesson'), ['class' => 'btn btn-secondary mb-3']);
$latest = \mod_aienglishlesson\local\quiz::latest($versionid, $USER->id);
if ($latest) {
    echo html_writer::div(get_string('quizresult', 'aienglishlesson', (object)[
        'score' => $latest->score, 'attempts' => $latest->attemptno]), 'alert alert-info');
}
$review = $manage || (optional_param('review', 0, PARAM_BOOL) && $latest);
$saved = $latest ? json_decode($latest->answers, true) : [];
if ($latest && !$manage) {
    echo html_writer::link(new moodle_url('/mod/aienglishlesson/quiz.php',
        ['id' => $id, 'version' => $versionid, 'review' => $review ? 0 : 1]),
        get_string($review ? 'retakequiz' : 'reviewquizanswers', 'aienglishlesson'),
        ['class' => 'btn btn-secondary mb-3']);
}
if (!$manage && !$review) {
    $token = bin2hex(random_bytes(16));
    $key = 'aiel_quiz_' . $versionid;
    $SESSION->$key = $token;
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'attempttoken', 'value' => $token]);
}
foreach ($content['quiz'] as $i => $question) {
    echo html_writer::start_tag('section', ['class' => 'aiel-practice aiel-quiz-question']);
    echo html_writer::tag('h3', s(($i + 1) . '. ' . $question['question']));
    foreach ($question['choices'] as $j => $choice) {
        $input = ($manage || $review) ? '' : html_writer::empty_tag('input', ['type' => 'radio', 'name' => 'answer_' . $i,
            'value' => $j, 'required' => 'required', 'id' => 'aiel-q-' . $i . '-' . $j]);
        $class = 'aiel-quiz-choice' . ($review ? ' aiel-quiz-choice--reviewed' : '');
        $status = '';
        if ($review && $j === $question['correct']) {
            $class .= ' aiel-quiz-choice--correct';
            $status = html_writer::tag('strong', get_string('quizcorrect', 'aienglishlesson'),
                ['class' => 'aiel-choice-status']);
        } else if ($review && !$manage && ($saved[$i] ?? -1) === $j) {
            $class .= ' aiel-quiz-choice--incorrect';
            $status = html_writer::tag('strong', get_string('quizincorrect', 'aienglishlesson'),
                ['class' => 'aiel-choice-status']);
        }
        $attrs = ['class' => $class];
        if ($input !== '') { $attrs['for'] = 'aiel-q-' . $i . '-' . $j; }
        echo html_writer::tag($input !== '' ? 'label' : 'div', $input . ' ' . s($choice) . $status, $attrs);
    }
    if ($review) {
        if (!$manage) {
            echo html_writer::div(get_string('yourquizanswer', 'aienglishlesson',
                $question['choices'][$saved[$i] ?? -1] ?? '—'));
            echo html_writer::div(get_string(($saved[$i] ?? -1) === $question['correct'] ?
                'quizcorrect' : 'quizincorrect', 'aienglishlesson'));
        }
        echo html_writer::div(get_string('correctanswer', 'aienglishlesson', $question['choices'][$question['correct']]));
        echo html_writer::div(s($question['explanation']), 'aiel-text');
    }
    echo html_writer::end_tag('section');
}
if (!$manage && !$review) {
    echo html_writer::tag('button', get_string('submitquiz', 'aienglishlesson'),
        ['type' => 'submit', 'class' => 'btn btn-primary my-3']);
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
