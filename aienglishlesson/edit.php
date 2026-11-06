<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);
$versionid = required_param('version', PARAM_INT);
$cm = get_coursemodule_from_id('aienglishlesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aienglishlesson:manage', $context);
$version = $DB->get_record('aiel_versions', ['id' => $versionid, 'lessonid' => $cm->instance, 'status' => 'draft'], '*', MUST_EXIST);
$content = \mod_aienglishlesson\local\lesson::validate($version->content);
$PAGE->set_url('/mod/aienglishlesson/edit.php', ['id' => $id, 'version' => $versionid]);
$PAGE->set_title(get_string('editdraft', 'aienglishlesson'));
$PAGE->set_heading($course->fullname);
$form = new \mod_aienglishlesson\form\edit(null, ['id' => $id, 'version' => $versionid,
    'modified' => $version->timemodified, 'content' => $content]);
$back = new moodle_url('/mod/aienglishlesson/view.php', ['id' => $id, 'version' => $versionid]);
if ($form->is_cancelled()) { redirect($back); }
if ($data = $form->get_data()) {
    require_sesskey();
    $lock = \core\lock\lock_config::get_lock_factory('mod_aienglishlesson')->get_lock('lesson' . $cm->instance, 10);
    if (!$lock) { throw new moodle_exception('busy', 'aienglishlesson'); }
    try {
        $current = $DB->get_record('aiel_versions', ['id' => $versionid, 'status' => 'draft'], '*', MUST_EXIST);
        if ($current->timemodified != $data->modified) { throw new moodle_exception('editconflict', 'aienglishlesson'); }
        $content['title'] = $data->title;
        $content['focus'] = $data->focus;
        foreach ($content['sections'] as $i => &$section) {
            $section['origin'] = $data->{'origin_' . $i};
            foreach (['title', 'body', 'example', 'practice', 'answer', 'feedback', 'hint', 'simpler', 'extraexample', 'audioscript'] as $key) { $section[$key] = $data->{$key . '_' . $i}; }
            foreach ($section['cards'] as $j => &$card) {
                foreach (['term', 'meaning', 'example'] as $key) { $card[$key] = $data->{'card_' . $i . '_' . $j . '_' . $key}; }
            }
            unset($card);
        }
        unset($section);
        $current->content = json_encode(\mod_aienglishlesson\local\lesson::validate(json_encode($content)));
        $current->timemodified = max(time(), $current->timemodified + 1);
        $current->audiostatus = 'none';
        $DB->update_record('aiel_versions', $current);
        get_file_storage()->delete_area_files($context->id, 'mod_aienglishlesson', 'audio', $versionid);
    } finally { $lock->release(); }
    redirect($back);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editdraft', 'aienglishlesson'));
echo $OUTPUT->notification(get_string('exactanswerhelp', 'aienglishlesson'), 'info');
$form->display();
echo $OUTPUT->footer();
