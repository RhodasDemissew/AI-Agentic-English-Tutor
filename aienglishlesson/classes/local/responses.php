<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
class responses {
    /** Exact checks are opt-in: a teacher prefixes an answer with [exact]. */
    public static function check_answer(array $section, string $response): ?bool {
        $answer = $section['answer'] ?? '';
        if (!is_string($answer) || !str_starts_with($answer, '[exact] ')) { return null; }
        $expected = trim(substr($answer, 8));
        if ($expected === '') { return null; }
        $normalize = static function(string $text): string {
            return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
        };
        return $normalize($response) === $normalize($expected);
    }
    public static function save(\stdClass $version, int $section, int $userid, string $response, ?array $upload = null, ?array $image = null): int {
        global $DB;
        $data = lesson::validate($version->content);
        if ($version->status !== 'published' || !isset($data['sections'][$section]) || empty($data['sections'][$section]['practice']) || strlen($response) > 12000) {
            throw new \moodle_exception('invalidrequest');
        }
        $hasfile = $upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasfile && ($data['sections'][$section]['kind'] !== 'speaking' || $upload['error'] !== UPLOAD_ERR_OK ||
            !is_uploaded_file($upload['tmp_name']) || $upload['size'] > 8 * 1024 * 1024)) { throw new \moodle_exception('recordingerror', 'aienglishlesson'); }
        $mime = '';
        $extension = '';
        if ($hasfile) {
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
            $types = ['audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'video/ogg' => 'ogg',
                'application/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'video/mp4' => 'm4a', 'audio/mpeg' => 'mp3',
                'audio/wav' => 'wav', 'audio/x-wav' => 'wav'];
            if (!isset($types[$mime])) { throw new \moodle_exception('recordingerror', 'aienglishlesson'); }
            $extension = $types[$mime];
        }
        $hasimage = $image && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $imagemime = '';
        $imageextension = '';
        if ($hasimage) {
            if ($image['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($image['tmp_name']) || $image['size'] > 5 * 1024 * 1024) {
                throw new \moodle_exception('imageerror', 'aienglishlesson');
            }
            $imagemime = (new \finfo(FILEINFO_MIME_TYPE))->file($image['tmp_name']);
            $images = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($images[$imagemime]) || !getimagesize($image['tmp_name'])) {
                throw new \moodle_exception('imageerror', 'aienglishlesson');
            }
            $imageextension = $images[$imagemime];
        }
        if (trim($response) === '' && !$hasfile && !$hasimage) { throw new \moodle_exception('emptyresponse', 'aienglishlesson'); }
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('response' . $version->id . '-' . $userid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $recent = $DB->get_records('aiel_responses', ['versionid' => $version->id,
                'userid' => $userid, 'sectionno' => $section], 'id DESC', '*', 0, 1);
            if ($recent && ($last = reset($recent)) && $last->response === $response &&
                    $last->timemodified > time() - 30) {
                $cm = get_coursemodule_from_instance('aienglishlesson', $version->lessonid, 0, false, MUST_EXIST);
                $context = \context_module::instance($cm->id);
                $samefiles = true;
                foreach (['recording' => $hasfile ? $upload : null, 'image' => $hasimage ? $image : null] as $area => $incoming) {
                    $stored = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson',
                        $area, $last->id, 'id', false);
                    $file = $stored ? reset($stored) : false;
                    if ((bool)$incoming !== (bool)$file ||
                            ($incoming && $file->get_contenthash() !== sha1_file($incoming['tmp_name']))) {
                        $samefiles = false;
                        break;
                    }
                }
                if ($samefiles) { return (int)$last->id; }
            }
            $transaction = $DB->start_delegated_transaction();
            $record = (object)['lessonid' => $version->lessonid, 'versionid' => $version->id,
                'userid' => $userid, 'sectionno' => $section, 'response' => $response, 'timemodified' => time()];
            $record->id = $DB->insert_record('aiel_responses', $record);
            if ($hasfile) {
                $cm = get_coursemodule_from_instance('aienglishlesson', $version->lessonid, 0, false, MUST_EXIST);
                $context = \context_module::instance($cm->id);
                $fs = get_file_storage();
                $fs->create_file_from_pathname((object)['contextid' => $context->id, 'component' => 'mod_aienglishlesson',
                    'filearea' => 'recording', 'itemid' => $record->id, 'filepath' => '/', 'filename' => 'recording.' . $extension,
                    'userid' => $userid, 'mimetype' => $mime], $upload['tmp_name']);
            }
            if ($hasimage) {
                if (!isset($context)) {
                    $cm = get_coursemodule_from_instance('aienglishlesson', $version->lessonid, 0, false, MUST_EXIST);
                    $context = \context_module::instance($cm->id);
                }
                get_file_storage()->create_file_from_pathname((object)['contextid' => $context->id,
                    'component' => 'mod_aienglishlesson', 'filearea' => 'image', 'itemid' => $record->id,
                    'filepath' => '/', 'filename' => 'answer.' . $imageextension, 'userid' => $userid,
                    'mimetype' => $imagemime], $image['tmp_name']);
            }
            $transaction->allow_commit();
            return (int)$record->id;
        } finally { $lock->release(); }
    }
    public static function delete(\context $context, array $conditions): void {
        global $DB;
        foreach ($DB->get_records('aiel_responses', $conditions) as $record) {
            get_file_storage()->delete_area_files($context->id, 'mod_aienglishlesson', 'recording', $record->id);
            get_file_storage()->delete_area_files($context->id, 'mod_aienglishlesson', 'image', $record->id);
            $DB->delete_records('aiel_responses', ['id' => $record->id]);
        }
    }
}
