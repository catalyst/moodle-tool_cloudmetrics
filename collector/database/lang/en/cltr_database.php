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
 * Language strings
 *
 * @package    cltr_database
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['aggregated'] = 'Displayed time frequency differs from the data collected. Each data point is aggregated over time periods of {$a} and also displays the min and max for that period.';
$string['collector_not_supported'] = 'Collector \'{$a}\' does not support backfilled data collection.';
$string['different_frequency'] = 'The frequency of the data points has been altered to {$a->to} from {$a->from} to limit the number of displayed points.';
$string['displaying_records'] = 'Displaying {$a->count} data points at intervals of {$a->freq}.';
$string['label'] = 'Internal';
$string['maxrecords'] = 'There are more than {$a} data points for this graph, it is currently only displaying the first {$a} records. Please change either the time scale or the time resolution';
$string['metric_auto_backfill'] = 'Auto backfill';
$string['metric_auto_backfill_desc'] = 'Enable or disable auto backfill function.';
$string['metric_backfill'] = 'Cloudmetrics Backfill';
$string['metric_display'] = 'Cloudmetrics Charts';
$string['metric_expiry'] = 'Time to keep data';
$string['metric_expiry_desc'] = 'Length of time to keep data before deleting. If set to 0 data will never expire.';
$string['metrics_autobackfill_task'] = 'Auto back-fill task';
$string['metrics_backfill_queued'] = 'Backfill adhoc task queued for {$a}.';
$string['metrics_cleanup_task'] = 'Cleanup metrics task';
$string['multiplemetrics'] = 'Multiple metrics displayed';
$string['no_metrics_enabled'] = 'No metrics enabled';
$string['norecords'] = 'No data collected for the selected time period.';
$string['pluginname'] = 'Cloudmetrics database collector';
$string['privacy:metadata'] = 'No personal information is stored';
$string['select_graph_freq'] = 'Select graph frequency.';
$string['select_graph_period'] = 'Select graph period.';
$string['select_metric_for_display'] = 'Select metric for display.';
$string['select_group'] = 'Display metrics by group.';
$string['strftimedatetime'] = '%d %h %Y, %H:%M';
$string['strftimemonth'] = '%h %Y';
