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

use tool_cloudmetrics\collector\base;
use tool_cloudmetrics\metric\metric_item;

/**
 * Base class for collectors that can read the stored metrics and other metadata.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class readable_base extends base
{
    /**
     * Can we retrieve saved metrics and metadata from this collector?
     *
     * @return bool
     */
    final public function is_readable(): bool {
        return true;
    }

    /**
     * Gets the range of the currently stored metric data.
     *
     * @param string $metricname
     * @return ?array An array of start and end, or null if no data is found.
     */
    abstract public function get_metric_range(string $metricname): ?array;

    /**
     * Gets the last backfilled frequency set via set_last_backfilled_frequency.
     * Note: This value does not necessarily reflect the actual frequency of the stored data.
     *
     * @param string $metricname
     * @return int|false
     */
    abstract public function get_last_backfilled_frequency(string $metricname): int|false;

    /**
     * Sets the last backfilled frequency.
     * Note: This value does not necessarily reflect the actual frequency of the stored data.
     *
     * @param string $metricname
     * @param int $frequency
     */
    abstract public function set_last_backfilled_frequency(string $metricname, int $frequency);
}
