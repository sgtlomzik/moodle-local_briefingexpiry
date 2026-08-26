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

namespace local_briefingexpiry;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the local_briefingexpiry helper class.
 *
 * @package    local_briefingexpiry
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_briefingexpiry\helper
 */
final class helper_test extends \advanced_testcase {

    /**
     * Configure a course as a briefing course via the custom fields created at install.
     *
     * @param int $courseid Course ID
     * @param int $periodindex 1-based index in the briefing_period select options
     * @param int $autoreset Value for the briefing_autoreset checkbox
     */
    private function set_briefing_fields(int $courseid, int $periodindex, int $autoreset = 0): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $values = [
            'briefing_enabled' => 1,
            'briefing_period' => $periodindex,
            'briefing_autoreset' => $autoreset,
        ];
        foreach ($values as $shortname => $value) {
            $fieldrec = $DB->get_record('customfield_field', ['shortname' => $shortname], '*', MUST_EXIST);
            $field = \core_customfield\field_controller::create($fieldrec->id);
            $generator->add_instance_data($field, $courseid, $value);
        }
    }

    /**
     * Mark a course completion for a user at the given time.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @param int $timecompleted Completion timestamp
     */
    private function mark_completed(int $courseid, int $userid, int $timecompleted): void {
        global $CFG;
        require_once($CFG->dirroot . '/completion/completion_completion.php');
        $ccompletion = new \completion_completion(['course' => $courseid, 'userid' => $userid]);
        $ccompletion->mark_complete($timecompleted);
    }

    /**
     * Set base plugin configuration for notification tests.
     *
     * @param int $recipientid Digest recipient user id
     */
    private function set_base_config(int $recipientid): void {
        set_config('warningdays', 30, 'local_briefingexpiry');
        set_config('notifyexpired', 1, 'local_briefingexpiry');
        set_config('recipients', (string)$recipientid, 'local_briefingexpiry');
        set_config('includeunenrolled', 1, 'local_briefingexpiry');
        set_config('enableautoreset', 0, 'local_briefingexpiry');
        set_config('resetquizattempts', 1, 'local_briefingexpiry');
        set_config('notifystudent', 1, 'local_briefingexpiry');
    }

    public function test_get_period_spec(): void {
        $this->assertSame('6 months', helper::get_period_spec(1, null));
        $this->assertSame('1 year', helper::get_period_spec(2, null));
        $this->assertSame('2 years', helper::get_period_spec(3, null));
        $this->assertSame('3 years', helper::get_period_spec(4, null));
        $this->assertSame('3 months', helper::get_period_spec(5, null));
        $this->assertSame('3 months', helper::get_period_spec(0, '3 месяца'));
        $this->assertSame('1 year', helper::get_period_spec(0, '1 год'));
        $this->assertNull(helper::get_period_spec(0, ''));
        $this->assertNull(helper::get_period_spec(0, null));
    }

    public function test_calculate_expiry(): void {
        $base = mktime(12, 0, 0, 3, 15, 2026);
        $this->assertSame(mktime(12, 0, 0, 3, 15, 2027), helper::calculate_expiry($base, '1 year'));
        $this->assertSame(mktime(12, 0, 0, 9, 15, 2026), helper::calculate_expiry($base, '6 months'));
        $this->assertSame(mktime(12, 0, 0, 6, 15, 2026), helper::calculate_expiry($base, '3 months'));
        $this->assertSame(mktime(12, 0, 0, 3, 15, 2029), helper::calculate_expiry($base, '3 years'));
    }

    public function test_get_briefing_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $briefing = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_course(); // Regular course, not flagged.

        $this->set_briefing_fields($briefing->id, 2);

        $courses = helper::get_briefing_courses();
        $this->assertCount(1, $courses);
        $this->assertArrayHasKey($briefing->id, $courses);
    }

    public function test_check_expiry_sends_warning(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = get_admin();

        $this->set_base_config($admin->id);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->set_briefing_fields($course->id, 2); // 1 year.

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Completed ~350 days ago: expires in ~15 days, inside the 30-day warning window.
        $timecompleted = time() - (350 * DAYSECS);
        $this->mark_completed($course->id, $user->id, $timecompleted);

        $sink = $this->redirectMessages();
        helper::check_expiry();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($admin->id, $messages[0]->useridto);
        $this->assertEquals('expirynotice', $messages[0]->eventtype);

        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'notificationtype' => 'warning',
        ]));

        // Second run must not send a duplicate warning.
        $sink = $this->redirectMessages();
        helper::check_expiry();
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    public function test_check_expiry_expired_without_autoreset(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = get_admin();

        $this->set_base_config($admin->id);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->set_briefing_fields($course->id, 2, 1); // 1 year, course-level autoreset on but global off.

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $timecompleted = time() - (400 * DAYSECS);
        $this->mark_completed($course->id, $user->id, $timecompleted);

        $sink = $this->redirectMessages();
        helper::check_expiry();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($admin->id, $messages[0]->useridto);

        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'notificationtype' => 'expired',
        ]));

        // Global auto-reset is off: completion must be intact, no archive record.
        $this->assertTrue($DB->record_exists('course_completions', [
            'course' => $course->id,
            'userid' => $user->id,
        ]));
        $this->assertFalse($DB->record_exists('local_briefingexpiry_arch', ['userid' => $user->id]));

        // Second run: no duplicates.
        $sink = $this->redirectMessages();
        helper::check_expiry();
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    public function test_check_expiry_expired_with_autoreset(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = get_admin();

        $this->set_base_config($admin->id);
        set_config('enableautoreset', 1, 'local_briefingexpiry');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->set_briefing_fields($course->id, 2, 1); // 1 year + autoreset.

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $timecompleted = time() - (400 * DAYSECS);
        $this->mark_completed($course->id, $user->id, $timecompleted);

        // Give the user a final course grade so it can be archived.
        require_once($CFG->libdir . '/gradelib.php');
        $courseitem = \grade_item::fetch_course_item($course->id);
        $courseitem->update_final_grade($user->id, 75.0, 'local_briefingexpiry');
        grade_regrade_final_grades($course->id);

        $sink = $this->redirectMessages();
        helper::check_expiry();
        $messages = $sink->get_messages();
        $sink->close();

        // One digest to the admin + one reset notice to the student.
        $this->assertCount(2, $messages);
        $recipients = array_map(static function($message) {
            return $message->useridto;
        }, $messages);
        $this->assertContains((string)$admin->id, array_map('strval', $recipients));
        $this->assertContains((string)$user->id, array_map('strval', $recipients));

        // Completion reset.
        $this->assertFalse($DB->record_exists('course_completions', [
            'course' => $course->id,
            'userid' => $user->id,
        ]));

        // Grade wiped.
        $gradegrade = \grade_grade::fetch(['itemid' => $courseitem->id, 'userid' => $user->id]);
        $this->assertFalse($gradegrade);

        // Archive record with the pre-reset grade.
        $arch = $DB->get_record('local_briefingexpiry_arch', ['userid' => $user->id, 'courseid' => $course->id]);
        $this->assertNotEmpty($arch);
        $this->assertEquals($timecompleted, $arch->timecompleted);
        $this->assertEqualsWithDelta(75.0, (float)$arch->finalgrade, 0.001);

        // Log written.
        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'notificationtype' => 'expired',
        ]));

        // Second run: nothing left to process.
        $sink = $this->redirectMessages();
        helper::check_expiry();
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    public function test_check_expiry_unenrolled_user(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = get_admin();

        $this->set_base_config($admin->id);
        set_config('enableautoreset', 1, 'local_briefingexpiry');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->set_briefing_fields($course->id, 2, 1);

        // User has an old completion but is NOT enrolled any more.
        $user = $this->getDataGenerator()->create_user();
        $timecompleted = time() - (400 * DAYSECS);
        $this->mark_completed($course->id, $user->id, $timecompleted);

        $sink = $this->redirectMessages();
        helper::check_expiry();
        $messages = $sink->get_messages();
        $sink->close();

        // Digest sent (unenrolled section), but no reset for unenrolled users.
        $this->assertCount(1, $messages);
        $this->assertEquals($admin->id, $messages[0]->useridto);
        $this->assertTrue($DB->record_exists('course_completions', [
            'course' => $course->id,
            'userid' => $user->id,
        ]));
        $this->assertFalse($DB->record_exists('local_briefingexpiry_arch', ['userid' => $user->id]));

        // Second run: no duplicates.
        $sink = $this->redirectMessages();
        helper::check_expiry();
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    public function test_check_expiry_notifyexpired_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = get_admin();

        $this->set_base_config($admin->id);
        set_config('notifyexpired', 0, 'local_briefingexpiry');
        set_config('enableautoreset', 1, 'local_briefingexpiry');
        set_config('notifystudent', 0, 'local_briefingexpiry');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->set_briefing_fields($course->id, 2, 1);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $timecompleted = time() - (400 * DAYSECS);
        $this->mark_completed($course->id, $user->id, $timecompleted);

        $sink = $this->redirectMessages();
        helper::check_expiry();
        $messages = $sink->get_messages();
        $sink->close();

        // No digest and no student notice, but the auto-reset still happened.
        $this->assertCount(0, $messages);
        $this->assertFalse($DB->record_exists('course_completions', [
            'course' => $course->id,
            'userid' => $user->id,
        ]));
        $this->assertTrue($DB->record_exists('local_briefingexpiry_arch', [
            'userid' => $user->id,
            'courseid' => $course->id,
        ]));
        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'notificationtype' => 'expired',
        ]));
    }

    public function test_check_expiry_quarterly_period(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = get_admin();

        $this->set_base_config($admin->id);

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->set_briefing_fields($course->id, 5); // 3 months.

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $timecompleted = time() - (100 * DAYSECS); // Over 3 months ago.
        $this->mark_completed($course->id, $user->id, $timecompleted);

        $sink = $this->redirectMessages();
        helper::check_expiry();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'notificationtype' => 'expired',
        ]));
    }

    public function test_reset_user_completion_with_quiz(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('resetquizattempts', 1, 'local_briefingexpiry');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $context = \context_module::instance($cm->id);

        // Create a finished attempt backed by a real question usage.
        require_once($CFG->dirroot . '/question/engine/lib.php');
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        \question_engine::save_questions_usage_by_activity($quba);

        $attempt = (object)[
            'quiz' => $quiz->id,
            'userid' => $user->id,
            'attempt' => 1,
            'uniqueid' => $quba->get_id(),
            'layout' => '0',
            'currentpage' => 0,
            'preview' => 0,
            'state' => 'finished',
            'timestart' => time() - 200,
            'timefinish' => time() - 100,
            'timemodified' => time() - 100,
            'timemodifiedoffline' => 0,
            'timecheckstate' => null,
            'sumgrades' => 1,
        ];
        $DB->insert_record('quiz_attempts', $attempt);

        $this->mark_completed($course->id, $user->id, time() - 100);
        $this->assertTrue($DB->record_exists('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $user->id]));

        helper::reset_user_completion($course->id, $user->id);

        $this->assertFalse($DB->record_exists('course_completions', [
            'course' => $course->id,
            'userid' => $user->id,
        ]));
        $this->assertFalse($DB->record_exists('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $user->id]));
        $this->assertFalse($DB->record_exists('quiz_grades', ['quiz' => $quiz->id, 'userid' => $user->id]));
    }
}
