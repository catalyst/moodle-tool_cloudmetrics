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

namespace tool_cloudmetrics;

use tool_cloudmetrics\metric\manager;
use core\output\inplace_editable;

/**
 * Admin setting object for managing metrics
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_manage_metrics extends \admin_setting {
    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct('managemetrics', get_string('manage_metrics', 'tool_cloudmetrics'), '', '');
    }

    /**
     * Always returns true
     *
     * @return true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Always returns true
     *
     * @return true
     */
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Always returns '' and doesn't write anything
     *
     * @param mixed $data string or array, must not be NULL
     * @return string Always returns ''
     */
    public function write_setting($data) {
        // Do not write any setting.
        return '';
    }

    /**
     * Return XHTML to display control
     *
     * @param mixed $data Unused
     * @param string $query
     * @return string highlight
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $metrics = manager::get_metrics(false);
        // Sort by group.
        usort($metrics, function ($metric1, $metric2) {
            return $metric1->group <=> $metric2->group;
        });

        $txt = get_strings(['plugin', 'settings', 'name', 'group', 'description', 'enable', 'disable', 'default', 'show', 'actions', 'report']);
        $txt->frequency = get_string('frequency', 'tool_cloudmetrics');
        $txt->colour = get_string('colour', 'tool_cloudmetrics');
        $table = new \html_table();
        $table->head  = [$txt->plugin, $txt->name, $txt->group, $txt->description, $txt->frequency, $txt->actions, get_string('backfillable', 'tool_cloudmetrics')];
        $table->align = ['left', 'left', 'left', 'left'];
        $table->attributes['class'] = 'manageformattable generaltable admintable w-auto';
        $table->data  = [];

        foreach ($metrics as $metric) {
            $url = new \moodle_url(
                '/admin/tool/cloudmetrics/metrics.php',
                ['sesskey' => sesskey(), 'name' => $metric->get_name()]
            );
            $displayname = $metric->get_label();
            $description = $metric->get_description();
            $group = !empty($metric->group) ? get_string($metric->group, 'tool_cloudmetrics') : '';

            // Colour.
            $colour = $metric->get_colour();
            $swatch = \html_writer::span(
                '',
                'rounded',
                ['style' => "height: 1em; width: 1em; background-color: $colour; display: inline-block"]
            );

            // Enable/disable link.
            if ($metric->is_enabled()) {
                $class = '';
                $hideshow = \html_writer::link(
                    $url->out(false, ['action' => 'disable']),
                    $OUTPUT->pix_icon('t/hide', $txt->disable, 'moodle', ['class' => 'iconsmall'])
                );
            } else {
                $class = 'dimmed_text';
                $hideshow = \html_writer::link(
                    $url->out(false, ['action' => 'enable']),
                    $OUTPUT->pix_icon('t/show', $txt->enable, 'moodle', ['class' => 'iconsmall'])
                );
            }

            // Settings link.
            $settingsurl = $metric->get_settings_url();
            if (is_null($settingsurl)) {
                $attributes = ['class' => 'invisible'];
            } else {
                $attributes = [];
            }
            $settingslink = \html_writer::link(
                $settingsurl,
                $OUTPUT->pix_icon('a/setting', $txt->settings),
                $attributes
            );

            // Chart link.
            $url = new \moodle_url('/admin/tool/cloudmetrics/collector/database/chart.php', [$metric->get_name() => 1]);
            if (!$metric->is_enabled()) {
                $attributes = ['class' => 'invisible'];
            } else {
                $attributes = [];
            }
            $chartlink = \html_writer::link(
                $url,
                $OUTPUT->pix_icon('i/report', $txt->report),
                $attributes
            );

            $backfillurl = new \moodle_url('/admin/tool/cloudmetrics/collector/database/backfill.php', ['metric' => $metric->get_name()]);
            // Metric backfill support and if so - link.
            if ($metric->is_backfillable()) {
                $class = '';
                $backfilllink = \html_writer::link($backfillurl->out(false), $metric->get_label() . ' backfill');
            } else {
                $backfilllink = get_string('no_support', 'tool_cloudmetrics');
            }

            // Inplace editables required an integer ID, whereas metrics are identified with strings.
            // We use a hash function to convert to an integer, to future proof the code.
            $intid = hexdec(substr(md5($metric->get_name()), 0, 8));

            $options = manager::get_frequency_labels();
            if ($metric->is_frequency_fixed()) {
                $freq = $options[$metric->get_frequency()];
            } else {
                $editable = new inplace_editable(
                    'tool_cloudmetrics',
                    'metrics_freq',
                    $intid,
                    true,
                    null,
                    $metric->get_frequency(),
                    get_string('change_frequency', 'tool_cloudmetrics'),
                    $txt->frequency
                );
                $editable->set_type_select($options);
                $freq = $OUTPUT->render($editable);
            }

            $row = new \html_table_row([
                $metric->get_plugin_name(),
                $swatch . ' ' . $displayname,
                $group,
                $description,
                $freq,
                $hideshow . $settingslink . $chartlink,
                $backfilllink,
            ]);
            if ($class) {
                $row->attributes['class'] = $class;
            }
            $table->data[] = $row;
        }
        $return = \html_writer::table($table);
        return highlight($query, $return);
    }
}
