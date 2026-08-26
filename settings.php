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
 * Settings for local_briefingexpiry.
 *
 * @package    local_briefingexpiry
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_briefingexpiry', get_string('pluginname', 'local_briefingexpiry'));

    $settings->add(new admin_setting_configtext(
        'local_briefingexpiry/warningdays',
        get_string('warningdays', 'local_briefingexpiry'),
        get_string('warningdays_desc', 'local_briefingexpiry'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_briefingexpiry/notifyexpired',
        get_string('notifyexpired', 'local_briefingexpiry'),
        get_string('notifyexpired_desc', 'local_briefingexpiry'),
        1
    ));

    $settings->add(new admin_setting_users_with_capability(
        'local_briefingexpiry/recipients',
        get_string('recipients', 'local_briefingexpiry'),
        get_string('recipients_desc', 'local_briefingexpiry'),
        [],
        'local/briefingexpiry:receivenotifications'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_briefingexpiry/includeunenrolled',
        get_string('includeunenrolled', 'local_briefingexpiry'),
        get_string('includeunenrolled_desc', 'local_briefingexpiry'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_briefingexpiry/enableautoreset',
        get_string('enableautoreset', 'local_briefingexpiry'),
        get_string('enableautoreset_desc', 'local_briefingexpiry'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_briefingexpiry/resetquizattempts',
        get_string('resetquizattempts', 'local_briefingexpiry'),
        get_string('resetquizattempts_desc', 'local_briefingexpiry'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_briefingexpiry/notifystudent',
        get_string('notifystudent', 'local_briefingexpiry'),
        get_string('notifystudent_desc', 'local_briefingexpiry'),
        1
    ));

    $ADMIN->add('localplugins', $settings);
}

$ADMIN->add('reports', new admin_externalpage(
    'local_briefingexpiry_report',
    get_string('archivereport', 'local_briefingexpiry'),
    new moodle_url('/local/briefingexpiry/report.php'),
    'local/briefingexpiry:viewreport'
));
