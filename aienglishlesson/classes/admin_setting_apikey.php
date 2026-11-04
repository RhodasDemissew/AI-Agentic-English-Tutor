<?php
namespace mod_aienglishlesson;
defined('MOODLE_INTERNAL') || die();
/** Write-only credential setting: saved secrets are never returned in HTML. */
class admin_setting_apikey extends \admin_setting_configpasswordunmask {
    public function output_html($data, $query = '') {
        return parent::output_html('', $query);
    }
    public function write_setting($data) {
        $data = trim($data);
        if ($data === '' && $this->get_setting() !== null) { return ''; }
        if ($data === '-') { $data = ''; }
        return parent::write_setting($data);
    }
}
