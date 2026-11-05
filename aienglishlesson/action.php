<?php
require_once('../../config.php');
require_once($CFG->libdir . '/completionlib.php');
$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aienglishlesson:view', $context);
if (!data_submitted()) { throw new moodle_exception('invalidrequest'); }
require_sesskey();
$back = new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id]);
if ($action === 'generate') {
    require_capability('mod/aienglishlesson:manage', $context);
    \mod_aienglishlesson\local\jobs::queue((int)$cm->instance);
} else if ($action === 'audio') {
    require_capability('mod/aienglishlesson:manage', $context);
    $versionid = required_param('version', PARAM_INT);
    $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $cm->instance, 10);
    if (!$lock) { throw new moodle_exception('busy', 'aienglishlesson'); }
    try {
        $v = $DB->get_record('aiel_versions', ['id' => $versionid, 'lessonid' => $cm->instance, 'status' => 'draft'], '*', MUST_EXIST);
        \mod_aienglishlesson\local\audio::queue($versionid);
    } finally { $lock->release(); }
    $back->param('version', $versionid);
} else if ($action === 'publish') {
    require_capability('mod/aienglishlesson:manage', $context);
    $versionid = required_param('version', PARAM_INT);
    $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $cm->instance, 10);
    if (!$lock) { throw new moodle_exception('busy', 'aienglishlesson'); }
    try {
        $v = $DB->get_record('aiel_versions', ['id' => $versionid, 'lessonid' => $cm->instance, 'status' => 'draft'], '*', MUST_EXIST);
        if ($v->timemodified != required_param('modified', PARAM_INT)) { throw new moodle_exception('editconflict', 'aienglishlesson'); }
        \mod_aienglishlesson\local\lesson::validate($v->content);
        if (!\mod_aienglishlesson\local\audio::ready($v, $context)) { throw new moodle_exception('notready', 'aienglishlesson'); }
        $tx = $DB->start_delegated_transaction();
        $DB->set_field('aiel_versions', 'status', 'draft', ['lessonid' => $cm->instance, 'status' => 'published']);
        $v->status = 'published'; $v->timemodified = time(); $DB->update_record('aiel_versions', $v);
        $tx->allow_commit();
    } finally { $lock->release(); }
} else if ($action === 'submit' || $action === 'clearresponse') {
    if (isguestuser() || !is_enrolled($context, $USER, '', true)) { throw new required_capability_exception($context, 'mod/aienglishlesson:view', 'nopermissions', ''); }
    $versionid = required_param('version', PARAM_INT);
    $section = required_param('section', PARAM_INT);
    $v = $DB->get_record('aiel_versions', ['id' => $versionid, 'lessonid' => $cm->instance, 'status' => 'published'], '*', MUST_EXIST);
    if ($action === 'submit') {
        \mod_aienglishlesson\local\responses::save($v, $section, $USER->id, required_param('response', PARAM_RAW_TRIMMED), $_FILES['recording'] ?? null);
    } else {
        \mod_aienglishlesson\local\responses::delete($context, ['versionid' => $versionid, 'userid' => $USER->id, 'sectionno' => $section]);
    }
    $back->params(['section' => $section, 'version' => $versionid]);
} else if ($action === 'progress') {
    if (isguestuser() || !is_enrolled($context, $USER, '', true)) { throw new required_capability_exception($context, 'mod/aienglishlesson:view', 'nopermissions', ''); }
    $versionid = required_param('version', PARAM_INT);
    $section = required_param('section', PARAM_INT);
    $v = $DB->get_record('aiel_versions', ['id' => $versionid, 'lessonid' => $cm->instance, 'status' => 'published'], '*', MUST_EXIST);
    $content = \mod_aienglishlesson\local\lesson::validate($v->content);
    if ($section < 0 || $section >= count($content['sections'])) { throw new moodle_exception('invalidrequest'); }
    $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('progress' . $versionid . '-' . $USER->id, 10);
    if (!$lock) { throw new moodle_exception('busy', 'aienglishlesson'); }
    try {
        $record = ['versionid' => $versionid, 'userid' => $USER->id, 'sectionno' => $section];
        if (!$DB->record_exists('aiel_progress', $record)) {
            $DB->insert_record('aiel_progress', (object)($record + ['lessonid' => $cm->instance, 'timemodified' => time()]));
        }
    } finally { $lock->release(); }
    $back->param('section', min($section + 1, count($content['sections']) - 1));
} else { throw new moodle_exception('invalidrequest'); }
redirect($back);
