<?php
// Run without Moodle to check the pure lesson, quiz, grade and text-source paths.
define('MOODLE_INTERNAL', true);
class moodle_exception extends Exception {
    public function __construct(string $errorcode, string $component = '') { parent::__construct($errorcode); }
}
class stored_file {
    public function __construct(private string $filename, private string $content) {}
    public function get_filename(): string { return $this->filename; }
    public function get_filesize(): int { return strlen($this->content); }
    public function get_content(): string { return $this->content; }
    public function get_mimetype(): string { return str_ends_with($this->filename, '.pdf') ? 'application/pdf' : 'text/plain'; }
}
function get_config($component, $name = null) { return false; }
function make_request_directory(): string { return sys_get_temp_dir(); }
require_once __DIR__ . '/../aienglishlesson/classes/local/pdf.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/source_files.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/lesson.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/quiz.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/assessment.php';
use mod_aienglishlesson\local\{source_files, lesson, quiz, assessment};

function check($condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
}
$sections = [];
foreach (lesson::KINDS as $kind) {
    $sections[] = ['kind' => $kind, 'title' => ucfirst($kind), 'body' => 'A complete section.',
        'example' => '', 'practice' => in_array($kind, assessment::SKILLS) ? 'Answer this.' : '',
        'answer' => in_array($kind, ['reading', 'listening', 'writing']) ? 'Reference answer.' : '',
        'feedback' => 'Reference feedback.', 'hint' => '', 'simpler' => '', 'extraexample' => '',
        'audioscript' => $kind === 'listening' ? 'Listen carefully.' : '', 'origin' => 'generated',
        'cards' => $kind === 'vocabulary' ? [['term' => 'term', 'meaning' => 'meaning', 'example' => 'example']] : []];
}
$questions = [];
foreach (range(0, 9) as $i) {
    $questions[] = ['question' => 'Question ' . ($i + 1), 'choices' => ['A', 'B', 'C', 'D'],
        'correct' => $i % 4, 'explanation' => 'Because the lesson explains it.'];
}
$data = ['title' => 'Test', 'focus' => 'reading', 'readable' => true, 'sections' => $sections, 'quiz' => $questions];
$validated = lesson::validate(json_encode($data));
$student = lesson::student_data($validated);
check(!isset($student['quiz'][0]['correct']) && !isset($student['quiz'][0]['explanation']), 'quiz answer hidden');
check(!isset($student['sections'][5]['answer']), 'skill answer hidden');
check(quiz::score($questions, array_column($questions, 'correct')) === 100.0, 'quiz perfect score');
check(assessment::final_grade(80.0, 60.0, true) === 70.0, '50/50 final score');
check(assessment::final_grade(80.0, null, true) === null, 'incomplete grade');
check(assessment::final_grade(80.0, null, false) === 80.0, 'legacy grade');
unset($data['quiz']);
check(!isset(lesson::validate(json_encode($data))['quiz']), 'legacy lesson');
$data['quiz'] = $questions;
$data['quiz'][0]['choices'][1] = 'A';
try { lesson::validate(json_encode($data)); throw new RuntimeException('duplicate choices accepted'); }
catch (moodle_exception $e) { check($e->getMessage() === 'invalidlesson', 'quiz choice validation'); }
$parts = source_files::parts([new stored_file('lesson.txt', 'Readable source text.'),
    new stored_file('notes.html', '<p>Readable <b>notes</b>.</p>')], '');
check(count($parts) === 2 && str_contains($parts[1]['text'], 'Readable notes'), 'text extraction');
try { source_files::parts([new stored_file('notes.txt', 'test')], '1-2'); throw new RuntimeException('range accepted'); }
catch (moodle_exception $e) { check($e->getMessage() === 'rangeonlypdf', 'range restriction'); }
try { source_files::parts(array_fill(0, 6, new stored_file('lesson.txt', 'test')), '');
    throw new RuntimeException('six files accepted'); }
catch (moodle_exception $e) { check($e->getMessage() === 'invalidsources', 'file limit'); }
$pdfparts = source_files::parts([new stored_file('reading.pdf', '%PDF-1.4 fake portable fixture')], '1');
check(($pdfparts[1]['inlineData']['mimeType'] ?? '') === 'application/pdf', 'PDF media');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+nmZkAAAAASUVORK5CYII=');
$imageparts = source_files::parts([new stored_file('picture.png', $png)], '');
check(($imageparts[1]['inlineData']['mimeType'] ?? '') === 'image/png', 'image media');
if (class_exists(ZipArchive::class)) {
    foreach (['docx' => ['word/document.xml', '<w:p><w:t>Document lesson text</w:t></w:p>'],
            'pptx' => ['ppt/slides/slide1.xml', '<a:p><a:t>Slide lesson text</a:t></a:p>'],
            'odt' => ['content.xml', '<text:p>Open document lesson text</text:p>']] as $ext => [$entry, $xml]) {
        $path = tempnam(sys_get_temp_dir(), 'aiel');
        $zip = new ZipArchive();
        check($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'zip fixture');
        $zip->addFromString($entry, $xml); $zip->close();
        $part = source_files::parts([new stored_file('lesson.' . $ext, file_get_contents($path))], '');
        unlink($path);
        check(str_contains($part[0]['text'], 'lesson text'), strtoupper($ext) . ' extraction');
    }
}
echo "Smoke checks passed\n";
