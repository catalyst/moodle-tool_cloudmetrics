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
 * Upgrade script for databases.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cloudmetrics\metric\manager;

/**
 * Function to upgrade tool_cloudmetrics.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_tool_cloudmetrics_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2022051600) {
        // Reverse the logic of existing disabled settings.
        foreach (['cloudwatch', 'database'] as $collector) {
            $x = get_config('cltr_' . $collector, 'disabled');
            // Never used. Default is now disabled, so leave alone.
            if ($x === false) {
                continue;
            }
            set_config('enabled', 1 - (int) $x, 'cltr_' . $collector);
            unset_config('disabled', 'cltr_' . $collector);
        }
        upgrade_plugin_savepoint(true, 2022051600, 'tool', 'cloudmetrics');
    }

    // Upgrade script turn all metrics on.
    if ($oldversion < 2022081500) {
        $metrics = manager::get_metrics(false);

        foreach ($metrics as $metric) {
            if (!$metric->is_enabled()) {
                $metric->set_enabled(true);
            }
        }
        upgrade_plugin_savepoint(true, 2022081500, 'tool', 'cloudmetrics');
    }

    // Upgrade script turn off metric expiry.
    if ($oldversion < 2022081600) {
        if (get_config('metric_expiry', 'cltr_database') != 0) {
            set_config('metric_expiry', 0, 'cltr_database');
        }
        upgrade_plugin_savepoint(true, 2022081600, 'tool', 'cloudmetrics');
    }

    if ($oldversion < 2022082503) {
        $backfilltask = new \tool_cloudmetrics\task\autobackfill_metrics_task();
        \core\task\manager::queue_adhoc_task($backfilltask);
        upgrade_plugin_savepoint(true, 2022082503, 'tool', 'cloudmetrics');
    }
    return true;
}
