<?php
namespace mod_aienglishlesson\privacy;
defined('MOODLE_INTERNAL') || die();
use core_privacy\local\metadata\collection;
use core_privacy\local\request\{contextlist, approved_contextlist, userlist, approved_userlist, writer};
class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider, \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(collection $collection): collection {
        $fields = [];
        foreach (['userid', 'lessonid', 'versionid', 'sectionno', 'timemodified'] as $field) { $fields[$field] = 'privacy:metadata:' . $field; }
        $collection->add_database_table('aiel_progress', $fields, 'privacy:metadata:progress');
        $collection->add_external_location_link('gemini', ['source' => 'privacy:metadata:source'], 'privacy:metadata:gemini');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql('SELECT c.id FROM {context} c JOIN {course_modules} cm ON cm.id = c.instanceid
            JOIN {modules} m ON m.id = cm.module JOIN {aiel_progress} p ON p.lessonid = cm.instance
            WHERE c.contextlevel = :level AND m.name = :name AND p.userid = :userid',
            ['level' => CONTEXT_MODULE, 'name' => 'aienglishlesson', 'userid' => $userid]);
        return $list;
    }
    private static function lessonid(\context $context): int {
        if ($context->contextlevel !== CONTEXT_MODULE) { return 0; }
        $cm = get_coursemodule_from_id('aienglishlesson', $context->instanceid);
        return $cm ? (int)$cm->instance : 0;
    }
    public static function get_users_in_context(userlist $userlist) {
        $id = self::lessonid($userlist->get_context());
        if ($id) { $userlist->add_from_sql('userid', 'SELECT userid FROM {aiel_progress} WHERE lessonid = :id', ['id' => $id]); }
    }
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $id = self::lessonid($context);
            if (!$id) { continue; }
            $records = $DB->get_records('aiel_progress', ['lessonid' => $id, 'userid' => $contextlist->get_user()->id]);
            writer::with_context($context)->export_data([get_string('pluginname', 'aienglishlesson')], (object)['progress' => array_values($records)]);
        }
    }
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($id = self::lessonid($context)) { $DB->delete_records('aiel_progress', ['lessonid' => $id]); }
    }
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($id = self::lessonid($context)) { $DB->delete_records('aiel_progress', ['lessonid' => $id, 'userid' => $contextlist->get_user()->id]); }
        }
    }
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $id = self::lessonid($userlist->get_context());
        if (!$id || !$userlist->get_userids()) { return; }
        [$sql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['lessonid'] = $id;
        $DB->delete_records_select('aiel_progress', 'lessonid = :lessonid AND userid ' . $sql, $params);
    }
}
