<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();

/** One learner's position in a published lesson, used by the lesson header and end-of-activity screens. */
class progress {
    /** Scores below this are suggested for more practice. */
    public const FOCUS_BELOW = 70;

    public static function summary(\stdClass $version, array $content, int $userid): array {
        global $DB;
        $done = $DB->get_fieldset_select('aiel_progress', 'sectionno', 'versionid = ? AND userid = ?', [$version->id, $userid]);
        $attempts = $DB->get_records('aiel_responses', ['versionid' => $version->id, 'userid' => $userid], 'id DESC');
        $quiz = isset($content['quiz']) ? quiz::latest((int)$version->id, $userid) : false;
        $games = !empty($content['activities']) ? practice::latest((int)$version->id, $userid) : false;
        $grade = $DB->get_record('aiel_grades', ['lessonid' => $version->lessonid, 'userid' => $userid]);
        return self::build($content, array_map('intval', $done), assessment::skill_scores($content, $attempts),
            $quiz ? (float)$quiz->score : null, $games ? (float)$games->score : null,
            $grade && (int)$grade->versionid === (int)$version->id ? (float)$grade->grade : null);
    }

    /** Pure part of summary(), kept separate so it can be checked without a database. */
    public static function build(array $content, array $donesections, array $skillscores, ?float $quizscore,
            ?float $gamescore, ?float $finalgrade): array {
        $total = count($content['sections']);
        $nextsection = null;
        $sectionofkind = [];
        foreach ($content['sections'] as $i => $section) {
            $sectionofkind[$section['kind']] = $i;
            if ($nextsection === null && !in_array($i, $donesections, true)) { $nextsection = $i; }
        }
        $hasgames = !empty($content['activities']);
        $hasquiz = isset($content['quiz']);
        $complete = $nextsection === null && (!$hasgames || $gamescore !== null) && (!$hasquiz || $quizscore !== null);
        $focus = [];
        foreach (assessment::SKILLS as $skill) {
            $score = $skillscores[$skill]['score'] ?? null;
            if ($score === null || $score < self::FOCUS_BELOW) {
                $focus[] = ['area' => $skill, 'score' => $score, 'section' => $sectionofkind[$skill] ?? null];
            }
        }
        if ($hasgames && $gamescore !== null && $gamescore < self::FOCUS_BELOW) {
            $focus[] = ['area' => 'games', 'score' => $gamescore, 'section' => null];
        }
        if ($hasquiz && $quizscore !== null && $quizscore < self::FOCUS_BELOW) {
            $focus[] = ['area' => 'quiz', 'score' => $quizscore, 'section' => null];
        }
        // Missing work first, then the weakest score.
        usort($focus, static function($a, $b) {
            return [$a['score'] !== null, $a['score']] <=> [$b['score'] !== null, $b['score']];
        });
        return ['done' => count(array_unique($donesections)), 'total' => $total, 'nextsection' => $nextsection,
            'hasgames' => $hasgames, 'hasquiz' => $hasquiz, 'gamescore' => $gamescore, 'quizscore' => $quizscore,
            'finalgrade' => $finalgrade, 'complete' => $complete, 'focus' => $focus];
    }
}
