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

namespace cltr_database;

use tool_cloudmetrics\metric\metric_item;
use tool_cloudmetrics\collector\base;
use tool_cloudmetrics\metric;

/**
 * Collector class for the internal database.
 *
 * @package   cltr_database
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collector extends base {
    /**
     * Records metric in cltr table.
     *
     * @param metric_item $item Item representing metric to record.
     */
    public function record_metric(metric_item $item) {
        global $DB;

        $DB->insert_record(
            lib::TABLE,
            ['name' => $item->name, 'value' => $item->value, 'time' => $item->time]
        );
    }

    /**
     * Deletes every metric from cltr table for a given metric name.
     *
     * @param string $metricname Metric name to remove.
     */
    public function delete_metrics(string $metricname, $starttime = null, $endtime = null) {
        global $DB;
        $select = 'name = :metricname';
        $params = ['metricname' => $metricname];
        // If no starttime and endtime passed all metrics are removed.
        if (!is_null($starttime) && !is_null($endtime)) {
            $select .= ' AND time >= :starttime AND time <= :endtime';
            $params['starttime'] = $starttime;
            $params['endtime'] = $endtime;
        }
        $DB->delete_records_select(lib::TABLE, $select, $params);
    }

    /**
     * Returns stored metrics for the collector.
     *
     * @param  mixed  $metricnames The metrics to be retrieved. Either a single string, or an
     *         array of strings. If empty, then all available metrics will be retrieved.
     * @param  int | false $since The earliest timestamp to retrieve.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_metrics($metricnames = null, $since = false): array {
        global $DB;
        $starting = '';

        if (is_null($metricnames)) {
            $metricnames = [];
            $metrics = metric\manager::get_metrics(true);
            foreach ($metrics as $metric) {
                $metricnames[] = $metric->get_name();
            }
        } else if (is_string($metricnames)) {
            $metricnames = [$metricnames];
        }
        if ($since) {
            $starting = " AND time > " . (time() - $since);
        }
        list ($clause, $params) = $DB->get_in_or_equal($metricnames);
        $sql = "SELECT id, name, time, value
                  FROM {cltr_database_metrics}
                 WHERE name $clause
                 $starting
               ORDER BY time asc";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Records retrieved data in collector.
     *
     * @param  \tool_cloudmetrics\metric\base $metricclass Class representing metric.
     * @param  array $data Array of metric items.
     */
    public function record_saved_metrics(\tool_cloudmetrics\metric\base $metricclass, array $metricitems = []) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        if (count($metricitems) != 0 && !$metricclass->sameconfig) {
            $this->record_metrics($metricitems);
        }
        $transaction->allow_commit();
        // Sets what data has been sent to collector.
        $metricclass->set_data_sent_config();
    }

    /**
     * Abilitity for a collector to retrieve old data.
     *
     * @return bool
     */
    public function supports_backfillable_metrics(): bool {
        return true;
    }
}
