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
    return true;
}
