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

namespace tool_cloudmetrics\metric;

/**
 * Manager class for metrics
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int Gauge metric type. */
    const TYPE_GAUGE = 1;

    /** @var int Per minute frequency. */
    const FREQ_MIN = 1;

    /** @var int Per 5 minutes frequency. */
    const FREQ_5MIN = 2;

    /** @var int Per 15 minutes frequency. */
    const FREQ_15MIN = 4;

    /** @var int Per 30 minutes frequency. */
    const FREQ_30MIN = 8;

    /** @var int Per hour frequency. */
    const FREQ_HOUR = 16;

    /** @var int Per 3 hours frequency. */
    const FREQ_3HOUR = 32;

    /** @var int Per 12 hours frequency. */
    const FREQ_12HOUR = 128;

    /** @var int Per day frequency. */
    const FREQ_DAY = 512;

    /** @var int Per week frequency. */
    const FREQ_WEEK = 1024;

    /** @var int Per month frequency. */
    const FREQ_MONTH = 4096;

    /**
     * Get frequency labels
     *
     * @return array
     */
    public static function get_frequency_labels(): array {
        return [
            self::FREQ_MIN => get_string('one_minute', 'tool_cloudmetrics'),
            self::FREQ_5MIN => get_string('five_minutes', 'tool_cloudmetrics'),
            self::FREQ_15MIN => get_string('fifteen_minutes', 'tool_cloudmetrics'),
            self::FREQ_30MIN => get_string('thirty_minutes', 'tool_cloudmetrics'),
            self::FREQ_HOUR => get_string('one_hour', 'tool_cloudmetrics'),
            self::FREQ_3HOUR => get_string('three_hour', 'tool_cloudmetrics'),
            self::FREQ_12HOUR => get_string('twelve_hour', 'tool_cloudmetrics'),
            self::FREQ_DAY => get_string('one_day', 'tool_cloudmetrics'),
            self::FREQ_WEEK => get_string('one_week', 'tool_cloudmetrics'),
            self::FREQ_MONTH => get_string('one_month', 'tool_cloudmetrics'),
        ];
    }

    /** @var int[string] Default settings for builtin metric frequencies. */
    const FREQ_DEFAULTS = [
        'activeusers' => self::FREQ_DAY,
        'newusers' => self::FREQ_DAY,
        'onlineusers' => self::FREQ_5MIN,
        'dailyusers' => self::FREQ_DAY,
        'yearlyactiveusers' => self::FREQ_DAY,
    ];

    /**
     * Gets all metrics installed on the system. Returns an associative array of all metrics installed.
     *
     * @param bool $enabledonly
     * @return array An associative array of name => metric.
     */
    public static function get_metrics(bool $enabledonly = true): array {
        // Builtin metrics.
        $metrics = [
            'activeusers' => new active_users_metric(),
            'newusers' => new new_users_metric(),
            'onlineusers' => new online_users_metric(),
            'dailyusers' => new daily_users_metric(),
            'yearlyactiveusers' => new yearly_active_users_metric(),
            'currenttaskcount' => new current_task_count_metric(),
        ];
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            // Add testing metrics to the list.
            $metrics['foobar'] = new test_metric();
        }
        // Find metrics from plugins.
        $more = get_plugins_with_function('metrics', 'lib.php');
        foreach ($more as $plugins) {
            foreach ($plugins as $pluginfunction) {
                $result = $pluginfunction();
                foreach ($result as $metric) {
                    $metrics[$metric->get_name()] = $metric;
                }
            }
        }

        if ($enabledonly) {
            foreach ($metrics as $name => $metric) {
                if (!$metric->is_enabled()) {
                    unset($metrics[$name]);
                }
            }
        }

        // Sort them so the most frequent metrics are first.
        uasort($metrics, function ($a, $b) {
            return $a->get_frequency() <=> $b->get_frequency();
        });

        return $metrics;
    }
}
