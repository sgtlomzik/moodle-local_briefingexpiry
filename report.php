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
 * Archive report of reset briefing completions.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_briefingexpiry_report');

$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$total = $DB->count_records('local_briefingexpiry_arch');

$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$sql = "SELECT a.id, a.userid, a.courseid, a.timecompleted, a.timeexpires, a.timereset, a.finalgrade,
               c.fullname AS coursename, u.email, {$namefields}
          FROM {local_briefingexpiry_arch} a
          JOIN {user} u ON u.id = a.userid
          JOIN {course} c ON c.id = a.courseid
      ORDER BY a.timereset DESC, a.id DESC";
$records = $DB->get_records_sql($sql, [], $page * $perpage, $perpage);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('archivereport', 'local_briefingexpiry'));

if (empty($records)) {
    echo $OUTPUT->notification(get_string('digest_no_data', 'local_briefingexpiry'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('digest_header_fullname', 'local_briefingexpiry'),
        get_string('digest_header_course', 'local_briefingexpiry'),
        get_string('digest_header_completed', 'local_briefingexpiry'),
        get_string('digest_header_expires', 'local_briefingexpiry'),
        get_string('report_header_reset', 'local_briefingexpiry'),
        get_string('report_header_grade', 'local_briefingexpiry'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($records as $record) {
        $userurl = new moodle_url('/user/view.php', ['id' => $record->userid]);
        $courseurl = new moodle_url('/course/view.php', ['id' => $record->courseid]);
        $grade = ($record->finalgrade !== null) ? format_float((float)$record->finalgrade, 2) : '-';

        $table->data[] = [
            html_writer::link($userurl, fullname($record)) . '<br><small>' . s($record->email) . '</small>',
            html_writer::link($courseurl, format_string($record->coursename)),
            userdate($record->timecompleted, '%d.%m.%Y'),
            userdate($record->timeexpires, '%d.%m.%Y'),
            userdate($record->timereset, '%d.%m.%Y %H:%M'),
            $grade,
        ];
    }

    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url);
}

echo $OUTPUT->footer();
