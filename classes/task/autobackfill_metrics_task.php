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

use tool_cloudmetrics\collector\manager;
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
    /** @var int Number of records saved for each dot displayed */
    private const NUM_PER_DOT = 10;

    /** @var int Number of dots to be displayed on each line. */
    private const DOTS_PER_LINE = 80;

    /** @var array Enabled plugins */
    private array $plugins;

    /** @var ?\tool_cloudmetrics\collector\base  */
    private ?\tool_cloudmetrics\collector\base $collector  = null;

    /** @var int Used to control the printing of progress dots. */
    private static $progresscount = 0;

    /**
     * Callback for metric recording progress.
     *
     * @param $count int Current count
     * @param $total int Expected total
     */
    public static function progress_calllback(int $count, int $total) {
        ++self::$progresscount;
        if (self::$progresscount % self::NUM_PER_DOT != 0) {
            return;
        }
        if (self::$progresscount % (self::NUM_PER_DOT * self::DOTS_PER_LINE) == 0) {
            $percent = '';
            // Printing a running percentage only makes sense if we are batch processing.
            if ($total != 1) {
                $percent = ' ' . round(($count / $total) * 100) . '%';
            }
            mtrace(".$percent");
        } else {
            mtrace('.', '');
        }
    }

    /**
     * Reset the progress counter.
     */
    public static function reset_progress() {
        if (self::$progresscount % (self::NUM_PER_DOT * self::DOTS_PER_LINE) != 0) {
            mtrace('');
        }
        self::$progresscount = 0;
    }

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
        if (!empty($this->collector)) {
            $this->collector->record_metrics($metricitems, [self::class, 'progress_calllback']);
        } else {
            foreach ($this->plugins as $plugin) {
                $collector = $plugin->get_collector();
                if ($collector->supports_backfillable_metrics()) {
                    $collector->record_metrics($metricitems, [self::class, 'progress_calllback']);
                    if ($collector->is_readable()) {
                        $collector->set_last_backfilled_frequency($metricclass->get_name(), $metricclass->get_frequency());
                    }
                }
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
        if (isset($customdata->collector)) {
            $this->collector = manager::get_collector($customdata->collector);
            if (!$this->collector->supports_backfillable_metrics()) {
                mtrace('No backfillable collectors to send metrics to!');
                return;
            }
        } else {
            $proceedwithbackfill = false;
            foreach ($this->plugins as $plugin) {
                $collector = $plugin->get_collector();
                if ($collector->supports_backfillable_metrics()) {
                    $proceedwithbackfill = true;
                }
            }
            if (!$proceedwithbackfill) {
                mtrace('No backfillable collectors to send metrics to!');
                return;
            }
        }
        // If a specific metric is not set, then this must be an automatic task.
        $autobackfill = !isset($customdata->metric);
        $metrictypes = metric\manager::get_metrics(true);

        // TODO: When it is set to run automatically, idempotency is not used. With the current design, idempotency can
        // only work when the there is one collector being used.

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
            mtrace('Visiting metric ' . $metrictype->get_name());
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
            if ($this->collector && $this->collector->is_readable()) {
                $range = $this->collector->get_metric_range($metrictype->get_name());
                if (!is_null($range)) {
                    // We only need to backfill further back than this time.
                    // We subtract 1 because we don't want to include it.
                    $finishtime = $range['mintime'] - 1;
                }
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
            mtrace('Finished generating metrics');

            $count = 0;
            try {
                if ($metrictype->is_backfill_incremental()) {
                    // This metric is slow, so we want to send metrics to the collector as soon as each one is obtained.
                    mtrace('Slow metric. Process with an iterator.');
                    foreach ($metrics as $metric) {
                        $this->backfill_metrics($metrictype, [$metric]);
                        $count++;
                    }
                } else {
                    // Process the metrics as a batch.
                    mtrace('Process with an array.');
                    $metrics = iterator_to_array($metrics);
                    mtrace('There are ' . count($metrics) . ' to process.');
                    if ($metrics) {
                        $this->backfill_metrics($metrictype, $metrics);
                    }
                    $count = count($metrics);
                }
                self::reset_progress();
            } catch (\Exception $e) {
                self::reset_progress();
                mtrace('Error occurred: ' . $e->getMessage());
            }
            mtrace('Processed ' . $count . ' metrics.');

            $total += $count;
            mtrace('Finished with metric ' . $metrictype->get_name());
        }
        mtrace('Backfilled a total of ' . $total . ' metrics');
    }
}
