<?php
define('MOODLE_INTERNAL', true);
class moodle_exception extends Exception { public string $errorcode;
    public function __construct(string $code, string $component = '') { $this->errorcode = $code; parent::__construct($code); }
}
class stored_file {
    public function __construct(private string $bytes) {}
    public function get_filesize(): int { return strlen($this->bytes); }
    public function get_mimetype(): string { return 'application/pdf'; }
    public function get_content(): string { return $this->bytes; }
}
function get_config(string $component, string $name): mixed {
    return $name === 'pdfinfo' ? '/nonexistent/pdfinfo' : false;
}
require_once __DIR__ . '/../aienglishlesson/classes/local/pdf.php';
use mod_aienglishlesson\local\pdf;
$bytes = "%PDF-1.4\nexample";
[$selected, $pages, $isolated] = pdf::selected_content(new stored_file($bytes), '2-3');
if ($selected !== $bytes || $pages !== null || $isolated !== false) { throw new RuntimeException('Portable range disclosure failed'); }
echo "PASS unavailable extraction discloses full PDF\n";
try { pdf::inspect(new stored_file(''), ''); throw new RuntimeException('Empty PDF accepted'); }
catch (moodle_exception $e) { if ($e->errorcode !== 'invalidpdf') { throw $e; } }
echo "PASS empty PDF rejected\n";
