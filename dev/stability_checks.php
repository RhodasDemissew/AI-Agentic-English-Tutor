<?php
// Portable checks; no Moodle installation or provider key required.
define('MOODLE_INTERNAL', true);
class moodle_exception extends Exception {
    public string $errorcode;
    public function __construct(string $code, string $component = '') {
        $this->errorcode = $code;
        parent::__construct($code);
    }
}
require_once __DIR__ . '/../aienglishlesson/classes/local/provider.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/provider_error.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/lesson.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/gemini.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/responses.php';

use mod_aienglishlesson\local\gemini;
use mod_aienglishlesson\local\lesson;
use mod_aienglishlesson\local\provider_error;
use mod_aienglishlesson\local\responses;

function check(bool $ok, string $name): void {
    if (!$ok) { throw new RuntimeException($name); }
    echo "PASS $name\n";
}
function rejects(callable $action, string $code): void {
    try { $action(); }
    catch (moodle_exception $e) { check($e->errorcode === $code, $code); return; }
    throw new RuntimeException("Expected $code");
}
$raw = ['title' => 'Market language', 'focus' => 'reading', 'readable' => true, 'sections' => []];
foreach (lesson::KINDS as $kind) {
    $raw['sections'][] = ['kind' => $kind, 'title' => ucfirst($kind),
        'body' => 'At the market, Ana asks for apples politely.', 'origin' => 'source',
        'practice' => 'What does Ana ask for?', 'answer' => 'Apples',
        'audioscript' => $kind === 'listening' ? 'Ana asks for apples.' : '',
        'cards' => $kind === 'vocabulary' ? [['term' => 'market', 'meaning' => 'a place to shop',
            'example' => 'Ana visits the market.']] : []];
}
$method = new ReflectionMethod(gemini::class, 'normalize_payload');
$normalized = $method->invoke(new gemini(), $raw);
check(lesson::validate(json_encode($normalized))['sections'][0]['body'] === $raw['sections'][0]['body'], 'valid source retained');
$missing = $raw; array_splice($missing['sections'], 6, 1);
rejects(fn() => lesson::validate(json_encode($method->invoke(new gemini(), $missing))), 'invalidlesson');
$empty = $raw; $empty['sections'][5]['practice'] = '   ';
rejects(fn() => lesson::validate(json_encode($method->invoke(new gemini(), $empty))), 'invalidlesson');
$unreadable = ['readable' => false];
rejects(fn() => $method->invoke(new gemini(), $unreadable), 'unreadablepdf');
foreach ([503 => true, 400 => false, 401 => false, 429 => true] as $status => $transient) {
    try { gemini::check_response(0, $status); }
    catch (provider_error $e) { check($e->transient === $transient && $e->getMessage() === 'http' . $status,
        "HTTP $status classification"); continue; }
    throw new RuntimeException("HTTP $status accepted");
}
check(responses::check_answer(['answer' => '[exact] Two apples'], 'two   apples') === true, 'exact answer correct');
check(responses::check_answer(['answer' => '[exact] Two apples'], 'three apples') === false, 'exact answer incorrect');
check(responses::check_answer(['answer' => 'Two apples'], 'three apples') === null, 'open answer ungraded');
