<?php
namespace mod_aienglishlesson;
defined('MOODLE_INTERNAL') || die();

final class lesson_visual_test extends \advanced_testcase {
    public function test_legacy_ungraded_fields_remain_valid_and_visual_review_targets_assessments(): void {
        $sections = [];
        foreach (\mod_aienglishlesson\local\lesson::KINDS as $kind) {
            $sections[] = [
                'kind' => $kind, 'title' => ucfirst($kind), 'body' => 'A picture is discussed in the passage.',
                'example' => '', 'practice' => 'Old ungraded practice question',
                'answer' => 'Teacher answer', 'feedback' => '', 'hint' => 'Old hint',
                'simpler' => '', 'extraexample' => '', 'audioscript' => $kind === 'listening' ? 'Listen carefully.' : '',
                'origin' => 'generated', 'cards' => $kind === 'vocabulary' ?
                    [['term' => 'word', 'meaning' => 'meaning', 'example' => 'example']] : [],
            ];
        }
        $sections[5]['practice'] = 'What does the picture show?';
        $quiz = [];
        for ($i = 0; $i < 10; $i++) {
            $quiz[] = ['question' => $i === 2 ? 'Look at the diagram. What follows?' : 'What follows from the passage?',
                'choices' => $i === 4 ? ['A', 'B', 'The diagram', 'D'] : ['A', 'B', 'C', 'D'],
                'correct' => 0, 'explanation' => 'The passage explains it.'];
        }
        $content = \mod_aienglishlesson\local\lesson::validate(json_encode([
            'title' => 'Older lesson', 'focus' => 'reading', 'readable' => true,
            'sections' => $sections, 'quiz' => $quiz,
        ]));
        $this->assertSame('Old ungraded practice question', $content['sections'][0]['practice']);
        $student = \mod_aienglishlesson\local\lesson::student_data($content);
        $this->assertSame(false, isset($student['sections'][0]['practice']));
        $this->assertSame('What does the picture show?', $student['sections'][5]['practice']);
        $this->assertSame([['type' => 'section', 'index' => 5], ['type' => 'quiz', 'index' => 2],
            ['type' => 'quiz', 'index' => 4]],
            \mod_aienglishlesson\local\lesson::visual_question_references($content));
    }
}
