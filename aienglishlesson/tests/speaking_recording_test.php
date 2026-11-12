<?php
namespace mod_aienglishlesson;
defined('MOODLE_INTERNAL') || die();

class speaking_capture_provider extends \mod_aienglishlesson\local\gemini {
    public array $sent = [];

    protected function request(string $method, string $path, ?array $body = null, int $maxbytes = 1000000,
            int $maxattempts = 3, int $timeout = 0): array {
        $this->sent = $body ?? [];
        return ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['text' => '{"score":80,"confidence":90,"feedback":"Speak more clearly on your next attempt."}'],
        ]]]]];
    }
}

final class speaking_recording_test extends \advanced_testcase {
    private function lesson_content(): string {
        $sections = [];
        foreach (\mod_aienglishlesson\local\lesson::KINDS as $kind) {
            $sections[] = ['kind' => $kind, 'title' => ucfirst($kind), 'body' => 'Lesson text.',
                'example' => '', 'practice' => in_array($kind, ['reading', 'listening', 'speaking', 'writing'], true) ?
                    'Answer this task.' : '', 'answer' => 'Teacher answer', 'feedback' => '', 'hint' => '',
                'simpler' => '', 'extraexample' => '', 'audioscript' => $kind === 'listening' ? 'Listen.' : '',
                'origin' => 'generated', 'cards' => $kind === 'vocabulary' ?
                    [['term' => 'word', 'meaning' => 'meaning', 'example' => 'example']] : []];
        }
        return json_encode(['title' => 'Speaking lesson', 'focus' => 'speaking',
            'readable' => true, 'sections' => $sections]);
    }

    public function test_notes_without_recording_cannot_be_submitted(): void {
        $version = (object)['id' => 1, 'lessonid' => 1, 'status' => 'published',
            'content' => $this->lesson_content()];
        try {
            \mod_aienglishlesson\local\responses::save($version, 7, 1, 'These are only notes.');
            $this->fail('A recording is required.');
        } catch (\moodle_exception $e) {
            $this->assertSame('recordingrequired', $e->errorcode);
        }
        try {
            \mod_aienglishlesson\local\responses::save($version, 7, 1, '', null,
                ['error' => UPLOAD_ERR_OK, 'tmp_name' => 'unused']);
            $this->fail('An image cannot replace a recording.');
        } catch (\moodle_exception $e) {
            $this->assertSame('recordingrequired', $e->errorcode);
        }
        $this->assertNull(\mod_aienglishlesson\local\responses::check_answer(
            ['kind' => 'speaking', 'answer' => '[exact] Teacher answer'], 'Teacher answer'));
    }

    public function test_speaking_provider_rejects_missing_audio(): void {
        $provider = new speaking_capture_provider();
        $this->expectException(\mod_aienglishlesson\local\provider_error::class);
        $provider->assess(['kind' => 'speaking'], 'Notes alone', 'Audio only.', []);
    }

    public function test_speaking_provider_receives_audio_without_notes(): void {
        $this->resetAfterTest();
        foreach (['provider' => 'gemini', 'eligible' => 1, 'freeonly' => 1,
                'apikey' => 'FAKE-TEST-KEY', 'model' => 'gemini-3.5-flash-lite'] as $key => $value) {
            set_config($key, $value, 'aienglishlesson');
        }
        $file = get_file_storage()->create_file_from_string((object)[
            'contextid' => \context_system::instance()->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => 197, 'filepath' => '/', 'filename' => 'speaking.wav', 'mimetype' => 'audio/wav',
        ], 'RIFF-test-audio-bytes');
        $provider = new speaking_capture_provider();
        $section = ['kind' => 'speaking', 'practice' => 'Explain this aloud.',
            'body' => 'Lesson text.', 'answer' => 'Teacher reference.'];
        $result = $provider->assess($section, 'PRIVATE-NOTES-MUST-NOT-BE-SENT',
            'Use only the recording.', [$file]);
        $this->assertSame(80, $result['score']);
        $parts = $provider->sent['contents'][0]['parts'];
        $this->assertCount(2, $parts);
        $this->assertSame('audio/wav', $parts[0]['inlineData']['mimeType']);
        $this->assertStringNotContainsString('PRIVATE-NOTES-MUST-NOT-BE-SENT', $parts[1]['text']);
        $this->assertStringNotContainsString('learnerresponse', $parts[1]['text']);
    }
}
