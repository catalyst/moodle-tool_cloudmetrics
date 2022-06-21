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
 * Menu displaying reset option concerning metrics.
 *
 * @package   cltr_database
 * @author    Ghaly Marc-Alexandre <marc-alexandreghaly@catalyst-ca.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_cloudmetrics\metric\manager;
use cltr_database\collector;
use cltr_database\form\metric_reset_form;

admin_externalpage_setup('cltr_database_reset');

$ctx = context_system::instance();

$url = new moodle_url('/admin/tool/cloudmetrics/collector/database/reset.php');

$PAGE->set_context($ctx);
$PAGE->set_url($url);

$metricname = optional_param('metric', 'onlineusers', PARAM_ALPHANUMEXT);

$metrics = manager::get_metrics(true);
$metriclabels = [];
foreach ($metrics as $m) {
    $metriclabels[$m->get_name()] = $m->get_label();
    if ($m->get_name() == $metricname) {
        $metric = $m;
    }
}
$collector = new collector();

// Error management if metric is not enabled.
if (!isset($metrics[$metricname])) {
    throw new moodle_exception('metric_not_enabled', 'tool_cloudmetrics', '', $metricname);
}

$mform = new metric_reset_form(null, [$metriclabels]);
$context['form'] = $mform->render();
if ($fromform = $mform->get_data()) {
    $metricselect = $fromform->metricselect;
    $resetdelete = (int)$fromform->resetdelete;
    $startdate = null;
    $stopdate = null;
    if ($resetdelete == 1) {
        $startdate = $fromform->assesstimestar ?? null;
        $stopdate = $fromform->assesstimeto ?? null;
    }
    $collector->delete_metrics($metricselect, $startdate, $stopdate);
    if (is_null($startdate) && is_null($stopdate)) {
        // Cleans config.
        $metrics[$metricselect]->unset_range_retrieved();
    }
    if ($resetdelete == 1) {
        \core\notification::success(get_string('properly_deleted', 'tool_cloudmetrics',
            [
            'name' => $metricselect,
            'startdate' => userdate($startdate, get_string('strftimedatetime', 'cltr_database'), $CFG->timezone),
            'enddate' => userdate($stopdate, get_string('strftimedatetime', 'cltr_database'), $CFG->timezone)]));
    } else {
        \core\notification::success(get_string('properly_reset', 'tool_cloudmetrics', $metricselect));
    }
}

$renderer = $PAGE->get_renderer('tool_cloudmetrics');

echo $OUTPUT->header();
echo $renderer->render_reset_page($context);
echo $OUTPUT->footer();
