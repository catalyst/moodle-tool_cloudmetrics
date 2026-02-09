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
 * Settings
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cloudmetrics\admin_setting_manage_collectors;
use tool_cloudmetrics\admin_setting_manage_metrics;
use tool_cloudmetrics\metric\manager;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category('tool_cloudmetrics_reports', get_string('pluginname', 'tool_cloudmetrics')));

    $settings = new admin_settingpage(
        'tool_cloudmetrics',
        get_string('generalsettings', 'admin')
    );

    $ADMIN->add('tool_cloudmetrics_reports', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'tool_cloudmetrics/collectors',
            get_string('manage_collectors', 'tool_cloudmetrics'),
            get_string('enable_disable_collectors', 'tool_cloudmetrics')
        ));

        $settings->add(new admin_setting_manage_collectors());

        $settings->add(new admin_setting_heading(
            'tool_cloudmetrics/metrics',
            get_string('manage_metrics', 'tool_cloudmetrics'),
            get_string('enable_disable_metrics', 'tool_cloudmetrics')
        ));

        $settings->add(new admin_setting_manage_metrics());

        $settings->add(new admin_setting_heading(
            'tool_cloudmetrics/builtin_metrics',
            get_string('builtin_metrics_settings', 'tool_cloudmetrics'),
            ''
        ));

        $settings->add(new admin_setting_configduration(
            'tool_cloudmetrics/activeusers_time_window',
            get_string('activeusers_time_window', 'tool_cloudmetrics'),
            get_string('activeusers_time_window_desc', 'tool_cloudmetrics'),
            30 * DAYSECS,
            DAYSECS
        ));

        $settings->add(new admin_setting_configduration(
            'tool_cloudmetrics/newusers_time_window',
            get_string('newusers_time_window', 'tool_cloudmetrics'),
            get_string('newusers_time_window_desc', 'tool_cloudmetrics'),
            30 * DAYSECS,
            DAYSECS
        ));

        $settings->add(new admin_setting_configduration(
            'tool_cloudmetrics/onlineusers_time_window',
            get_string('onlineusers_time_window', 'tool_cloudmetrics'),
            get_string('onlineusers_time_window_desc', 'tool_cloudmetrics'),
            5 * MINSECS,
            MINSECS
        ));
    }

    foreach (core_plugin_manager::instance()->get_plugins_of_type('cltr') as $plugin) {
        $plugin->load_settings($ADMIN, 'tool_cloudmetrics_reports', $hassiteconfig);
    }
}
