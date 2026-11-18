<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();

class quiz {
    public static function score(array $questions, array $answers): float {
        if (count($questions) !== 10 || count($answers) !== 10) { throw new \moodle_exception('invalidquiz', 'aienglishlesson'); }
        $correct = 0;
        foreach ($questions as $i => $question) {
            if (!isset($answers[$i]) || !is_int($answers[$i]) || $answers[$i] < 0 || $answers[$i] > 3) {
                throw new \moodle_exception('invalidquiz', 'aienglishlesson');
            }
            if ($answers[$i] === $question['correct']) { $correct++; }
        }
        return $correct * 10.0;
    }

    public static function submit(\stdClass $version, int $userid, array $answers): int {
        global $DB;
        if ($version->status !== 'published') { throw new \moodle_exception('invalidrequest'); }
        $content = lesson::validate($version->content);
        if (empty($content['quiz'])) { throw new \moodle_exception('invalidquiz', 'aienglishlesson'); }
        $score = self::score($content['quiz'], $answers);
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock(
            'quiz' . $version->id . '-' . $userid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $latest = self::latest($version->id, $userid);
            $tx = $DB->start_delegated_transaction();
            $id = $DB->insert_record('aiel_quizattempts', (object)[
                'lessonid' => $version->lessonid, 'versionid' => $version->id, 'userid' => $userid,
                'attemptno' => $latest ? $latest->attemptno + 1 : 1,
                'answers' => json_encode($answers), 'score' => $score, 'timecreated' => time(),
            ]);
            try { assessment::update_grade($version, $userid); }
            catch (\moodle_exception $e) {
                if ($e->errorcode !== 'gradelocked') { throw $e; }
            }
            $tx->allow_commit();
            return $id;
        } finally { $lock->release(); }
    }

    public static function latest(int $versionid, int $userid) {
        global $DB;
        $rows = $DB->get_records('aiel_quizattempts', ['versionid' => $versionid, 'userid' => $userid],
            'attemptno DESC', '*', 0, 1);
        return $rows ? reset($rows) : false;
    }
}
