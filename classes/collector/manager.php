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

namespace tool_cloudmetrics\collector;

use tool_cloudmetrics\metric\metric_item;
use tool_cloudmetrics\plugininfo\cltr;

/**
 * Manager class for collectors.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string status prefix */
    const STATUS_PREFIX = 'collector_status:';

    /**
     * Get all the collector classes.
     *
     * @return array
     */
    public static function get_collector_classes(): array {
        return \core_component::get_plugin_list_with_class('cltr', 'collector');
    }

    /**
     * Sends an array of metrics to all enabled collectors.
     *
     * @param array $items An array of metric_item.
     */
    public static function send_metrics(array $items) {
        $plugins = cltr::get_enabled_plugin_instances();
        if (!$plugins) {
            mtrace('No collectors to send metrics to!');
            return;
        }

        if (!$items) {
            mtrace('No metrics to send at the moment');
            return;
        }

        foreach ($plugins as $plugin) {
            $collector = $plugin->get_collector();

            // If the status is empty then we don't know. If it it positive then
            // it is a timestamp of when it originally failed. If it is negative
            // then it is a timestamp of when it originally worked.
            $key = self::STATUS_PREFIX . $plugin->name;
            $status = get_config('tool_cloudmetrics', $key);

            // Migrate from old cache value.
            if ($status == 'pass') {
                $status = false;
            }
            $time = (int)$status;

            try {
                if ($collector->is_ready()) {
                    $collector->record_metrics($items);
                    mtrace("Sending " . count($items) . " metrics to '{$plugin->name}' collector");
                    if ($status === false || $time > 0) {
                        // Only record the point in time is changed from not working to working.
                        mtrace("Collector '{$plugin->name}' is now working");
                        set_config($key, -time(), 'tool_cloudmetrics');
                    }
                } else {
                    mtrace("Collector '{$plugin->name}' is not ready!");
                    if ($status === false || $time <= 0) {
                        mtrace("Collector '{$plugin->name}' is NOT ready since now");
                        set_config($key, time(), 'tool_cloudmetrics');
                    }
                }
            } catch (\Exception $e) {
                mtrace("Collector '{$plugin->name}' failed. " . $e->getMessage());
                debugging("Collector '{$plugin->name}' failed. " . $e->getMessage());
                // Store plugin name with timestamp of initial failure to track when problem first arose.
                if ($status === false || $time <= 0) {
                    mtrace("Collector '{$plugin->name}' is NOT working since now");
                    set_config($key, time(), 'tool_cloudmetrics');
                }
            }
        }
    }
}
