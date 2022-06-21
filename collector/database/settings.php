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
 * Cloudmetrics - Database collector settings.
 *
 * @package   cltr_database
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add(
        'reports',
        new admin_externalpage(
            'cltr_database_chart',
            get_string('metric_display', 'cltr_database'),
            new moodle_url('/admin/tool/cloudmetrics/collector/database/chart.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'reports',
        new admin_externalpage(
            'cltr_database_backfill',
            get_string('metric_backfill', 'cltr_database'),
            new moodle_url('/admin/tool/cloudmetrics/collector/database/backfill.php'),
            'moodle/site:config'
        )
    );

    $ADMIN->add(
        'reports',
        new admin_externalpage(
            'cltr_database_reset',
            get_string('metric_reset', 'cltr_database'),
            new moodle_url('/admin/tool/cloudmetrics/collector/database/reset.php'),
            'moodle/site:config'
        )
    );

    if ($ADMIN->fulltree) {
        $settings->add(
            new admin_setting_configduration(
                'cltr_database/metric_expiry',
                get_string('metric_expiry', 'cltr_database'),
                get_string('metric_expiry_desc', 'cltr_database'),
                30 * DAYSECS
            )
        );
    }
}
