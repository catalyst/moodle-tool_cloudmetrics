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
 * Shows a chart of recorded metrics.
 *
 * @package   cltr_database
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\chart_line;
use core\chart_series;
use tool_cloudmetrics\lib;
use tool_cloudmetrics\metric;
use tool_cloudmetrics\metric\manager;

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('cltr_database_chart');

$context = context_system::instance();

$url = new moodle_url('/admin/tool/cloudmetrics/collector/database/chart.php');

$PAGE->set_context($context);
$PAGE->set_url($url);

// Graph display timespan, in seconds.
$graphperiodsec = optional_param('graphperiod', -1, PARAM_INT);
// Group of metrics to display (if any).
$metricgroup = optional_param('groupselect', '', PARAM_ALPHANUMEXT);

$metrics = metric\manager::get_metrics(true);
if (empty($metrics)) {
    // Error management if no metrics enabled.
    throw new moodle_exception('no_metrics_enabled', 'cltr_database');
}

$metriclabels = [];
$checkboxes = [];
$displayedmetrics = [];
$notifications = [];
$groups = [];

foreach ($metrics as $m) {
    $metriclabels[$m->get_name()] = $m->get_label();

    $metricparam = optional_param($m->get_name(), 0, PARAM_INT);
    $displayed = false;
    if ($metricparam || ($metricgroup == $m->group)) {
        $displayedmetrics[] = $m->get_name();
        $displayed = true;
    }
    $checkbox = html_writer::checkbox(
        $m->get_name(),
        1,
        $displayed,
        '',
        ['id' => $m->get_name(), 'onchange' => 'this.form.submit()']
    );
    $label = html_writer::tag('label', $m->get_label(), ['for' => $m->get_name(), 'style' => 'display:inline-block; margin: 0;']);
    $color = html_writer::tag(
        'span',
        '',
        ['style' => 'display: inline-block; width: 2.5em; height: 1em; background-color: ' . $m->get_colour() .
            '; margin: 0 6px; vertical-align: middle;']
    );
    $checkboxes[] = [
        'checkbox' => html_writer::tag(
            'div',
            $checkbox . $color . $label,
            ['style' => 'display: inline-flex; align-items: center; margin-right: 16px;']
        ),
    ];

    if (!in_array($m->group, $groups) && !empty($m->group)) {
        $groups += [$m->group => get_string($m->group, 'tool_cloudmetrics')];
    }
}

if (empty($displayedmetrics)) {
    $displayedmetrics[] = reset($metrics)->get_name();
    $checkboxes[0] = ['checkbox' => html_writer::checkbox(
        reset($metrics)->get_name(),
        1,
        true,
        reset($metrics)->get_label(),
        ['onchange' => 'this.form.submit()']
    )];
}

if ($graphperiodsec === -1) {
    $graphperiodsec = get_config('cltr_database', 'chart_period');
    if (!$graphperiodsec) {
        $graphperiodsec = \cltr_database\lib::period_from_interval($metrics[$displayedmetrics[0]]);
    }
} else {
    require_login(null, false);
    require_capability('moodle/site:config', context_system::instance());
    set_config('chart_period', $graphperiodsec, 'cltr_database');
    \core_plugin_manager::reset_caches();
}

$context = [];

// Prepare time window selector.
$periods = [
    HOURSECS      => get_string('one_hour', 'tool_cloudmetrics'),
    HOURSECS * 2  => get_string('two_hours', 'tool_cloudmetrics'),
    HOURSECS * 3  => get_string('three_hour', 'tool_cloudmetrics'),
    HOURSECS * 4  => get_string('four_hours', 'tool_cloudmetrics'),
    HOURSECS * 6  => get_string('six_hours', 'tool_cloudmetrics'),
    HOURSECS * 8  => get_string('eight_hours', 'tool_cloudmetrics'),
    HOURSECS * 12 => get_string('twelve_hour', 'tool_cloudmetrics'),
    DAYSECS       => get_string('one_day', 'tool_cloudmetrics'),
    WEEKSECS      => get_string('one_week', 'tool_cloudmetrics'),
    WEEKSECS * 2  => get_string('two_week', 'tool_cloudmetrics'),
    DAYSECS * 30  => get_string('one_month', 'tool_cloudmetrics'),
    DAYSECS * 61  => get_string('two_month', 'tool_cloudmetrics'),
    DAYSECS * 122 => get_string('four_month', 'tool_cloudmetrics'),
    DAYSECS * 183 => get_string('six_month', 'tool_cloudmetrics'),
    YEARSECS      => get_string('twelve_month', 'tool_cloudmetrics'),
    YEARSECS * 2  => get_string('two_year', 'tool_cloudmetrics'),
];

$collector = new \cltr_database\collector();

$configfrequency = $metrics[$displayedmetrics[0]]->get_frequency();
// The frequency of the data points (if any).
$displayfrequency = optional_param('graphfrequency', $configfrequency ?? 1, PARAM_INT);
$selectedfrequency = $displayfrequency;

// Create a new URL object to avoid poisoning the existing one.
$url = clone $url;

$groupurl = clone $url;
$groupurl->param('graphfrequency', $displayfrequency);

foreach ($displayedmetrics as $displayedmetric) {
    $url->param($displayedmetric, 1);
}

$periodurl = clone $url;
$periodurl->param('graphfrequency', $displayfrequency);

$groupselect = new \single_select(
    $groupurl,
    'groupselect',
    $groups,
    $metricgroup
);

$periodselect = new \single_select(
    $periodurl,
    'graphperiod',
    $periods,
    $graphperiodsec
);

$freqoptions = manager::get_frequency_labels();
$freqselect = new \single_select(
    $url,
    'graphfrequency',
    $freqoptions,
    $displayfrequency
);

$backfillurl = new moodle_url('/admin/tool/cloudmetrics/collector/database/backfill.php', ['metric' => $displayedmetrics[0]]);

$groupselect->set_label(get_string('select_group', 'cltr_database'));

$periodselect->set_label(get_string('select_graph_period', 'cltr_database'));

$freqselect->set_label(get_string('select_graph_freq', 'cltr_database'));

$aggregatefreqtimes = lib::FREQ_TIMES;

// TODO Handle a month properly currently aggregated over last 30 days.
$aggregatefreqtimes[4096] = 30 * 24 * 60 * 60;
$aggregatefreqtime = $aggregatefreqtimes[$displayfrequency];

$maxrecords = 1000;

$values = [];
$labels = [];
$mins = [];
$maxs = [];
$diffs = [];
$count = 0;
$times = [];
$chart = new chart_line();

// We want to keep the number of data points to be below the maximum, so we scale up the time interval to reduce the
// number of data point obtained.
while ($graphperiodsec / $aggregatefreqtime > $maxrecords) {
    $nextfreq = lib::next_frequency($displayfrequency);
    if ($nextfreq === false) {
        break;
    }
    $displayfrequency = $nextfreq;
    $aggregatefreqtime = $aggregatefreqtimes[$displayfrequency];
}

$records = $collector->get_metrics_aggregated($displayedmetrics, $graphperiodsec, $maxrecords, $aggregatefreqtime);
$lastvaluearr = [];
foreach ($records as $record) {
    foreach ($displayedmetrics as $displayedmetric) {
        $value = !$record->{$displayedmetric} ? null : round($record->{$displayedmetric}, 1);
        $values[$displayedmetric][] = $value;
    }

    $times[] = (int) $record->increment_start;

    if (count($displayedmetrics) == 1) {
        $mins[] = (float)$record->min;
        $maxs[] = (float)$record->max;
        $diffs[] = (float)$record->max - (float)$record->min;
    }
    $count++;
}

if ($count) {
    // Insert padding at the end to get the chart to display the full time period.
    $latesttime = time();
    $currenttime = end($times) + $aggregatefreqtime;
    while ($currenttime <= $latesttime && $count < $maxrecords) {
        $times[] = $currenttime;
        foreach ($displayedmetrics as $displayedmetric) {
            $values[$displayedmetric][] = null;
        }
        if (count($displayedmetrics) == 1) {
            $mins[] = null;
            $maxs[] = null;
        }
        $currenttime += $aggregatefreqtime;
        ++$count;
    }

    // Insert padding at the beginning to get the chart to display the full time period.
    $earliesttime = time() - $graphperiodsec;
    $currenttime = $times[0] - $aggregatefreqtime;
    while ($currenttime >= $earliesttime && $count < $maxrecords) {
        array_unshift($times, $currenttime);
        foreach ($displayedmetrics as $displayedmetric) {
            array_unshift($values[$displayedmetric], null);
        }
        if (count($displayedmetrics) == 1) {
            array_unshift($mins, null);
            array_unshift($maxs, null);
        }
        $currenttime -= $aggregatefreqtime;
        ++$count;
    }

    // Make human readable labels for the times.

    // If freq 12hr or greater set to UTC.
    $timezone = $CFG->timezone;
    if ($displayfrequency >= 128) {
        $timezone = 'UTC';
    }

    foreach ($times as $time) {
        if ($displayfrequency == 4096) {
            // If time increment is month display data at start of month.
            $labels[] = userdate($time + $aggregatefreqtime, get_string('strftimemonth', 'cltr_database'), $timezone);
        } else {
            $labels[] = userdate($time, get_string('strftimedatetime', 'cltr_database'), $timezone);
        }
    }
}

foreach ($displayedmetrics as $displayedmetric) {
    $chartseries = new chart_series($metriclabels[$displayedmetric], $values[$displayedmetric] ?? null);
    $chartseries->set_color($metrics[$displayedmetric]->get_colour());
    if (count($displayedmetrics) > 1) {
        $chart->add_series($chartseries);
    }
}

$chart->set_labels($labels);

if (count($displayedmetrics) == 1) {
    $displayaggregates = true;
    // Calculate threshold for displaying aggregation or not.
    if (count($diffs) > 0) {
        $avgdiff = array_sum($diffs) / count($diffs);
        // If the ratio between the average(MAX-MIN) and the number of total data points is below 0.25 only display the default aggregate.
        if ($avgdiff / $count < 0.25) {
            $displayaggregates = false;
        }
    }
    $minseries = new chart_series('Minimum ' . $metriclabels[$displayedmetrics[0]], $mins);
    $color = $metrics[$displayedmetrics[0]]->get_colour();
    $minseries->set_color($metrics[$displayedmetrics[0]]->get_colour());
    $maxseries = new chart_series('Maximum ' . $metriclabels[$displayedmetrics[0]], $maxs);
    $maxseries->set_color($metrics[$displayedmetrics[0]]->get_colour());
    if ($displayaggregates || ($metrics[$displayedmetrics[0]]->aggregatedefault == 'AVG')) {
        $chart->add_series($chartseries);
    }
    if ($displayaggregates || ($metrics[$displayedmetrics[0]]->aggregatedefault == 'MIN')) {
        $chart->add_series($minseries);
    }
    if ($displayaggregates || ($metrics[$displayedmetrics[0]]->aggregatedefault == 'MAX')) {
        $chart->add_series($maxseries);
    }
    $context['backfillable'] = $metrics[$displayedmetrics[0]]->is_backfillable();
    $context['metriclabel'] = $metrics[$displayedmetrics[0]]->get_label();
    $context['metricdescription'] = $metrics[$displayedmetrics[0]]->get_description();
    $context['metriclabeltolower'] = strtolower($metrics[$displayedmetrics[0]]->get_label());
}

$context['chart'] = $OUTPUT->render($chart);
$context['groupselect'] = $OUTPUT->render($groupselect);
$context['periodselect'] = $OUTPUT->render($periodselect);
$context['freqselect'] = $OUTPUT->render($freqselect);
$context['backfillurl'] = $backfillurl;
$context['checkboxes'] = $checkboxes;
$context['metriclabel'] = $context['metriclabel'] ?? get_string('multiplemetrics', 'cltr_database');
$context['frequency'] = html_writer::empty_tag(
    'input',
    ['type' => 'hidden', 'name' => 'graphfrequency', 'value' => $displayfrequency]
);
$renderer = $PAGE->get_renderer('tool_cloudmetrics');

echo $OUTPUT->header();
echo $renderer->render_chart_page($context);
if ($count == 0) {
    echo $OUTPUT->notification(get_string('norecords', 'cltr_database', $maxrecords), 'info');
} else {
    echo $OUTPUT->notification(get_string('displaying_records', 'cltr_database', ['count' => $count, 'freq' => $freqoptions[$displayfrequency]]), 'info');
    if ($displayfrequency != $selectedfrequency) {
        echo $OUTPUT->notification(
            get_string(
                'different_frequency',
                'cltr_database',
                ['from' => $freqoptions[$selectedfrequency], 'to' => $freqoptions[$displayfrequency]]
            ),
            'info'
        );
    }
    if ($count === $maxrecords) {
        echo $OUTPUT->notification(get_string('maxrecords', 'cltr_database', $maxrecords), 'info');
    }
    if ($displayfrequency != $configfrequency) {
        echo $OUTPUT->notification(get_string('aggregated', 'cltr_database', $freqoptions[$displayfrequency]), 'info');
    }
}
echo $OUTPUT->footer();
