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
    if ($oldversion < 2026110900) {
        $manager = $DB->get_manager();
        $versions = new xmldb_table('aiel_versions');
        $rubrics = new xmldb_field('rubrics', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timemodified');
        if (!$manager->field_exists($versions, $rubrics)) { $manager->add_field($versions, $rubrics); }
        $responses = new xmldb_table('aiel_responses');
        foreach ([
            new xmldb_field('aistatus', XMLDB_TYPE_CHAR, '20', null, null, null, 'none', 'timemodified'),
            new xmldb_field('aiscore', XMLDB_TYPE_INTEGER, '10'),
            new xmldb_field('aifeedback', XMLDB_TYPE_TEXT),
            new xmldb_field('aiconfidence', XMLDB_TYPE_INTEGER, '10'),
            new xmldb_field('teacherscore', XMLDB_TYPE_INTEGER, '10'),
            new xmldb_field('teacherfeedback', XMLDB_TYPE_TEXT),
            new xmldb_field('approvedby', XMLDB_TYPE_INTEGER, '10'),
            new xmldb_field('timeapproved', XMLDB_TYPE_INTEGER, '10'),
        ] as $field) {
            if (!$manager->field_exists($responses, $field)) { $manager->add_field($responses, $field); }
        }
        $oldindex = new xmldb_index('usersection', XMLDB_INDEX_UNIQUE, ['versionid', 'userid', 'sectionno']);
        if ($manager->index_exists($responses, $oldindex)) { $manager->drop_index($responses, $oldindex); }
        $newindex = new xmldb_index('usersection', XMLDB_INDEX_NOTUNIQUE, ['versionid', 'userid', 'sectionno']);
        if (!$manager->index_exists($responses, $newindex)) { $manager->add_index($responses, $newindex); }
        $approvalindex = new xmldb_index('approval', XMLDB_INDEX_NOTUNIQUE, ['versionid', 'userid', 'timeapproved']);
        if (!$manager->index_exists($responses, $approvalindex)) { $manager->add_index($responses, $approvalindex); }
        $xml = new xmldb_file(__DIR__ . '/install.xml');
        $xml->loadXMLStructure();
        $grades = $xml->getStructure()->getTable('aiel_grades');
        if (!$manager->table_exists($grades)) { $manager->create_table($grades); }
        require_once(__DIR__ . '/../lib.php');
        foreach ($DB->get_records('aienglishlesson') as $activity) {
            aienglishlesson_grade_item_update($activity);
        }
        upgrade_mod_savepoint(true, 2026110900, 'aienglishlesson');
    }
    return true;
}
