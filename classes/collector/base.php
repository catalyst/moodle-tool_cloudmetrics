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

/**
 * Base class for collectors.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {

    /**
     * Records a single metric.
     *
     * @param metric_item $metric
     * @return mixed
     */
    abstract public function record_metric(metric_item $metric);

    /**
     * Records a number of metrics.
     *
     * @param array $metrics
     * @param \progress_bar|null $progress
     * @return mixed
     */
    public function record_metrics(array $metrics, \progress_bar $progress = null) {
        $count = 0;
        foreach ($metrics as $metric) {
            $this->record_metric($metric);
            if ($progress) {
                $progress->update($count, count($metrics),
                    get_string('backfillsaving', 'tool_cloudmetrics', $metric->name));
                $count++;
            }
        }
    }

    /**
     * Is the collector ready to receive data.
     *
     * A collector is considered to be ready if it is able to receive data. This means that it is enabled,
     * properly configured, third party libraries are present, and any external connection must be sound.
     *
     * @return bool
     */
    abstract public function is_ready(): bool;

    /**
     * Abilitity for a collector to save old data.
     *
     * @return bool
     */
    public function supports_backfillable_metrics(): bool {
        return false;
    }

    /**
     * Abilitity for auto backfilling.
     *
     * @return bool
     */
    public function is_auto_backfill(): bool {
        return false;
    }
}
