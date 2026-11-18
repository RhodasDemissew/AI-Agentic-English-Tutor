<?php
namespace mod_aienglishlesson\task;
defined('MOODLE_INTERNAL') || die();
class generate extends \core\task\adhoc_task {
    public function execute() {
        global $DB;
        $jobid = (int)$this->get_custom_data()->jobid;
        for ($stage = 0; $stage < 3; $stage++) {
            \mod_aienglishlesson\local\jobs::run($jobid);
            $status = $DB->get_field('aiel_jobs', 'status', ['id' => $jobid]);
            if ($status !== 'queued') { break; }
        }
    }
}
