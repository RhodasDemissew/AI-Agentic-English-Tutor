<?php
namespace mod_aienglishlesson;
defined('MOODLE_INTERNAL') || die();

final class assessment_test extends \advanced_testcase {
    public function test_four_latest_scored_skills_are_required(): void {
        $content = ['sections' => [
            ['kind' => 'reading'], ['kind' => 'listening'], ['kind' => 'speaking'], ['kind' => 'writing'],
        ]];
        $attempts = [
            (object)['sectionno' => 0, 'timeapproved' => null, 'teacherscore' => null, 'aistatus' => 'none'],
            (object)['sectionno' => 0, 'timeapproved' => 20, 'teacherscore' => 80],
            (object)['sectionno' => 0, 'timeapproved' => 10, 'teacherscore' => 10],
            (object)['sectionno' => 1, 'timeapproved' => 20, 'teacherscore' => 90],
            (object)['sectionno' => 2, 'timeapproved' => 20, 'teacherscore' => 100],
        ];
        $this->assertNull(\mod_aienglishlesson\local\assessment::grade_from_attempts($content, $attempts));
        $attempts[] = (object)['sectionno' => 3, 'timeapproved' => 20, 'teacherscore' => 70];
        $this->assertNull(\mod_aienglishlesson\local\assessment::grade_from_attempts($content, $attempts));
        $attempts[0]->aistatus = 'succeeded';
        $attempts[0]->aiscore = 60;
        $this->assertSame(80.0, \mod_aienglishlesson\local\assessment::grade_from_attempts($content, $attempts));
    }
    public function test_quiz_score_and_answer_validation(): void {
        $questions = [];
        foreach (range(0, 9) as $i) { $questions[] = ['correct' => $i % 4]; }
        $answers = array_column($questions, 'correct');
        $this->assertSame(100.0, \mod_aienglishlesson\local\quiz::score($questions, $answers));
        $answers[3] = ($answers[3] + 1) % 4;
        $this->assertSame(90.0, \mod_aienglishlesson\local\quiz::score($questions, $answers));
        $answers[3] = 4;
        $this->expectException(\moodle_exception::class);
        \mod_aienglishlesson\local\quiz::score($questions, $answers);
    }
    public function test_final_grade_and_legacy_lesson(): void {
        $this->assertNull(\mod_aienglishlesson\local\assessment::final_grade(80.0, null, true));
        $this->assertSame(70.0, \mod_aienglishlesson\local\assessment::final_grade(80.0, 60.0, true));
        $this->assertSame(80.0, \mod_aienglishlesson\local\assessment::final_grade(80.0, null, false));
    }
}
