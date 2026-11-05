<?php
namespace mod_aienglishlesson\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class edit extends \moodleform {
    public function definition() {
        $f = $this->_form;
        foreach (['id', 'version', 'modified'] as $key) {
            $f->addElement('hidden', $key, $this->_customdata[$key]); $f->setType($key, PARAM_INT);
        }
        $f->addElement('text', 'title', get_string('name'), ['size' => 70]); $f->setType('title', PARAM_TEXT);
        $f->addRule('title', null, 'required');
        $f->addElement('select', 'focus', get_string('focus', 'aienglishlesson'),
            ['reading' => 'Reading', 'listening' => 'Listening', 'speaking' => 'Speaking', 'writing' => 'Writing']);
        $f->setDefault('focus', $this->_customdata['content']['focus']);
        foreach ($this->_customdata['content']['sections'] as $i => $section) {
            $f->addElement('header', 'section' . $i, $section['title']);
            $f->addElement('select', 'origin_' . $i, get_string('origin', 'aienglishlesson'),
                ['source' => 'Source', 'generated' => 'Generated', 'mixed' => 'Mixed']);
            $f->setDefault('origin_' . $i, $section['origin']);
            foreach (['title', 'body', 'example', 'practice', 'answer', 'feedback', 'hint', 'simpler', 'extraexample', 'audioscript'] as $key) {
                $f->addElement('textarea', $key . '_' . $i, get_string($key, 'aienglishlesson'), ['rows' => $key === 'body' ? 6 : 3, 'cols' => 80]);
                $f->setType($key . '_' . $i, PARAM_TEXT);
                $f->setDefault($key . '_' . $i, $section[$key] ?? '');
            }
            foreach ($section['cards'] as $j => $card) {
                foreach (['term', 'meaning', 'example'] as $key) {
                    $name = 'card_' . $i . '_' . $j . '_' . $key;
                    $f->addElement('text', $name, get_string($key, 'aienglishlesson'), ['size' => 70]);
                    $f->setType($name, PARAM_TEXT); $f->setDefault($name, $card[$key]);
                }
            }
        }
        $f->setDefault('title', $this->_customdata['content']['title']);
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
