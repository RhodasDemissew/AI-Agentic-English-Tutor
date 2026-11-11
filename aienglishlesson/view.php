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
$versionid = optional_param('version', 0, PARAM_INT);
$conditions = ['lessonid' => $activity->id];
if (!$manage) { $conditions['status'] = 'published'; }
if ($versionid) { $conditions['id'] = $versionid; }
else { $conditions['status'] = 'published'; }
$versions = $DB->get_records('aiel_versions', $conditions, 'id DESC', '*', 0, 1);
$version = $versions ? reset($versions) : false;
if ($version && $version->status === 'published') { (new completion_info($course))->set_module_viewed($cm); }
echo $OUTPUT->header();
echo html_writer::div(html_writer::empty_tag('img', ['src' => $OUTPUT->image_url('logo', 'mod_aienglishlesson')->out(false),
    'alt' => get_string('logoalt', 'aienglishlesson'), 'class' => 'aiel-logo']), 'aiel-brand');
echo $OUTPUT->heading(format_string($activity->name));
if ($manage) {
    if ($version) {
        echo html_writer::link(new moodle_url('/mod/aienglishlesson/rubrics.php',
            ['id' => $id, 'version' => $version->id]), get_string('rubrics', 'aienglishlesson'),
            ['class' => 'btn btn-secondary mr-2']);
    }
    if (has_capability('moodle/site:config', context_system::instance())) {
        echo html_writer::link(new moodle_url('/mod/aienglishlesson/diagnostics.php', ['id' => $id]),
            get_string('generationdiagnostics', 'aienglishlesson'), ['class' => 'btn btn-secondary mr-2']);
    }
    echo html_writer::link(new moodle_url('/mod/aienglishlesson/responses.php', ['id' => $id]), get_string('reviewresponses', 'aienglishlesson'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->notification(get_string('reviewwarning', 'aienglishlesson'), 'info');
    echo $OUTPUT->notification(get_string(\mod_aienglishlesson\local\jobs::direct() ? 'directnotice' : 'backgroundnotice', 'aienglishlesson'), 'info');
    if (!get_config('aienglishlesson', 'pdfstrict') ||
            ($activity->pagerange && (!is_executable('/usr/bin/pdfseparate') || !is_executable('/usr/bin/pdfunite')))) {
        echo html_writer::div(get_string('portablepdfnotice', 'aienglishlesson'), 'small mb-2');
    }
    $jobs = $DB->get_records('aiel_jobs', ['lessonid' => $activity->id], 'id DESC', '*', 0, 1);
    $latestjob = $jobs ? reset($jobs) : false;
    $resume = $latestjob && ($latestjob->status === 'queued' ||
        ($latestjob->status === 'running' && $latestjob->timemodified < time() - \mod_aienglishlesson\local\jobs::stale_after()));
    echo $OUTPUT->single_button(new moodle_url('/mod/aienglishlesson/action.php',
        ['id' => $id, 'action' => $resume ? 'resume' : 'generate']),
        get_string($resume ? 'resume' : 'generate', 'aienglishlesson'), 'post');
    if ($jobs) {
        $job = reset($jobs);
        $diagnostic = $job->diagnostic;
        $key = 'diagnostic_' . $diagnostic;
        if ($diagnostic && get_string_manager()->string_exists($key, 'aienglishlesson')) {
            $diagnostic = get_string($key, 'aienglishlesson');
        } else if ($diagnostic && get_string_manager()->string_exists($diagnostic, 'aienglishlesson')) {
            $diagnostic = get_string($diagnostic, 'aienglishlesson');
        } else if ($diagnostic) {
            $diagnostic = get_string('diagnostic_internal', 'aienglishlesson');
        }
        echo html_writer::div(s(get_string('jobstatus', 'aienglishlesson', (object)['status' => $job->status, 'attempts' => $job->attempts, 'diagnostic' => $diagnostic])), 'alert alert-info');
    }
    $files = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
    foreach ($files as $file) {
        echo html_writer::div(html_writer::link(moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'source', 0, '/', $file->get_filename()), get_string('sourcepdf', 'aienglishlesson')));
    }
    foreach ($DB->get_records('aiel_versions', ['lessonid' => $activity->id], 'id DESC') as $v) {
        echo html_writer::div(html_writer::link(new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $v->id]),
            s(get_string('versionlabel', 'aienglishlesson', (object)['id' => $v->id, 'status' => $v->status]))));
    }
    if ($version && $version->status === 'draft') {
        $draftcontent = \mod_aienglishlesson\local\lesson::validate($version->content);
        $speechtarget = \mod_aienglishlesson\local\gemini::speech_word_guidance((string)$activity->gradeband)['target'];
        foreach ($draftcontent['sections'] as $sectiondata) {
            if (in_array($sectiondata['kind'], ['listening', 'speaking'], true) &&
                    str_word_count($sectiondata['audioscript'] ?? '') < $speechtarget) {
                echo $OUTPUT->notification(get_string('shortaudioreview', 'aienglishlesson'), 'warning');
                break;
            }
        }
        if (isset($draftcontent['quiz'])) {
            echo html_writer::link(new moodle_url('/mod/aienglishlesson/quiz.php', ['id' => $id, 'version' => $version->id]),
                get_string('reviewquiz', 'aienglishlesson'), ['class' => 'btn btn-secondary my-2']);
        }
        echo $OUTPUT->notification(get_string('audiostatus', 'aienglishlesson', $version->audiostatus), 'info');
        if (\mod_aienglishlesson\local\audio::provider() !== 'upload') {
            $url = \mod_aienglishlesson\local\jobs::direct() ? '/mod/aienglishlesson/prepareaudio.php' : '/mod/aienglishlesson/action.php';
            echo $OUTPUT->single_button(new moodle_url($url, ['id' => $id, 'version' => $version->id, 'action' => 'audio']), get_string('buildaudio', 'aienglishlesson'), 'post');
        }
        echo html_writer::link(new moodle_url('/mod/aienglishlesson/edit.php', ['id' => $id, 'version' => $version->id]), get_string('editdraft', 'aienglishlesson'), ['class' => 'btn btn-secondary my-2']);
        echo $OUTPUT->single_button(new moodle_url('/mod/aienglishlesson/action.php', ['id' => $id, 'version' => $version->id, 'modified' => $version->timemodified, 'action' => 'publish']), get_string('publish', 'aienglishlesson'), 'post');
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
    $content = \mod_aienglishlesson\local\lesson::validate($version->content);
    $fullcontent = $content;
    if (isset($content['quiz'])) {
        echo html_writer::link(new moodle_url('/mod/aienglishlesson/quiz.php', ['id' => $id, 'version' => $version->id]),
            get_string('quiz', 'aienglishlesson'), ['class' => 'btn btn-primary my-2']);
    }
    if ($version->status === 'published' && !$manage) {
        $attempts = $DB->get_records('aiel_responses', ['versionid' => $version->id, 'userid' => $USER->id], 'id DESC');
        $skills = \mod_aienglishlesson\local\assessment::grade_from_attempts($content, $attempts);
        $quizattempt = isset($content['quiz']) ? \mod_aienglishlesson\local\quiz::latest($version->id, $USER->id) : false;
        $quizscore = $quizattempt ? $quizattempt->score : null;
        $items = [
            get_string('skills', 'aienglishlesson') => $skills === null ? '—' : round($skills, 1) . '/100',
            get_string('quiz', 'aienglishlesson') => !isset($content['quiz']) ?
                get_string('noquizlegacy', 'aienglishlesson') : ($quizscore === null ? '—' : $quizscore . '/100'),
            get_string('finalgrade', 'aienglishlesson') => $grade && $grade->versionid == $version->id ?
                $grade->grade . '/100' : '—',
        ];
        $cards = '';
        foreach ($items as $label => $value) {
            $cards .= html_writer::div(html_writer::tag('h3', s($label)) . html_writer::tag('p', s($value)), 'aiel-grade-card');
        }
        echo html_writer::div($cards, 'aiel-grades');
    }
    if (!$manage) { $content = \mod_aienglishlesson\local\lesson::student_data($content); }
    $section = max(0, min(count($content['sections']) - 1, optional_param('section', 0, PARAM_INT)));
    $data = $content['sections'][$section];
    $data['lessontitle'] = $content['title']; $data['focus'] = $content['focus'];
    $data['speaking'] = $data['kind'] === 'speaking';
    $data['assessed'] = in_array($data['kind'], \mod_aienglishlesson\local\assessment::SKILLS, true);
    $data['ungraded'] = !$data['assessed'];
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
        $data['submitted'] = true;
        if ($response->aistatus === 'succeeded') {
            $data['aiscore'] = get_string('provisionalscore', 'aienglishlesson', $response->aiscore);
            $data['aifeedback'] = $response->aifeedback;
        } else if ($response->aistatus === 'failed') {
            $data['aifeedback'] = get_string('aifailed', 'aienglishlesson');
        } else if ($response->aistatus === 'running') {
            $data['aifeedback'] = get_string('airunning', 'aienglishlesson');
        }
        if ($response->timeapproved) {
            $data['approvedscore'] = get_string('approvedscore', 'aienglishlesson', $response->teacherscore);
            $data['teacherfeedback'] = $response->teacherfeedback;
        }
        $checked = \mod_aienglishlesson\local\responses::check_answer($fullcontent['sections'][$section], $response->response);
        if ($checked !== null) {
            $data['answercheck'] = get_string($checked ? 'answercorrect' : 'answerincorrect', 'aienglishlesson');
        }
        $recordings = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'recording', $response->id, 'id', false);
        if ($recordings) {
            $recording = reset($recordings);
            $data['recordingurl'] = moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'recording', $response->id, '/', $recording->get_filename())->out(false);
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
    $data['canuploadaudio'] = $manage && $version->status === 'draft' && !empty($data['audioscript']);
    $data['section'] = $section; $data['sesskey'] = sesskey();
    $data['actionurl'] = (new moodle_url('/mod/aienglishlesson/action.php'))->out(false);
    $data['cansave'] = $version->status === 'published' && !isguestuser() && is_enrolled($context, $USER, '', true);
    $done = $DB->get_records('aiel_progress', ['versionid' => $version->id, 'userid' => $USER->id]);
    $completed = array_column($done, 'sectionno');
    $data['progress'] = get_string('progresscount', 'aienglishlesson', (object)['done' => count($done), 'total' => count($content['sections'])]);
    $data['masterynotice'] = get_string('assessmentnotice', 'aienglishlesson');
    $data['ainotice'] = get_string(\mod_aienglishlesson\local\assessment::enabled() ?
        'aienabledstudent' : 'noaistudent', 'aienglishlesson');
    foreach ($content['sections'] as $i => $part) {
        $data['navigation'][] = ['title' => $part['title'], 'current' => $i === $section, 'done' => in_array($i, $completed),
            'url' => (new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $version->id, 'section' => $i]))->out(false)];
    }
    foreach (['previous' => $section - 1, 'next' => $section + 1] as $key => $i) {
        if ($i >= 0 && $i < count($content['sections'])) {
            $data[$key] = (new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $version->id, 'section' => $i]))->out(false);
        }
    }
    if ($section === count($content['sections']) - 1 && isset($data['quizurl'])) { $data['next'] = $data['quizurl']; }
    echo $OUTPUT->render_from_template('mod_aienglishlesson/lesson', $data);
}
echo $OUTPUT->footer();
