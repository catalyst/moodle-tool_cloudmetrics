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

namespace tool_cloudmetrics\check;

use core\output\action_link;
use tool_cloudmetrics\metric\base;
use core\check\result;
use core_admin\reportbuilder\local\entities\task_log;
use tool_cloudmetrics\local\task_load\task_load_estimator;

/**
 * Performance check result for task load estimator metric.
 *
 * @package tool_cloudmetrics
 * @author  Jason den Dulk <jasondendulk@catalyst-au.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Catalyst IT
 */
class task_load_metric_performance_check_result extends result {
    /** @var base The metric that was checked */
    private readonly base $metric;

    /**
     * Construct the result object.
     * @param base $metric The metric that was checked
     * @param string $status
     * @param string $summary
     * @param string $details
     */
    public function __construct(
        base $metric,
        string $status,
        string $summary,
        string $details = '',
    ) {
        $this->metric = $metric;
        parent::__construct($status, $summary, $details);
    }

    /**
     * Get additional details about the check.
     *
     * @return string HTML markup describing the check in more detail
     */
    public function get_details(): string {
        $details = \html_writer::tag('p', parent::get_details());
        $table = new \html_table();
        $table->head = [
            get_string('task_name', 'tool_cloudmetrics'),
            get_string('estimatedtaskload_s', 'tool_cloudmetrics'),
        ];
        foreach ($this->get_data() as $row) {
            $table->data[] = [
                self::format_classname(ltrim($row->task_name, '\\')),
                round($row->estimated_load, 1),
            ];
        }

        $details .= \html_writer::table($table);
        return $details;
    }

    /**
     * Formats a task name with its classname
     *
     * @param string $classname
     * @return string html formatted name
     */
    public static function format_classname(string $classname): string {
        // TODO: 5.3 adds this function to core_admin\reportbuilder\local\entities\task_log.
        $output = '';
        if (class_exists($classname)) {
            $task = new $classname();
            if ($task instanceof \core\task\task_base) {
                $output = $task->get_name();
            }
        }

        $output .= \html_writer::tag('div', "\\{$classname}", [
            'class' => 'small text-muted',
        ]);
        return $output;
    }

    /**
     * Fetch the task load estimates as rows.
     *
     * Estimates are generated for the time window supplied by the metric.
     *
     * @return \stdClass[] the report rows, keyed by column name
     */
    private function get_data(): array {
        $estimator = task_load_estimator::create();
        $estimates = $estimator->generate_load_estimates($this->metric->get_time_window());

        $rows = [];
        foreach ($estimates as $estimate) {
            $rows[] = (object) [
                'task_name' => $estimate['classname'],
                'estimated_load' => $estimate['totalduration'],
            ];
        }
        return $rows;
    }
}
