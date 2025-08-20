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

/**
 * Install script for databases.
 *
 * @package   tool_cloudmetrics
 * @author    Dustin Huynh <dustinhuynh@catalyst-au.net>
 * @copyright 2025, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to install tool_cloudmetrics.
 */
function xmldb_tool_cloudmetrics_install() {
    if (PHPUNIT_TEST) {
        return;
    }
    $backfilltask = new \tool_cloudmetrics\task\autobackfill_metrics_task();
    \core\task\manager::queue_adhoc_task($backfilltask);
}
