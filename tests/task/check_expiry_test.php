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
 * Unit tests for the briefing expiry scheduled task.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_briefingexpiry\task;

/**
 * Tests that the scheduled task is registered and does the expiry check.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_briefingexpiry\task\check_expiry
 */
final class check_expiry_test extends \advanced_testcase {
    /**
     * The task is named from a language string rather than a hardcoded label.
     */
    public function test_get_name(): void {
        $this->resetAfterTest();

        $this->assertSame(
            get_string('task_check_expiry', 'local_briefingexpiry'),
            (new check_expiry())->get_name()
        );
    }

    /**
     * The task is registered in db/tasks.php and is picked up by core.
     */
    public function test_the_task_is_scheduled(): void {
        $this->resetAfterTest();

        $task = \core\task\manager::get_scheduled_task(check_expiry::class);

        $this->assertInstanceOf(check_expiry::class, $task);
        $this->assertSame('local_briefingexpiry', $task->get_component());

        // The check is a daily job, as declared in db/tasks.php.
        $this->assertSame('0', $task->get_minute());
        $this->assertSame('6', $task->get_hour());
        $this->assertSame('*', $task->get_day());
        $this->assertSame('*', $task->get_day_of_week());
        $this->assertSame('*', $task->get_month());
    }

    /**
     * Running the task with nothing to do is harmless and sends no messages.
     */
    public function test_execute_with_no_briefing_courses(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->getDataGenerator()->create_course();

        $sink = $this->redirectMessages();
        (new check_expiry())->execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(0, $messages);
    }

    /**
     * Running the task performs the same expiry check the helper does.
     */
    public function test_execute_runs_the_expiry_check(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $admin = get_admin();

        set_config('warningdays', 30, 'local_briefingexpiry');
        set_config('notifyexpired', 1, 'local_briefingexpiry');
        set_config('recipients', (string)$admin->id, 'local_briefingexpiry');
        set_config('enableautoreset', 0, 'local_briefingexpiry');

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        foreach (['briefing_enabled' => 1, 'briefing_period' => 2] as $shortname => $value) {
            $fieldrec = $DB->get_record('customfield_field', ['shortname' => $shortname], '*', MUST_EXIST);
            $generator->add_instance_data(\core_customfield\field_controller::create($fieldrec->id), $course->id, $value);
        }

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        global $CFG;
        require_once($CFG->dirroot . '/completion/completion_completion.php');
        $completion = new \completion_completion(['course' => $course->id, 'userid' => $user->id]);
        $completion->mark_complete(time() - (400 * DAYSECS));

        $sink = $this->redirectMessages();
        (new check_expiry())->execute();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals($admin->id, $messages[0]->useridto);
        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'notificationtype' => 'expired',
        ]));
    }
}
