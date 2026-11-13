<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();

/** Graded practice games (word order, matching, gap fill). Scored by PHP; no AI requests. */
class practice {
    /** Stable shuffle per lesson version and game, never equal to the original order. */
    public static function shuffled(array $values, string $seed): array {
        $keyed = [];
        foreach (array_values($values) as $i => $value) {
            $keyed[] = ['v' => $value, 'k' => hash('sha256', $seed . '|' . $i . '|' . $value)];
        }
        usort($keyed, static function($a, $b) { return strcmp($a['k'], $b['k']); });
        $out = array_column($keyed, 'v');
        if (count($out) > 1 && $out === array_values($values)) {
            $out[] = array_shift($out);
        }
        return $out;
    }

    /** Normalise free text for comparison only. */
    private static function norm(string $text): string {
        return \core_text::strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
    }

    /** Whether one submitted answer is fully correct. */
    public static function is_correct(array $activity, $answer): bool {
        if ($activity['type'] === 'gap') {
            return is_int($answer) && $answer === $activity['correct'];
        }
        if (!is_array($answer)) { return false; }
        if ($activity['type'] === 'order') {
            $expected = array_map([self::class, 'norm'], $activity['items']);
            $given = array_map(static function($word) { return is_string($word) ? self::norm($word) : ''; }, array_values($answer));
            return $given === $expected;
        }
        foreach ($activity['pairs'] as $i => $pair) {
            if (!isset($answer[$i]) || !is_string($answer[$i]) || self::norm($answer[$i]) !== self::norm($pair['right'])) {
                return false;
            }
        }
        return true;
    }

    /** The right answer in display form, revealed after a game is checked. */
    public static function solution(array $activity) {
        if ($activity['type'] === 'order') { return implode(' ', $activity['items']); }
        if ($activity['type'] === 'gap') {
            return ['index' => $activity['correct'], 'text' => $activity['choices'][$activity['correct']],
                'sentence' => str_replace('___', $activity['choices'][$activity['correct']], $activity['sentence'])];
        }
        return array_map(static function($pair) { return ['left' => $pair['left'], 'right' => $pair['right']]; }, $activity['pairs']);
    }

    /** What the browser needs to draw each game. Never includes the answers. */
    public static function client_games(array $activities, int $versionid): array {
        $games = [];
        foreach ($activities as $i => $activity) {
            $seed = $versionid . '-' . $i;
            $game = ['type' => $activity['type'], 'prompt' => $activity['prompt']];
            if ($activity['type'] === 'order') {
                $game['words'] = self::shuffled($activity['items'], $seed);
            } else if ($activity['type'] === 'match') {
                $game['lefts'] = array_column($activity['pairs'], 'left');
                $game['rights'] = self::shuffled(array_column($activity['pairs'], 'right'), $seed);
            } else {
                $game['sentence'] = $activity['sentence'];
                $game['choices'] = $activity['choices'];
            }
            $games[] = $game;
        }
        return $games;
    }

    /** Turn a browser answer into the stored shape for one game; null when malformed. */
    public static function normalise_answer(array $activity, $answer) {
        if ($activity['type'] === 'gap') {
            return is_int($answer) && $answer >= 0 && $answer < count($activity['choices']) ? $answer : null;
        }
        $count = $activity['type'] === 'order' ? count($activity['items']) : count($activity['pairs']);
        if (!is_array($answer) || count($answer) !== $count) { return null; }
        $out = [];
        foreach (array_values($answer) as $value) {
            if (!is_string($value) || \core_text::strlen($value) > 200) { return null; }
            $out[] = $value;
        }
        return $out;
    }

    /** A fresh play-through: every game unanswered. */
    public static function new_run(): array {
        return ['token' => bin2hex(random_bytes(16)), 'answers' => []];
    }

    /**
     * Mark one game in a run. The first answer is kept and locks the game; later checks return the same result.
     * Returns ['correct' => bool, 'solution' => mixed, 'repeat' => bool].
     */
    public static function check(array &$run, array $activities, int $index, $answer): array {
        if (!isset($activities[$index])) { throw new \moodle_exception('invalidpractice', 'aienglishlesson'); }
        $repeat = array_key_exists($index, $run['answers']);
        if (!$repeat) {
            $answer = self::normalise_answer($activities[$index], $answer);
            if ($answer === null) { throw new \moodle_exception('invalidpractice', 'aienglishlesson'); }
            $run['answers'][$index] = $answer;
        }
        return ['correct' => self::is_correct($activities[$index], $run['answers'][$index]),
            'solution' => self::solution($activities[$index]), 'repeat' => $repeat];
    }

    /** Stored answers in game order once every game in the run has been checked; otherwise null. */
    public static function run_answers(array $run, array $activities): ?array {
        $answers = [];
        foreach (array_keys($activities) as $i) {
            if (!array_key_exists($i, $run['answers'])) { return null; }
            $answers[] = $run['answers'][$i];
        }
        return $answers;
    }

    /** 1-3 stars for the finish screen. */
    public static function stars(float $score): int {
        return $score >= 80 ? 3 : ($score >= 50 ? 2 : 1);
    }

    /** Percentage of games answered fully correctly. */
    public static function score(array $activities, array $answers): float {
        if (!$activities || count($answers) !== count($activities)) {
            throw new \moodle_exception('invalidpractice', 'aienglishlesson');
        }
        $correct = 0;
        foreach ($activities as $i => $activity) {
            if (self::is_correct($activity, $answers[$i] ?? null)) { $correct++; }
        }
        return round($correct * 100 / count($activities), 2);
    }

    /** Read submitted answers from the request in the same shape as the stored games. */
    public static function answers_from_request(array $activities): array {
        $answers = [];
        foreach ($activities as $i => $activity) {
            if ($activity['type'] === 'gap') {
                $answers[] = optional_param('act_' . $i, -1, PARAM_INT);
                continue;
            }
            $count = $activity['type'] === 'order' ? count($activity['items']) : count($activity['pairs']);
            $values = [];
            for ($j = 0; $j < $count; $j++) {
                $value = optional_param('act_' . $i . '_' . $j, '', PARAM_TEXT);
                $values[] = \core_text::substr($value, 0, 200);
            }
            $answers[] = $values;
        }
        return $answers;
    }

    public static function submit(\stdClass $version, int $userid, array $answers): int {
        global $DB;
        if ($version->status !== 'published') { throw new \moodle_exception('invalidrequest'); }
        $content = lesson::validate($version->content);
        if (empty($content['activities'])) { throw new \moodle_exception('invalidpractice', 'aienglishlesson'); }
        $score = self::score($content['activities'], $answers);
        $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock(
            'practice' . $version->id . '-' . $userid, 10);
        if (!$lock) { throw new \moodle_exception('busy', 'aienglishlesson'); }
        try {
            $latest = self::latest($version->id, $userid);
            $tx = $DB->start_delegated_transaction();
            $id = $DB->insert_record('aiel_practiceattempts', (object)[
                'lessonid' => $version->lessonid, 'versionid' => $version->id, 'userid' => $userid,
                'attemptno' => $latest ? $latest->attemptno + 1 : 1,
                'answers' => json_encode($answers, JSON_UNESCAPED_UNICODE), 'score' => $score, 'timecreated' => time(),
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
        $rows = $DB->get_records('aiel_practiceattempts', ['versionid' => $versionid, 'userid' => $userid],
            'attemptno DESC', '*', 0, 1);
        return $rows ? reset($rows) : false;
    }
}
