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

$string['pluginname'] = 'Cloudmetrics database collector';
$string['label'] = 'Internal';

// Privacy.
$string['privacy:metadata'] = 'No personal information is stored';

// Settings.
$string['metric_expiry'] = 'Time to keep data';
$string['metric_expiry_desc'] = 'Length of time to keep data before deleting.';

// Chart display.
$string['metric_display'] = 'Cloudmetrics Charts';
$string['metric_backfill'] = 'Cloudmetrics Backfill';
$string['metric_reset'] = 'Cloudmetrics Reset';
$string['select_metric_for_display'] = 'Select metric for display.';
$string['select_graph_period'] = 'Select graph period.';

// Scheduled tasks.
$string['metrics_cleanup_task'] = 'Cleanup metrics task';

// Time format.
$string['strftimedatetime'] = '%d %h %Y, %H:%M';

// Error.
$string['collector_not_supported'] = 'Collector \'{$a}\' does not support backfilled data collection.';
