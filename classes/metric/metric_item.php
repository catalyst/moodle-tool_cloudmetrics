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

namespace tool_cloudmetrics\metric;

/**
 * Data class for metric values.
 *
 * @package    tool_cloudmetrics
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metric_item {
    /** @var string Metric type name.*/
    public $name;
    /** @var int Time of the metric. This should be the time of the original recording of the source data. */
    public $time;
    /** @var mixed The value of the metric. */
    public $value;
    /** @var base Reference to the metric type object. */
    public $metric;

    /**
     * metric_item constructor.
     * @param string $name
     * @param int $time
     * @param string $value
     * @param base $item
     */
    public function __construct(string $name, int $time, $value, base $item) {
        $this->name = $name;
        $this->time = $time;
        $this->value = $value;
        $this->metric = $item;
    }
}
