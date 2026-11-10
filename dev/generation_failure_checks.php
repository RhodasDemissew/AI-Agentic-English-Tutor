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
echo "Generation failure checks passed\n";
