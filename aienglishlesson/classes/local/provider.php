<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
interface provider {
    public function generate(\stdClass $activity, \stored_file $pdf): array;
    public function test(): void;
}
