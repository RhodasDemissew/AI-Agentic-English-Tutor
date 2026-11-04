<?php
// Recover the interrupted local first installation after moving the source volume.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/adminlib.php');
if ($CFG->wwwroot !== 'http://localhost:8088') { die('Local only'); }
$admin = get_admin();
update_internal_user_password($admin, 'LocalDev-Only-42!');
$admin->email = 'admin@example.invalid'; $admin->firstname = 'Local'; $admin->lastname = 'Administrator';
user_update_user($admin, false, false);
unset_user_preference('auth_forcepasswordchange', $admin);
$DB->set_field('course', 'fullname', 'AI English Lessons Development', ['id' => SITEID]);
$DB->set_field('course', 'shortname', 'AIEL Dev', ['id' => SITEID]);
set_config('registerauth', '');
set_config('enablecompletion', 1);
set_config('rolesactive', 1);
\core\session\manager::set_user(get_admin());
admin_apply_default_settings(null, false);
set_config('siteidentifier', random_string(32));
echo "Local installation account finalized.\n";
