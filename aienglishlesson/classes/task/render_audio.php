<?php
namespace mod_aienglishlesson\task;
defined('MOODLE_INTERNAL') || die();
class render_audio extends \core\task\adhoc_task {
    public function execute() { \mod_aienglishlesson\local\audio::render((int)$this->get_custom_data()->versionid); }
}
