<?php
// Administrator view of Gemini usage recorded by this plugin, in the viewer's own time zone.
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');
admin_externalpage_setup('managemodules');
$context = context_system::instance();
require_capability('moodle/site:config', $context);
$download = optional_param('download', '', PARAM_ALPHA);
$url = new moodle_url('/mod/aienglishlesson/usage.php');
$now = time();
$pacific = new DateTimeZone('America/Los_Angeles');
$daystart = (new DateTime('@' . $now))->setTimezone($pacific)->setTime(0, 0)->getTimestamp();
$nextreset = (new DateTime('@' . $daystart))->setTimezone($pacific)->modify('+1 day')->getTimestamp();
$retention = \mod_aienglishlesson\local\gemini::USAGE_RETENTION;

if ($download === 'csv') {
    $rows = $DB->get_records_select('aiel_usage', 'timecreated > ?', [$now - $retention], 'timecreated ASC');
    $data = [];
    foreach ($rows as $row) {
        $data[] = [userdate($row->timecreated, '%Y-%m-%d %H:%M:%S'), (string)$row->purpose, (string)$row->model,
            (string)$row->status, (int)$row->inputtokens, (int)$row->outputtokens, (string)$row->lessonid];
    }
    csv_export_writer::download_array('aienglishlesson-usage-' . userdate($now, '%Y%m%d'),
        ['time', 'purpose', 'model', 'http_status', 'input_tokens', 'output_tokens', 'activity_id'], $data);
    exit;
}

$config = get_config('aienglishlesson');
$limits = ['rpm' => (int)($config->googlerpm ?? 15) ?: 15, 'tpm' => (int)($config->googletpm ?? 250000) ?: 250000,
    'rpd' => (int)($config->googlerpd ?? 500) ?: 500, 'tts' => (int)($config->googletts ?? 10) ?: 10];
$cap = \mod_aienglishlesson\local\gemini::daily_limit();
$week = $DB->get_records_select('aiel_usage', 'timecreated > ?', [$daystart - 6 * DAYSECS], 'timecreated ASC');
$today = array_filter($week, static function($r) use ($daystart) { return $r->timecreated >= $daystart; });
$rolling = $DB->count_records_select('aiel_usage', 'timecreated > ?', [$now - DAYSECS]);
$speechtoday = count(array_filter($today, static function($r) { return $r->purpose === 'speech'; }));
$failedtoday = count(array_filter($today, static function($r) { return $r->status !== null && (int)$r->status !== 200; }));
$minutes = [];
foreach ($week as $row) {
    if ($row->timecreated < $now - HOURSECS || $row->purpose === 'speech') { continue; }
    $key = intdiv((int)$row->timecreated, 60);
    $minutes[$key] = $minutes[$key] ?? ['requests' => 0, 'tokens' => 0];
    $minutes[$key]['requests']++;
    $minutes[$key]['tokens'] += (int)$row->inputtokens;
}
$peakrequests = $minutes ? max(array_column($minutes, 'requests')) : 0;
$peaktokens = $minutes ? max(array_column($minutes, 'tokens')) : 0;

$PAGE->set_url($url);
$PAGE->set_title(get_string('usagepage', 'aienglishlesson'));
$PAGE->set_heading(get_string('usagepage', 'aienglishlesson'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('usagepage', 'aienglishlesson'));
echo html_writer::tag('p', get_string('usageintro', 'aienglishlesson'), ['class' => 'aiel-note']);
echo html_writer::div(get_string('usagereset', 'aienglishlesson', (object)[
    'time' => userdate($nextreset, get_string('strftimetime', 'langconfig')),
    'date' => userdate($nextreset, get_string('strftimedaydate', 'langconfig')),
    'zone' => core_date::get_user_timezone()]), 'aiel-callout aiel-callout--focus');

$tile = static function(string $label, int $used, int $limit, string $note = '') {
    $percent = $limit > 0 ? min(100, (int)round(100 * $used / $limit)) : 0;
    $band = $percent >= 90 ? 'low' : ($percent >= 60 ? 'mid' : 'high');
    return html_writer::div(html_writer::tag('h3', s($label)) .
        html_writer::tag('p', html_writer::tag('strong', number_format($used)) .
            html_writer::tag('span', ' / ' . number_format($limit), ['class' => 'aiel-score-of']), ['class' => 'aiel-score-value']) .
        html_writer::div(html_writer::span('', '', ['style' => 'width: ' . $percent . '%']), 'aiel-meter') .
        ($note ? html_writer::tag('small', s($note)) : ''), 'aiel-grade-card aiel-band-' . $band);
};
echo html_writer::div(
    $tile(get_string('usagegoogleday', 'aienglishlesson'), count($today), $limits['rpd'], get_string('usagegoogledaynote', 'aienglishlesson')) .
    $tile(get_string('usagepluginday', 'aienglishlesson'), $rolling, $cap, get_string('usageplugindaynote', 'aienglishlesson')) .
    $tile(get_string('usagepeakrpm', 'aienglishlesson'), $peakrequests, $limits['rpm'], get_string('usagelasthour', 'aienglishlesson')) .
    $tile(get_string('usagepeaktpm', 'aienglishlesson'), $peaktokens, $limits['tpm'], get_string('usagelasthour', 'aienglishlesson')) .
    $tile(get_string('usagespeech', 'aienglishlesson'), $speechtoday, $limits['tts'], get_string('usagegoogledaynote', 'aienglishlesson')),
    'aiel-grades aiel-usage-tiles');
if ($failedtoday) {
    echo $OUTPUT->notification(get_string('usagefailed', 'aienglishlesson', $failedtoday), 'warning');
}

// Last seven Google days, newest first, split by purpose.
$purposes = ['generate', 'assess', 'speech', 'test', 'diagnose', 'other'];
$table = new html_table();
$table->head = [get_string('usageday', 'aienglishlesson')];
foreach ($purposes as $purpose) { $table->head[] = get_string('usagepurpose_' . $purpose, 'aienglishlesson'); }
$table->head[] = get_string('usagetotal', 'aienglishlesson');
$table->head[] = get_string('usagetokens', 'aienglishlesson');
$table->attributes['class'] = 'generaltable aiel-usage-table';
for ($d = 0; $d < 7; $d++) {
    $start = (new DateTime('@' . $daystart))->setTimezone($pacific)->modify('-' . $d . ' day')->getTimestamp();
    $end = (new DateTime('@' . $start))->setTimezone($pacific)->modify('+1 day')->getTimestamp();
    $rows = array_filter($week, static function($r) use ($start, $end) { return $r->timecreated >= $start && $r->timecreated < $end; });
    $cells = [html_writer::tag('strong', userdate($start, get_string('strftimedaydate', 'langconfig')))];
    foreach ($purposes as $purpose) {
        $cells[] = count(array_filter($rows, static function($r) use ($purpose) {
            return ($r->purpose ?: 'other') === $purpose;
        })) ?: '—';
    }
    $cells[] = html_writer::tag('strong', count($rows));
    $cells[] = number_format(array_sum(array_map(static function($r) {
        return (int)$r->inputtokens + (int)$r->outputtokens;
    }, $rows)));
    $table->data[] = $cells;
}
echo html_writer::tag('h3', get_string('usageweek', 'aienglishlesson'));
echo html_writer::table($table);
echo html_writer::tag('p', get_string('usagelimitsnote', 'aienglishlesson'), ['class' => 'aiel-note']);
echo html_writer::div(
    html_writer::link(new moodle_url($url, ['download' => 'csv']), get_string('usagecsv', 'aienglishlesson'), ['class' => 'btn btn-primary']) .
    html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'modsettingaienglishlesson']),
        get_string('usagesettings', 'aienglishlesson'), ['class' => 'btn btn-secondary']), 'aiel-toolbar');
echo $OUTPUT->footer();
