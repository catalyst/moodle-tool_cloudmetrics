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

namespace tool_cloudmetrics\event;

use core\event\base;

/**
 * Collector plugin was disabled
 *
 * @package   tool_cloudmetrics
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @author    Michael Kotlyar <michael.kotlyar@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collector_plugin_disabled extends base {
    #[\Override]
    public function init() {
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['crud'] = 'u';
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventcollectorplugindisabled', 'tool_cloudmetrics');
    }

    #[\Override]
    public function get_description() {
        return 'User ' .  $this->data['userid']  . ' disabled collector plugin ' . $this->data['other']['pluginname'];
    }
}
