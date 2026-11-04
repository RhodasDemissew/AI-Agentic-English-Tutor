<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
admin_externalpage_setup('managemodules');
require_capability('moodle/site:config', context_system::instance());
$PAGE->set_url('/mod/aienglishlesson/testconnection.php');
$PAGE->set_title(get_string('testconnection', 'aienglishlesson'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('testconnection', 'aienglishlesson'));
if (data_submitted()) {
    require_sesskey();
    try {
        (new \mod_aienglishlesson\local\gemini())->test();
        echo $OUTPUT->notification(get_string('connectionsuccess', 'aienglishlesson'), 'success');
    } catch (\Throwable $e) {
        $code = $e instanceof \mod_aienglishlesson\local\provider_error ? $e->getMessage() : 'internal';
        echo $OUTPUT->notification(get_string('connectionfailed', 'aienglishlesson', $code), 'error');
    }
}
echo $OUTPUT->single_button($PAGE->url, get_string('testconnection', 'aienglishlesson'), 'post');
echo $OUTPUT->footer();
