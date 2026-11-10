<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
interface provider {
    public function generate(\stdClass $activity, array $files): array;
    public function generate_stage(\stdClass $activity, array $files, int $stage, array $partial): array;
    public function test(): void;
}
