<?php
namespace mod_aienglishlesson\local;
defined('MOODLE_INTERNAL') || die();
class provider_error extends \RuntimeException {
    public bool $transient;
    public function __construct(string $safe, bool $transient = false) {
        parent::__construct($safe);
        $this->transient = $transient;
    }
}
