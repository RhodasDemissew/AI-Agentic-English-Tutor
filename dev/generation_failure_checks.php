<?php
// Run without Moodle to check the provider's stage-failure diagnostics.
define('MOODLE_INTERNAL', true);
require_once __DIR__ . '/../aienglishlesson/classes/local/provider.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/provider_error.php';
require_once __DIR__ . '/../aienglishlesson/classes/local/gemini.php';

use mod_aienglishlesson\local\gemini;
use mod_aienglishlesson\local\provider_error;

$reasons = [
    'MAX_TOKENS' => 'outputlimit',
    'SAFETY' => 'blocked',
    'RECITATION' => 'blocked',
    'BLOCKLIST' => 'blocked',
    'OTHER' => 'incomplete',
];
foreach ($reasons as $reason => $expected) {
    try {
        gemini::check_generation_finish(['candidates' => [['finishReason' => $reason]]]);
        throw new RuntimeException('Expected failure for ' . $reason);
    } catch (provider_error $e) {
        if ($e->getMessage() !== $expected) { throw new RuntimeException('Wrong diagnostic for ' . $reason); }
    }
}
try {
    gemini::check_generation_finish(['promptFeedback' => ['blockReason' => 'SAFETY']]);
    throw new RuntimeException('Expected blocked prompt');
} catch (provider_error $e) {
    if ($e->getMessage() !== 'blocked') { throw new RuntimeException('Wrong blocked-prompt diagnostic'); }
}
gemini::check_generation_finish(['candidates' => [['finishReason' => 'STOP']]]);
if (gemini::section_token_limit('gemini-3.5-flash-lite', 1) !== 16384 ||
        gemini::section_token_limit('gemini-3.5-flash', 1) !== 16384 ||
        gemini::section_token_limit('gemini-3.5-flash-lite', 0) !== 8192 ||
        gemini::section_token_limit('gemini-2.5-flash', 1) !== 8192) {
    throw new RuntimeException('Wrong section token allowance');
}
$expected = [
    'KG' => ['range' => '200-230', 'minimum' => 180, 'target' => 200],
    'G6' => ['range' => '270-320', 'minimum' => 200, 'target' => 270],
    'G10' => ['range' => '350-400', 'minimum' => 210, 'target' => 350],
    'adult' => ['range' => '350-400', 'minimum' => 210, 'target' => 350],
];
foreach ($expected as $grade => $guidance) {
    if (gemini::speech_word_guidance($grade) !== $guidance) {
        throw new RuntimeException('Wrong speech guidance for ' . $grade);
    }
}
echo "Generation failure checks passed\n";
