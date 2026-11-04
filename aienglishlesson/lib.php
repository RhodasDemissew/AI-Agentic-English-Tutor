<?php
defined('MOODLE_INTERNAL') || die();
function aienglishlesson_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO: case FEATURE_SHOW_DESCRIPTION: case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_MOD_PURPOSE: return MOD_PURPOSE_CONTENT;
        case FEATURE_GRADE_HAS_GRADE: case FEATURE_GROUPS: return false;
        default: return null;
    }
}
function aienglishlesson_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = $data->timemodified = time();
    $data->id = $DB->insert_record('aienglishlesson', $data);
    aienglishlesson_save_pdf($data);
    return $data->id;
}
function aienglishlesson_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('aienglishlesson', $data);
    aienglishlesson_save_pdf($data);
    return true;
}
function aienglishlesson_save_pdf($data) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');
    if (isset($data->sourcepdf)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files($data->sourcepdf, $context->id, 'mod_aienglishlesson', 'source', 0,
            ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']]);
    }
}
function aienglishlesson_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('aienglishlesson', ['id' => $id])) { return false; }
    $cm = get_coursemodule_from_instance('aienglishlesson', $id);
    if ($cm) { get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_aienglishlesson'); }
    foreach (['aiel_progress', 'aiel_jobs', 'aiel_versions'] as $table) { $DB->delete_records($table, ['lessonid' => $id]); }
    $DB->delete_records('aienglishlesson', ['id' => $id]);
    return true;
}
function aienglishlesson_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== 'source') { return false; }
    require_login($course, true, $cm);
    require_capability('mod/aienglishlesson:manage', $context);
    $itemid = array_shift($args);
    if ((int)$itemid !== 0 || count($args) !== 1) { return false; }
    $file = get_file_storage()->get_file($context->id, 'mod_aienglishlesson', 'source', 0, '/', $args[0]);
    if (!$file || $file->is_directory()) { return false; }
    send_stored_file($file, 0, 0, true, $options);
}
