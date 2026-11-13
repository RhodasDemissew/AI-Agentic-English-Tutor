<?php
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($id);
require_capability('mod/aienglishlesson:view', $context);
$activity = $DB->get_record('aienglishlesson', ['id' => $cm->instance], '*', MUST_EXIST);
$manage = has_capability('mod/aienglishlesson:manage', $context);
$PAGE->set_url('/mod/aienglishlesson/view.php', ['id' => $id]);
$PAGE->set_title(format_string($activity->name)); $PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('mod_aienglishlesson/recorder', 'init');
$PAGE->requires->js_call_amd('mod_aienglishlesson/learner', 'init');
$versionid = optional_param('version', 0, PARAM_INT);
$conditions = ['lessonid' => $activity->id];
if (!$manage) { $conditions['status'] = 'published'; }
if ($versionid) { $conditions['id'] = $versionid; }
else { $conditions['status'] = 'published'; }
$versions = $DB->get_records('aiel_versions', $conditions, 'id DESC', '*', 0, 1);
$version = $versions ? reset($versions) : false;
if ($version && $version->status === 'published') { (new completion_info($course))->set_module_viewed($cm); }
$lessoncontent = $version ? \mod_aienglishlesson\local\lesson::validate($version->content) : null;
echo $OUTPUT->header();
// Personal greeting header: who is learning, which lesson, and how far they are.
$hour = (int)userdate(time(), '%H', 99, false);
$greetingkey = $hour < 12 ? 'greetingmorning' : ($hour < 17 ? 'greetingafternoon' : 'greetingevening');
$hero = html_writer::empty_tag('img', ['src' => $OUTPUT->image_url('logo', 'mod_aienglishlesson')->out(false),
    'alt' => get_string('logoalt', 'aienglishlesson'), 'class' => 'aiel-hero-logo']);
$herotext = html_writer::tag('p', s(get_string($greetingkey, 'aienglishlesson', $USER->firstname)), ['class' => 'aiel-hero-greeting']);
$herotext .= html_writer::tag('h2', s($lessoncontent ? $lessoncontent['title'] : format_string($activity->name)), ['class' => 'aiel-hero-title']);
$herotext .= html_writer::tag('p', s(format_string($activity->name) . ' · ' . format_string($course->fullname)), ['class' => 'aiel-hero-meta']);
$heroextra = '';
if ($lessoncontent && $version->status === 'published' && !$manage) {
    $summary = \mod_aienglishlesson\local\progress::summary($version, $lessoncontent, (int)$USER->id);
    $donecount = $summary['done'];
    $total = $summary['total'];
    $percent = (int)round(100 * min($donecount, $total) / max(1, $total));
    $herotext .= html_writer::div(html_writer::div('', 'aiel-hero-bar-fill', ['style' => 'width: ' . $percent . '%']),
        'aiel-hero-bar', ['role' => 'progressbar', 'aria-valuemin' => 0, 'aria-valuemax' => 100, 'aria-valuenow' => $percent,
            'aria-label' => get_string('progresscount', 'aienglishlesson', (object)['done' => $donecount, 'total' => $total])]);
    $herotext .= html_writer::tag('p', get_string('heroprogress', 'aienglishlesson',
        (object)['done' => $donecount, 'total' => $total, 'percent' => $percent]), ['class' => 'aiel-hero-progress']);
    $sectionurl = static function(int $section) use ($id, $version) {
        return new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $version->id, 'section' => $section]);
    };
    $practiceurl = new moodle_url('/mod/aienglishlesson/practice.php', ['id' => $id, 'version' => $version->id]);
    $quizpageurl = new moodle_url('/mod/aienglishlesson/quiz.php', ['id' => $id, 'version' => $version->id]);
    if ($summary['nextsection'] !== null) {
        $heroextra = html_writer::link($sectionurl($summary['nextsection']), get_string('herocontinue', 'aienglishlesson',
            s($lessoncontent['sections'][$summary['nextsection']]['title'])), ['class' => 'btn btn-primary aiel-hero-cta']);
    } else if ($summary['hasgames'] && $summary['gamescore'] === null) {
        $heroextra = html_writer::link($practiceurl, get_string('herogames', 'aienglishlesson'), ['class' => 'btn btn-primary aiel-hero-cta']);
    } else if ($summary['hasquiz'] && $summary['quizscore'] === null) {
        $heroextra = html_writer::link($quizpageurl, get_string('heroquiz', 'aienglishlesson'), ['class' => 'btn btn-primary aiel-hero-cta']);
    }
    if ($summary['complete']) {
        // Short end-of-lesson report: what to practise next, or praise when every area is strong.
        $report = html_writer::tag('span', '✓ ' . get_string('lessoncomplete', 'aienglishlesson'), ['class' => 'aiel-hero-done']);
        if ($summary['finalgrade'] !== null) {
            $report .= html_writer::tag('span', get_string('herofinal', 'aienglishlesson', format_float($summary['finalgrade'], 0)),
                ['class' => 'aiel-hero-final']);
        }
        if ($summary['focus']) {
            $items = [];
            foreach ($summary['focus'] as $item) {
                $label = get_string($item['area'] === 'games' ? 'practicegames' :
                    ($item['area'] === 'quiz' ? 'quiz' : 'kind_' . $item['area']), 'aienglishlesson');
                $text = $item['score'] === null ? get_string('focusmissing', 'aienglishlesson', $label) :
                    $label . ' ' . format_float($item['score'], 0);
                $url = $item['area'] === 'games' ? $practiceurl : ($item['area'] === 'quiz' ? $quizpageurl :
                    ($item['section'] !== null ? $sectionurl($item['section']) : null));
                $items[] = $url ? html_writer::link($url, s($text)) : s($text);
            }
            $report .= html_writer::tag('p', html_writer::tag('strong', get_string('focustitle', 'aienglishlesson')) . ' ' .
                implode(' · ', $items) . ' — ' . get_string('focusadvice', 'aienglishlesson'), ['class' => 'aiel-hero-report']);
        } else {
            $report .= html_writer::tag('p', html_writer::tag('strong', get_string('focusexcellent', 'aienglishlesson')) . ' ' .
                get_string('focusexcellentdetail', 'aienglishlesson', \mod_aienglishlesson\local\progress::FOCUS_BELOW),
                ['class' => 'aiel-hero-report aiel-hero-report--good']);
        }
        $herotext .= html_writer::div($report, 'aiel-hero-summary');
    }
}
echo html_writer::div($hero . html_writer::div($herotext, 'aiel-hero-text') . $heroextra, 'aiel-hero');
if ($manage) {
    $jobs = $DB->get_records('aiel_jobs', ['lessonid' => $activity->id], 'id DESC', '*', 0, 1);
    $latestjob = $jobs ? reset($jobs) : false;
    $resume = $latestjob && ($latestjob->status === 'queued' ||
        ($latestjob->status === 'running' && $latestjob->timemodified < time() - \mod_aienglishlesson\local\jobs::stale_after()));
    // Teacher toolbar: the main action first, then review tools.
    $toolbar = $OUTPUT->single_button(new moodle_url('/mod/aienglishlesson/action.php',
        ['id' => $id, 'action' => $resume ? 'resume' : 'generate']),
        get_string($resume ? 'resume' : 'generate', 'aienglishlesson'), 'post', ['type' => 'primary']);
    if ($version) {
        $toolbar .= html_writer::link(new moodle_url('/mod/aienglishlesson/rubrics.php',
            ['id' => $id, 'version' => $version->id]), get_string('rubrics', 'aienglishlesson'),
            ['class' => 'btn btn-secondary']);
    }
    $toolbar .= html_writer::link(new moodle_url('/mod/aienglishlesson/responses.php', ['id' => $id]),
        get_string('reviewresponses', 'aienglishlesson'), ['class' => 'btn btn-secondary']);
    // Staged generation tests are an administrator tool, hidden unless switched on in the plugin settings.
    if (get_config('aienglishlesson', 'showdiagnostics') &&
            has_capability('moodle/site:config', context_system::instance())) {
        $toolbar .= html_writer::link(new moodle_url('/mod/aienglishlesson/diagnostics.php', ['id' => $id]),
            get_string('generationdiagnostics', 'aienglishlesson'), ['class' => 'btn btn-outline-secondary']);
    }
    echo html_writer::div($toolbar, 'aiel-toolbar');
    if ($latestjob && $latestjob->status === 'failed') {
        $diagnostic = $latestjob->diagnostic;
        $key = 'diagnostic_' . $diagnostic;
        if ($diagnostic && get_string_manager()->string_exists($key, 'aienglishlesson')) {
            $diagnostic = get_string($key, 'aienglishlesson');
        } else if ($diagnostic && get_string_manager()->string_exists($diagnostic, 'aienglishlesson')) {
            $diagnostic = get_string($diagnostic, 'aienglishlesson');
        } else {
            $diagnostic = get_string('diagnostic_internal', 'aienglishlesson');
        }
        echo $OUTPUT->notification(get_string('generationfailed', 'aienglishlesson', $diagnostic), 'warning');
    } else if ($latestjob && in_array($latestjob->status, ['queued', 'running'], true)) {
        echo $OUTPUT->notification(get_string($resume ? 'generationpaused' : 'generationinprogress', 'aienglishlesson'), 'info');
    }
    $details = '';
    $files = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
    if ($files) {
        $items = [];
        foreach ($files as $file) {
            $items[] = html_writer::link(moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'source', 0, '/',
                $file->get_filename()), s($file->get_filename()));
        }
        $details .= html_writer::div(html_writer::tag('strong', get_string('sourcefiles', 'aienglishlesson')) . ' ' .
            implode(' · ', $items), 'aiel-teacher-line');
    }
    $allversions = $DB->get_records('aiel_versions', ['lessonid' => $activity->id], 'id DESC');
    if ($allversions) {
        $items = [];
        foreach ($allversions as $v) {
            $statuskey = 'status_' . $v->status;
            $label = s(get_string('versionlabel', 'aienglishlesson', (object)['id' => $v->id,
                'status' => get_string_manager()->string_exists($statuskey, 'aienglishlesson') ?
                    get_string($statuskey, 'aienglishlesson') : $v->status]));
            $items[] = $version && $v->id == $version->id ? html_writer::tag('strong', $label, ['aria-current' => 'true']) :
                html_writer::link(new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $v->id]), $label);
        }
        $details .= html_writer::div(html_writer::tag('strong', get_string('versions', 'aienglishlesson')) . ' ' .
            implode(' · ', $items), 'aiel-teacher-line');
    }
    if ($details) { echo html_writer::div($details, 'aiel-teacher-details'); }
    if ($version && $version->status === 'draft') {
        $draftcontent = \mod_aienglishlesson\local\lesson::validate($version->content);
        $audiostatus = explode(':', (string)$version->audiostatus)[0];
        if ($audiostatus !== 'ready' && $audiostatus !== 'none') {
            $audiokey = 'audiostatus_' . $audiostatus;
            echo $OUTPUT->notification(get_string(get_string_manager()->string_exists($audiokey, 'aienglishlesson') ?
                $audiokey : 'audiostatus_other', 'aienglishlesson'), $audiostatus === 'failed' ? 'warning' : 'info');
        }
        $draftbar = html_writer::tag('span', get_string('draftreview', 'aienglishlesson'), ['class' => 'aiel-draft-label']);
        $draftbar .= html_writer::link(new moodle_url('/mod/aienglishlesson/edit.php', ['id' => $id, 'version' => $version->id]),
            get_string('editdraft', 'aienglishlesson'), ['class' => 'btn btn-secondary']);
        if (isset($draftcontent['quiz'])) {
            $draftbar .= html_writer::link(new moodle_url('/mod/aienglishlesson/quiz.php', ['id' => $id, 'version' => $version->id]),
                get_string('reviewquiz', 'aienglishlesson'), ['class' => 'btn btn-secondary']);
        }
        if (!empty($draftcontent['activities'])) {
            $draftbar .= html_writer::link(new moodle_url('/mod/aienglishlesson/practice.php', ['id' => $id, 'version' => $version->id]),
                get_string('reviewpractice', 'aienglishlesson'), ['class' => 'btn btn-secondary']);
        }
        // Audio is prepared automatically after generation; offer a retry only when some is still missing.
        if (\mod_aienglishlesson\local\audio::provider() !== 'upload' &&
                !in_array($audiostatus, ['ready', 'queued', 'running'], true) &&
                !\mod_aienglishlesson\local\audio::ready($version, $context)) {
            $url = \mod_aienglishlesson\local\jobs::direct() ? '/mod/aienglishlesson/prepareaudio.php' : '/mod/aienglishlesson/action.php';
            $draftbar .= $OUTPUT->single_button(new moodle_url($url, ['id' => $id, 'version' => $version->id, 'action' => 'audio']), get_string('retryaudio', 'aienglishlesson'), 'post');
        }
        $draftbar .= $OUTPUT->single_button(new moodle_url('/mod/aienglishlesson/action.php', ['id' => $id, 'version' => $version->id, 'modified' => $version->timemodified, 'action' => 'publish']), get_string('publish', 'aienglishlesson'), 'post', ['type' => 'primary']);
        echo html_writer::div($draftbar, 'aiel-toolbar aiel-toolbar--draft');
    }
}
if (!$version) { echo $OUTPUT->notification(get_string('nopublished', 'aienglishlesson'), 'info'); }
else {
    $grade = $DB->get_record('aiel_grades', ['lessonid' => $activity->id, 'userid' => $USER->id]);
    if ($grade && ($grade->versionid != $version->id || $manage)) {
        echo $OUTPUT->notification(get_string($grade->versionid == $version->id ?
            'releasedgrade' : 'oldreleasedgrade', 'aienglishlesson',
            (object)['grade' => $grade->grade, 'version' => $grade->versionid]), 'info');
    }
    $content = $lessoncontent;
    $fullcontent = $content;
    if ($version->status === 'published' && !$manage) {
        // One card per scored part, so learners can see which skill needs work.
        $attempts = $DB->get_records('aiel_responses', ['versionid' => $version->id, 'userid' => $USER->id], 'id DESC');
        $skillscores = \mod_aienglishlesson\local\assessment::skill_scores($content, $attempts);
        $quizattempt = isset($content['quiz']) ? \mod_aienglishlesson\local\quiz::latest($version->id, $USER->id) : false;
        $practiceattempt = !empty($content['activities']) ?
            \mod_aienglishlesson\local\practice::latest($version->id, $USER->id) : false;
        $card = static function(string $label, ?float $score, string $extra = '', string $class = '') {
            $band = $score === null ? 'none' : ($score >= 80 ? 'high' : ($score >= 50 ? 'mid' : 'low'));
            $value = $score === null ? html_writer::tag('span', get_string('notyet', 'aienglishlesson'), ['class' => 'aiel-score-none']) :
                html_writer::tag('strong', format_float($score, 0)) . html_writer::tag('span', '/100', ['class' => 'aiel-score-of']);
            return html_writer::div(html_writer::tag('h3', s($label)) . html_writer::tag('p', $value, ['class' => 'aiel-score-value']) .
                ($extra ? html_writer::tag('small', $extra) : ''), 'aiel-grade-card aiel-band-' . $band . ' ' . $class);
        };
        $cards = '';
        foreach (\mod_aienglishlesson\local\assessment::SKILLS as $skill) {
            $info = $skillscores[$skill] ?? null;
            $cards .= $card(get_string('kind_' . $skill, 'aienglishlesson'), $info ? $info['score'] : null,
                $info && $info['approved'] ? '✓ ' . get_string('teacherapproved', 'aienglishlesson') : '');
        }
        if (isset($content['quiz'])) {
            $cards .= $card(get_string('quiz', 'aienglishlesson'), $quizattempt ? (float)$quizattempt->score : null);
        }
        if (!empty($content['activities'])) {
            $cards .= $card(get_string('practicegames', 'aienglishlesson'), $practiceattempt ? (float)$practiceattempt->score : null);
        }
        $final = $grade && $grade->versionid == $version->id ? (float)$grade->grade : null;
        $cards .= $card(get_string('finalgrade', 'aienglishlesson'), $final,
            get_string(!empty($content['activities']) ? 'finalgradeweights' : 'finalgradelegacy', 'aienglishlesson'), 'aiel-grade-card--final');
        echo html_writer::div($cards, 'aiel-grades');
    }
    if (!$manage) { $content = \mod_aienglishlesson\local\lesson::student_data($content); }
    $section = max(0, min(count($content['sections']) - 1, optional_param('section', 0, PARAM_INT)));
    $data = $content['sections'][$section];
    if (in_array($data['kind'], ['listening', 'speaking'], true) && !empty($data['audioscript']) &&
            str_contains($data['body'], $data['audioscript'])) {
        $data['body'] = trim(str_replace($data['audioscript'], '', $data['body']));
    }
    $data['lessontitle'] = $content['title']; $data['focus'] = $content['focus'];
    $data['speaking'] = $data['kind'] === 'speaking';
    $data['assessed'] = in_array($data['kind'], \mod_aienglishlesson\local\assessment::SKILLS, true);
    $data['young'] = $activity->gradeband === 'KG' || preg_match('/^G[1-4]$/', (string)$activity->gradeband) === 1;
    $data['showexample'] = $data['kind'] === 'workedexample' ? $data['example'] : '';
    $audiofile = \mod_aienglishlesson\local\audio::file($context, $version->id, $section);
    if ($audiofile) { $data['audiourl'] = moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'audio', $version->id, '/', $audiofile->get_filename())->out(false); }
    $responses = $data['assessed'] ? $DB->get_records('aiel_responses', ['versionid' => $version->id,
        'userid' => $USER->id, 'sectionno' => $section], 'id DESC', '*', 0, 1) : [];
    $response = $responses ? reset($responses) : false;
    if ($response) {
        \mod_aienglishlesson\local\assessment::recover_stale($response->id);
        if ($response->aistatus === 'running' && $response->timemodified < time() - 180) {
            $response->aistatus = 'failed';
        }
        $data['response'] = $response->response;
        $data['attemptid'] = $response->id;
        $data['submitted'] = true;
        if ($response->aistatus === 'succeeded') {
            $data['aiscore'] = get_string('provisionalscore', 'aienglishlesson', $response->aiscore);
            $data['scoreband'] = $response->aiscore >= 80 ? 'high' : ($response->aiscore >= 50 ? 'mid' : 'low');
            $data['aifeedback'] = $response->aifeedback;
            $data['aimistake'] = trim((string)($response->aimistake ?? ''));
            $data['aitranscript'] = trim((string)($response->aitranscript ?? ''));
        } else if ($response->aistatus === 'failed') {
            $data['aifeedback'] = get_string('aifailed', 'aienglishlesson');
        } else if ($response->aistatus === 'running') {
            $data['aifeedback'] = get_string('airunning', 'aienglishlesson');
        } else if ($response->aistatus === 'limited') {
            $data['aifeedback'] = get_string('ailimited', 'aienglishlesson');
        }
        if ($response->timeapproved) {
            $data['approvedscore'] = get_string('approvedscore', 'aienglishlesson', $response->teacherscore);
            $data['teacherfeedback'] = $response->teacherfeedback;
        }
        $checked = $data['speaking'] ? null :
            \mod_aienglishlesson\local\responses::check_answer($fullcontent['sections'][$section], $response->response);
        if ($checked !== null) {
            $data['answercheck'] = get_string($checked ? 'answercorrect' : 'answerincorrect', 'aienglishlesson');
        }
        $recordings = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'recording', $response->id, 'id', false);
        if ($recordings) {
            $recording = reset($recordings);
            $data['recordingurl'] = moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'recording', $response->id, '/', $recording->get_filename())->out(false);
        }
        if ($data['speaking'] && !$recordings) {
            $data['speakingmissing'] = get_string('speakingrecordingmissing', 'aienglishlesson');
        }
        $data['deletelabel'] = get_string($recordings ? 'clearresponserecording' : 'clearresponse', 'aienglishlesson');
        $images = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'image', $response->id, 'id', false);
        if ($images) {
            $image = reset($images);
            $data['imageurl'] = moodle_url::make_pluginfile_url($context->id,
                'mod_aienglishlesson', 'image', $response->id, '/', $image->get_filename())->out(false);
        }
    }
    $data['teacher'] = $manage; $data['id'] = $id; $data['version'] = $version->id;
    if (isset($content['quiz'])) {
        $data['quizurl'] = (new moodle_url('/mod/aienglishlesson/quiz.php',
            ['id' => $id, 'version' => $version->id]))->out(false);
    }
    if (!empty($fullcontent['activities'])) {
        $data['practiceurl'] = (new moodle_url('/mod/aienglishlesson/practice.php',
            ['id' => $id, 'version' => $version->id]))->out(false);
    }
    $data['kindlabel'] = get_string('kind_' . $data['kind'], 'aienglishlesson');
    $data['canuploadaudio'] = $manage && $version->status === 'draft' && !empty($data['audioscript']);
    $data['section'] = $section; $data['sesskey'] = sesskey();
    $data['actionurl'] = (new moodle_url('/mod/aienglishlesson/action.php'))->out(false);
    $data['cansave'] = $version->status === 'published' && !isguestuser() && is_enrolled($context, $USER, '', true);
    if (!empty($SESSION->aiel_formerror)) {
        $formerror = $SESSION->aiel_formerror;
        unset($SESSION->aiel_formerror);
        if ($formerror['version'] == $version->id && $formerror['section'] == $section && $data['assessed']) {
            $data['response'] = $formerror['response'];
            $data[in_array($formerror['code'], ['recordingrequired', 'recordingerror',
                'speakingimageerror'], true) ? 'recordingerror' : 'answererror'] =
                get_string($formerror['code'], 'aienglishlesson');
        }
    }
    $done = $DB->get_records('aiel_progress', ['versionid' => $version->id, 'userid' => $USER->id]);
    $completed = array_column($done, 'sectionno');
    $data['progress'] = get_string('progresscount', 'aienglishlesson', (object)['done' => count($done), 'total' => count($content['sections'])]);
    $data['progresspercent'] = (int)round(100 * min(count($done), count($content['sections'])) / max(1, count($content['sections'])));
    $data['ainotice'] = get_string($data['speaking'] ? 'speakingainotice' :
        (\mod_aienglishlesson\local\assessment::enabled() ? 'aienabledstudent' : 'noaistudent'),
        'aienglishlesson');
    foreach ($content['sections'] as $i => $part) {
        $data['navigation'][] = ['title' => $part['title'], 'current' => $i === $section, 'done' => in_array($i, $completed),
            'url' => (new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $version->id, 'section' => $i]))->out(false)];
    }
    foreach (['previous' => $section - 1, 'next' => $section + 1] as $key => $i) {
        if ($i >= 0 && $i < count($content['sections'])) {
            $data[$key] = (new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $version->id, 'section' => $i]))->out(false);
        }
    }
    if ($section === count($content['sections']) - 1) {
        if (isset($data['practiceurl'])) { $data['next'] = $data['practiceurl']; }
        else if (isset($data['quizurl'])) { $data['next'] = $data['quizurl']; }
    }
    echo $OUTPUT->render_from_template('mod_aienglishlesson/lesson', $data);
}
echo $OUTPUT->footer();
