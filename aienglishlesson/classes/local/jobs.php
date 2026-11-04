<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
class jobs {
    public static function queue(int $lessonid): int {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $lessonid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $existing = $DB->get_record_select('aiel_jobs', 'lessonid = ? AND status IN (?, ?)', [$lessonid, 'queued', 'running']);
            if ($existing) { return (int)$existing->id; }
            $transaction = $DB->start_delegated_transaction();
            $id = $DB->insert_record('aiel_jobs', (object)['lessonid' => $lessonid, 'status' => 'queued',
                'attempts' => 0, 'diagnostic' => '', 'timecreated' => time(), 'timemodified' => time()]);
            $task = new \mod_aienglishlesson\task\generate();
            $task->set_custom_data(['jobid' => $id]);
            \core\task\manager::queue_adhoc_task($task);
            $transaction->allow_commit();
            return $id;
        } finally { $lock->release(); }
    }
    public static function run(int $id, ?provider $provider = null): void {
        global $DB;
        $job = $DB->get_record('aiel_jobs', ['id' => $id]);
        if (!$job || in_array($job->status, ['succeeded', 'failed'])) { return; }
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $job->lessonid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $job = $DB->get_record('aiel_jobs', ['id' => $id]);
            if (!$job || in_array($job->status, ['succeeded', 'failed'])) { return; }
            if ($job->attempts >= 3) {
                $job->status = 'failed'; $job->diagnostic = 'retrylimit';
                $DB->update_record('aiel_jobs', $job); return;
            }
            $job->status = 'running'; $job->attempts++; $job->timemodified = time();
            $DB->update_record('aiel_jobs', $job);
            try {
                $activity = $DB->get_record('aienglishlesson', ['id' => $job->lessonid], '*', MUST_EXIST);
                $cm = get_coursemodule_from_instance('aienglishlesson', $activity->id, 0, false, MUST_EXIST);
                $context = \context_module::instance($cm->id);
                $files = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
                if (count($files) !== 1) { throw new \moodle_exception('missingpdf', 'aienglishlesson'); }
                $content = ($provider ?? new gemini())->generate($activity, reset($files));
                $content = lesson::validate(json_encode($content));
                $transaction = $DB->start_delegated_transaction();
                $DB->insert_record('aiel_versions', (object)['lessonid' => $activity->id, 'status' => 'draft',
                    'content' => json_encode($content), 'timecreated' => time(), 'timemodified' => time()]);
                $job->status = 'succeeded'; $job->diagnostic = '';
                $DB->update_record('aiel_jobs', $job);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $retry = $e instanceof provider_error && $e->transient && $job->attempts < 3;
                $job->status = $retry ? 'queued' : 'failed';
                $job->diagnostic = $e instanceof provider_error ? $e->getMessage() :
                    ($e instanceof \moodle_exception ? $e->errorcode : 'internal');
                // Only locally selected diagnostic codes are persisted. No raw provider response.
                $job->timemodified = time();
                $DB->update_record('aiel_jobs', $job);
                if ($retry) { throw new \moodle_exception('retrying', 'aienglishlesson'); }
            }
        } finally { $lock->release(); }
    }
}
