<?php
defined('MOODLE_INTERNAL') || die();
class backup_aienglishlesson_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $root = new backup_nested_element('aienglishlesson', ['id'], ['name', 'intro', 'introformat',
            'gradeband', 'proficiency', 'topic', 'pagerange', 'duration', 'instructions', 'timecreated', 'timemodified']);
        $versions = new backup_nested_element('versions');
        $version = new backup_nested_element('version', ['id'], ['status', 'content', 'timecreated', 'timemodified']);
        $root->add_child($versions); $versions->add_child($version);
        $progresses = new backup_nested_element('progresses');
        $progress = new backup_nested_element('progress', ['id'], ['versionid', 'userid', 'sectionno', 'timemodified']);
        $root->add_child($progresses); $progresses->add_child($progress);
        $root->set_source_table('aienglishlesson', ['id' => backup::VAR_ACTIVITYID]);
        $version->set_source_table('aiel_versions', ['lessonid' => backup::VAR_PARENTID]);
        if ($this->get_setting_value('userinfo')) {
            $progress->set_source_table('aiel_progress', ['lessonid' => backup::VAR_PARENTID]);
            $progress->annotate_ids('user', 'userid');
        }
        $root->annotate_files('mod_aienglishlesson', 'intro', null);
        $root->annotate_files('mod_aienglishlesson', 'source', null);
        return $this->prepare_activity_structure($root);
    }
}
