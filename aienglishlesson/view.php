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
echo $OUTPUT->heading(format_string($activity->name));
if ($manage) {
    echo html_writer::link(new moodle_url('/mod/aienglishlesson/responses.php', ['id' => $id]), get_string('reviewresponses', 'aienglishlesson'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->notification(get_string('reviewwarning', 'aienglishlesson'), 'info');
    echo $OUTPUT->notification(get_string(\mod_aienglishlesson\local\jobs::direct() ? 'directnotice' : 'backgroundnotice', 'aienglishlesson'), 'info');
    if (!get_config('aienglishlesson', 'pdfstrict')) {
        echo html_writer::div(get_string('portablepdfnotice', 'aienglishlesson'), 'small mb-2');
    }
    echo $OUTPUT->single_button(new moodle_url('/mod/aienglishlesson/action.php', ['id' => $id, 'action' => 'generate']), get_string('generate', 'aienglishlesson'), 'post');
    $jobs = $DB->get_records('aiel_jobs', ['lessonid' => $activity->id], 'id DESC', '*', 0, 1);
    if ($jobs) {
        $job = reset($jobs);
        $diagnostic = $job->diagnostic;
        $key = 'diagnostic_' . $diagnostic;
        if ($diagnostic && get_string_manager()->string_exists($key, 'aienglishlesson')) {
            $diagnostic = get_string($key, 'aienglishlesson');
        } else if ($diagnostic && get_string_manager()->string_exists($diagnostic, 'aienglishlesson')) {
            $diagnostic = get_string($diagnostic, 'aienglishlesson');
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
    $content = \mod_aienglishlesson\local\lesson::validate($version->content);
    $fullcontent = $content;
    if (!$manage) { $content = \mod_aienglishlesson\local\lesson::student_data($content); }
    $section = max(0, min(count($content['sections']) - 1, optional_param('section', 0, PARAM_INT)));
    $data = $content['sections'][$section];
    $data['lessontitle'] = $content['title']; $data['focus'] = $content['focus'];
    $data['speaking'] = $data['kind'] === 'speaking';
    $audiofile = \mod_aienglishlesson\local\audio::file($context, $version->id, $section);
    if ($audiofile) { $data['audiourl'] = moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'audio', $version->id, '/', $audiofile->get_filename())->out(false); }
    $response = $DB->get_record('aiel_responses', ['versionid' => $version->id, 'userid' => $USER->id, 'sectionno' => $section]);
    if ($response) {
        $data['response'] = $response->response;
        $data['submitted'] = true;
        $data['feedback'] = $fullcontent['sections'][$section]['feedback'] ?? '';
        $recordings = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'recording', $response->id, 'id', false);
        if ($recordings) {
            $recording = reset($recordings);
            $data['recordingurl'] = moodle_url::make_pluginfile_url($context->id, 'mod_aienglishlesson', 'recording', $response->id, '/', $recording->get_filename())->out(false);
        }
    } else { unset($data['feedback']); }
    $data['teacher'] = $manage; $data['id'] = $id; $data['version'] = $version->id;
    $data['canuploadaudio'] = $manage && $version->status === 'draft' && !empty($data['audioscript']);
    $data['section'] = $section; $data['sesskey'] = sesskey();
    $data['actionurl'] = (new moodle_url('/mod/aienglishlesson/action.php'))->out(false);
    $data['cansave'] = $version->status === 'published' && !isguestuser() && is_enrolled($context, $USER, '', true);
    $done = $DB->get_records('aiel_progress', ['versionid' => $version->id, 'userid' => $USER->id]);
    $completed = array_column($done, 'sectionno');
    $data['progress'] = get_string('progresscount', 'aienglishlesson', (object)['done' => count($done), 'total' => count($content['sections'])]);
    foreach ($content['sections'] as $i => $part) {
        $data['navigation'][] = ['title' => $part['title'], 'current' => $i === $section, 'done' => in_array($i, $completed),
            'url' => (new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $version->id, 'section' => $i]))->out(false)];
    }
    foreach (['previous' => $section - 1, 'next' => $section + 1] as $key => $i) {
        if ($i >= 0 && $i < count($content['sections'])) {
            $data[$key] = (new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $version->id, 'section' => $i]))->out(false);
        }
    }
    echo $OUTPUT->render_from_template('mod_aienglishlesson/lesson', $data);
}
echo $OUTPUT->footer();
