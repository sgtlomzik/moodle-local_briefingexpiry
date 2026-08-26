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
 * Helper class for local_briefingexpiry.
 *
 * @package    local_briefingexpiry
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_briefingexpiry;

defined('MOODLE_INTERNAL') || die();

class helper {

    /**
     * Get all courses configured as briefing courses.
     *
     * @return array Array of course records
     */
    public static function get_briefing_courses(): array {
        global $DB;

        $sql = "SELECT c.*
                  FROM {course} c
                  JOIN {customfield_data} cd ON cd.instanceid = c.id
                  JOIN {customfield_field} cf ON cf.id = cd.fieldid AND cf.shortname = :shortname
                  JOIN {customfield_category} cc ON cc.id = cf.categoryid
                       AND cc.component = 'core_course' AND cc.area = 'course'
                 WHERE cd.intvalue = 1";
        return $DB->get_records_sql($sql, ['shortname' => 'briefing_enabled']);
    }

    /**
     * Convert a briefing_period custom field value into a DateTime modify() spec.
     *
     * The select field stores a 1-based option index in intvalue. String values are
     * accepted as a fallback in case the field options were re-created manually.
     *
     * @param mixed $val Value stored by the select field (1-based option index)
     * @param string|null $str Exported (display) value of the field
     * @return string|null Period spec such as '1 year', or null if not set
     */
    public static function get_period_spec($val, ?string $str): ?string {
        if ($val == 1 || $str === '6 месяцев' || $str === '6 months') {
            return '6 months';
        } else if ($val == 2 || $str === '1 год' || $str === '1 year') {
            return '1 year';
        } else if ($val == 3 || $str === '2 года' || $str === '2 years') {
            return '2 years';
        } else if ($val == 4 || $str === '3 года' || $str === '3 years') {
            return '3 years';
        } else if ($val == 5 || $str === '3 месяца' || $str === '3 months') {
            return '3 months';
        }
        return null;
    }

    /**
     * Calculate the expiry timestamp for a completion.
     *
     * @param int $timecompleted Completion timestamp
     * @param string $period Period spec such as '1 year'
     * @return int Expiry timestamp
     */
    public static function calculate_expiry(int $timecompleted, string $period): int {
        $dt = new \DateTime('now', \core_date::get_server_timezone_object());
        $dt->setTimestamp($timecompleted);
        $dt->modify('+' . $period);
        return $dt->getTimestamp();
    }

    /**
     * Get the final course grade of a user, or null if there is none.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return float|null
     */
    public static function get_course_final_grade(int $courseid, int $userid): ?float {
        global $CFG;
        require_once($CFG->dirroot . '/grade/querylib.php');

        $coursegrade = grade_get_course_grade($userid, $courseid);
        if ($coursegrade && $coursegrade->grade !== null && $coursegrade->grade !== false) {
            return (float)$coursegrade->grade;
        }
        return null;
    }

    /**
     * Performs targetted reset of course completion and grading for a user.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @throws \Exception
     */
    public static function reset_user_completion(int $courseid, int $userid) {
        global $DB, $CFG;

        $transaction = $DB->start_delegated_transaction();

        try {
            // Delete course completion records.
            $DB->delete_records('course_completions', ['course' => $courseid, 'userid' => $userid]);
            $DB->delete_records('course_completion_crit_compl', ['course' => $courseid, 'userid' => $userid]);

            // Delete activity completion records.
            $DB->delete_records_select('course_modules_completion',
                'userid = ? AND coursemoduleid IN (SELECT id FROM {course_modules} WHERE course = ?)',
                [$userid, $courseid]);
            $DB->delete_records_select('course_modules_viewed',
                'userid = ? AND coursemoduleid IN (SELECT id FROM {course_modules} WHERE course = ?)',
                [$userid, $courseid]);

            // Reset quiz attempts if enabled. This must happen before the
            // gradebook wipe: quiz_delete_attempt() recalculates quiz_grades and pushes
            // the result to the gradebook, so a stale quiz grade cannot resurrect later.
            $resetquiz = (bool)get_config('local_briefingexpiry', 'resetquizattempts');
            if ($resetquiz) {
                $quizzes = $DB->get_records('quiz', ['course' => $courseid]);
                if ($quizzes) {
                    require_once($CFG->dirroot . '/mod/quiz/lib.php');
                    require_once($CFG->dirroot . '/mod/quiz/locallib.php');
                    foreach ($quizzes as $quiz) {
                        $attempts = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $userid]);
                        foreach ($attempts as $attempt) {
                            quiz_delete_attempt($attempt, $quiz);
                        }
                    }
                }
            }

            // Clear gradebook entries for this user in this course.
            require_once($CFG->libdir . '/gradelib.php');
            $gradeitems = \grade_item::fetch_all(['courseid' => $courseid]);
            if ($gradeitems) {
                foreach ($gradeitems as $gradeitem) {
                    $gradegrade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
                    if ($gradegrade) {
                        $gradegrade->delete('local_briefingexpiry');
                    }
                }
            }

            // Purge completion and course caches.
            $completioncache = \cache::make('core', 'completion');
            $completioncache->delete("{$userid}_{$courseid}");
            $coursecompletioncache = \cache::make('core', 'coursecompletion');
            $coursecompletioncache->delete("{$userid}_{$courseid}");

            $course = get_course($courseid);
            \core_courseformat\base::session_cache_reset($course);
            get_fast_modinfo($courseid, 0, true);

            $transaction->allow_commit();
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Checks all completed courses for briefing expirations and auto-resets completion.
     */
    public static function check_expiry() {
        global $DB;

        $courses = self::get_briefing_courses();
        if (empty($courses)) {
            return;
        }

        $warningdays = get_config('local_briefingexpiry', 'warningdays');
        $warningdays = ($warningdays === false) ? 30 : (int)$warningdays;
        if ($warningdays < 0) {
            $warningdays = 30;
        }
        $notifyexpired = (bool)get_config('local_briefingexpiry', 'notifyexpired');
        $includeunenrolled = (bool)get_config('local_briefingexpiry', 'includeunenrolled');
        $globalautoreset = (bool)get_config('local_briefingexpiry', 'enableautoreset');
        $notifystudent = (bool)get_config('local_briefingexpiry', 'notifystudent');

        $expiring_users = [];
        $expired_users = [];
        $unenrolled_expired_users = [];
        $logs_to_write = [];

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        foreach ($courses as $course) {
            $handler = \core_course\customfield\course_handler::create();
            $instancesdata = $handler->get_instances_data([$course->id], true);
            $period = null;
            $autoreset = false;

            if (isset($instancesdata[$course->id])) {
                foreach ($instancesdata[$course->id] as $fielddata) {
                    $shortname = $fielddata->get_field()->get('shortname');
                    if ($shortname === 'briefing_period') {
                        $period = self::get_period_spec($fielddata->get_value(), (string)$fielddata->export_value());
                    } else if ($shortname === 'briefing_autoreset') {
                        $autoreset = (bool)$fielddata->get_value();
                    }
                }
            }

            if (empty($period)) {
                continue;
            }

            $sql = "SELECT cc.id, cc.userid, cc.timecompleted, u.email, u.lang, {$namefields}
                      FROM {course_completions} cc
                      JOIN {user} u ON u.id = cc.userid
                     WHERE cc.course = :courseid AND cc.timecompleted > 0 AND u.deleted = 0 AND u.suspended = 0";
            $completions = $DB->get_records_sql($sql, ['courseid' => $course->id]);

            if (empty($completions)) {
                continue;
            }

            $enrolled_userids = $DB->get_fieldset_sql("
                SELECT DISTINCT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND ue.status = :active AND e.status = :enrolactive
            ", [
                'courseid' => $course->id,
                'active' => \ENROL_USER_ACTIVE,
                'enrolactive' => \ENROL_INSTANCE_ENABLED
            ]);
            $enrolled_set = array_flip($enrolled_userids);

            // Batch-load already sent notifications for this course.
            $sent_set = [];
            $sentlogs = $DB->get_records('local_briefingexpiry_log', ['courseid' => $course->id],
                '', 'id, userid, timecompleted, notificationtype');
            foreach ($sentlogs as $sentlog) {
                $sent_set["{$sentlog->userid}_{$sentlog->timecompleted}_{$sentlog->notificationtype}"] = true;
            }

            $now = time();

            foreach ($completions as $completion) {
                $userid = $completion->userid;
                $timecompleted = (int)$completion->timecompleted;

                $expiry_time = self::calculate_expiry($timecompleted, $period);
                $warning_time = $expiry_time - ($warningdays * 86400);
                $is_enrolled = isset($enrolled_set[$userid]);

                $warning_sent = isset($sent_set["{$userid}_{$timecompleted}_warning"]);
                $expired_sent = isset($sent_set["{$userid}_{$timecompleted}_expired"]);

                if ($now >= $expiry_time) {
                    if ($expired_sent) {
                        continue;
                    }

                    $entry = [
                        'user' => $completion,
                        'course' => $course,
                        'timecompleted' => $timecompleted,
                        'timeexpires' => $expiry_time,
                    ];

                    if ($is_enrolled) {
                        $didreset = false;
                        if ($globalautoreset && $autoreset) {
                            // Capture the final grade before it is wiped by the reset.
                            $finalgrade = self::get_course_final_grade($course->id, $userid);

                            self::reset_user_completion($course->id, $userid);
                            $didreset = true;

                            $arch = new \stdClass();
                            $arch->userid = $userid;
                            $arch->courseid = $course->id;
                            $arch->timecompleted = $timecompleted;
                            $arch->timeexpires = $expiry_time;
                            $arch->timereset = time();
                            $arch->finalgrade = $finalgrade;
                            $DB->insert_record('local_briefingexpiry_arch', $arch);

                            if ($notifystudent) {
                                self::notify_student($course, $userid, $timecompleted, $expiry_time);
                            }

                            // The reset is irreversible, so mark this completion as
                            // processed right away regardless of digest delivery.
                            self::write_log($userid, $course->id, $timecompleted, $expiry_time, 'expired');
                        }

                        if ($notifyexpired) {
                            $expired_users[] = $entry;
                            if (!$didreset) {
                                $logs_to_write[] = self::make_log($userid, $course->id, $timecompleted,
                                    $expiry_time, 'expired');
                            }
                        }
                    } else {
                        if ($includeunenrolled && $notifyexpired) {
                            $unenrolled_expired_users[] = $entry;
                            $logs_to_write[] = self::make_log($userid, $course->id, $timecompleted,
                                $expiry_time, 'expired');
                        }
                        // Otherwise nothing irreversible happened: leave the record
                        // unlogged so it is picked up if the settings change later.
                    }
                } else if ($now >= $warning_time) {
                    if (!$warning_sent && $is_enrolled) {
                        $expiring_users[] = [
                            'user' => $completion,
                            'course' => $course,
                            'timecompleted' => $timecompleted,
                            'timeexpires' => $expiry_time,
                        ];
                        $logs_to_write[] = self::make_log($userid, $course->id, $timecompleted,
                            $expiry_time, 'warning');
                    }
                }
            }
        }

        if (!empty($expiring_users) || !empty($expired_users) || !empty($unenrolled_expired_users)) {
            $sent = self::send_digest($expiring_users, $expired_users, $unenrolled_expired_users);
            if ($sent) {
                // Write notification logs to DB so they are not sent again.
                foreach ($logs_to_write as $log) {
                    $DB->insert_record('local_briefingexpiry_log', $log);
                }
            }
        }
    }

    /**
     * Build a log record object.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $timecompleted Completion timestamp
     * @param int $timeexpires Expiry timestamp
     * @param string $type Notification type ('warning' or 'expired')
     * @return \stdClass
     */
    protected static function make_log(int $userid, int $courseid, int $timecompleted,
            int $timeexpires, string $type): \stdClass {
        $log = new \stdClass();
        $log->userid = $userid;
        $log->courseid = $courseid;
        $log->timecompleted = $timecompleted;
        $log->timeexpires = $timeexpires;
        $log->notificationtype = $type;
        $log->timecreated = time();
        return $log;
    }

    /**
     * Insert a log record immediately.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $timecompleted Completion timestamp
     * @param int $timeexpires Expiry timestamp
     * @param string $type Notification type ('warning' or 'expired')
     */
    protected static function write_log(int $userid, int $courseid, int $timecompleted,
            int $timeexpires, string $type): void {
        global $DB;
        $DB->insert_record('local_briefingexpiry_log',
            self::make_log($userid, $courseid, $timecompleted, $timeexpires, $type));
    }

    /**
     * Send daily summary digest report to configured recipients.
     *
     * @param array $expiring_users Expiring users array
     * @param array $expired_users Expired users array
     * @param array $unenrolled_expired_users Unenrolled expired users array
     * @return bool True if the digest was delivered to at least one recipient
     */
    public static function send_digest(array $expiring_users, array $expired_users, array $unenrolled_expired_users): bool {
        $recipients_cfg = get_config('local_briefingexpiry', 'recipients');
        if (empty($recipients_cfg)) {
            return false;
        }

        $recipients = get_users_from_config($recipients_cfg, 'local/briefingexpiry:receivenotifications');
        if (empty($recipients)) {
            return false;
        }

        $sentcount = 0;

        foreach ($recipients as $recipient) {
            // Load language strings in recipient's language.
            $lang = $recipient->lang;
            $stringmanager = get_string_manager();

            $subject = $stringmanager->get_string('digest_subject', 'local_briefingexpiry', null, $lang);
            $intro = $stringmanager->get_string('digest_intro', 'local_briefingexpiry', null, $lang);

            $expiring_title = $stringmanager->get_string('digest_expiring_title', 'local_briefingexpiry', null, $lang);
            $expired_title = $stringmanager->get_string('digest_expired_title', 'local_briefingexpiry', null, $lang);
            $unenrolled_title = $stringmanager->get_string('digest_unenrolled_title', 'local_briefingexpiry', null, $lang);

            $hdr_fullname = $stringmanager->get_string('digest_header_fullname', 'local_briefingexpiry', null, $lang);
            $hdr_course = $stringmanager->get_string('digest_header_course', 'local_briefingexpiry', null, $lang);
            $hdr_completed = $stringmanager->get_string('digest_header_completed', 'local_briefingexpiry', null, $lang);
            $hdr_expires = $stringmanager->get_string('digest_header_expires', 'local_briefingexpiry', null, $lang);
            $hdr_daysleft = $stringmanager->get_string('digest_header_daysleft', 'local_briefingexpiry', null, $lang);
            $hdr_daysago = $stringmanager->get_string('digest_header_daysago', 'local_briefingexpiry', null, $lang);

            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <style>
                    body {
                        font-family: "Outfit", "Inter", "Helvetica Neue", Helvetica, Arial, sans-serif;
                        background-color: #f8fafc;
                        color: #1e293b;
                        margin: 0;
                        padding: 0;
                        -webkit-font-smoothing: antialiased;
                    }
                    .wrapper {
                        background-color: #f8fafc;
                        padding: 40px 20px;
                    }
                    .container {
                        max-width: 680px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        border-radius: 16px;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                        border: 1px solid #e2e8f0;
                        overflow: hidden;
                    }
                    .header {
                        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
                        color: #ffffff;
                        padding: 32px 40px;
                        text-align: left;
                    }
                    .header h1 {
                        font-size: 22px;
                        font-weight: 700;
                        margin: 0;
                        letter-spacing: -0.025em;
                    }
                    .header p {
                        font-size: 13px;
                        margin: 8px 0 0 0;
                        opacity: 0.9;
                    }
                    .content {
                        padding: 40px;
                    }
                    .intro {
                        font-size: 15px;
                        line-height: 1.6;
                        color: #475569;
                        margin-bottom: 32px;
                    }
                    .section-title {
                        font-size: 16px;
                        font-weight: 600;
                        margin-top: 32px;
                        margin-bottom: 16px;
                        border-bottom: 2px solid #f1f5f9;
                        padding-bottom: 8px;
                    }
                    .section-title.expiring {
                        border-color: #fef08a;
                        color: #854d0e;
                    }
                    .section-title.expired {
                        border-color: #fca5a5;
                        color: #991b1b;
                    }
                    .section-title.unenrolled {
                        border-color: #cbd5e1;
                        color: #475569;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 24px;
                    }
                    th {
                        background-color: #f8fafc;
                        text-align: left;
                        font-size: 11px;
                        text-transform: uppercase;
                        letter-spacing: 0.05em;
                        color: #64748b;
                        padding: 12px 16px;
                        border-bottom: 1px solid #e2e8f0;
                    }
                    td {
                        padding: 14px 16px;
                        font-size: 14px;
                        border-bottom: 1px solid #f1f5f9;
                        color: #334155;
                    }
                    tr:last-child td {
                        border-bottom: none;
                    }
                    .badge {
                        display: inline-block;
                        padding: 2px 8px;
                        border-radius: 9999px;
                        font-size: 12px;
                        font-weight: 500;
                    }
                    .badge-warning {
                        background-color: #fef9c3;
                        color: #713f12;
                    }
                    .badge-danger {
                        background-color: #fee2e2;
                        color: #991b1b;
                    }
                    .footer {
                        background-color: #f8fafc;
                        padding: 24px 40px;
                        text-align: center;
                        font-size: 11px;
                        color: #94a3b8;
                        border-top: 1px solid #e2e8f0;
                    }
                </style>
            </head>
            <body>
                <div class="wrapper">
                    <div class="container">
                        <div class="header">
                            <h1>' . s($subject) . '</h1>
                            <p>' . userdate(time(), '%d %B %Y') . '</p>
                        </div>
                        <div class="content">
                            <div class="intro">' . $intro . '</div>';

            // Expiring soon.
            if (!empty($expiring_users)) {
                $html .= '<h2 class="section-title expiring">' . s($expiring_title) . '</h2>';
                $html .= '<table>
                    <thead>
                        <tr>
                            <th>' . s($hdr_fullname) . '</th>
                            <th>' . s($hdr_course) . '</th>
                            <th>' . s($hdr_completed) . '</th>
                            <th>' . s($hdr_expires) . '</th>
                            <th>' . s($hdr_daysleft) . '</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($expiring_users as $item) {
                    $user = $item['user'];
                    $course = $item['course'];
                    $daysleft = ceil(($item['timeexpires'] - time()) / 86400);
                    if ($daysleft < 0) {
                        $daysleft = 0;
                    }
                    $fullname = fullname($user);
                    $html .= '<tr>
                        <td><strong>' . s($fullname) . '</strong><br><small style="color:#64748b;">' . s($user->email) . '</small></td>
                        <td>' . s(format_string($course->fullname)) . '</td>
                        <td>' . userdate($item['timecompleted'], '%d.%m.%Y') . '</td>
                        <td>' . userdate($item['timeexpires'], '%d.%m.%Y') . '</td>
                        <td><span class="badge badge-warning">' . $daysleft . '</span></td>
                    </tr>';
                }
                $html .= '</tbody></table>';
            }

            // Expired.
            if (!empty($expired_users)) {
                $html .= '<h2 class="section-title expired">' . s($expired_title) . '</h2>';
                $html .= '<table>
                    <thead>
                        <tr>
                            <th>' . s($hdr_fullname) . '</th>
                            <th>' . s($hdr_course) . '</th>
                            <th>' . s($hdr_completed) . '</th>
                            <th>' . s($hdr_expires) . '</th>
                            <th>' . s($hdr_daysago) . '</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($expired_users as $item) {
                    $user = $item['user'];
                    $course = $item['course'];
                    $daysago = floor((time() - $item['timeexpires']) / 86400);
                    if ($daysago < 0) {
                        $daysago = 0;
                    }
                    $fullname = fullname($user);
                    $html .= '<tr>
                        <td><strong>' . s($fullname) . '</strong><br><small style="color:#64748b;">' . s($user->email) . '</small></td>
                        <td>' . s(format_string($course->fullname)) . '</td>
                        <td>' . userdate($item['timecompleted'], '%d.%m.%Y') . '</td>
                        <td>' . userdate($item['timeexpires'], '%d.%m.%Y') . '</td>
                        <td><span class="badge badge-danger">' . $daysago . '</span></td>
                    </tr>';
                }
                $html .= '</tbody></table>';
            }

            // Unenrolled expired.
            if (!empty($unenrolled_expired_users)) {
                $html .= '<h2 class="section-title unenrolled">' . s($unenrolled_title) . '</h2>';
                $html .= '<table>
                    <thead>
                        <tr>
                            <th>' . s($hdr_fullname) . '</th>
                            <th>' . s($hdr_course) . '</th>
                            <th>' . s($hdr_completed) . '</th>
                            <th>' . s($hdr_expires) . '</th>
                            <th>' . s($hdr_daysago) . '</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($unenrolled_expired_users as $item) {
                    $user = $item['user'];
                    $course = $item['course'];
                    $daysago = floor((time() - $item['timeexpires']) / 86400);
                    if ($daysago < 0) {
                        $daysago = 0;
                    }
                    $fullname = fullname($user);
                    $html .= '<tr>
                        <td><strong>' . s($fullname) . '</strong><br><small style="color:#64748b;">' . s($user->email) . '</small></td>
                        <td>' . s(format_string($course->fullname)) . '</td>
                        <td>' . userdate($item['timecompleted'], '%d.%m.%Y') . '</td>
                        <td>' . userdate($item['timeexpires'], '%d.%m.%Y') . '</td>
                        <td><span class="badge badge-danger">' . $daysago . '</span></td>
                    </tr>';
                }
                $html .= '</tbody></table>';
            }

            $html .= '
                        </div>
                        <div class="footer">
                            <p>' . s($stringmanager->get_string('pluginname', 'local_briefingexpiry', null, $lang)) . ' &bull; Moodle</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>';

            $message = new \core\message\message();
            $message->component = 'local_briefingexpiry';
            $message->name = 'expirynotice';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = html_to_text($html);
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessagehtml = $html;
            $message->smallmessage = $subject;
            $message->notification = 1;

            try {
                if (message_send($message)) {
                    $sentcount++;
                } else {
                    mtrace("Error sending digest to user {$recipient->id}.");
                }
            } catch (\Exception $e) {
                mtrace("Error sending digest to user {$recipient->id}: " . $e->getMessage());
            }
        }

        return $sentcount > 0;
    }

    /**
     * Notifies a student that their briefing has expired and completion has been reset.
     *
     * @param \stdClass $course Course object
     * @param int $userid User ID
     * @param int $timecompleted Timestamp when briefing was previously completed
     * @param int $timeexpires Timestamp when briefing expired
     */
    public static function notify_student(\stdClass $course, int $userid, int $timecompleted, int $timeexpires) {
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return;
        }

        $lang = $user->lang;
        $stringmanager = get_string_manager();

        $fullname = fullname($user);
        $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);

        $a = new \stdClass();
        $a->fullname = $fullname;
        $a->coursename = format_string($course->fullname);
        $a->completeddate = userdate($timecompleted, '%d.%m.%Y');
        $a->expirydate = userdate($timeexpires, '%d.%m.%Y');
        $a->courseurl = $courseurl->out(false);

        $subject = $stringmanager->get_string('student_notification_subject', 'local_briefingexpiry', $a, $lang);
        $body = $stringmanager->get_string('student_notification_body', 'local_briefingexpiry', $a, $lang);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body {
                    font-family: "Outfit", "Inter", "Helvetica Neue", Helvetica, Arial, sans-serif;
                    background-color: #f8fafc;
                    color: #1e293b;
                    margin: 0;
                    padding: 0;
                    -webkit-font-smoothing: antialiased;
                }
                .wrapper {
                    background-color: #f8fafc;
                    padding: 40px 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
                    border: 1px solid #e2e8f0;
                    overflow: hidden;
                }
                .header {
                    background: linear-gradient(135deg, #b91c1c 0%, #ef4444 100%);
                    color: #ffffff;
                    padding: 32px 40px;
                    text-align: left;
                }
                .header h1 {
                    font-size: 18px;
                    font-weight: 700;
                    margin: 0;
                    letter-spacing: -0.025em;
                }
                .content {
                    padding: 40px;
                }
                .message-body {
                    font-size: 15px;
                    line-height: 1.6;
                    color: #334155;
                    margin-bottom: 32px;
                }
                .footer {
                    background-color: #f8fafc;
                    padding: 24px 40px;
                    text-align: center;
                    font-size: 11px;
                    color: #94a3b8;
                    border-top: 1px solid #e2e8f0;
                }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="container">
                    <div class="header">
                        <h1>' . s($subject) . '</h1>
                    </div>
                    <div class="content">
                        <div class="message-body">' . $body . '</div>
                    </div>
                    <div class="footer">
                        <p>' . s($stringmanager->get_string('pluginname', 'local_briefingexpiry', null, $lang)) . ' &bull; Moodle</p>
                    </div>
                </div>
            </div>
        </body>
        </html>';

        $message = new \core\message\message();
        $message->component = 'local_briefingexpiry';
        $message->name = 'resetnotice';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = html_to_text($html);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $subject;
        $message->notification = 1;

        try {
            message_send($message);
        } catch (\Exception $e) {
            mtrace("Error sending student notice to user {$user->id}: " . $e->getMessage());
        }
    }
}
