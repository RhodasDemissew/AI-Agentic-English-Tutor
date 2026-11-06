<?php
defined('MOODLE_INTERNAL') || die();
function xmldb_aienglishlesson_upgrade($oldversion) {
    global $DB;
    if ($oldversion < 2026110401) {
        $manager = $DB->get_manager();
        $xml = new xmldb_file(__DIR__ . '/install.xml');
        $xml->loadXMLStructure();
        $table = $xml->getStructure()->getTable('aiel_responses');
        if (!$manager->table_exists($table)) { $manager->create_table($table); }
        $versions = new xmldb_table('aiel_versions');
        $field = new xmldb_field('audiostatus', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'none', 'content');
        if (!$manager->field_exists($versions, $field)) { $manager->add_field($versions, $field); }
        upgrade_mod_savepoint(true, 2026110401, 'aienglishlesson');
    }
    if ($oldversion < 2026110510) {
        if (get_config('aienglishlesson', 'model') === 'gemini-2.5-flash') {
            set_config('model', 'gemini-3.5-flash', 'aienglishlesson');
        }
        upgrade_mod_savepoint(true, 2026110510, 'aienglishlesson');
    }
    if ($oldversion < 2026110511) {
        if (get_config('aienglishlesson', 'requireaudio') === false) {
            set_config('requireaudio', 0, 'aienglishlesson');
        }
        upgrade_mod_savepoint(true, 2026110511, 'aienglishlesson');
    }
    return true;
}
