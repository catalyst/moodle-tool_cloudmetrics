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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/../../../tests/metric_testcase.php"); // This is needed. File will not be automatically included.

use tool_cloudmetrics\collector\manager as collectormanager;
use tool_cloudmetrics\metric\manager;
use tool_cloudmetrics\metric\online_users_metric;
use tool_cloudmetrics\metric\active_users_metric;

/**
 * Unit test for database collector
 *
 * @package   cltr_database
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cltr_database_test extends \tool_cloudmetrics\metric_testcase {
    /** @var int Hours in a day*/
    const DAYHOURS = 24;

    /**
     * Set up before each test
     */
    protected function setUp(): void {
        global $DB;
        $DB->delete_records(lib::TABLE);
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the basic properties of a database collector.
     */
    public function test_basic_properties(): void {
        $collector = collectormanager::get_collector('database');
        $this->assertEquals('cltr_database\\collector', $collector::class);

        $this->assertTrue($collector->is_readable());
        $this->assertTrue($collector->supports_backfillable_metrics());
        $this->assertTrue($collector->is_auto_backfill());

        $this->assertFalse($collector->get_last_backfilled_frequency('foobar'));
        $collector->set_last_backfilled_frequency('foobar', manager::FREQ_5MIN);
        $this->assertEquals(manager::FREQ_5MIN, $collector->get_last_backfilled_frequency('foobar'));
    }

    /**
     * Test get_midnight_of.
     *
     * @dataProvider midnight_provider
     * @covers \cltr_database\lib::get_midnight_of
     * @param string $datestr
     * @param string  $expected   The expected result of the transformation
     */
    public function test_midnight(string $datestr, string $expected) {
        $tz = \core_date::get_server_timezone_object();
        $time = lib::get_midnight_of($datestr, $tz);
        $expecteddate = new \DateTimeImmutable($expected, $tz);
        $this->assertEquals($expecteddate->format(\DateTime::ATOM), $time->format(\DateTime::ATOM));
    }

    /**
     * Data for test_midnight.
     * The first value is the date string that will be converted to midnight.
     * The second value is what's expected, either today, tomorrow or yesterday.
     *
     * @return \string[][]
     */
    public function midnight_provider(): array {
        return [
            ['today -5 hours', 'yesterday'],
            ['today +20 hours', 'today'],
            ['today', 'today'],
            ['tomorrow +5 seconds', 'tomorrow'],
            ['tomorrow -3 seconds', 'today'],
            ['today +26 hours', 'tomorrow'],
            ['tomorrow -26 hours', 'yesterday'],
            ['midnight +5 hours', 'today'],
            ['-1 second midnight', 'yesterday' ],
        ];
    }

    /**
     * Tests get_midnight_of's ability to handle timestamps.
     *
     * @dataProvider midnight_provider
     * @covers \cltr_database\lib::get_midnight_of
     * @param string $datestr
     * @param string $expected
     */
    public function test_midnight_timestamp(string $datestr, string $expected) {
        $tz = \core_date::get_server_timezone_object();
        $ti = new \DateTimeImmutable($datestr, $tz);
        $ts = $ti->getTimestamp();
        $time = lib::get_midnight_of($ts, $tz);
        $expecteddate = new \DateTimeImmutable($expected, $tz);
        $this->assertEquals($expecteddate->format(\DateTime::ATOM), $time->format(\DateTime::ATOM));
    }

    /**
     * Tests the database collector
     *
     * @covers \cltr_database\collector
     */
    public function test_collector() {
        global $DB;

        $stub = $this->get_metric_stub([1, 2, 3]);
        $collector = new collector();

        $rec = $DB->get_records(lib::TABLE);
        $this->assertEquals(0, count($rec));

        $time = 100;
        $collector->record_metric($stub->generate_metric_item(0, $time));
        $time += 10;

        // Should have one metric of value 1.
        $rec = array_values($DB->get_records(lib::TABLE));
        $this->assertEquals(1, count($rec));
        $this->assertEquals('mock', $rec[0]->name);
        $this->assertEquals('1', $rec[0]->value);

        $collector->record_metric($stub->generate_metric_item(0, $time));
        $time += 10;

        // Should have two metrics of values 1 & 2.
        $rec = array_values($DB->get_records(lib::TABLE, null, 'time ASC'));
        $this->assertEquals(2, count($rec));
        $this->assertEquals('mock', $rec[0]->name);
        $this->assertEquals('1', $rec[0]->value);
        $this->assertEquals('mock', $rec[1]->name);
        $this->assertEquals('2', $rec[1]->value);

        // Should get the same result with get_metrics().
        $rec = array_values($collector->get_metrics('mock'));
        $this->assertEquals(2, count($rec));
        $this->assertEquals('1', $rec[0]->value);
        $this->assertEquals('2', $rec[1]->value);

        $collector->record_metric($stub->generate_metric_item(0, $time));
        $time += 10;
        $collector->record_metric($stub->generate_metric_item(0, $time));

        // Should have four metrics of values 1, 2, 3 & 1.
        $rec = array_values($collector->get_metrics('mock'));
        $this->assertEquals(4, count($rec));
        $this->assertEquals('1', $rec[0]->value);
        $this->assertEquals('2', $rec[1]->value);
        $this->assertEquals('3', $rec[2]->value);
        $this->assertEquals('1', $rec[3]->value);
    }

    /**
     * Test get_metric_range()
     *
     * @covers \cltr_database\collector::get_metric_range
     */
    public function test_get_metric_range(): void {
        $stub = $this->get_metric_stub([1, 2, 3]);
        $collector = new collector();

        // Test empty database.
        $range = $collector->get_metric_range('mock');
        $this->assertNull($range);

        for ($time = 100; $time <= 200; $time += 10) {
            $collector->record_metric($stub->generate_metric_item(0, $time));
        }

        // Test range values.
        $range = $collector->get_metric_range('mock');
        $this->assertTrue(isset($range['mintime']));
        $this->assertTrue(isset($range['maxtime']));
        $this->assertEquals(100, $range['mintime']);
        $this->assertEquals(200, $range['maxtime']);
    }

    /**
     * Test expiry metric_expiry task is working properly
     *
     * @dataProvider expiry_provider
     * @covers \cltr_database\task\metrics_cleanup_task
     * @param int $daysago
     * @param int $houradjustment
     * @param int $expiry
     * @param int $numrecords
     * @param int $numexpected
     * @throws \dml_exception
     */
    public function test_expiry(int $daysago, int $houradjustment, int $expiry, int $numrecords, int $numexpected) {
        global $DB;

        $tz = \core_date::get_server_timezone_object();

        // Get the starting time. The number of days ago, adjusted to midnight, then adjusted again by the
        // hour adjustment.
        $datestr = '-' . $daysago . ' days';
        $time = lib::get_midnight_of($datestr, $tz);
        if ($houradjustment >= 0) {
            $time->add(new \DateInterval('PT' . $houradjustment . 'H'));
            $expectedhour = sprintf('%02d:00:00', $houradjustment);
        } else {
            $time->sub(new \DateInterval('PT' . abs($houradjustment) . 'H'));
            $expectedhour = sprintf('%02d:00:00', (self::DAYHOURS - abs($houradjustment)));
        }
        $this->assertEquals($expectedhour, $time->format('H:i:s')); // Sanity check.

        $time = $time->getTimestamp();

        $stub = $this->get_metric_stub([1, 2, 3]);
        $collector = new collector();

        for ($i = 0; $i < $numrecords; ++$i) {
            $collector->record_metric($stub->generate_metric_item(0, $time));
            $time += DAYSECS;
        }

        // Sanity check. There should be $numrecords items in the database.
        $count = $DB->count_records(lib::TABLE);
        $this->assertEquals($numrecords, $count);

        // We want to remove data recorded as more than expiry seconds old.
        set_config('metric_expiry', $expiry, 'cltr_database');

        $task = new \cltr_database\task\metrics_cleanup_task();
        ob_start();
        $task->execute();
        ob_end_clean();

        // There should now be only $numexpected items in the database.
        $count = $DB->count_records(lib::TABLE);
        $this->assertEquals($numexpected, $count);
    }

    /**
     *  Data provider for test_expiry()
     *
     * @return array
     */
    public function expiry_provider(): array {
        return [
            [20, 10, 10 * DAYSECS, 20, 10],
            [20, -2, 10 * DAYSECS, 20, 9],
            [10, 0, 8 * DAYSECS, 5, 3],
        ];
    }

    /**
     * Tests lib::period_from_inerval().
     *
     * @dataProvider period_from_interval_provider
     * @covers \cltr_database\lib::period_from_interval
     * @param int $freq
     * @param int $expected
     */
    public function test_period_from_interval(int $freq, int $expected) {
        $metric = new \tool_cloudmetrics\metric\online_users_metric();
        $metric->set_frequency($freq);
        $this->assertEquals($expected, lib::period_from_interval($metric));
    }

    /**
     * Provider for test_period_from_interval().
     *
     * @return array[]
     */
    public function period_from_interval_provider(): array {
        return [
            [ manager::FREQ_MIN, DAYSECS * 7],
            [ manager::FREQ_5MIN, DAYSECS * 7],
            [ manager::FREQ_15MIN, DAYSECS * 30],
            [ manager::FREQ_30MIN, DAYSECS * 30],
            [ manager::FREQ_HOUR, DAYSECS * 30],
            [ manager::FREQ_3HOUR, DAYSECS * 30],
            [ manager::FREQ_12HOUR, DAYSECS * 120],
            [ manager::FREQ_DAY, DAYSECS * 120],
            [ manager::FREQ_WEEK, DAYSECS * 120],
            [ manager::FREQ_MONTH, DAYSECS * 365],
        ];
    }
}
