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
use tool_cloudmetrics\lib;
use tool_cloudmetrics\plugininfo\cltr;
use tool_cloudmetrics\collector\readable_base as collector;

/**
 * Auto back fills all data for each metric automatically everytime plugin installed or upgraded.
 *
 * @package   tool_cloudmetrics
 * @author    Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autobackfill_metrics_task extends \core\task\adhoc_task {
    use \core\task\stored_progress_task_trait;

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('metrics_autobackfill_task', 'cltr_database');
    }

    /**
     * Execute task
     *
     * Backfills metrics into collectors. Customdata can have up to three properties:
     * - metric: The name of the metric to read. If null, all enabled backfillable metrics are used.
     * - period: The length of time into the past to read data from. Defaults to one year.
     * - collector: The name of the collector to write to. If null, all enabled backfillable collectors are used.
     * - isupgrade: Whether this task is being run during an upgrade or install. Defaults to false.
     */
    public function execute() {
        $nowts = \core\di::get(\core\clock::class)->time();

        $customdata = $this->get_custom_data();
        $timerange = $customdata->period ?? YEARSECS;
        $metricname = $customdata->metric ?? null;
        $collectorname = $customdata->collector ?? null;
        $isupgrade = $customdata->isupgrade ?? false;

        // Get metrics to process.
        if ($metricname) {
            // Get specific metric.
            $allmetrics = metric\manager::get_metrics(false);
            if (!isset($allmetrics[$metricname])) {
                mtrace("Metric '{$metricname}' not found.");
                return;
            }
            // Validate requested metric is backfillable if specified.
            if (!$allmetrics[$metricname]->is_backfillable()) {
                mtrace("Metric '{$metricname}' is not backfillable.");
                return;
            }
            if ($isupgrade && !$allmetrics[$metricname]->can_backfill_during_upgrade()) {
                mtrace("The '{$metricname}' metric does not support backfilling during upgrades");
                return;
            }
            $metrics = [$metricname => $allmetrics[$metricname]];
        } else {
            // Get all enabled backfillable metrics.
            $metrics = [];
            $allmetrics = metric\manager::get_metrics(true);
            foreach ($allmetrics as $name => $m) {
                if ($m->is_backfillable() && (!$isupgrade || $m->can_backfill_during_upgrade())) {
                    $metrics[$name] = $m;
                }
            }
        }

        if (empty($metrics)) {
            mtrace('No backfillable metrics available.');
            return;
        }

        // Get collectors to write to.
        if ($collectorname) {
            // Get specific collector.
            $collectorplugins = cltr::get_enabled_plugin_instances();
            if (!isset($collectorplugins[$collectorname])) {
                mtrace("Collector '{$collectorname}' not found or not enabled.");
                return;
            }
            $plugin = $collectorplugins[$collectorname];
            $collector = $plugin->get_collector();
            if (!$collector->supports_backfillable_metrics()) {
                mtrace("Collector '{$collectorname}' does not support backfillable metrics.");
                return;
            }
            $collectors = [$collectorname => $collector];
        } else {
            // Get all enabled collectors that support backfillable metrics.
            $collectors = [];
            $collectorplugins = cltr::get_enabled_plugin_instances() ?? [];
            foreach ($collectorplugins as $name => $plugin) {
                $collector = $plugin->get_collector();
                if ($collector->supports_backfillable_metrics()) {
                    $collectors[$name] = $collector;
                }
            }
            if (empty($collectors)) {
                mtrace('No backfillable collectors available.');
                return;
            }
        }

        // Calculate time range.
        $rangestart = $nowts - $timerange;
        $rangeend = $nowts;

        // Start reporting progress via the task API, so it can be tracked in the admin UI.
        $this->start_stored_progress();

        $total = count($metrics) * count($collectors);
        $done = 0;

        // For each metric, for each collector, backfill gaps.
        foreach ($metrics as $metricname => $metric) {
            foreach ($collectors as $collectorname => $collector) {
                mtrace("Backfilling metric '{$metricname}' to collector '{$collectorname}'...");
                $this->progress->update(
                    $done,
                    $total,
                    "Backfilling metric '{$metricname}' to collector '{$collectorname}'..."
                );

                $this->do_backfill($metric, $collector, $rangestart, $rangeend);

                $done++;
                mtrace("Backfilling complete for metric '{$metricname}' to collector '{$collectorname}'.");
            }
        }

        $this->progress->update_full(100, 'Backfilling complete.');
    }

    /**
     * Performs a backfill for a single metric and collector.
     *
     * @param metric\base $metric
     * @param collector $collector
     * @param int $starttime
     * @param int $endtime
     * @return void
     * @throws \Exception
     */
    public function do_backfill(metric\base $metric, collector $collector, int $starttime, int $endtime): void {
        // Get records from collector.
        $backrecords = $collector->get_times($metric->get_name(), $starttime, $endtime);
        if (is_array($backrecords)) {
            $backrecords = new \ArrayIterator($backrecords);
        }

        $freq = $metric->get_frequency();
        $endtime = lib::get_last_whole_tick($endtime, $freq);
        $period = lib::get_period($freq);

        // Records are in reverse chronological order, matching our descending iteration,
        // so we can walk both in lockstep.
        $backrecords->rewind();

        // First pass: identify every gap (there may be several, separated by existing records)
        // so we can report cumulative progress across the whole range instead of resetting
        // the progress bar back to 0% at the start of each individual gap.
        $gaps = [];
        $first = $last = null;
        for (
            $time = $endtime;
            $time >= $starttime;
            $time = lib::get_previous_time($time, $freq)
        ) {
            // Skip past any records more recent than the current time.
            while ($backrecords->valid() && $backrecords->current() > $time) {
                $backrecords->next();
            }

            if ($backrecords->valid() && $backrecords->current() == $time) {
                // A record exists for this time, so the gap (if any) has ended. Backfill the kept times.
                $backrecords->next();
                if ($first !== null) {
                    $gaps[] = [$first, $last];
                    $first = $last = null;
                }
            } else {
                // Time is missing; keep track of the first and last times to form a range gap. $first is earliest time,
                // $last is latest time.
                $first = $time;
                if ($last === null) {
                    $last = $time;
                }
            }
        }

        // Backfill any remaining gap reaching the start of the range.
        if ($first !== null) {
            $gaps[] = [$first, $last];
        }

        // Total number of ticks across all gaps, used as the grand total for cumulative progress.
        $totalitems = 0;
        foreach ($gaps as [$low, $high]) {
            $totalitems += ($high - $low) / $period + 1;
        }

        $processed = 0;
        foreach ($gaps as [$low, $high]) {
            $processed = $this->transfer($metric, $collector, $low, $high, $processed, $totalitems);
        }
    }

    /**
     * Transfers metric data from a gap to the collector.
     *
     * @param metric\base $metric
     * @param collector $collector
     * @param int $low
     * @param int $high
     * @param int $processed Number of items already backfilled for this metric/collector, across earlier gaps.
     * @param int|null $totalitems Total items to backfill for this metric/collector, across all gaps.
     * @return int Updated count of items processed, including this gap.
     */
    public function transfer(
        metric\base $metric,
        collector $collector,
        int $low,
        int $high,
        int $processed = 0,
        ?int $totalitems = null
    ): int {
        mtrace("Filling gap from $low to $high");
        $items = $metric->generate_metric_items($low, $high);
        // Reuse the task's stored progress bar (set up by execute() via start_stored_progress())
        // so per-item progress is visible via the task API. Fall back to a plain progress bar
        // when transfer()/do_backfill() are called directly (e.g. in unit tests) without execute().
        $progressbar = $this->progress ?? new \core\output\progress_bar();
        if ($this->progress === null) {
            $progressbar->create();
        }
        if ($metric->is_backfill_incremental()) {
            // Incremental metrics are stored one at a time.
            // We do not know the exact number of records to save, so we approximate it.
            $roughtotal = $totalitems ?? (($high - $low) / lib::get_period($metric->get_frequency()) + 1);
            foreach ($items as $item) {
                $collector->record_metric($item);
                $processed++;
                $progressbar->update(
                    $processed,
                    $roughtotal,
                    userdate($item->time, '%e %b %Y, %H:%M')
                );
            }
        } else {
            $itemsarray = iterator_to_array($items);
            $roughtotal = $totalitems ?? count($itemsarray);
            $collector->record_metrics($itemsarray, $progressbar, $processed, $roughtotal);
            $processed += count($itemsarray);
        }
        return $processed;
    }
}
