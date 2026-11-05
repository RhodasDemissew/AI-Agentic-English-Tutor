<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/lib.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local only'); }
\core\session\manager::set_user(get_admin());
$fixture = json_decode(file_get_contents('/var/moodledata/aiel-test.json'));
$cm = get_coursemodule_from_id('aienglishlesson', $fixture->cmid);
foreach ([$fixture->published, $fixture->draft] as $id) {
    $version = $DB->get_record('aiel_versions', ['id' => $id]);
    $DB->set_field('aiel_versions', 'status', 'draft', ['id' => $id]);
    \mod_aienglishlesson\local\audio::render($id);
    $DB->set_field('aiel_versions', 'status', $version->status, ['id' => $id]);
    if ($DB->get_field('aiel_versions', 'audiostatus', ['id' => $id]) !== 'ready') { throw new Exception('Audio rendering failed'); }
    echo 'PASS: Local audio generated for version ' . $id . "\n";
}
$peer = $DB->get_record('user', ['username' => 'aielpeer']);
if (!$peer) {
    $id = user_create_user((object)['username' => 'aielpeer', 'password' => 'LocalPeer-42!', 'firstname' => 'Fictional',
        'lastname' => 'Peer', 'email' => 'peer@example.invalid', 'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id]);
    $peer = $DB->get_record('user', ['id' => $id]);
}
$manual = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $cm->course, 'enrol' => 'manual'], '*', MUST_EXIST);
$manual->enrol_user($instance, $peer->id, $DB->get_field('role', 'id', ['shortname' => 'student']));
echo "PASS: Peer enrolled for recording-isolation tests\n";
