<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/aienglishlesson/backup/moodle2/backup_aienglishlesson_stepslib.php');
class backup_aienglishlesson_activity_task extends backup_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new backup_aienglishlesson_activity_structure_step('aienglishlesson_structure', 'aienglishlesson.xml'));
    }
    public static function encode_content_links($content) { return $content; }
}
