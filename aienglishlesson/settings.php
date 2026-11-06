<?php
defined('MOODLE_INTERNAL') || die();
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configselect('aienglishlesson/executionmode', get_string('executionmode', 'aienglishlesson'),
        get_string('executionmode_desc', 'aienglishlesson'), 'direct', ['direct' => get_string('directmode', 'aienglishlesson'),
        'background' => get_string('backgroundmode', 'aienglishlesson')]));
    $settings->add(new admin_setting_configcheckbox('aienglishlesson/pdfstrict', get_string('pdfstrict', 'aienglishlesson'),
        get_string('pdfstrict_desc', 'aienglishlesson'), 0));
    $settings->add(new admin_setting_configselect('aienglishlesson/audioprovider', get_string('audioprovider', 'aienglishlesson'),
        get_string('audioprovider_desc', 'aienglishlesson'), 'upload', ['upload' => get_string('uploadmode', 'aienglishlesson'),
        'gemini' => 'Gemini cloud speech', 'local' => 'Local eSpeak NG']));
    $settings->add(new admin_setting_configcheckbox('aienglishlesson/requireaudio', get_string('requireaudio', 'aienglishlesson'),
        get_string('requireaudio_desc', 'aienglishlesson'), 0));
    $settings->add(new admin_setting_heading('aienglishlesson/providerinfo', get_string('provider', 'aienglishlesson'), get_string('providerwarning', 'aienglishlesson')));
    $settings->add(new admin_setting_configselect('aienglishlesson/provider', get_string('provider', 'aienglishlesson'), '', 'disabled',
        ['disabled' => get_string('disabled', 'aienglishlesson'), 'gemini' => 'Gemini Developer API']));
    foreach (['eligible', 'freeonly'] as $key) {
        $settings->add(new admin_setting_configcheckbox('aienglishlesson/' . $key, get_string($key, 'aienglishlesson'), get_string($key . '_desc', 'aienglishlesson'), 0));
    }
    $settings->add(new \mod_aienglishlesson\admin_setting_apikey('aienglishlesson/apikey', get_string('apikey', 'aienglishlesson'), get_string('apikey_desc', 'aienglishlesson'), ''));
    foreach (['model' => 'gemini-3.5-flash', 'speechmodel' => 'gemini-3.8-flash-lite-tts', 'speechvoice' => 'Kore', 'timeout' => 120, 'dailycalls' => 10, 'maxmb' => 10, 'maxpages' => 100, 'pdfinfo' => '/usr/bin/pdfinfo', 'speechbinary' => '/usr/bin/espeak-ng'] as $key => $default) {
        $settings->add(new admin_setting_configtext('aienglishlesson/' . $key, get_string($key, 'aienglishlesson'), '', $default,
            is_int($default) ? PARAM_INT : PARAM_TEXT));
    }
    $settings->add(new admin_setting_heading('aienglishlesson/testlink', '',
        html_writer::link(new moodle_url('/mod/aienglishlesson/testconnection.php'), get_string('testconnection', 'aienglishlesson'))));
}
