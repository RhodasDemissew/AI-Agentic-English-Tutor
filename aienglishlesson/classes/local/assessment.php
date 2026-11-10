<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();

/** Assessment orchestration. Teacher approval, rather than an AI response, releases grades. */
class assessment {
    public const SKILLS = ['reading', 'listening', 'speaking', 'writing'];

    public static function enabled(): bool {
        return get_config('aienglishlesson', 'assessmentprovider') === 'gemini' &&
            get_config('aienglishlesson', 'provider') === 'gemini';
    }

    public static function rubrics(\stdClass $version): array {
        $saved = json_decode($version->rubrics ?? '', true);
        if (!is_array($saved)) { $saved = []; }
        $defaults = [
            'reading' => 'Award credit for accurate understanding, evidence from the passage, and a clear answer.',
            'listening' => 'Award credit for accurate understanding of the spoken content and a clear answer.',
            'speaking' => 'Award credit for relevant ideas, clarity, and understandable spoken English. Do not infer pronunciation from typed notes.',
            'writing' => 'Award credit for relevance, organisation, vocabulary, grammar, and clear expression.',
        ];
        foreach ($defaults as $skill => $default) {
            if (!isset($saved[$skill]) || !is_string($saved[$skill]) || trim($saved[$skill]) === '') {
                $saved[$skill] = $default;
            }
        }
        return array_intersect_key($saved, $defaults);
    }

    /** Aggregate the latest approved attempt for each of the four fixed lesson skills. */
    public static function grade_from_attempts(array $content, array $attempts): ?float {
        $scores = [];
        foreach ($attempts as $row) {
            $kind = $content['sections'][$row->sectionno]['kind'] ?? '';
            if (in_array($kind, self::SKILLS, true) && !isset($scores[$kind]) && $row->timeapproved) {
                $scores[$kind] = (int)$row->teacherscore;
            }
        }
        return count($scores) === 4 ? array_sum($scores) / 4 : null;
    }

    public static function evaluate(int $attemptid, ?gemini $provider = null): void {
        global $DB;
        if (!self::enabled()) { return; }
        $attempt = $DB->get_record('aiel_responses', ['id' => $attemptid]);
        if (!$attempt || ($attempt->aistatus ?? 'none') !== 'none') { return; }
        $version = $DB->get_record('aiel_versions', ['id' => $attempt->versionid, 'lessonid' => $attempt->lessonid]);
        if (!$version) { return; }
        $content = lesson::validate($version->content);
        if (!isset($content['sections'][$attempt->sectionno]) ||
                !in_array($content['sections'][$attempt->sectionno]['kind'], self::SKILLS, true)) { return; }
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('assess' . $attemptid, 10);
        if (!$lock) { return; }
        try {
            $attempt = $DB->get_record('aiel_responses', ['id' => $attemptid]);
            if (!$attempt || $attempt->aistatus !== 'none' || !self::enabled()) { return; }
            $DB->set_field('aiel_responses', 'aistatus', 'running', ['id' => $attemptid]);
        } finally { $lock->release(); }
        try {
            $version = $DB->get_record('aiel_versions', ['id' => $attempt->versionid, 'lessonid' => $attempt->lessonid], '*', MUST_EXIST);
            $lesson = lesson::validate($version->content);
            $section = $lesson['sections'][$attempt->sectionno];
            $cm = get_coursemodule_from_instance('aienglishlesson', $attempt->lessonid, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $media = [];
            foreach (['recording', 'image'] as $area) {
                $files = get_file_storage()->get_area_files($context->id, 'mod_aienglishlesson', $area, $attemptid, 'id', false);
                foreach ($files as $file) { $media[] = $file; }
            }
            $result = ($provider ?? new gemini())->assess($section, $attempt->response,
                self::rubrics($version)[$section['kind']] ?? '', $media);
            if ($DB->record_exists('aiel_responses', ['id' => $attemptid])) {
                $DB->update_record('aiel_responses', (object)['id' => $attemptid, 'aistatus' => 'succeeded',
                    'aiscore' => $result['score'], 'aifeedback' => $result['feedback'],
                    'aiconfidence' => $result['confidence']]);
            }
        } catch (\Throwable $e) {
            // Never expose provider errors, credentials, or raw responses to learners.
            if ($DB->record_exists('aiel_responses', ['id' => $attemptid])) {
                $DB->set_field('aiel_responses', 'aistatus', 'failed', ['id' => $attemptid]);
            }
        }
    }

    /** A terminated web request must not leave an attempt appearing active forever. */
    public static function recover_stale(int $attemptid): void {
        global $DB;
        $attempt = $DB->get_record('aiel_responses', ['id' => $attemptid]);
        if ($attempt && $attempt->aistatus === 'running' && $attempt->timemodified < time() - 180) {
            $DB->set_field('aiel_responses', 'aistatus', 'failed', ['id' => $attemptid, 'aistatus' => 'running']);
        }
    }

    public static function clear_latest(\context $context, int $versionid, int $userid, int $section): void {
        global $DB;
        $rows = $DB->get_records('aiel_responses', ['versionid' => $versionid,
            'userid' => $userid, 'sectionno' => $section], 'id DESC', '*', 0, 1);
        if (!$rows) { return; }
        $latest = reset($rows);
        if ($latest->timeapproved) { throw new \moodle_exception('approvedlocked', 'aienglishlesson'); }
        responses::delete($context, ['id' => $latest->id]);
    }

    public static function approve(\stdClass $attempt, int $score, string $feedback, int $teacherid): void {
        global $DB;
        if ($score < 0 || $score > 100 || strlen($feedback) > 4000) { throw new \moodle_exception('invalidrequest'); }
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock(
            'grade' . $attempt->lessonid . '-' . $attempt->userid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $latest = $DB->get_records('aiel_responses', ['versionid' => $attempt->versionid,
                'userid' => $attempt->userid, 'sectionno' => $attempt->sectionno], 'id DESC', '*', 0, 1);
            if (!$latest || (int)reset($latest)->id !== (int)$attempt->id) {
                throw new \moodle_exception('oldattempt', 'aienglishlesson');
            }
            $version = $DB->get_record('aiel_versions', ['id' => $attempt->versionid,
                'lessonid' => $attempt->lessonid], '*', MUST_EXIST);
            if ($version->status !== 'published') { throw new \moodle_exception('invalidrequest'); }
            $content = lesson::validate($version->content);
            if (!in_array($content['sections'][$attempt->sectionno]['kind'], self::SKILLS, true)) {
                throw new \moodle_exception('invalidrequest');
            }
            $tx = $DB->start_delegated_transaction();
            $DB->update_record('aiel_responses', (object)['id' => $attempt->id,
                'teacherscore' => $score, 'teacherfeedback' => $feedback,
                'approvedby' => $teacherid, 'timeapproved' => time()]);
            $attempts = $DB->get_records('aiel_responses', ['versionid' => $version->id,
                'userid' => $attempt->userid], 'id DESC');
            $grade = self::grade_from_attempts($content, $attempts);
            if ($grade !== null) {
                require_once(__DIR__ . '/../../lib.php');
                $activity = $DB->get_record('aienglishlesson', ['id' => $attempt->lessonid], '*', MUST_EXIST);
                aienglishlesson_grade_item_update($activity);
                $gradeitem = \grade_item::fetch(['courseid' => $activity->course, 'itemtype' => 'mod',
                    'itemmodule' => 'aienglishlesson', 'iteminstance' => $activity->id, 'itemnumber' => 0]);
                $usergrade = $gradeitem ? \grade_grade::fetch(['itemid' => $gradeitem->id,
                    'userid' => $attempt->userid]) : false;
                if (($gradeitem && $gradeitem->is_locked()) || ($usergrade && $usergrade->is_locked())) {
                    throw new \moodle_exception('gradelocked', 'aienglishlesson');
                }
                $record = $DB->get_record('aiel_grades', ['lessonid' => $attempt->lessonid, 'userid' => $attempt->userid]);
                $record = $record ?: (object)['lessonid' => $attempt->lessonid, 'userid' => $attempt->userid];
                $record->versionid = $version->id; $record->grade = $grade; $record->timemodified = time();
                if (empty($record->id)) { $DB->insert_record('aiel_grades', $record); }
                else { $DB->update_record('aiel_grades', $record); }
            }
            if ($grade !== null) {
                $result = aienglishlesson_grade_item_update($activity,
                    (object)['userid' => $attempt->userid, 'rawgrade' => $grade]);
                if ($result !== GRADE_UPDATE_OK) { throw new \moodle_exception('gradeerror', 'aienglishlesson'); }
            }
            $tx->allow_commit();
        } finally { $lock->release(); }
    }
}
