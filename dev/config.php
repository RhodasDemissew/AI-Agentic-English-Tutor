<?php
// Local Docker development only. Never copy this configuration to production.
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'db';
$CFG->dbname = 'moodle';
$CFG->dbuser = 'moodle';
$CFG->dbpass = 'local-moodle-only';
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbcollation' => 'utf8mb4_unicode_ci'];
$CFG->wwwroot = 'http://localhost:8088';
$CFG->dataroot = '/var/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02777;
$CFG->passwordpolicy = 1;
$CFG->debug = 32767;
$CFG->debugdisplay = 0;
require_once(__DIR__ . '/lib/setup.php');
