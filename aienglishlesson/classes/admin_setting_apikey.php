<?php
namespace mod_aienglishlesson;
defined('MOODLE_INTERNAL') || die();
/** Write-only credential setting: saved secrets are never returned in HTML. */
class admin_setting_apikey extends \admin_setting_configpasswordunmask {
    public function output_html($data, $query = '') {
        // A native input works even when a theme breaks Moodle's click-to-edit JS.
        // Never populate it with the saved credential, including on validation errors.
        $attributes = ['type' => 'password', 'id' => $this->get_id(),
            'name' => $this->get_full_name(), 'value' => '', 'size' => 40,
            'class' => 'form-control', 'autocomplete' => 'new-password', 'spellcheck' => 'false'];
        if ($this->is_readonly()) { $attributes['disabled'] = 'disabled'; }
        $element = \html_writer::empty_tag('input', $attributes);
        return format_admin_setting($this, $this->visiblename, $element, $this->description, true, '', null, $query);
    }
    public function write_setting($data) {
        $data = trim($data);
        if ($data === '' && $this->get_setting() !== null) { return ''; }
        if ($data === '-') { $data = ''; }
        return parent::write_setting($data);
    }
}
