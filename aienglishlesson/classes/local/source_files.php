<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();

/** Bounded source extraction. Media is kept as media so scanned pages remain usable. */
class source_files {
    public static function extensions(): array {
        return ['.pdf', '.docx', '.pptx', '.odt', '.txt', '.md', '.csv', '.html', '.htm', '.png', '.jpg', '.jpeg', '.webp'];
    }

    public static function parts(array $files, string $pagerange): array {
        if (!$files || count($files) > 5) { throw new \moodle_exception('invalidsources', 'aienglishlesson'); }
        if ($pagerange && (count($files) !== 1 || strtolower(pathinfo(reset($files)->get_filename(), PATHINFO_EXTENSION)) !== 'pdf')) {
            throw new \moodle_exception('rangeonlypdf', 'aienglishlesson');
        }
        $parts = [];
        $totaltext = 0;
        $totalinline = 0;
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!in_array('.' . $ext, self::extensions(), true) || $file->get_filesize() > pdf::maxbytes() || $file->get_filesize() < 1) {
                throw new \moodle_exception('invalidsources', 'aienglishlesson');
            }
            if ($ext === 'pdf') {
                [$bytes] = pdf::selected_content($file, $pagerange);
                $mime = 'application/pdf';
            } else if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                $bytes = $file->get_content();
                $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$ext];
                if (!getimagesizefromstring($bytes)) { throw new \moodle_exception('invalidsources', 'aienglishlesson'); }
            } else {
                $plain = self::extract_text($file, $ext);
                $totaltext += strlen($plain);
                if ($totaltext > 100000 || trim($plain) === '') { throw new \moodle_exception('invalidsources', 'aienglishlesson'); }
                $parts[] = ['text' => 'Source file ' . $file->get_filename() . ":\n" . $plain];
                continue;
            }
            $totalinline += strlen($bytes);
            if ($totalinline > 12 * 1024 * 1024) { throw new \moodle_exception('invalidsources', 'aienglishlesson'); }
            $parts[] = ['text' => 'Source file: ' . $file->get_filename()];
            $parts[] = ['inlineData' => ['mimeType' => $mime, 'data' => base64_encode($bytes)]];
        }
        return $parts;
    }

    private static function extract_text(\stored_file $file, string $ext): string {
        $bytes = $file->get_content();
        if (in_array($ext, ['txt', 'md', 'csv'], true)) { return self::clean($bytes); }
        if (in_array($ext, ['html', 'htm'], true)) {
            $bytes = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $bytes);
            return self::clean(html_entity_decode(strip_tags($bytes), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (!class_exists('ZipArchive')) { throw new \moodle_exception('invalidsources', 'aienglishlesson'); }
        $temp = tempnam(make_request_directory(), 'aiel');
        file_put_contents($temp, $bytes);
        $zip = new \ZipArchive();
        if ($zip->open($temp) !== true) { unlink($temp); throw new \moodle_exception('invalidsources', 'aienglishlesson'); }
        $pattern = $ext === 'docx' ? '~^word/document\.xml$~' :
            ($ext === 'odt' ? '~^content\.xml$~' : '~^ppt/slides/slide[0-9]+\.xml$~');
        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match($pattern, $name)) { continue; }
            $stat = $zip->statIndex($i);
            if (($stat['size'] ?? 0) > 2 * 1024 * 1024 || strlen($text) > 100000) {
                $zip->close(); unlink($temp);
                throw new \moodle_exception('invalidsources', 'aienglishlesson');
            }
            $xml = $zip->getFromIndex($i);
            if ($xml === false) { continue; }
            $xml = preg_replace('~</(?:w:p|a:p|text:p)>~', "\n", $xml);
            $text .= ' ' . html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        $zip->close(); unlink($temp);
        return self::clean($text);
    }

    private static function clean(string $text): string {
        if (!mb_check_encoding($text, 'UTF-8')) { throw new \moodle_exception('invalidsources', 'aienglishlesson'); }
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', ' ', $text));
    }
}
