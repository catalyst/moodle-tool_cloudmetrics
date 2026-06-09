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

/**
 * Main file
 *
 * @package   tool_cloudmetrics
 * @author     Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright  2022, Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_cloudmetrics\metric\active_users_metric;
use tool_cloudmetrics\metric\manager;
use core\output\inplace_editable;

/**
 * Update the frequency config for metrics.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return inplace_editable|void
 * @throws coding_exception
 * @throws dml_exception
 * @throws invalid_parameter_exception
 * @throws moodle_exception
 * @throws required_capability_exception
 * @throws restricted_context_exception
 */
function tool_cloudmetrics_inplace_editable(string $itemtype, int $itemid, string $newvalue) {
    if ($itemtype == 'metrics_freq') {
        \external_api::validate_context(\context_system::instance());
        require_capability('moodle/site:config', \context_system::instance());
        $metrics = manager::get_metrics(false);
        $metric = null;
        foreach ($metrics as $m) {
            $intid = hexdec(substr(md5($m->get_name()), 0, 8));
            if ($intid === $itemid) {
                $metric = $m;
                break;
            }
        }
        if (is_null($metric)) {
            throw new moodle_exception('Unknown metric ' . $itemid);
        }
        $newvalue = clean_param($newvalue, PARAM_INT);
        $metric->set_frequency($newvalue);

        $options = manager::get_frequency_labels();
        $editable = new inplace_editable(
            'tool_cloudmetrics',
            'metrics_freq',
            $itemid,
            true,
            null,
            $newvalue,
            get_string('change_frequency', 'tool_cloudmetrics'),
            get_string('frequency', 'tool_cloudmetrics')
        );
        $editable->set_type_select($options);
        return $editable;
    }
}

/**
 * Returns cloumetrics status checks
 *
 * @return array
 */
function tool_cloudmetrics_status_checks() {
    return [new tool_cloudmetrics\check\collectorcheck()];
}

/**
 * Returns cloumetrics performance checks
 *
 * @return array
 */
function tool_cloudmetrics_performance_checks() {
    $checks = [];
    $metrics = manager::get_metrics(false);
    foreach ($metrics as $metric) {
        $checks[] = tool_cloudmetrics\check\metriccheck::get_check($metric);
    }
    return $checks;
}
