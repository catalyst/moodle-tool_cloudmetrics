<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_cloudmetrics\task;

use tool_cloudmetrics\metric;
use tool_cloudmetrics\plugininfo\cltr;

/**
 * Auto back fills all data for each metric automatically everytime plugin installed or upgraded.
 *
 * @package   tool_cloudmetrics
 * @author    Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autobackfill_metrics_task extends \core\task\adhoc_task {

    /** @var array Enabled plugins */
    private array $plugins;

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('metrics_autobackfill_task', 'cltr_database');
    }

    /**
     * Will backfill the metrics using collectors.
     *
     * @param \tool_cloudmetrics\metric\base $metricclass Class representing metric.
     * @param array $metricitems Array of metric items.
     */
    public function backfill_metrics(\tool_cloudmetrics\metric\base $metricclass, array $metricitems) {
        if (!$metricitems) {
            mtrace('No metrics to send at the moment');
            return;
        }
        foreach ($this->plugins as $plugin) {
            $collector = $plugin->get_collector();
            if ($collector->supports_backfillable_metrics()) {
                $collector->record_saved_metrics($metricclass, $metricitems);
                mtrace(sprintf("Recorded %s '%s' metrics to %s", count($metricitems), $metricclass->get_name(), $plugin->name));
            }
        }
    }

    /**
     *  Execute task
     */
    public function execute() {
        $this->plugins = cltr::get_enabled_plugin_instances();
        if (!$this->plugins) {
            mtrace('No collectors to send metrics to!');
            return;
        }

        $customdata = $this->get_custom_data();
        $nowts = time();
        $collectingperiod = $customdata->period ?? YEARSECS;
        $autobackfill = !isset($customdata->metric);
        $metrictypes = metric\manager::get_metrics(true);

        // Filter to a specific metric.
        if (isset($customdata->metric)) {
            $metric = $customdata->metric;
            if (!array_key_exists($metric, $metrictypes)) {
                mtrace("{$metric} is an invalid metric type");
                return;
            }

            $metrictypes = [
                $metric => $metrictypes[$metric],
            ];
        }

        $total = 0;
        foreach ($metrictypes as $metrictype) {
            if (!$metrictype->is_ready()) {
                continue;
            }
            if (!$metrictype->is_backfillable()) {
                mtrace("The '{$metrictype->get_name()}' metric does not support backfilling data");
                continue;
            }
            if ($autobackfill && !$metrictype->is_autobackfill()) {
                mtrace("The '{$metrictype->get_name()}' metric does not support auto backfilling");
                continue;
            }

            $starttime = $nowts - $collectingperiod;
            $finishtime = $nowts;

            // Check if data has already been backfilled.
            [$backfillmin, $backfillmax, $backfillinterval] = $metrictype->get_range_retrieved();
            if ($backfillinterval === $metrictype->get_frequency() && $backfillmin > 0) {
                // If a backfill range exists for this frequency, we only need to backfill older data.
                $finishtime = $backfillmin;
            }

            if ($finishtime < $starttime) {
                mtrace(sprintf(
                    "The '%s' metric has already been backfilled to %s",
                    $metrictype->get_name(),
                    userdate($finishtime, '%e %b %Y, %H:%M')
                ));
                continue;
            }

            mtrace(sprintf(
                'Generating metrics for %s from %s to %s',
                $metrictype->get_name(),
                userdate($finishtime, '%e %b %Y, %H:%M'),
                userdate($starttime, '%e %b %Y, %H:%M')
            ));

            $metrics = $metrictype->generate_metric_items($collectingperiod, $finishtime);
            if ($metrictype->is_backfill_incremental()) {
                // We have a slow query so want to send metrics to the collector immediately.
                $count = 0;
                foreach ($metrics as $metric) {
                    $this->backfill_metrics($metrictype, [$metric]);
                    $count++;
                }
            } else {
                // Process the metrics as a batch.
                $metrics = iterator_to_array($metrics);
                if ($metrics) {
                    $this->backfill_metrics($metrictype, $metrics);
                }
                $count = count($metrics);
            }

            mtrace(sprintf('Generated %s %s metrics', $count,  $metrictype->get_name()));
            $total += $count;
        }
        mtrace('Backfilled totally '.$total.' metrics');
    }
}
