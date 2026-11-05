<?php
// Build a portable, fictional demonstration. Never calls a provider.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local development only'); }
\core\session\manager::set_user(get_admin());
$course = create_course((object)['fullname' => 'AI English Lessons demonstration', 'shortname' => 'AIEL-DEMO-' . time(),
    'category' => 1, 'enablecompletion' => 1]);
$module = add_moduleinfo((object)['modulename' => 'aienglishlesson',
    'module' => $DB->get_field('modules', 'id', ['name' => 'aienglishlesson']), 'name' => 'At the market - sample lesson',
    'intro' => 'Prepared demonstration content, not live AI output.', 'introformat' => FORMAT_HTML,
    'gradeband' => 'adult', 'proficiency' => 'A2', 'topic' => 'Polite shopping requests', 'pagerange' => '',
    'duration' => 30, 'instructions' => '', 'section' => 0, 'visible' => 1, 'completion' => 2,
    'completionview' => 1, 'completiongradeitemnumber' => '', 'completionexpected' => 0], $course);
$context = context_module::instance($module->coursemodule);
$fs = get_file_storage();
$fs->create_file_from_pathname((object)['contextid' => $context->id, 'component' => 'mod_aienglishlesson',
    'filearea' => 'source', 'itemid' => 0, 'filepath' => '/', 'filename' => 'sample.pdf', 'mimetype' => 'application/pdf'], '/opt/aiel-dev/sample.pdf');
$passage = 'Ana visits the market on Saturday. She asks, May I have two apples, please? The seller replies, Of course. Here you are. Ana pays and says, Thank you.';
$parts = [
    ['objectives', 'What you will learn', 'Read and listen to a short shopping story. Make a polite request aloud and write a short shopping dialogue.', '', ''],
    ['warmup', 'Before you begin', 'Think about a market or shop you know. What do people buy there?', 'Name two things you buy at a market.', 'Apples and bread are possible answers.'],
    ['vocabulary', 'Useful words', 'Explore the words below. Say each word and read its example.', 'Write one sentence using please.', 'May I have some bread, please?'],
    ['explanation', 'Making a polite request', 'Use May I have + item + please? Say thank you after receiving the item.', 'Change Give me an apple into a polite request.', 'May I have an apple, please?'],
    ['workedexample', 'A short conversation', 'Customer: May I have two apples, please?\nSeller: Of course. Here you are.\nCustomer: Thank you.', 'Replace apples with oranges in the request.', 'May I have two oranges, please?'],
    ['reading', 'Read about Ana', $passage, 'What does Ana buy? Which day does she visit the market?', 'Ana buys two apples. She visits on Saturday.'],
    ['listening', 'Listen for details', 'Listen before opening the transcript. Focus on the item and the number.', 'What does Ana ask for? What does she say after paying?', 'She asks for two apples and says thank you.'],
    ['speaking', 'Practise your request', 'Listen to the model, rehearse, then record yourself asking politely for three oranges. You can upload an audio file instead.', 'Record your request. Add a short note about a word you practised.', 'A model request is: May I have three oranges, please? Check that the item and number are clear and that you say please.'],
    ['writing', 'Write your own dialogue', 'Write a three-line conversation between a customer and a seller. Choose a different food.', 'Include a polite request, a reply, and thanks.', 'Customer: May I have some bread, please? Seller: Of course. Here you are. Customer: Thank you. Check all three parts.'],
    ['recap', 'What can you do now?', 'You practised reading, listening, speaking and writing about shopping.', 'Write the polite request pattern from memory.', 'May I have + item + please?'],
];
$content = ['title' => 'At the market', 'focus' => 'speaking', 'readable' => true, 'sections' => []];
foreach ($parts as [$kind, $title, $body, $practice, $answer]) {
    $content['sections'][] = ['kind' => $kind, 'title' => $title, 'body' => str_replace('\\n', "\n", $body),
        'example' => 'May I have a banana, please?', 'practice' => $practice, 'answer' => $answer,
        'feedback' => $answer, 'hint' => $practice ? 'Look for the item, number and polite words.' : '',
        'simpler' => 'Ask for something politely. Say please and thank you.', 'extraexample' => 'May I have some rice, please?',
        'audioscript' => $kind === 'listening' ? $passage : ($kind === 'speaking' ? 'May I have three oranges, please? Of course. Here you are. Thank you.' : ''),
        'origin' => 'generated', 'cards' => $kind === 'vocabulary' ? [
            ['term' => 'please', 'meaning' => 'A word used to make a request polite.', 'example' => 'Two apples, please.'],
            ['term' => 'seller', 'meaning' => 'A person who sells things.', 'example' => 'The seller gives Ana two apples.']
        ] : []];
}
\mod_aienglishlesson\local\lesson::validate(json_encode($content));
$versionid = $DB->insert_record('aiel_versions', (object)['lessonid' => $module->instance, 'status' => 'draft',
    'content' => json_encode($content), 'timecreated' => time(), 'timemodified' => time()]);
\mod_aienglishlesson\local\audio::render($versionid);
$version = $DB->get_record('aiel_versions', ['id' => $versionid]);
if (!\mod_aienglishlesson\local\audio::ready($version, $context)) { throw new Exception('Demo audio missing'); }
$DB->set_field('aiel_versions', 'status', 'published', ['id' => $versionid]);
$manual = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
$manual->enrol_user($instance, $DB->get_field('user', 'id', ['username' => 'aielstudent']), $DB->get_field('role', 'id', ['shortname' => 'student']));
$bc = new backup_controller(backup::TYPE_1ACTIVITY, $module->coursemodule, backup::FORMAT_MOODLE,
    backup::INTERACTIVE_NO, backup::MODE_GENERAL, get_admin()->id);
$bc->get_plan()->get_setting('users')->set_value(false);
$bc->execute_plan();
$file = $bc->get_results()['backup_destination'];
$file->copy_content_to('/var/moodledata/aiel-demo.mbz');
// Restore this exact archive to verify a portable backup with no learner data.
$folder = restore_controller::get_tempdir_name($course->id, get_admin()->id);
$path = $CFG->tempdir . '/backup/' . $folder;
get_file_packer('application/vnd.moodle.backup')->extract_to_pathname($file, $path);
$rc = new restore_controller($folder, $course->id, backup::INTERACTIVE_NO, backup::MODE_GENERAL, get_admin()->id, backup::TARGET_CURRENT_ADDING);
if (!$rc->execute_precheck()) { throw new Exception('Demo restore precheck failed'); }
$rc->execute_plan();
$restored = $DB->get_record_sql('SELECT * FROM {aienglishlesson} ORDER BY id DESC', [], IGNORE_MULTIPLE);
$restoredcm = get_coursemodule_from_instance('aienglishlesson', $restored->id);
$restoredcontext = context_module::instance($restoredcm->id);
if ($DB->count_records('aiel_responses', ['lessonid' => $restored->id]) ||
    count($fs->get_area_files($restoredcontext->id, 'mod_aienglishlesson', 'audio', false, 'id', false)) !== 2) {
    throw new Exception('Demo restore data mismatch');
}
$rc->destroy(); $bc->destroy();
course_delete_module($restoredcm->id);
echo "PASS: Portable demonstration restored with two audio files and no learner responses\n";
echo 'DEMO_URL=' . $CFG->wwwroot . '/mod/aienglishlesson/view.php?id=' . $module->coursemodule . "\n";
