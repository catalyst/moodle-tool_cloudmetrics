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
 * Plugin strings are defined here.
 *
 * @package   cltr_cloudwatch
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2022, Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['awskey'] = 'AWS access key';
$string['awskey_desc'] = 'The access key ID assigned to the IAM user.';
$string['awsregion'] = 'AWS region';
$string['awsregion_desc'] = 'The AWS region to use for API calls';
$string['awssecret'] = 'AWS access secret';
$string['awssecret_desc'] = 'The secret access key assigned to the IAM user.';
$string['awssettings'] = 'AWS settings';
$string['awssettings_desc'] = 'Settings for aws. The library automatically use iam role and environment variables. If you need a specific api key and secret, it needs to go into config.php, please see README.md.';
$string['awsversion'] = 'API version';
$string['awsversion_desc'] = "CloudWatch API version.";
$string['environment'] = 'Environment';
$string['environment_desc'] = 'Execution environment that will be presented as a dimension in CloudWatch.';
$string['generalsettings'] = 'General settings';
$string['generalsettings_desc'] = 'Settings for the general behaviour of the plugin';
$string['namespace'] = 'Namespace';
$string['namespace_desc'] = 'Unique namespace to store metrics under in CloudWatch. If left empty, then $CFG->wwwroot will be used';
$string['pluginname'] = 'AWS CloudWatch collector';
$string['pluginnamedesc'] = 'Cloudmetrics collector that exports metrics to AWS CloudWatch.';
$string['privacy:metadata'] = 'No personal information is stored';
$string['unsatisfied_requirements'] = 'Unsatisfied Requirements';
