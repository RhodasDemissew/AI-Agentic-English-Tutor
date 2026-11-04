<?php
defined('MOODLE_INTERNAL') || die();
class restore_aienglishlesson_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = [new restore_path_element('aienglishlesson', '/activity/aienglishlesson'),
            new restore_path_element('aiel_version', '/activity/aienglishlesson/versions/version')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('aiel_progress', '/activity/aienglishlesson/progresses/progress');
        }
        return $this->prepare_activity_structure($paths);
    }
    protected function process_aienglishlesson($data) {
        global $DB;
        $data = (object)$data; $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $this->apply_activity_instance($DB->insert_record('aienglishlesson', $data));
    }
    protected function process_aiel_version($data) {
        global $DB;
        $data = (object)$data; $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('aienglishlesson');
        \mod_aienglishlesson\local\lesson::validate($data->content);
        if (!in_array($data->status, ['draft', 'published'])) { $data->status = 'draft'; }
        $id = $DB->insert_record('aiel_versions', $data);
        $this->set_mapping('aiel_version', $oldid, $id);
    }
    protected function process_aiel_progress($data) {
        global $DB;
        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('aienglishlesson');
        $data->versionid = $this->get_mappingid('aiel_version', $data->versionid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->userid && $data->versionid) { $DB->insert_record('aiel_progress', $data); }
    }
    protected function after_execute() {
        $this->add_related_files('mod_aienglishlesson', 'intro', null);
        $this->add_related_files('mod_aienglishlesson', 'source', null);
    }
}
