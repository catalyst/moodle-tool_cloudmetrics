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

namespace tool_cloudmetrics\output;

/**
 * Class renderer for rendering chart page.
 *
 * @package    tool_cloudmetrics
 * @copyright  2022, Catalyst IT
 * @author     2022 Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the chart page.
     *
     * @param array $data
     */
    public function render_chart_page($data) {
        return $this->render_from_template('tool_cloudmetrics/chart_page', $data);
    }

    /**
     * Render the backfill page.
     *
     * @param array $data
     */
    public function render_backfill_page($data) {
        return $this->render_from_template('tool_cloudmetrics/backfill_page', $data);
    }

    /**
     * Render the reset page.
     *
     * @param array $data
     */
    public function render_reset_page($data) {
        return $this->render_from_template('tool_cloudmetrics/reset_page', $data);
    }
}
