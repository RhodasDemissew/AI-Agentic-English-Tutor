<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
class jobs {
    public static function direct(): bool {
        return get_config('aienglishlesson', 'executionmode') !== 'background';
    }
    public static function stale_after(): int {
        return min(360, max(180, (int)(get_config('aienglishlesson', 'timeout') ?: 120) + 60));
    }
    public static function queue(int $lessonid): int {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $lessonid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $existing = $DB->get_record_select('aiel_jobs', 'lessonid = ? AND status IN (?, ?)', [$lessonid, 'queued', 'running']);
            if ($existing) {
                if (!self::direct() && ($existing->status === 'queued' || $existing->timemodified < time() - self::stale_after())) {
                    self::schedule((int)$existing->id);
                }
                return (int)$existing->id;
            }
            $transaction = $DB->start_delegated_transaction();
            $id = $DB->insert_record('aiel_jobs', (object)['lessonid' => $lessonid, 'status' => 'queued',
                'attempts' => 0, 'stage' => 0, 'partial' => null, 'diagnostic' => '',
                'timecreated' => time(), 'timemodified' => time()]);
            if (!self::direct()) { self::schedule($id); }
            $transaction->allow_commit();
            return $id;
        } finally { $lock->release(); }
    }
    private static function schedule(int $id): void {
        $task = new \mod_aienglishlesson\task\generate();
        $task->set_custom_data(['jobid' => $id]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
    public static function run(int $id, ?provider $provider = null): void {
        global $DB;
        $initial = $DB->get_record('aiel_jobs', ['id' => $id]);
        if (!$initial) { return; }
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $initial->lessonid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $job = $DB->get_record('aiel_jobs', ['id' => $id]);
            if (!$job || in_array($job->status, ['succeeded', 'failed'], true)) { return; }
            // A crashed web request can leave a running row. Each stage has its own timeout.
            if ($job->status === 'running' && $job->timemodified > time() - self::stale_after()) { return; }
            if ($job->attempts >= 3) {
                $job->status = 'failed'; $job->diagnostic = 'retrylimit';
                $job->timemodified = time();
                $DB->update_record('aiel_jobs', $job);
                return;
            }
            $job->status = 'running'; $job->attempts++; $job->timemodified = time();
            $DB->update_record('aiel_jobs', $job);
        } finally { $lock->release(); }

        // Never hold the lesson lock while waiting for the provider.
        try {
            $activity = $DB->get_record('aienglishlesson', ['id' => $job->lessonid], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('aienglishlesson', $activity->id, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $files = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', 'source', 0, 'id', false);
            if (!$files || count($files) > 5) { throw new \moodle_exception('invalidsources', 'aienglishlesson'); }
            $partial = $job->partial ? json_decode($job->partial, true) : ['sections' => []];
            if (!is_array($partial) || !is_array($partial['sections'] ?? null) || !in_array((int)$job->stage, [0, 1, 2], true)) {
                throw new \moodle_exception('invalidlesson', 'aienglishlesson');
            }
            $fingerprint = hash('sha256', json_encode([(int)$activity->timemodified,
                array_map(static function($file) { return $file->get_contenthash(); }, array_values($files))]));
            if ((int)$job->stage === 0) { $partial['fingerprint'] = $fingerprint; }
            else if (($partial['fingerprint'] ?? '') !== $fingerprint) {
                throw new \moodle_exception('sourcechanged', 'aienglishlesson');
            }
            $part = ($provider ?? new gemini())->generate_stage($activity, array_values($files), (int)$job->stage, $partial);
            if ($job->stage < 2) { $partial['sections'] = array_merge($partial['sections'], $part['sections']); }
            else { $partial['quiz'] = $part['quiz']; }
            $content = (int)$job->stage === 2 ? gemini::assemble($activity, $partial) : null;
            $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $job->lessonid, 10);
            if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
            try {
                $current = $DB->get_record('aiel_jobs', ['id' => $id]);
                if (!$current || $current->status !== 'running' ||
                        (int)$current->attempts !== (int)$job->attempts) { return; }
                $transaction = $DB->start_delegated_transaction();
                if ($content === null) {
                    $current->partial = json_encode($partial, JSON_UNESCAPED_UNICODE);
                    $current->stage++; $current->attempts = 0;
                    $current->status = 'queued'; $current->diagnostic = '';
                } else {
                    $versionid = $DB->insert_record('aiel_versions', (object)['lessonid' => $activity->id, 'status' => 'draft',
                        'content' => json_encode($content, JSON_UNESCAPED_UNICODE), 'timecreated' => time(), 'timemodified' => time()]);
                    $current->status = 'succeeded'; $current->diagnostic = '';
                    $current->partial = null;
                }
                $current->timemodified = time();
                $DB->update_record('aiel_jobs', $current);
                $transaction->allow_commit();
                if ($content !== null) { audio::queue($versionid); }
            } finally { $lock->release(); }
        } catch (\Throwable $e) {
            $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $job->lessonid, 10);
            if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
            try {
                $current = $DB->get_record('aiel_jobs', ['id' => $id]);
                if (!$current || $current->status !== 'running' ||
                        (int)$current->attempts !== (int)$job->attempts) { return; }
                $retry = $e instanceof provider_error && $e->transient && $current->attempts < 3;
                $current->status = $retry ? 'queued' : 'failed';
                $code = $e instanceof provider_error ? $e->getMessage() :
                    ($e instanceof \moodle_exception ? $e->errorcode : 'internal');
                $current->diagnostic = preg_match('/^[a-z0-9]{1,40}$/D', $code) ? $code : 'internal';
                $current->timemodified = time();
                $DB->update_record('aiel_jobs', $current);
            } finally { $lock->release(); }
            if ($retry && !self::direct()) { throw new \moodle_exception('retrying', 'aienglishlesson'); }
        }
    }
}
