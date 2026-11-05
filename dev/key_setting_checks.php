<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/adminlib.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local development only'); }
\core\session\manager::set_user(get_admin());
// A separate temporary setting leaves the actual provider key untouched.
$setting = new \mod_aienglishlesson\admin_setting_apikey('aienglishlesson/keyfieldtest', 'Test key', '', '');
function verify($ok, $message) { if (!$ok) { throw new Exception($message); } echo "PASS: $message\n"; }
try {
    $setting->write_setting('FICTIONAL-KEY-FOR-TEST');
    verify($setting->get_setting() === 'FICTIONAL-KEY-FOR-TEST', 'Key can be saved');
    $html = $setting->output_html($setting->get_setting());
    verify(strpos($html, 'type="password"') !== false && strpos($html, 'name="s_aienglishlesson_keyfieldtest"') !== false,
        'Native password input uses Moodle setting name');
    verify(strpos($html, 'FICTIONAL-KEY-FOR-TEST') === false && strpos($html, 'data-passwordunmask') === false,
        'Saved key absent from HTML; no click-to-edit dependency');
    $setting->write_setting('');
    verify($setting->get_setting() === 'FICTIONAL-KEY-FOR-TEST', 'Blank preserves key');
    $setting->write_setting('FICTIONAL-REPLACEMENT');
    verify($setting->get_setting() === 'FICTIONAL-REPLACEMENT', 'Replacement saves');
    $setting->write_setting('-');
    verify($setting->get_setting() === '', 'Dash clears key');
    foreach ($DB->get_records('config_log', ['plugin' => 'aienglishlesson', 'name' => 'keyfieldtest']) as $log) {
        verify(strpos($log->value . $log->oldvalue, 'FICTIONAL') === false, 'Configuration log masks credentials');
    }
} finally { unset_config('keyfieldtest', 'aienglishlesson'); }
