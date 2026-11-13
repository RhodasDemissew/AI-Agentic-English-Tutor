<?php
// Graded practice games for one published lesson version. Scored by PHP; no AI requests.
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
if (empty($content['activities'])) { throw new moodle_exception('invalidpractice', 'aienglishlesson'); }
$activities = $content['activities'];
$back = new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $versionid]);
$url = new moodle_url('/mod/aienglishlesson/practice.php', ['id' => $id, 'version' => $versionid]);
$quizurl = isset($content['quiz']) ? new moodle_url('/mod/aienglishlesson/quiz.php', ['id' => $id, 'version' => $versionid]) : null;
$ajax = optional_param('ajax', '', PARAM_ALPHA);
if ($ajax !== '') {
    // Live game: one check per game (first answer locks it), then finish saves the run as one attempt.
    $respond = static function(int $status, array $payload) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };
    if (!data_submitted() || !confirm_sesskey()) { $respond(403, ['error' => get_string('practicesession', 'aienglishlesson')]); }
    if ($manage || isguestuser() || !is_enrolled($context, $USER, '', true) || $version->status !== 'published') {
        $respond(403, ['error' => get_string('practicesession', 'aienglishlesson')]);
    }
    $runs = $SESSION->aiel_practice_run ?? [];
    $run = $runs[$versionid] ?? null;
    if (!$run || !hash_equals($run['token'], optional_param('token', '', PARAM_ALPHANUM))) {
        $respond(409, ['error' => get_string('practicesession', 'aienglishlesson')]);
    }
    try {
        if ($ajax === 'check') {
            $answer = json_decode(required_param('answer', PARAM_RAW), true);
            $result = \mod_aienglishlesson\local\practice::check($run, $activities, required_param('game', PARAM_INT), $answer);
            $runs[$versionid] = $run;
            $SESSION->aiel_practice_run = $runs;
            $respond(200, $result);
        }
        if ($ajax === 'finish') {
            $answers = \mod_aienglishlesson\local\practice::run_answers($run, $activities);
            if ($answers === null) { $respond(400, ['error' => get_string('practiceunfinished', 'aienglishlesson')]); }
            \mod_aienglishlesson\local\practice::submit($version, $USER->id, $answers);
            unset($runs[$versionid]);
            $SESSION->aiel_practice_run = $runs;
            $saved = \mod_aienglishlesson\local\practice::latest($versionid, $USER->id);
            $score = (float)$saved->score;
            $correct = 0;
            foreach ($activities as $i => $activity) {
                if (\mod_aienglishlesson\local\practice::is_correct($activity, $answers[$i])) { $correct++; }
            }
            $band = $score >= 80 ? 'high' : ($score >= 50 ? 'mid' : 'low');
            $respond(200, ['score' => format_float($score, 0), 'stars' => \mod_aienglishlesson\local\practice::stars($score),
                'band' => $band, 'message' => get_string('resultmessage_' . $band, 'aienglishlesson'),
                'detail' => get_string('gamecorrectcount', 'aienglishlesson', (object)['correct' => $correct, 'total' => count($activities)])]);
        }
    } catch (moodle_exception $e) {
        $respond(400, ['error' => get_string('practicesession', 'aienglishlesson')]);
    }
    $respond(400, ['error' => get_string('practicesession', 'aienglishlesson')]);
}
if (data_submitted()) {
    require_sesskey();
    if ($manage || isguestuser() || !is_enrolled($context, $USER, '', true)) { throw new moodle_exception('invalidrequest'); }
    $token = required_param('attempttoken', PARAM_ALPHANUM);
    $key = 'aiel_practice_' . $versionid;
    if (empty($SESSION->$key) || !hash_equals($SESSION->$key, $token)) { throw new moodle_exception('invalidrequest'); }
    unset($SESSION->$key);
    $answers = \mod_aienglishlesson\local\practice::answers_from_request($activities);
    \mod_aienglishlesson\local\practice::submit($version, $USER->id, $answers);
    redirect(new moodle_url('/mod/aienglishlesson/practice.php', ['id' => $id, 'version' => $versionid, 'review' => 1]));
}
$PAGE->set_url($url);
$PAGE->set_title(get_string('practicegames', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('mod_aienglishlesson/practice', 'init');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('practicegames', 'aienglishlesson'));
echo html_writer::tag('p', get_string('practiceintro', 'aienglishlesson'), ['class' => 'aiel-note']);
$latest = \mod_aienglishlesson\local\practice::latest($versionid, $USER->id);
$review = $manage || (optional_param('review', 0, PARAM_BOOL) && $latest);
$saved = $latest ? json_decode($latest->answers, true) : [];
$quizdone = $quizurl && \mod_aienglishlesson\local\quiz::latest($versionid, $USER->id);
// Where to go after the games: the quiz until it has been taken, then back to the lesson report.
$nexturl = $quizurl && !$quizdone ? $quizurl : $back;
$nextlabel = get_string($quizurl && !$quizdone ? 'nextquiz' : 'finishlesson', 'aienglishlesson');
if ($latest && !$manage && $review) {
    $band = $latest->score >= 80 ? 'high' : ($latest->score >= 50 ? 'mid' : 'low');
    $stars = \mod_aienglishlesson\local\practice::stars((float)$latest->score);
    echo html_writer::div(
        html_writer::div(html_writer::tag('span', format_float($latest->score, 0) . '%', ['class' => 'aiel-result-percent']),
            'aiel-result-dial') .
        html_writer::div(
            html_writer::tag('p', str_repeat('★', $stars) . str_repeat('☆', 3 - $stars), ['class' => 'aiel-stars', 'aria-hidden' => 'true']) .
            html_writer::tag('p', get_string('resultmessage_' . $band, 'aienglishlesson'), ['class' => 'aiel-result-message']) .
            html_writer::tag('p', get_string('attemptcount', 'aienglishlesson', $latest->attemptno) . ' · ' .
                get_string('latestcounts', 'aienglishlesson'), ['class' => 'aiel-banner-meta']),
            'aiel-result-text'),
        'aiel-result-card aiel-band-' . $band);
} else if ($latest && !$manage) {
    echo html_writer::tag('p', get_string('lastscore', 'aienglishlesson', format_float($latest->score, 0)), ['class' => 'aiel-note']);
}
$buttons = html_writer::link($back, get_string('backtolesson', 'aienglishlesson'), ['class' => 'btn btn-secondary']);
if ($latest && !$manage && !$review) {
    $buttons .= html_writer::link(new moodle_url($url, ['review' => 1]), get_string('reviewpracticeanswers', 'aienglishlesson'),
        ['class' => 'btn btn-secondary']);
}
if ($review && !$manage) {
    $buttons .= html_writer::link($url, get_string('retrypractice', 'aienglishlesson'), ['class' => 'btn btn-secondary']) .
        html_writer::link($nexturl, $nextlabel, ['class' => 'btn btn-primary']);
}
if ($manage && $quizurl) {
    $buttons .= html_writer::link($quizurl, get_string('nextquiz', 'aienglishlesson'), ['class' => 'btn btn-primary']);
}
echo html_writer::div($buttons, 'aiel-toolbar');
$form = !$manage && !$review;
if ($form) {
    // Live game for JavaScript browsers; the plain form below remains as the fallback.
    $runs = $SESSION->aiel_practice_run ?? [];
    $runs[$versionid] = \mod_aienglishlesson\local\practice::new_run();
    $SESSION->aiel_practice_run = $runs;
    $strings = [];
    foreach (['gamecheck', 'gamenext', 'gamefinish', 'gameofn', 'gameright', 'gamewrong', 'gameanswerwas', 'gamereset',
            'gametapwords', 'gametapmatch', 'gametapgap', 'gamepoints', 'gamefinishing', 'retrypractice',
            'reviewpracticeanswers', 'practicesession', 'gameorder', 'gamematch', 'gamegap', 'gamecomplete'] as $key) {
        $strings[$key] = get_string($key, 'aienglishlesson');
    }
    $config = ['url' => $url->out(false), 'sesskey' => sesskey(), 'token' => $runs[$versionid]['token'],
        'games' => \mod_aienglishlesson\local\practice::client_games($activities, $versionid), 'strings' => $strings,
        'againurl' => $url->out(false), 'reviewurl' => (new moodle_url($url, ['review' => 1]))->out(false),
        'nexturl' => $nexturl->out(false), 'nextlabel' => $nextlabel];
    echo html_writer::div('', 'aiel-arcade', ['id' => 'aiel-arcade', 'hidden' => 'hidden',
        'data-config' => json_encode($config, JSON_UNESCAPED_UNICODE)]);
    $token = bin2hex(random_bytes(16));
    $key = 'aiel_practice_' . $versionid;
    $SESSION->$key = $token;
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'aiel-practice-form']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'attempttoken', 'value' => $token]);
}
$typelabels = ['order' => get_string('gameorder', 'aienglishlesson'), 'match' => get_string('gamematch', 'aienglishlesson'),
    'gap' => get_string('gamegap', 'aienglishlesson')];
foreach ($activities as $i => $activity) {
    $seed = $versionid . '-' . $i;
    $answer = $saved[$i] ?? null;
    $correct = $review && !$manage ? \mod_aienglishlesson\local\practice::is_correct($activity, $answer) : null;
    $class = 'aiel-practice aiel-game aiel-game--' . $activity['type'];
    if ($correct !== null) { $class .= $correct ? ' aiel-game--right' : ' aiel-game--wrong'; }
    echo html_writer::start_tag('section', ['class' => $class, 'data-type' => $activity['type']]);
    $head = html_writer::tag('span', $typelabels[$activity['type']], ['class' => 'aiel-chip']);
    if ($correct !== null) {
        $head .= html_writer::tag('span', get_string($correct ? 'quizcorrect' : 'quizincorrect', 'aienglishlesson'),
            ['class' => 'aiel-chip ' . ($correct ? 'aiel-chip--good' : 'aiel-chip--bad')]);
    }
    echo html_writer::div($head, 'aiel-game-head');
    echo html_writer::tag('h3', s(($i + 1) . '. ' . $activity['prompt']));
    if ($activity['type'] === 'order') {
        $words = \mod_aienglishlesson\local\practice::shuffled($activity['items'], $seed);
        if ($form) {
            echo html_writer::div('', 'aiel-order-answer', ['data-region' => 'answer', 'aria-live' => 'polite']);
            $chips = '';
            foreach ($words as $word) {
                $chips .= html_writer::tag('button', s($word), ['type' => 'button', 'class' => 'aiel-word', 'data-word' => $word]);
            }
            echo html_writer::div($chips, 'aiel-order-pool', ['data-region' => 'pool', 'hidden' => 'hidden']);
            echo html_writer::tag('button', get_string('gamereset', 'aienglishlesson'),
                ['type' => 'button', 'class' => 'btn btn-link btn-sm px-0', 'data-action' => 'reset', 'hidden' => 'hidden']);
            $selects = '';
            foreach ($activity['items'] as $j => $unused) {
                $options = ['' => '—'];
                foreach ($words as $word) { $options[$word] = $word; }
                $selects .= html_writer::label(get_string('gameposition', 'aienglishlesson', $j + 1), 'act_' . $i . '_' . $j, false,
                    ['class' => 'sr-only']) . html_writer::select($options, 'act_' . $i . '_' . $j, '', false,
                    ['id' => 'act_' . $i . '_' . $j, 'class' => 'custom-select aiel-order-select', 'required' => 'required']);
            }
            echo html_writer::div($selects, 'aiel-order-selects', ['data-region' => 'selects']);
        } else {
            if (!$manage) {
                echo html_writer::div(get_string('yourquizanswer', 'aienglishlesson', s(implode(' ', (array)$answer))));
            }
            echo html_writer::div(get_string('correctanswer', 'aienglishlesson', s(implode(' ', $activity['items']))), 'aiel-answer-line');
        }
    } else if ($activity['type'] === 'match') {
        $rights = \mod_aienglishlesson\local\practice::shuffled(array_column($activity['pairs'], 'right'), $seed);
        echo html_writer::start_div('aiel-match');
        foreach ($activity['pairs'] as $j => $pair) {
            $row = html_writer::tag('span', s($pair['left']), ['class' => 'aiel-match-left']);
            if ($form) {
                $options = ['' => get_string('gamechoose', 'aienglishlesson')];
                foreach ($rights as $right) { $options[$right] = $right; }
                $row .= html_writer::label(s($pair['left']), 'act_' . $i . '_' . $j, false, ['class' => 'sr-only']) .
                    html_writer::select($options, 'act_' . $i . '_' . $j, '', false,
                        ['id' => 'act_' . $i . '_' . $j, 'class' => 'custom-select', 'required' => 'required']);
            } else {
                $given = is_array($answer) ? ($answer[$j] ?? '') : '';
                $ok = \core_text::strtolower(trim($given)) === \core_text::strtolower($pair['right']);
                $row .= html_writer::tag('span', s($pair['right']), ['class' => 'aiel-match-right']);
                if (!$manage && !$ok) {
                    $row .= html_writer::tag('span', get_string('yourquizanswer', 'aienglishlesson', s($given ?: '—')),
                        ['class' => 'aiel-match-given']);
                }
            }
            echo html_writer::div($row, 'aiel-match-row');
        }
        echo html_writer::end_div();
    } else {
        echo html_writer::tag('p', s(str_replace('___', '______', $activity['sentence'])), ['class' => 'aiel-gap-sentence']);
        foreach ($activity['choices'] as $j => $choice) {
            if ($form) {
                $input = html_writer::empty_tag('input', ['type' => 'radio', 'name' => 'act_' . $i, 'value' => $j,
                    'required' => 'required', 'id' => 'act_' . $i . '_' . $j]);
                echo html_writer::tag('label', $input . ' ' . s($choice), ['class' => 'aiel-quiz-choice', 'for' => 'act_' . $i . '_' . $j]);
            } else {
                $choiceclass = 'aiel-quiz-choice aiel-quiz-choice--reviewed';
                if ($j === $activity['correct']) { $choiceclass .= ' aiel-quiz-choice--correct'; }
                else if (!$manage && $answer === $j) { $choiceclass .= ' aiel-quiz-choice--incorrect'; }
                echo html_writer::div(s($choice), $choiceclass);
            }
        }
    }
    echo html_writer::end_tag('section');
}
if ($form) {
    echo html_writer::tag('button', get_string('submitpractice', 'aienglishlesson'),
        ['type' => 'submit', 'class' => 'btn btn-primary btn-lg my-3']);
    echo html_writer::end_tag('form');
}
if ($review && !$manage) {
    // End-of-games actions at the bottom as well as the top.
    echo html_writer::div(
        html_writer::link($url, get_string('retrypractice', 'aienglishlesson'), ['class' => 'btn btn-secondary']) .
        html_writer::link($nexturl, $nextlabel, ['class' => 'btn btn-primary']), 'aiel-toolbar aiel-endbar');
}
echo $OUTPUT->footer();
