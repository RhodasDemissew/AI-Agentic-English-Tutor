<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
/** Offline synthesis. No student-triggered synthesis and no external speech service. */
class audio {
    public static function queue(int $versionid): void {
        global $DB;
        $DB->set_field('aiel_versions', 'audiostatus', 'queued', ['id' => $versionid]);
        $task = new \mod_aienglishlesson\task\render_audio();
        $task->set_custom_data(['versionid' => $versionid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
    public static function render(int $versionid): void {
        global $DB;
        $version = $DB->get_record('aiel_versions', ['id' => $versionid]);
        if (!$version || $version->status !== 'draft') { return; }
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $version->lessonid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $version = $DB->get_record('aiel_versions', ['id' => $versionid]);
            if (!$version || $version->status !== 'draft') { return; }
            $DB->set_field('aiel_versions', 'audiostatus', 'running', ['id' => $versionid]);
            $cm = get_coursemodule_from_instance('aienglishlesson', $version->lessonid, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $content = lesson::validate($version->content);
            $binary = get_config('aienglishlesson', 'speechbinary') ?: '/usr/bin/espeak-ng';
            if (!is_executable($binary) || !function_exists('proc_open')) { throw new \RuntimeException('speech unavailable'); }
            $directory = make_request_directory();
            $created = [];
            foreach ($content['sections'] as $i => $section) {
                if (empty($section['audioscript'])) { continue; }
                $script = $directory . '/script' . $i . '.txt';
                $wav = $directory . '/section' . $i . '.wav';
                file_put_contents($script, $section['audioscript']);
                $process = proc_open([$binary, '-b', '1', '-v', 'en-us', '-s', '140', '-f', $script, '-w', $wav],
                    [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
                if (!is_resource($process)) { throw new \RuntimeException('speech unavailable'); }
                $start = time();
                do {
                    $status = proc_get_status($process);
                    if (!$status['running']) { break; }
                    if (time() - $start >= 30) { proc_terminate($process, 9); break; }
                    usleep(100000);
                } while (true);
                proc_close($process);
                if ($status['running'] || $status['exitcode'] !== 0 || !is_file($wav) || filesize($wav) > 20 * 1024 * 1024) {
                    throw new \RuntimeException('speech failed');
                }
                $created[$i] = $wav;
            }
            if (!$created) { throw new \RuntimeException('no script'); }
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_aienglishlesson', 'audio', $versionid);
            foreach ($created as $i => $wav) {
                $fs->create_file_from_pathname((object)['contextid' => $context->id, 'component' => 'mod_aienglishlesson',
                    'filearea' => 'audio', 'itemid' => $versionid, 'filepath' => '/', 'filename' => 'section' . $i . '.wav',
                    'mimetype' => 'audio/wav'], $wav);
            }
            $DB->set_field('aiel_versions', 'audiostatus', 'ready', ['id' => $versionid]);
        } catch (\Throwable $e) {
            $DB->set_field('aiel_versions', 'audiostatus', 'failed', ['id' => $versionid]);
        } finally { $lock->release(); }
    }
    public static function ready(\stdClass $version, \context_module $context): bool {
        $data = lesson::validate($version->content);
        foreach ($data['sections'] as $i => $section) {
            if (in_array($section['kind'], ['reading', 'listening', 'speaking', 'writing']) && trim($section['practice']) === '') { return false; }
            if ($section['kind'] === 'listening' && (empty($section['audioscript']) ||
                !get_file_storage()->file_exists($context->id, 'mod_aienglishlesson', 'audio', $version->id, '/', 'section' . $i . '.wav'))) { return false; }
        }
        return true;
    }
}
