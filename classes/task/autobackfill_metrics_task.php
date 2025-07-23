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

use tool_cloudmetrics\collector;
use tool_cloudmetrics\metric;

/**
 * Auto back fills all data for each metric automatically everytime plugin installed or upgraded.
 *
 * @package   tool_cloudmetrics
 * @author    Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autobackfill_metrics_task extends \core\task\adhoc_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('metrics_autobackfill_task', 'cltr_database');
    }

    /**
     * Will backfill the metrics using collectors.
     *
     * @param array $items
     */
    public function backfill_metrics(array $items) {
        collector\manager::backfill_metrics($items);
    }
    /**
     *  Execute task
     */
    public function execute() {
        $nowts = time();
        $collectingperiod = YEARSECS;
        $metrictypes = metric\manager::get_metrics(true);
        $total = 0;
        foreach ($metrictypes as $metrictype) {
            if (!$metrictype->is_ready()) {
                continue;
            }
            if (!$metrictype->is_backfillable()) {
                mtrace("The '{$metrictype->get_name()}' metric does not support backfilling data");
                continue;
            }
            if (!$metrictype->is_autobackfill()) {
                mtrace("The '{$metrictype->get_name()}' metric does not support auto backfilling");
                continue;
            }
            mtrace(sprintf(
                'Generating metrics for %s from %s to %s',
                $metrictype->get_name(),
                userdate($nowts, '%e %b %Y, %H:%M'),
                userdate(($nowts - $collectingperiod), '%e %b %Y, %H:%M')
            ));
            $items = $metrictype->generate_metric_items($collectingperiod, $nowts);
            mtrace(sprintf('Generated %s %s metrics', count($items),  $metrictype->get_name()));
            $this->backfill_metrics($items);
            $total += count($items);
        }
        mtrace('Backfilled totally '.$total.' metrics');
    }
}
