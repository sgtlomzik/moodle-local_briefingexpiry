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
 * English strings for local_briefingexpiry.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['archivereport'] = 'Briefing reset archive';
$string['briefingexpiry:receivenotifications'] = 'Receive briefing expiry notifications';
$string['briefingexpiry:viewreport'] = 'View briefing reset archive report';
$string['customfieldcategory'] = 'Briefings';
$string['digest_expired_title'] = 'Expired Briefings';
$string['digest_expiring_title'] = 'Expiring Briefings (soon to expire)';
$string['digest_header_completed'] = 'Completion Date';
$string['digest_header_course'] = 'Course';
$string['digest_header_daysago'] = 'Days Expired';
$string['digest_header_daysleft'] = 'Days Left';
$string['digest_header_expires'] = 'Expiry Date';
$string['digest_header_fullname'] = 'Full Name';
$string['digest_intro'] = 'Hello!<br><br>Here is the daily summary report on employee briefing status.';
$string['digest_no_data'] = 'No data.';
$string['digest_subject'] = 'Summary report on expiring and expired briefings';
$string['digest_unenrolled_title'] = 'Unenrolled employees with expired briefings';
$string['enableautoreset'] = 'Global Auto-Reset';
$string['enableautoreset_desc'] = 'Enable automatic reset of course completion on briefing expiry (also requires auto-reset to be enabled in individual course settings).';
$string['field_autoreset'] = 'Automatically reset completion when the briefing expires';
$string['field_enabled'] = 'This course is a briefing';
$string['field_period'] = 'Briefing validity period';
$string['includeunenrolled'] = 'Include Unenrolled';
$string['includeunenrolled_desc'] = 'Whether to include unenrolled users with expired briefings in a separate block of the digest.';
$string['messageprovider:expirynotice'] = 'Briefing expiry notifications (for managers)';
$string['messageprovider:resetnotice'] = 'Course completion reset notifications (for students)';
$string['notifyexpired'] = 'Notify Expired';
$string['notifyexpired_desc'] = 'Send a notification when the briefing is already expired and has not been completed again.';
$string['notifystudent'] = 'Notify Student';
$string['notifystudent_desc'] = 'Send a notification to the student after their course completion is automatically reset, reminding them to retake it.';
$string['period_1year'] = '1 year';
$string['period_2years'] = '2 years';
$string['period_3months'] = '3 months';
$string['period_3years'] = '3 years';
$string['period_6months'] = '6 months';
$string['pluginname'] = 'Briefing Expiry Management';
$string['privacy:archpath'] = 'Briefing Resets Archive';
$string['privacy:logpath'] = 'Briefing Notifications Log';
$string['privacy:metadata:arch'] = 'Archive of course completion resets for briefing courses.';
$string['privacy:metadata:arch:courseid'] = 'The ID of the course.';
$string['privacy:metadata:arch:finalgrade'] = 'The final grade of the user before reset.';
$string['privacy:metadata:arch:timecompleted'] = 'The previous course completion time.';
$string['privacy:metadata:arch:timeexpires'] = 'The expiry date of the briefing.';
$string['privacy:metadata:arch:timereset'] = 'When the completion was reset.';
$string['privacy:metadata:arch:userid'] = 'The ID of the user.';
$string['privacy:metadata:log'] = 'Log of briefing expiry notifications to prevent duplicate alerts.';
$string['privacy:metadata:log:courseid'] = 'The ID of the course.';
$string['privacy:metadata:log:notificationtype'] = 'The type of notification sent (warning or expired).';
$string['privacy:metadata:log:timecompleted'] = 'When the briefing was completed.';
$string['privacy:metadata:log:timecreated'] = 'When the notification was sent.';
$string['privacy:metadata:log:timeexpires'] = 'When the briefing expires.';
$string['privacy:metadata:log:userid'] = 'The ID of the user.';
$string['recipients'] = 'Notification Recipients';
$string['recipients_desc'] = 'Users who will receive daily digests of expiring and expired briefings.';
$string['report_header_grade'] = 'Grade';
$string['report_header_reset'] = 'Reset Date';
$string['resetquizattempts'] = 'Reset Quiz Attempts';
$string['resetquizattempts_desc'] = 'When resetting course completion, also delete all user quiz attempts within the course.';
$string['student_notification_body'] = 'Hello, {$a->fullname}!<br><br>The validity period of your previous completion of the briefing course "{$a->coursename}" has expired.<br>Previous completion date: {$a->completeddate}<br>Expiry date: {$a->expirydate}<br><br>Please retake the briefing at the following link: <a href="{$a->courseurl}">{$a->coursename}</a>';
$string['student_notification_subject'] = 'You need to retake the briefing: {$a->coursename}';
$string['task_check_expiry'] = 'Check briefing expiry';
$string['warningdays'] = 'Warning Days';
$string['warningdays_desc'] = 'How many days before briefing expiry to send a warning notification.';
