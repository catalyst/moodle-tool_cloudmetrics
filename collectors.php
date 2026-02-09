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
 * Sumbission for collector plugin management.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = required_param('action', PARAM_ALPHANUMEXT);
$name   = required_param('name', PARAM_PLUGIN);
$returnurl = optional_param('returnurl', get_local_referer(false), PARAM_LOCALURL);

$syscontext = context_system::instance();
$PAGE->set_url('/admin/tool/cloudmetrics/collectors.php');
$PAGE->set_context($syscontext);

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());
require_sesskey();

$return = new moodle_url($returnurl);

$plugins = core_plugin_manager::instance()->get_plugins_of_type('cltr');

if (!isset($plugins[$name])) {
    throw new moodle_exception(get_string('plugin_not_found', 'tool_cloudmetrics', $name), 'tool_cloudmetrics');
}

switch ($action) {
    case 'disable':
        $plugins[$name]->set_enabled(false);
        break;
    case 'enable':
        $plugins[$name]->set_enabled(true);
        break;
}
redirect($return);
