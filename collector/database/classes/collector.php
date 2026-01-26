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
            [
                'name' => $item->name,
                'value' => $item->value,
                'time' => $item->time,
                'date' => lib::get_midnight_of($item->time)->getTimestamp(),
            ]
        );
    }

    /**
     * Deletes every metric from cltr table for a given metric name.
     *
     * @param string $metricname Metric name to remove.
     */
    public function delete_metrics(string $metricname) {
        global $DB;

        $DB->delete_records(lib::TABLE, ['name' => $metricname]);
    }

    /**
     * Returns stored metrics for the collector.
     *
     * @param mixed $metricnames The metrics to be retrieved. Either a single string, or an
     *         array of strings. If empty, then all available metrics will be retrieved.
     * @param int|false $since The earliest timestamp to retrieve.
     * @param int $limit The max number of records to retrieve.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_metrics($metricnames = null, $since = false, int $limit = 1000): array {
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
        [$clause, $params] = $DB->get_in_or_equal($metricnames);
        $sql = "SELECT id, name, date, time, value
                  FROM {cltr_database_metrics}
                 WHERE name $clause
                 $starting
               ORDER BY time asc";
        return $DB->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * Returns stored metrics for the collector aggregated over a particular time increment.
     *
     * @param mixed $metricnames The metrics to be retrieved. Either a single string, or an
     *         array of strings. If empty, then all available metrics will be retrieved.
     * @param int|false $since The earliest timestamp to retrieve.
     * @param int $limit The max number of records to retrieve.
     * @param int $aggregate The time increment to aggregate data into, in secs.
     * @return \moodle_recordset
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_metrics_aggregated($metricnames = null, $since = false, int $limit = 1000, int $aggregate = 1) {
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

        if ($aggregate == DAYSECS) {
            $incrementstart = "date AS increment_start";
        } else {
            $incrementstart = "FLOOR(time/$aggregate) * $aggregate AS increment_start";
        }
        [$clause, $params] = $DB->get_in_or_equal($metricnames);
        if (count($metricnames) == 1) {
            $sql = "SELECT AVG(" . $DB->sql_cast_char2int('value', true) . ") AS \"$metricnames[0]\",
                MIN(" . $DB->sql_cast_char2int('value', true) . ") AS min,
                MAX(" . $DB->sql_cast_char2int('value', true) . ") AS max,
                $incrementstart
                FROM {cltr_database_metrics}
                WHERE name $clause
                $starting
                GROUP BY increment_start
                ORDER BY increment_start ASC";
        } else {
            $metricselect = '';
            foreach ($params as $param) {
                $metricselect .= "AVG(CASE name WHEN '$param' THEN" . $DB->sql_cast_char2int('value', true) . "END) AS \"$param\",";
            }
            $metricselect = rtrim($metricselect, ',');
            $sql = "SELECT $incrementstart,
                $metricselect
                FROM {cltr_database_metrics}
                WHERE 1=1 $starting
                GROUP BY increment_start
                ORDER BY increment_start ASC";
        }
        return $DB->get_recordset_sql($sql, $params, 0, $limit);
    }

    /**
     * Records retrieved data in collector.
     *
     * @param \tool_cloudmetrics\metric\base $metricclass Class representing metric.
     * @param array $metricitems Array of metric items.
     * @param \progress_bar|null $progress
     */
    public function record_saved_metrics(
        \tool_cloudmetrics\metric\base $metricclass,
        array $metricitems = [],
        ?\progress_bar $progress = null
    ) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        if (count($metricitems) != 0 && !$metricclass->sameconfig) {
            $this->record_metrics($metricitems, $progress);
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

    /**
     * Is the collector ready to receive data.
     *
     * A collector is considered to be ready if it is able to receive data. This means that it is enabled,
     * properly configured, third party libraries are present, and any external connection must be sound.
     *
     * @return bool
     */
    public function is_ready(): bool {
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('cltr_database');
        return $plugininfo->is_enabled();
    }

    /**
     * Abilitity for auto backfilling.
     *
     * @return bool
     * @throws \dml_exception
     */
    public function is_auto_backfill(): bool {
        return lib::get_metric_auto_backfill();
    }
}
