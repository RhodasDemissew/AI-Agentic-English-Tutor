<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
class pdf {
    /** Returns bytes, page count, and whether the requested range was physically isolated. */
    public static function selected_content(\stored_file $file, string $range): array {
        $pages = self::inspect($file, $range);
        $content = $file->get_content();
        if (!$range) { return [$content, $pages, true]; }
        $separate = '/usr/bin/pdfseparate';
        $unite = '/usr/bin/pdfunite';
        if ($pages === null || !function_exists('exec') || !is_executable($separate) || !is_executable($unite)) {
            return [$content, $pages, false];
        }
        $bounds = array_map('intval', explode('-', $range));
        $first = $bounds[0]; $last = $bounds[1] ?? $first;
        $dir = make_request_directory();
        $source = $dir . '/aiel-source.pdf';
        $out = $dir . '/aiel-selected.pdf';
        $file->copy_content_to($source);
        $pattern = $dir . '/aiel-page-%d.pdf';
        exec(escapeshellarg($separate) . ' -f ' . $first . ' -l ' . $last . ' ' .
            escapeshellarg($source) . ' ' . escapeshellarg($pattern) . ' 2>/dev/null', $unused, $code);
        if ($code) { throw new \moodle_exception('unreadablepdf', 'aienglishlesson'); }
        $parts = [];
        for ($i = $first; $i <= $last; $i++) {
            $part = $dir . '/aiel-page-' . $i . '.pdf';
            if (!is_file($part)) { throw new \moodle_exception('unreadablepdf', 'aienglishlesson'); }
            $parts[] = escapeshellarg($part);
        }
        exec(escapeshellarg($unite) . ' ' . implode(' ', $parts) . ' ' . escapeshellarg($out) . ' 2>/dev/null', $unused, $code);
        if ($code || !is_file($out) || filesize($out) < 5 || filesize($out) > self::maxbytes()) {
            throw new \moodle_exception('invalidpdf', 'aienglishlesson');
        }
        return [file_get_contents($out), $pages, true];
    }
    public static function maxbytes(): int {
        return min(10, max(1, (int)(get_config('aienglishlesson', 'maxmb') ?: 10))) * 1024 * 1024;
    }
    /** Null means page count is unknown in portable mode. */
    public static function inspect(\stored_file $file, string $range): ?int {
        if ($file->get_filesize() < 5 || $file->get_filesize() > self::maxbytes() || $file->get_mimetype() !== 'application/pdf' ||
                substr($file->get_content(), 0, 5) !== '%PDF-') {
            throw new \moodle_exception('invalidpdf', 'aienglishlesson');
        }
        if ($range && (!preg_match('/^([1-9][0-9]*)(?:-([1-9][0-9]*))?$/D', $range, $match) ||
                (isset($match[2]) && (int)$match[2] < (int)$match[1]))) {
            throw new \moodle_exception('rangeerror', 'aienglishlesson');
        }
        $binary = get_config('aienglishlesson', 'pdfinfo') ?: '/usr/bin/pdfinfo';
        if (!function_exists('exec') || !is_executable($binary)) {
            if (get_config('aienglishlesson', 'pdfstrict')) { throw new \moodle_exception('nopdfinfo', 'aienglishlesson'); }
            return null;
        }
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
        if ($pages < 1) { throw new \moodle_exception('unreadablepdf', 'aienglishlesson'); }
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
