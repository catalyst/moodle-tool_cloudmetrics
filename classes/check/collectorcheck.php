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

use core\check\check;
use core\check\result;
use tool_cloudmetrics\collector\manager;
use tool_cloudmetrics\plugininfo\cltr;

/**
 * Collector failure tests for Check API
 *
 * @package tool_cloudmetrics
 * @author Mike Macgirvin (mikemacgirvin@catalyst-au.net)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Catalyst IT
 */
class collectorcheck extends check {
    /**
     * A link to a place to action this
     *
     * @return action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/admin/category.php?category=tool_cloudmetrics_reports'),
            get_string('managelink', 'tool_cloudmetrics')
        );
    }

    /**
     * Return result
     * @return result
     */
    public function get_result(): result {
        $failures = false;
        $warnings = false;
        $messages = [];

        $names = cltr::get_ready_plugin_names();
        if (!$names) {
            return new result(result::INFO, get_string('no_collectors', 'tool_cloudmetrics'), '');
        }
        foreach ($names as $name) {
            $status = get_config('tool_cloudmetrics', manager::STATUS_PREFIX . $name);
            if (! $status) {
                $warnings = true;
                $messages[] = get_string('collector_never', 'tool_cloudmetrics', $name);
                continue;
            }

            // If the status is negative then it has been correctly working for some time.
            if ($status < 0) {
                $messages[] = get_string(
                    'collector_passed',
                    'tool_cloudmetrics',
                    ['name' => $name, 'time' => userdate((int)(-$status), '%e %b %Y, %H:%M')]
                );
                continue;
            }
            $failures = true;
            $messages[] = get_string(
                'collector_failed',
                'tool_cloudmetrics',
                ['name' => $name, 'time' => userdate((int) $status, '%e %b %Y, %H:%M')]
            );
        }

        $failuretype = result::OK;
        if ($warnings) {
            $failuretype = result::INFO;
        }
        if ($failures) {
            $failuretype = result::ERROR;
        }
        // This result contains the enumerated detail of each test.
        return new result($failuretype, implode('<br>', $messages));
    }
}
