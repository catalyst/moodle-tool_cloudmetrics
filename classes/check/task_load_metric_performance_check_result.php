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
use tool_cloudmetrics\metric\task_load_metric;
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
    /** @var task_load_metric The metric that was checked */
    private readonly task_load_metric $metric;

    /** @var \stdClass[]|null Cached estimate rows */
    private ?array $data = null;

    /**
     * Construct the result object.
     * @param task_load_metric $metric The metric that was checked
     * @param string $status
     * @param string $summary
     * @param string $details
     */
    public function __construct(
        task_load_metric $metric,
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
    public function get_summary(): string {
        return get_string('estimatedtaskload_summary', 'tool_cloudmetrics', (object) [
            'duration' => self::format_duration($this->get_total_seconds()),
            'window'   => format_time($this->metric->get_lookahead()),
        ]);
    }

    /**
     * Get additional details about the check.
     *
     * @return string HTML markup describing the check in more detail
     */
    public function get_details(): string {
        $details = \html_writer::tag('p', parent::get_details());
        $window = $this->metric->get_lookahead();
        $details .= \html_writer::tag(
            'p',
            get_string('estimatedtaskload_window', 'tool_cloudmetrics', format_time($window))
        );
        $table = new \html_table();
        $table->attributes['class'] = 'table w-auto';
        $table->head = [
            get_string('estimatedtaskload', 'tool_cloudmetrics'),
            get_string('task_name', 'tool_cloudmetrics'),
        ];
        $table->align = ['right', 'left'];
        $data = $this->get_data();
        usort($data, fn($a, $b) => $b->estimated_load <=> $a->estimated_load);
        foreach ($data as $row) {
            $table->data[] = [
                self::format_duration($row->estimated_load),
                self::format_classname(ltrim($row->task_name, '\\')),
            ];
        }
        $totalseconds = $this->get_total_seconds();
        $table->data[] = [
            \html_writer::tag('strong', self::format_duration($totalseconds)),
            \html_writer::tag('strong', get_string('total')),
        ];

        $details .= \html_writer::table($table);
        return $details;
    }

    /**
     * Formats a duration in seconds as M:SS.
     *
     * @param float $seconds
     * @return string e.g. "2:15"
     */
    public static function format_duration(float $seconds): string {
        $minutes = (int) floor($seconds / 60);
        $secs = (int) round(fmod($seconds, 60));
        return sprintf('%d:%02d', $minutes, $secs);
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
     * Returns the sum of all task estimates in seconds.
     *
     * @return float
     */
    private function get_total_seconds(): float {
        return array_sum(array_column(
            array_map(fn($r) => ['load' => $r->estimated_load], $this->get_data()),
            'load'
        ));
    }

    /**
     * Fetch the task load estimates as rows.
     *
     * Estimates are generated for the time window supplied by the metric.
     *
     * @return \stdClass[] the report rows, keyed by column name
     */
    private function get_data(): array {
        if ($this->data === null) {
            $estimator = task_load_estimator::create();
            $estimates = $estimator->generate_load_estimates($this->metric->get_lookahead());

            $this->data = [];
            foreach ($estimates as $estimate) {
                $this->data[] = (object) [
                    'task_name' => $estimate['classname'],
                    'estimated_load' => $estimate['totalduration'],
                ];
            }
        }
        return $this->data;
    }
}
