<?php
defined('MOODLE_INTERNAL') || die();
function aienglishlesson_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO: case FEATURE_SHOW_DESCRIPTION: case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_MOD_PURPOSE: return MOD_PURPOSE_CONTENT;
        case FEATURE_GRADE_HAS_GRADE: return true;
        case FEATURE_GROUPS: return false;
        default: return null;
    }
}
function aienglishlesson_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = $data->timemodified = time();
    $data->id = $DB->insert_record('aienglishlesson', $data);
    aienglishlesson_grade_item_update($data);
    aienglishlesson_save_pdf($data);
    return $data->id;
}
function aienglishlesson_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('aienglishlesson', $data);
    aienglishlesson_grade_item_update($data);
    aienglishlesson_save_pdf($data);
    return true;
}
function aienglishlesson_save_pdf($data) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');
    if (isset($data->sourcepdf)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files($data->sourcepdf, $context->id, 'mod_aienglishlesson', 'source', 0,
            ['subdirs' => 0, 'maxfiles' => 5, 'accepted_types' => \mod_aienglishlesson\local\source_files::extensions()]);
    }
}
function aienglishlesson_delete_instance($id) {
    global $DB;
    $activity = $DB->get_record('aienglishlesson', ['id' => $id]);
    if (!$activity) { return false; }
    $cm = get_coursemodule_from_instance('aienglishlesson', $id);
    if ($cm) { get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_aienglishlesson'); }
    foreach (['aiel_grades', 'aiel_quizattempts', 'aiel_progress', 'aiel_responses', 'aiel_jobs', 'aiel_versions'] as $table) { $DB->delete_records($table, ['lessonid' => $id]); }
    require_once(__DIR__ . '/../../lib/gradelib.php');
    grade_update('mod/aienglishlesson', $activity->course, 'mod', 'aienglishlesson', $id, 0, null, ['deleted' => 1]);
    $DB->delete_records('aienglishlesson', ['id' => $id]);
    return true;
}
function aienglishlesson_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;
    if ($context->contextlevel != CONTEXT_MODULE || !in_array($filearea, ['source', 'audio', 'recording', 'image'])) { return false; }
    require_login($course, true, $cm);
    require_capability('mod/aienglishlesson:view', $context);
    $manage = has_capability('mod/aienglishlesson:manage', $context);
    $itemid = (int)array_shift($args);
    if (count($args) !== 1) { return false; }
    if ($filearea === 'source') {
        require_capability('mod/aienglishlesson:manage', $context);
        if ($itemid !== 0) { return false; }
    } else {
        $versionid = $itemid;
        if ($filearea === 'recording' || $filearea === 'image') {
            $response = $DB->get_record('aiel_responses', ['id' => $itemid, 'lessonid' => $cm->instance]);
            if (!$response || (!$manage && $response->userid != $USER->id)) { return false; }
            $versionid = $response->versionid;
        }
        $v = $DB->get_record('aiel_versions', ['id' => $versionid, 'lessonid' => $cm->instance]);
        if (!$v || (!$manage && ($v->status !== 'published' || !is_enrolled($context, $USER, '', true)))) { return false; }
    }
    $file = get_file_storage()->get_file($context->id, 'mod_aienglishlesson', $filearea, $itemid, '/', $args[0]);
    if (!$file || $file->is_directory()) { return false; }
    send_stored_file($file, 0, 0, $filearea === 'source', $options);
}
function aienglishlesson_grade_item_update($activity, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $item = ['itemname' => $activity->name, 'gradetype' => GRADE_TYPE_VALUE,
        'grademin' => 0, 'grademax' => 100];
    return grade_update('mod/aienglishlesson', $activity->course, 'mod', 'aienglishlesson', $activity->id, 0, $grades, $item);
}
function aienglishlesson_update_grades($activity, $userid = 0) {
    global $DB;
    $conditions = ['lessonid' => $activity->id];
    if ($userid) { $conditions['userid'] = $userid; }
    $grades = [];
    foreach ($DB->get_records('aiel_grades', $conditions) as $record) {
        $grades[$record->userid] = (object)['userid' => $record->userid, 'rawgrade' => $record->grade];
    }
    return aienglishlesson_grade_item_update($activity, $grades ?: null);
}
