<?php
namespace mod_aienglishlesson\task;
defined('MOODLE_INTERNAL') || die();
class generate extends \core\task\adhoc_task {
    public function execute() {
        \mod_aienglishlesson\local\jobs::run((int)$this->get_custom_data()->jobid);
    }
}
