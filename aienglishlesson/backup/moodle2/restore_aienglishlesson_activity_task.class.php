<?php
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/aienglishlesson/backup/moodle2/restore_aienglishlesson_stepslib.php');
class restore_aienglishlesson_activity_task extends restore_activity_task {
    protected function define_my_settings() {}
    protected function define_my_steps() {
        $this->add_step(new restore_aienglishlesson_activity_structure_step('aienglishlesson_structure', 'aienglishlesson.xml'));
    }
    public static function define_decode_contents() {
        return [new restore_decode_content('aienglishlesson', ['intro'], 'aienglishlesson')];
    }
    public static function define_decode_rules() { return []; }
    public static function define_restore_log_rules() { return []; }
    public static function define_restore_log_rules_for_course() { return []; }
}
