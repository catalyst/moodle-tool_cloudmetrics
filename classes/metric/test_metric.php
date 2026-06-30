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

use tool_cloudmetrics\lib;

/**
 * Test metric that generates a random trail of integers.
 *
 * @package   tool_cloudmetrics
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_metric extends base {
    /** @var bool  */
    public static bool $isincremental = false;

    /** @var bool Whether the metric can be backfilled during an upgrade or install. */
    public static bool $canbackfillduringupgrade = true;

    /** @var string  */
    public $group = 'task_activity';

    /** @var int The frequency of the metric's sampling. */
    public static int $frequency = manager::FREQ_MIN;

    /** @var string Metric name. */
    public $name = 'foobar';

    /** @var int The value to be used next. */
    public $value = 100;
    /** @var int The amount the value may vary by (+/-) between generates. */
    public $variance = 10;

    #[\Override]
    public function get_name(): string {
        return $this->name;
    }

    #[\Override]
    public function get_label(): string {
        return 'Test metric'; // Don't use get_string as this is for testing only.
    }

    #[\Override]
    public function get_colour(): string {
        return '#ffffff';
    }

    #[\Override]
    public function get_description(): string {
        return 'Test metric';
    }

    #[\Override]
    public function get_plugin_name(): string {
        return 'tool_cloudmetrics';
    }

    #[\Override]
    public function get_frequency(): int {
        return self::$frequency;
    }

    #[\Override]
    public function get_frequency_default(): int {
        return manager::FREQ_MIN;
    }

    #[\Override]
    public function set_frequency(int $freq) {
        self::$frequency = $freq;
    }

    #[\Override]
    public function get_type(): int {
        return manager::TYPE_GAUGE;
    }

    #[\Override]
    public function is_enabled(): bool {
        return true;
    }

    #[\Override]
    public function is_backfillable(): bool {
        return true;
    }

    #[\Override]
    public function can_backfill_during_upgrade(): bool {
        return self::$canbackfillduringupgrade;
    }

    #[\Override]
    public function is_backfill_incremental(): bool {
        return self::$isincremental;
    }

    #[\Override]
    public function generate_metric_item($starttime, $finishtime): metric_item {
        $item = new metric_item($this->get_name(), $finishtime, $this->value, $this);
        $this->value += rand(-$this->variance, $this->variance);
        return $item;
    }

    #[\Override]
    public function generate_metric_items(
        int $starttime,
        ?int $finishtime = null,
        ?\progress_bar $progress = null
    ): \Iterator {
        $finishtime = $finishtime ?? \core\di::get(\core\clock::class)->time();
        $items = [];
        for ($time = $starttime; $time <= $finishtime; $time = lib::get_next_time($time, $this->get_frequency())) {
            $items[] = $this->generate_metric_item(lib::get_previous_time($time, $this->get_frequency()), $time);
        }
        $total = count($items);
        $items = array_reverse($items);
        $i = 0;
        foreach ($items as $item) {
            yield $item;
            ++$i;
            if ($progress) {
                $progress->update(
                    $i,
                    $total,
                    'test_metric'
                );
            }
        }
        return new \ArrayIterator(array_reverse($items));
    }
}
