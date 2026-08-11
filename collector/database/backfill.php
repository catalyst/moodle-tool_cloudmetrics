<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Menu displaying available retrievable metric data and form.
 *
 * @package   cltr_database
 * @author    Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_cloudmetrics\metric\manager;
use cltr_database\collector;
use cltr_database\form\metric_backfill_form;

admin_externalpage_setup('cltr_database_backfill');

$ctx = context_system::instance();

$url = new moodle_url('/admin/tool/cloudmetrics/collector/database/backfill.php');

$PAGE->set_context($ctx);
$PAGE->set_url($url);

$metricname = optional_param('metric', 'onlineusers', PARAM_ALPHANUMEXT);
$tochart = new moodle_url('/admin/tool/cloudmetrics/collector/database/chart.php', [$metricname => 1]);

$metrics = manager::get_metrics(true);
$collector = new collector();

// Error management if metric is not enabled.
if (!isset($metrics[$metricname])) {
    throw new moodle_exception('metric_not_enabled', 'tool_cloudmetrics', '', $metricname);
}
if (!$metrics[$metricname]->is_backfillable()) {
    throw new moodle_exception('backfill_not_supported', 'tool_cloudmetrics', '', $metricname);
}
if (!$collector->supports_backfillable_metrics()) {
    throw new moodle_exception('collector_not_supported', 'cltr_database', '', get_class($collector));
}

$periods = [
    HOURSECS      => get_string('one_hour', 'tool_cloudmetrics'),
    DAYSECS       => get_string('one_day', 'tool_cloudmetrics'),
    WEEKSECS      => get_string('one_week', 'tool_cloudmetrics'),
    WEEKSECS * 2  => get_string('two_week', 'tool_cloudmetrics'),
    DAYSECS * 30  => get_string('one_month', 'tool_cloudmetrics'),
    DAYSECS * 61  => get_string('two_month', 'tool_cloudmetrics'),
    DAYSECS * 122 => get_string('four_month', 'tool_cloudmetrics'),
    DAYSECS * 183 => get_string('six_month', 'tool_cloudmetrics'),
    YEARSECS      => get_string('twelve_month', 'tool_cloudmetrics'),
    YEARSECS * 2  => get_string('two_year', 'tool_cloudmetrics'),
];

for ($i = 3; $i <= 10; $i++) {
    $periods[YEARSECS * $i] = get_string('n_years', 'tool_cloudmetrics', $i);
}

if ($collector->is_readable()) {
    // Get some information about existing data.
    $range = $collector->get_metric_range($metricname);
    $freqretrieved = $collector->get_last_backfilled_frequency($metricname);
    if (!empty($range)) {
        $backfilledfrom = userdate($range['mintime'], get_string('strftimedatetime', 'cltr_database'), $CFG->timezone);
        $backfilledto = userdate($range['maxtime'], get_string('strftimedatetime', 'cltr_database'), $CFG->timezone);
        $backfilledinterval = (int)$freqretrieved;

        // Add a comment about different frequencies.
        if ($backfilledinterval !== $metrics[$metricname]->get_frequency()) {
            $isdifferentfreq = true;
            $context['cautiondata'] = get_string(
                'different_freq',
                'tool_cloudmetrics',
                ['backfilledfrom' => $backfilledfrom, 'backfilledto' => $backfilledto]
            );
        } else {
            $isdifferentfreq = false;
        }

        // Add a comment about available data.
        $context['dataindb'] = get_string(
            'data_in_db',
            'tool_cloudmetrics',
            ['dbstart' => $backfilledfrom, 'dbend' => $backfilledto]
        );
    } else {
        $emptydb = get_string('data_empty', 'tool_cloudmetrics');
    }
}

// Gets available data to backfill.
$daterange = $metrics[$metricname]->get_range_log_available();
$startdate = userdate($daterange->min, get_string('strftimedatetime', 'cltr_database'), $CFG->timezone);
$enddate = userdate($daterange->max, get_string('strftimedatetime', 'cltr_database'), $CFG->timezone);
$mform = new metric_backfill_form(null, [$daterange, $periods, $metricname]);
$context['form'] = $mform->render();

$context['dataperiod'] = get_string(
    'data_period',
    'tool_cloudmetrics',
    ['startdate' => $startdate ?? 0, 'enddate' => $enddate ?? 0]
);
$context['emptydb'] = $emptydb ?? false;
$context['linktochart'] = $tochart;
$context['metriclabel'] = $metrics[$metricname]->get_label();
$context['isdifferentfreq'] = $isdifferentfreq ?? null;

$renderer = $PAGE->get_renderer('tool_cloudmetrics');

if ($fromform = $mform->get_data()) {
    $backfilltask = new \tool_cloudmetrics\task\autobackfill_metrics_task();
    $backfilltask->set_custom_data([
        'metric' => $metricname,
        'period' => $fromform->periodretrieval,
        'collector' => 'database',
    ]);
    \core\task\manager::queue_adhoc_task($backfilltask, true);

    \core\notification::info(get_string('metrics_backfill_queued', 'cltr_database', $metricname));
    redirect($tochart);
}

echo $OUTPUT->header();
echo $renderer->render_backfill_page($context);
echo $OUTPUT->footer();
