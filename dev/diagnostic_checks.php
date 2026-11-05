<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local development only'); }
$cases = [[6, 0, 'curl6', true], [7, 0, 'curl7', true], [28, 0, 'curl28', true],
    [60, 0, 'curl60', false], [35, 0, 'curl35', true], [56, 200, 'curl56', true],
    [0, 429, 'http429', true], [0, 503, 'http503', true], [0, 500, 'http500', true],
    [0, 400, 'http400', false], [0, 403, 'http403', false], [0, 413, 'http413', false], [0, 0, 'http0', true]];
foreach ($cases as [$errno, $status, $code, $retry]) {
    try {
        \mod_aienglishlesson\local\gemini::check_response($errno, $status);
        throw new Exception('Expected rejection');
    } catch (\mod_aienglishlesson\local\provider_error $e) {
        if ($e->getMessage() !== $code || $e->transient !== $retry) { throw new Exception('Incorrect classification'); }
        echo "PASS: $code with correct retry behavior\n";
    }
}
\mod_aienglishlesson\local\gemini::check_response(0, 200);
echo "PASS: Successful response accepted; no external requests made\n";
