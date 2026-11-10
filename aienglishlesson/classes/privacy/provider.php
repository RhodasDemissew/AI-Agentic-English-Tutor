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
        $fields['response'] = 'privacy:metadata:response';
        foreach (['aistatus', 'aiscore', 'aifeedback', 'aiconfidence', 'teacherscore',
                'teacherfeedback', 'approvedby', 'timeapproved'] as $field) {
            $fields[$field] = 'privacy:metadata:assessment';
        }
        $collection->add_database_table('aiel_responses', $fields, 'privacy:metadata:responses');
        $collection->add_database_table('aiel_grades', ['userid' => 'privacy:metadata:userid',
            'lessonid' => 'privacy:metadata:lessonid', 'versionid' => 'privacy:metadata:versionid',
            'grade' => 'privacy:metadata:assessment', 'timemodified' => 'privacy:metadata:timemodified'],
            'privacy:metadata:grades');
        $collection->add_external_location_link('gemini', ['source' => 'privacy:metadata:source',
            'response' => 'privacy:metadata:response', 'media' => 'privacy:metadata:assessment'],
            'privacy:metadata:gemini');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql('SELECT DISTINCT c.id FROM {context} c JOIN {course_modules} cm ON cm.id = c.instanceid
            JOIN {modules} m ON m.id = cm.module LEFT JOIN {aiel_progress} p ON p.lessonid = cm.instance
            LEFT JOIN {aiel_responses} r ON r.lessonid = cm.instance
            LEFT JOIN {aiel_grades} g ON g.lessonid = cm.instance
            WHERE c.contextlevel = :level AND m.name = :name AND (p.userid = :userid OR r.userid = :responseuserid OR g.userid = :gradeuserid)',
            ['level' => CONTEXT_MODULE, 'name' => 'aienglishlesson', 'userid' => $userid,
                'responseuserid' => $userid, 'gradeuserid' => $userid]);
        return $list;
    }
    private static function lessonid(\context $context): int {
        if ($context->contextlevel !== CONTEXT_MODULE) { return 0; }
        $cm = get_coursemodule_from_id('aienglishlesson', $context->instanceid);
        return $cm ? (int)$cm->instance : 0;
    }
    public static function get_users_in_context(userlist $userlist) {
        $id = self::lessonid($userlist->get_context());
        if ($id) {
            foreach (['aiel_progress', 'aiel_responses', 'aiel_grades'] as $table) {
                $userlist->add_from_sql('userid', 'SELECT userid FROM {' . $table . '} WHERE lessonid = :id', ['id' => $id]);
            }
        }
    }
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $id = self::lessonid($context);
            if (!$id) { continue; }
            $records = $DB->get_records('aiel_progress', ['lessonid' => $id, 'userid' => $contextlist->get_user()->id]);
            $grades = $DB->get_records('aiel_grades', ['lessonid' => $id, 'userid' => $contextlist->get_user()->id]);
            writer::with_context($context)->export_data([get_string('pluginname', 'aienglishlesson')],
                (object)['progress' => array_values($records), 'grades' => array_values($grades)]);
            foreach ($DB->get_records('aiel_responses', ['lessonid' => $id, 'userid' => $contextlist->get_user()->id]) as $response) {
                $path = [get_string('pluginname', 'aienglishlesson'), 'Responses', (string)$response->id];
                writer::with_context($context)->export_data($path, $response);
                writer::with_context($context)->export_area_files($path, 'mod_aienglishlesson', 'recording', $response->id);
                writer::with_context($context)->export_area_files($path, 'mod_aienglishlesson', 'image', $response->id);
            }
        }
    }
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($id = self::lessonid($context)) {
            $DB->delete_records('aiel_progress', ['lessonid' => $id]);
            $DB->delete_records('aiel_grades', ['lessonid' => $id]);
            \mod_aienglishlesson\local\responses::delete($context, ['lessonid' => $id]);
        }
    }
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($id = self::lessonid($context)) {
                $conditions = ['lessonid' => $id, 'userid' => $contextlist->get_user()->id];
                $DB->delete_records('aiel_progress', $conditions);
                $DB->delete_records('aiel_grades', $conditions);
                \mod_aienglishlesson\local\responses::delete($context, $conditions);
            }
        }
    }
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $id = self::lessonid($userlist->get_context());
        if (!$id || !$userlist->get_userids()) { return; }
        [$sql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['lessonid'] = $id;
        $DB->delete_records_select('aiel_progress', 'lessonid = :lessonid AND userid ' . $sql, $params);
        $DB->delete_records_select('aiel_grades', 'lessonid = :lessonid AND userid ' . $sql, $params);
        foreach ($userlist->get_userids() as $userid) {
            \mod_aienglishlesson\local\responses::delete($userlist->get_context(), ['lessonid' => $id, 'userid' => $userid]);
        }
    }
}
