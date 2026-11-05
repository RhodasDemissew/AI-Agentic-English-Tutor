<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
/** Teacher-only preparation; students receive cached media. */
class audio {
    public static function provider(): string {
        return get_config('aienglishlesson', 'audioprovider') ?: 'upload';
    }
    public static function file(\context $context, int $versionid, int $section) {
        foreach (['wav', 'mp3'] as $extension) {
            $file = get_file_storage()->get_file($context->id, 'mod_aienglishlesson', 'audio', $versionid, '/', 'section' . $section . '.' . $extension);
            if ($file) { return $file; }
        }
        return false;
    }
    public static function queue(int $versionid): void {
        global $DB;
        $background = !jobs::direct() && self::provider() !== 'upload';
        $DB->set_field('aiel_versions', 'audiostatus', $background ? 'queued' : 'none', ['id' => $versionid]);
        if (!$background) { return; }
        $task = new \mod_aienglishlesson\task\render_audio();
        $task->set_custom_data(['versionid' => $versionid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
    public static function render(int $versionid, bool $one = false, ?callable $synthesizer = null): void {
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
            $mode = self::provider();
            if ($mode === 'upload') {
                $DB->set_field('aiel_versions', 'audiostatus', self::ready($version, $context) ? 'ready' : 'upload', ['id' => $versionid]);
                return;
            }
            $binary = get_config('aienglishlesson', 'speechbinary') ?: '/usr/bin/espeak-ng';
            if ($mode === 'local' && (!is_executable($binary) || !function_exists('proc_open'))) { throw new provider_error('speechunavailable'); }
            $directory = make_request_directory();
            $created = [];
            foreach ($content['sections'] as $i => $section) {
                if (empty($section['audioscript'])) { continue; }
                if (self::file($context, $versionid, $i)) { continue; }
                if ($one && $created) { break; }
                $script = $directory . '/script' . $i . '.txt';
                $wav = $directory . '/section' . $i . '.wav';
                if ($mode === 'gemini') {
                    $bytes = $synthesizer ? $synthesizer($section['audioscript']) : (new gemini())->speech($section['audioscript']);
                    file_put_contents($wav, $bytes);
                    $created[$i] = $wav;
                    // Persist each successful section so retry does not repeat a cloud request.
                    self::store($context, $versionid, $i, $wav, 'wav', 'audio/wav');
                    continue;
                }
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
                self::store($context, $versionid, $i, $wav, 'wav', 'audio/wav');
            }
            $remaining = false;
            foreach ($content['sections'] as $i => $section) {
                if (!empty($section['audioscript']) && !self::file($context, $versionid, $i)) { $remaining = true; }
            }
            $DB->set_field('aiel_versions', 'audiostatus', $remaining ? 'pending' : 'ready', ['id' => $versionid]);
        } catch (\Throwable $e) {
            $code = $e instanceof provider_error ? $e->getMessage() : 'internal';
            $DB->set_field('aiel_versions', 'audiostatus', 'failed:' . $code, ['id' => $versionid]);
        } finally { $lock->release(); }
    }
    public static function ready(\stdClass $version, \context_module $context): bool {
        $data = lesson::validate($version->content);
        foreach ($data['sections'] as $i => $section) {
            if (in_array($section['kind'], ['reading', 'listening', 'speaking', 'writing']) && trim($section['practice']) === '') { return false; }
            if ($section['kind'] === 'listening' && (empty($section['audioscript']) ||
                !self::file($context, $version->id, $i))) { return false; }
        }
        return true;
    }
    private static function store(\context $context, int $versionid, int $section, string $path, string $extension, string $mime): void {
        $fs = get_file_storage();
        foreach (['wav', 'mp3'] as $ext) {
            $old = $fs->get_file($context->id, 'mod_aienglishlesson', 'audio', $versionid, '/', 'section' . $section . '.' . $ext);
            if ($old) { $old->delete(); }
        }
        $fs->create_file_from_pathname((object)['contextid' => $context->id, 'component' => 'mod_aienglishlesson',
            'filearea' => 'audio', 'itemid' => $versionid, 'filepath' => '/', 'filename' => 'section' . $section . '.' . $extension,
            'mimetype' => $mime], $path);
    }
    public static function upload(\stdClass $version, int $section, array $upload): void {
        global $DB;
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name']) ||
                $upload['size'] > 8 * 1024 * 1024) { throw new \moodle_exception('lessonaudioinvalid', 'aienglishlesson'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extension = ['audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav'][$mime] ?? null;
        if (!$extension) { throw new \moodle_exception('lessonaudioinvalid', 'aienglishlesson'); }
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $version->lessonid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $version = $DB->get_record('aiel_versions', ['id' => $version->id, 'status' => 'draft'], '*', MUST_EXIST);
            $data = lesson::validate($version->content);
            if (empty($data['sections'][$section]['audioscript'])) { throw new \moodle_exception('invalidrequest'); }
            $cm = get_coursemodule_from_instance('aienglishlesson', $version->lessonid, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            self::store($context, $version->id, $section, $upload['tmp_name'], $extension, $mime);
            $DB->set_field('aiel_versions', 'audiostatus', self::ready($version, $context) ? 'ready' : 'upload', ['id' => $version->id]);
        } finally { $lock->release(); }
    }
}
