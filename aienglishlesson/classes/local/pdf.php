<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
class pdf {
    public static function maxbytes(): int {
        return min(10, max(1, (int)(get_config('aienglishlesson', 'maxmb') ?: 10))) * 1024 * 1024;
    }
    public static function inspect(\stored_file $file, string $range): int {
        if ($file->get_filesize() > self::maxbytes() || $file->get_mimetype() !== 'application/pdf' ||
                substr($file->get_content(), 0, 5) !== '%PDF-') {
            throw new \moodle_exception('invalidpdf', 'aienglishlesson');
        }
        $binary = get_config('aienglishlesson', 'pdfinfo') ?: '/usr/bin/pdfinfo';
        if (!is_executable($binary)) { throw new \moodle_exception('nopdfinfo', 'aienglishlesson'); }
        $temp = tempnam(make_request_directory(), 'pdf');
        $file->copy_content_to($temp);
        $output = [];
        exec(escapeshellarg($binary) . ' ' . escapeshellarg($temp) . ' 2>/dev/null', $output, $code);
        unlink($temp);
        $info = implode("\n", $output);
        if ($code || !preg_match('/Pages:\s+(\d+)/', $info, $m) || preg_match('/Encrypted:\s+yes/i', $info)) {
            throw new \moodle_exception('unreadablepdf', 'aienglishlesson');
        }
        $pages = (int)$m[1];
        if ($pages > min(1000, max(1, (int)(get_config('aienglishlesson', 'maxpages') ?: 100)))) {
            throw new \moodle_exception('toomanypages', 'aienglishlesson');
        }
        if ($range) {
            if (!preg_match('/^([1-9][0-9]*)(?:-([1-9][0-9]*))?$/D', $range, $m) ||
                (int)$m[1] > $pages || (isset($m[2]) && ((int)$m[2] < (int)$m[1] || (int)$m[2] > $pages))) {
                throw new \moodle_exception('rangeerror', 'aienglishlesson');
            }
        }
        return $pages;
    }
}
