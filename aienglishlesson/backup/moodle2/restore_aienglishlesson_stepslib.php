<?php
defined('MOODLE_INTERNAL') || die();
class restore_aienglishlesson_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = [new restore_path_element('aienglishlesson', '/activity/aienglishlesson'),
            new restore_path_element('aiel_version', '/activity/aienglishlesson/versions/version')];
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('aiel_progress', '/activity/aienglishlesson/progresses/progress');
            $paths[] = new restore_path_element('aiel_response', '/activity/aienglishlesson/responses/response');
            $paths[] = new restore_path_element('aiel_grade', '/activity/aienglishlesson/grades/grade');
            $paths[] = new restore_path_element('aiel_quizattempt', '/activity/aienglishlesson/quizattempts/quizattempt');
        }
        return $this->prepare_activity_structure($paths);
    }
    protected function process_aienglishlesson($data) {
        global $DB;
        $data = (object)$data; $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->id = $DB->insert_record('aienglishlesson', $data);
        $this->apply_activity_instance($data->id);
        require_once(__DIR__ . '/../../lib.php');
        aienglishlesson_grade_item_update($data);
    }
    protected function process_aiel_version($data) {
        global $DB;
        $data = (object)$data; $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('aienglishlesson');
        \mod_aienglishlesson\local\lesson::validate($data->content);
        if (!in_array($data->status, ['draft', 'published'])) { $data->status = 'draft'; }
        // Task queues are not restored; allow teachers to rebuild interrupted audio.
        if (empty($data->audiostatus) || in_array($data->audiostatus, ['queued', 'running'])) { $data->audiostatus = 'none'; }
        $id = $DB->insert_record('aiel_versions', $data);
        $this->set_mapping('aiel_version', $oldid, $id, true);
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
        $this->add_related_files('mod_aienglishlesson', 'audio', 'aiel_version');
        if ($this->get_setting_value('userinfo')) { $this->add_related_files('mod_aienglishlesson', 'recording', 'aiel_response'); }
        if ($this->get_setting_value('userinfo')) {
            $this->add_related_files('mod_aienglishlesson', 'image', 'aiel_response');
            global $DB;
            $activity = $DB->get_record('aienglishlesson', ['id' => $this->get_new_parentid('aienglishlesson')]);
            if ($activity) { aienglishlesson_update_grades($activity); }
        }
    }
    protected function process_aiel_response($data) {
        global $DB;
        $data = (object)$data; $oldid = $data->id;
        $data->lessonid = $this->get_new_parentid('aienglishlesson');
        $data->versionid = $this->get_mappingid('aiel_version', $data->versionid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->approvedby = !empty($data->approvedby) ? $this->get_mappingid('user', $data->approvedby) : null;
        if ($data->userid && $data->versionid) {
            $id = $DB->insert_record('aiel_responses', $data);
            $this->set_mapping('aiel_response', $oldid, $id, true);
        }
    }
    protected function process_aiel_grade($data) {
        global $DB;
        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('aienglishlesson');
        $data->versionid = $this->get_mappingid('aiel_version', $data->versionid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->userid && $data->versionid) { $DB->insert_record('aiel_grades', $data); }
    }
    protected function process_aiel_quizattempt($data) {
        global $DB;
        $data = (object)$data;
        $data->lessonid = $this->get_new_parentid('aienglishlesson');
        $data->versionid = $this->get_mappingid('aiel_version', $data->versionid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if ($data->userid && $data->versionid) { $DB->insert_record('aiel_quizattempts', $data); }
    }
}
