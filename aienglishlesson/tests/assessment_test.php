<?php
namespace mod_aienglishlesson;
defined('MOODLE_INTERNAL') || die();

final class assessment_test extends \advanced_testcase {
    public function test_four_skills_required_and_latest_approved_counts(): void {
        $content = ['sections' => [
            ['kind' => 'reading'], ['kind' => 'listening'], ['kind' => 'speaking'], ['kind' => 'writing'],
        ]];
        $attempts = [
            (object)['sectionno' => 0, 'timeapproved' => null, 'teacherscore' => null],
            (object)['sectionno' => 0, 'timeapproved' => 20, 'teacherscore' => 80],
            (object)['sectionno' => 0, 'timeapproved' => 10, 'teacherscore' => 10],
            (object)['sectionno' => 1, 'timeapproved' => 20, 'teacherscore' => 90],
            (object)['sectionno' => 2, 'timeapproved' => 20, 'teacherscore' => 100],
        ];
        $this->assertNull(\mod_aienglishlesson\local\assessment::grade_from_attempts($content, $attempts));
        $attempts[] = (object)['sectionno' => 3, 'timeapproved' => 20, 'teacherscore' => 70];
        $this->assertSame(85.0, \mod_aienglishlesson\local\assessment::grade_from_attempts($content, $attempts));
    }
}
