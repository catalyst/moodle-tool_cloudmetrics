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
 * @package    tool_cloudmetrics
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activeusers'] = 'Active users';
$string['activeusers_desc'] = 'Users that have been active in the recent past.';
$string['activeusers_frequency'] = 'Active users frequency';
$string['activeusers_frequency_desc'] = 'Frequency of taking the active users metric.';
$string['activeusers_time_window'] = 'Active user time window';
$string['activeusers_time_window_desc'] = 'Metric will include users who have been active in this time period.';
$string['backfill_not_supported'] = 'Metric \'{$a}\' does not support backfilling';
$string['backfillable'] = 'Backfillable';
$string['backfillcomplete'] = 'Backfilling data for {$a} complete.';
$string['backfilldata'] = 'Backfill data';
$string['backfillgenerating'] = 'Generating metric items for {$a}.';
$string['backfillsaving'] = 'Saving metric items for {$a}.';
$string['builtin_metrics_settings'] = 'Built in metric settings';
$string['change_frequency'] = 'Change frequency';
$string['checkcollectorcheck'] = 'Cloudmetrics collector status';
$string['clientnotfound'] = 'Client not found';
$string['collect_metrics_task'] = 'Collect Metrics';
$string['collector_failed'] = 'Collector \'{$a->name}\' failed {$a->time}';
$string['collector_frequency'] = 'Data collected every {$a}.';
$string['collector_never'] = 'Collector \'{$a}\' has never executed';
$string['collector_passed'] = 'Collector \'{$a->name}\' succeeded since {$a->time}';
$string['colour'] = 'Colour';
$string['currenttaskcount'] = 'Current task count';
$string['currenttaskcount_desc'] = 'Number of currently active tasks, both scheduled and adhoc.';
$string['dailyusers'] = 'Daily users';
$string['dailyusers_desc'] = 'Unique users over a day (fixed frequency).';
$string['data_empty'] = 'Your database for this metric is empty.';
$string['data_in_db'] = 'Your database contains data from {$a->dbstart} to {$a->dbend}.';
$string['data_period'] = 'Current information shows data can be retrieved from {$a->startdate} to {$a->enddate}.';
$string['different_freq'] = 'Caution - Current frequency is different from the last backfill. New data will complete currently present but no data will be added between {$a->backfilledfrom} and {$a->backfilledto}.';
$string['enable_disable_collectors'] = 'Enable or disable collectors.';
$string['enable_disable_metrics'] = 'Enable or disable metrics.';
$string['estimatedtaskload'] = 'Estimated task load (m)';
$string['estimatedtaskload_desc'] = 'Estimated task load, in minutes, within a defined time period';
$string['estimatedtaskload_s'] = 'Estimated task load (s)';
$string['eventcollectorplugindisabled'] = 'Collector plugin disabled';
$string['eventcollectorpluginenabled'] = 'Collector plugin enabled';
$string['fifteen_minutes'] = '15 minutes';
$string['five_minutes'] = '5 minutes';
$string['four_month'] = '4 months';
$string['frequency'] = 'Frequency';
$string['manage_collectors'] = 'Manage Collectors';
$string['manage_metrics'] = 'Manage Metrics';
$string['managelink'] = 'Manage collectors';
$string['metric_enabled'] = 'Metric \'{$a}\' enabled';
$string['metric_not_enabled'] = 'Metric \'{$a}\' not enabled';
$string['metric_not_found'] = 'Metric \'{$a}\' not found';
$string['newusers'] = 'New users';
$string['newusers_desc'] = 'Users who have signed up recently.';
$string['newusers_frequency'] = 'New users frequency';
$string['newusers_frequency_desc'] = 'Frequency of taking the new users metric.';
$string['newusers_time_window'] = 'New user time window';
$string['newusers_time_window_desc'] = 'Metric will include users who first started in this time period.';
$string['no_collectors'] = 'No active collectors';
$string['no_support'] = 'Not supported';
$string['eight_hours'] = '8 hours';
$string['four_hours'] = '4 hours';
$string['two_hours'] = '2 hours';
$string['one_day'] = '1 day';
$string['one_fortnight'] = '1 fortnight';
$string['one_hour'] = '1 hour';
$string['one_minute'] = '1 minute';
$string['one_month'] = '1 month';
$string['one_week'] = '1 week';
$string['onlineusers'] = 'Online users';
$string['onlineusers_desc'] = 'Users that are currently online.';
$string['onlineusers_frequency'] = 'Online users frequency';
$string['onlineusers_frequency_desc'] = 'Frequency of taking the online users metric.';
$string['onlineusers_time_window'] = 'Online user time window';
$string['onlineusers_time_window_desc'] = 'Metric will include users who were active in this time period.';
$string['period_select'] = 'Select period to retrieve data from: ';
$string['pluginname'] = 'Cloudmetrics';
$string['privacy:metadata'] = 'No personal information is stored';
$string['return_to_backfill'] = 'Backfill {$a} period';
$string['return_to_chart'] = 'Return to {$a} chart';
$string['same_freq'] = 'Backfilling to a further date in the past will complete already present data.';
$string['six_month'] = '6 months';
$string['subplugintype_cltr'] = 'Collector for a cloud metric service';
$string['subplugintype_cltr_plural'] = 'Collectors for cloud metric services';
$string['task_activity'] = 'Task activity';
$string['task_name'] = 'Task name';
$string['taskload_report'] = 'Task load report';
$string['thirty_minutes'] = '30 minutes';
$string['three_hour'] = '3 hours';
$string['twelve_hour'] = '12 hours';
$string['twelve_month'] = '12 months';
$string['two_month'] = '2 months';
$string['two_week'] = '2 week';
$string['two_year'] = '2 years';
$string['user_activity'] = 'User activity';
$string['view_chart'] = 'View {$a} chart';
$string['yearlyactiveusers'] = 'Yearly active users';
$string['yearlyactiveusers_desc'] = 'Users that have been active within the past 365 days from today.';
