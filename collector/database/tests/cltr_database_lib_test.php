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

namespace cltr_database;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use tool_cloudmetrics\metric\manager;

/**
 * Unit test for lib class
 *
 * @package cltr_database
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversMethod(\cltr_database\lib::class, 'get_metrics_aggregated_for_chart')]
final class cltr_database_lib_test extends \advanced_testcase {
    /**
     * Set up before each test.
     *
     * The clock is frozen to the real current time. This keeps the mocked clock (used to build the
     * database query window) consistent with the raw time() calls used by the padding logic.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Inserts a single metric record directly into the collector's table.
     *
     * @param string $name The metric name.
     * @param int $time The timestamp of the record.
     * @param int|float $value The recorded value.
     */
    private function insert_record(string $name, int $time, int|float $value): void {
        global $DB;
        $DB->insert_record(lib::TABLE, [
            'name' => $name,
            'value' => $value,
            'time' => $time,
            'date' => lib::get_midnight_of((string) $time)->getTimestamp(),
        ]);
    }

    /**
     * Returns the values of an array with all null entries removed, re-indexed.
     *
     * The padding and gap-filling logic only ever inserts nulls, so filtering nulls isolates the
     * data points that came from real records, which is stable regardless of edge padding.
     *
     * @param array $arr
     * @return array
     */
    private function non_null(array $arr): array {
        return array_values(array_filter($arr, fn($v) => $v !== null));
    }

    /**
     * Tests lib::get_metrics_aggregated_for_chart() over a range of inputs.
     *
     * @param array $records List of [name, bucketoffset, value]. bucketoffset is a (usually negative)
     *        integer multiple of $aggregatefreqtime placing the record relative to "now".
     * @param array $displayedmetrics
     * @param int $graphperiodsec
     * @param int $aggregatefreqtime
     * @param int $displayfrequency
     * @param int $maxrecords
     * @param array $expectedvalues Expected non-null values keyed by metric, in ascending time order.
     * @param array $expectedmins Expected non-null mins (single metric only).
     * @param array $expectedmaxs Expected non-null maxs (single metric only).
     * @param array $expecteddiffs Expected diffs (single metric only).
     */
    #[DataProvider('chart_provider')]
    public function test_get_metrics_aggregated_for_chart(
        array $records,
        array $displayedmetrics,
        int $graphperiodsec,
        int $aggregatefreqtime,
        int $displayfrequency,
        int $maxrecords,
        array $expectedvalues,
        array $expectedmins,
        array $expectedmaxs,
        array $expecteddiffs
    ): void {
        $nowts = $this->mock_clock_with_frozen()->time();
        foreach ($records as [$name, $bucketoffset, $value]) {
            $this->insert_record($name, $nowts + $bucketoffset * $aggregatefreqtime, $value);
        }

        [$count, $times, $labels, $values, $mins, $maxs, $diffs] = lib::get_metrics_aggregated_for_chart(
            $displayedmetrics,
            $graphperiodsec,
            $aggregatefreqtime,
            $displayfrequency,
            $maxrecords
        );

        // The value series must exist for exactly the requested metrics.
        // TODO maybe have these sorted.
        $this->assertEquals($displayedmetrics, array_keys($values));

        // Times, labels and each value series are all kept in lock-step with $count.
        $this->assertCount($count, $times);
        $this->assertCount($count, $labels);
        foreach ($displayedmetrics as $metric) {
            $this->assertCount($count, $values[$metric]);
        }

        // The real (non-null) data points must match the aggregated records.
        foreach ($expectedvalues as $metric => $expected) {
            $this->assertEquals($expected, $this->non_null($values[$metric]));
        }

        if (count($displayedmetrics) == 1) {
            $this->assertEquals($expectedmins, $this->non_null($mins));
            $this->assertEquals($expectedmaxs, $this->non_null($maxs));
            $this->assertEquals($expecteddiffs, $diffs);
        } else {
            // Min/max/diff series are only produced for single-metric charts.
            $this->assertSame([], $mins);
            $this->assertSame([], $maxs);
            $this->assertSame([], $diffs);
        }
    }

    /**
     * Data provider for test_get_metrics_aggregated_for_chart().
     *
     * @return array
     */
    public static function chart_provider(): array {
        return [
            'single metric, one record per adjacent bucket' => [
                'records' => [
                    ['mock', -3, 5],
                    ['mock', -2, 7],
                    ['mock', -1, 9],
                ],
                'displayedmetrics' => ['mock'],
                'graphperiodsec' => 300 * 6,
                'aggregatefreqtime' => 300,
                'displayfrequency' => manager::FREQ_5MIN,
                'maxrecords' => 1000,
                'expectedvalues' => ['mock' => [5, 7, 9]],
                'expectedmins' => [5, 7, 9],
                'expectedmaxs' => [5, 7, 9],
                'expecteddiffs' => [0, 0, 0],
            ],
            'single metric, values averaged within a bucket and rounded' => [
                'records' => [
                    ['mock', -2, 1],
                    ['mock', -2, 2],
                    ['mock', -1, 4],
                    ['mock', -1, 5],
                ],
                'displayedmetrics' => ['mock'],
                'graphperiodsec' => 600 * 5,
                'aggregatefreqtime' => 600,
                'displayfrequency' => manager::FREQ_5MIN,
                'maxrecords' => 1000,
                'expectedvalues' => ['mock' => [1.5, 4.5]],
                'expectedmins' => [1, 4],
                'expectedmaxs' => [2, 5],
                'expecteddiffs' => [1, 1],
            ],
            'multiple metrics, no min/max/diff series' => [
                'records' => [
                    ['mock', -2, 10],
                    ['mock', -1, 20],
                    ['mock2', -2, 100],
                    ['mock2', -1, 200],
                ],
                'displayedmetrics' => ['mock', 'mock2'],
                'graphperiodsec' => 300 * 6,
                'aggregatefreqtime' => 300,
                'displayfrequency' => manager::FREQ_5MIN,
                'maxrecords' => 1000,
                'expectedvalues' => ['mock' => [10, 20], 'mock2' => [100, 200]],
                'expectedmins' => [],
                'expectedmaxs' => [],
                'expecteddiffs' => [],
            ],
            'month display frequency exercises the month label branch' => [
                'records' => [
                    ['mock', -2, 50],
                    ['mock', -1, 80],
                ],
                'displayedmetrics' => ['mock'],
                'graphperiodsec' => 2592000 * 4,
                'aggregatefreqtime' => 2592000,
                'displayfrequency' => manager::FREQ_MONTH,
                'maxrecords' => 1000,
                'expectedvalues' => ['mock' => [50, 80]],
                'expectedmins' => [50, 80],
                'expectedmaxs' => [50, 80],
                'expecteddiffs' => [0, 0],
            ],
        ];
    }

    /**
     * An empty table produces an empty result set.
     */
    public function test_empty_table(): void {
        [$count, $times, $labels, $values, $mins, $maxs, $diffs] = lib::get_metrics_aggregated_for_chart(
            ['mock'],
            300 * 10,
            300,
            2
        );

        $this->assertSame(0, $count);
        $this->assertSame([], $times);
        $this->assertSame([], $labels);
        $this->assertSame([], $values);
        $this->assertSame([], $mins);
        $this->assertSame([], $maxs);
        $this->assertSame([], $diffs);
    }

    /**
     * A gap larger than twice the aggregate interval is filled with nulls between the data points.
     */
    public function test_gap_filling(): void {
        $nowts = $this->mock_clock_with_frozen()->time();
        $agg = 300;
        // Two records five buckets apart, i.e. a four-bucket gap (> 2 * agg) between them.
        $this->insert_record('mock', $nowts - 5 * $agg, 10);
        $this->insert_record('mock', $nowts - 1 * $agg, 20);

        [, , , $values] = lib::get_metrics_aggregated_for_chart(['mock'], $agg * 7, $agg, 2);

        $series = $values['mock'];
        $this->assertEquals([10, 20], $this->non_null($series));

        // The two data points must not be adjacent; nulls were inserted to show the break.
        $datakeys = array_keys(array_filter($series, fn($v) => $v !== null));
        $this->assertCount(2, $datakeys);
        $this->assertGreaterThan(1, $datakeys[1] - $datakeys[0]);
    }

    /**
     * Test that zero is not confused with null.
     */
    public function test_zero_value_is_not_null(): void {
        $nowts = $this->mock_clock_with_frozen()->time();
        $agg = 300;
        $this->insert_record('mock', $nowts - 2 * $agg, 0);
        $this->insert_record('mock', $nowts - 1 * $agg, 5);

        [, , , $values, $mins, $maxs, $diffs] = lib::get_metrics_aggregated_for_chart(['mock'], $agg * 5, $agg, 2);

        $this->assertEquals([0, 5], $this->non_null($values['mock']));
        $this->assertEquals([0, 5], $this->non_null($mins));
        $this->assertEquals([0, 5], $this->non_null($maxs));
        $this->assertEquals([0, 0], $diffs);
    }

    /**
     * The total number of data points never exceeds $maxrecords, even when padding would add more.
     */
    public function test_max_records_cap(): void {
        $nowts = $this->mock_clock_with_frozen()->time();
        $agg = 300;
        $maxrecords = 4;
        $this->insert_record('mock', $nowts - 2 * $agg, 1);
        $this->insert_record('mock', $nowts - 1 * $agg, 2);

        // A period far larger than maxrecords * agg would pad well beyond the cap if unbounded.
        [$count, $times, $labels, $values] = lib::get_metrics_aggregated_for_chart(
            ['mock'],
            $agg * 100,
            $agg,
            2,
            $maxrecords
        );

        $this->assertLessThanOrEqual($maxrecords, $count);
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertCount($count, $times);
        $this->assertCount($count, $labels);
        $this->assertCount($count, $values['mock']);
    }
}
