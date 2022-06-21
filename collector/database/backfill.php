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

require_once(__DIR__.'/../../../../../config.php');
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
$tochart = new moodle_url('/admin/tool/cloudmetrics/collector/database/chart.php', ['metric' => $metricname]);
$toreset = new moodle_url('/admin/tool/cloudmetrics/collector/database/reset.php', ['metric' => $metricname]);

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

// Gets already saved data range and interval.
[$mintmptmp, $maxtmpstmp, $freqretrieved] = $metrics[$metricname]->get_range_retrieved();
if ($mintmptmp != -1) {
    $backfilledfrom = userdate($mintmptmp, get_string('strftimedatetime', 'cltr_database'), $CFG->timezone);
    $backfilledto = userdate($maxtmpstmp, get_string('strftimedatetime', 'cltr_database'), $CFG->timezone);
    $backfilledinterval = (int)$freqretrieved;
    if ($backfilledinterval !== $metrics[$metricname]->get_frequency()) {
        $isdifferentfreq = true;
        $context['cautiondata'] = get_string('different_freq', 'tool_cloudmetrics',
            ['backfilledfrom' => $backfilledfrom, 'backfilledto' => $backfilledto]);;
    } else {
        $isdifferentfreq = false;
    }
    $context['dataindb'] = get_string('data_in_db', 'tool_cloudmetrics',
            ['dbstart' => $backfilledfrom, 'dbend' => $backfilledto]);
} else {
    $emptydb = get_string('data_empty', 'tool_cloudmetrics');
}
// Gets available data to backfill.
$daterange = $metrics[$metricname]->get_range_log_available();
$startdate = userdate($daterange->min, get_string('strftimedatetime', 'cltr_database'), $CFG->timezone);
$enddate = userdate($daterange->max, get_string('strftimedatetime', 'cltr_database'), $CFG->timezone);
$mform = new metric_backfill_form(null, [$daterange, $periods, $metricname]);
$context['form'] = $mform->render();
if ($fromform = $mform->get_data()) {
    $periodretrieval = $fromform->periodretrieval;
    $metricitems = $metrics[$metricname]->generate_metric_items($periodretrieval, $mintmptmp);
    $collector->record_saved_metrics($metrics[$metricname], $metricitems);
}

$context['dataperiod'] = get_string('data_period', 'tool_cloudmetrics',
            ['startdate' => $startdate ?? 0, 'enddate' => $enddate ?? 0]);
$context['emptydb'] = $emptydb ?? false;
$context['linktochart'] = $tochart;
$context['linktoreset'] = $toreset;
$context['metriclabel'] = $metrics[$metricname]->get_label();
$context['isdifferentfreq'] = $isdifferentfreq ?? null;

$renderer = $PAGE->get_renderer('tool_cloudmetrics');

echo $OUTPUT->header();
echo $renderer->render_backfill_page($context);
echo $OUTPUT->footer();
