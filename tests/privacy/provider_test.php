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
 * Unit tests for the local_briefingexpiry privacy provider.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_briefingexpiry\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the notification log and completion reset archive privacy provider.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_briefingexpiry\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Write one log row and one archive row for a user.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param string $type Notification type stored in the log row.
     */
    private function add_data(int $userid, int $courseid, string $type = 'warning'): void {
        global $DB;

        $timecompleted = time() - YEARSECS;

        $DB->insert_record('local_briefingexpiry_log', (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'timecompleted' => $timecompleted,
            'timeexpires' => $timecompleted + YEARSECS,
            'notificationtype' => $type,
            'timecreated' => time(),
        ]);

        $DB->insert_record('local_briefingexpiry_arch', (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'timecompleted' => $timecompleted,
            'timeexpires' => $timecompleted + YEARSECS,
            'timereset' => time(),
            'finalgrade' => 82.5,
        ]);
    }

    /**
     * Both plugin tables are declared, with a language string for every column.
     */
    public function test_get_metadata_describes_both_tables(): void {
        $this->resetAfterTest();

        $collection = provider::get_metadata(new collection('local_briefingexpiry'));
        $items = $collection->get_collection();

        $this->assertCount(2, $items);

        $names = array_map(static function ($item) {
            return $item->get_name();
        }, $items);
        $this->assertEqualsCanonicalizing(
            ['local_briefingexpiry_log', 'local_briefingexpiry_arch'],
            $names
        );

        foreach ($items as $item) {
            $this->assertTrue(
                get_string_manager()->string_exists($item->get_summary(), 'local_briefingexpiry'),
                "Missing language string {$item->get_summary()}"
            );

            foreach ($item->get_privacy_fields() as $field => $identifier) {
                $this->assertTrue(
                    get_string_manager()->string_exists($identifier, 'local_briefingexpiry'),
                    "Missing language string {$identifier} for field {$field}"
                );
            }
        }
    }

    /**
     * A user with data is placed in the system context, and one without in none.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $withdata = $this->getDataGenerator()->create_user();
        $withoutdata = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->add_data($withdata->id, $course->id);

        $contextlist = provider::get_contexts_for_userid($withdata->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(\context_system::instance()->id, $contextlist->get_contextids()[0]);

        $this->assertCount(0, provider::get_contexts_for_userid($withoutdata->id));
    }

    /**
     * A log row on its own is enough to place the user in the system context.
     */
    public function test_get_contexts_for_userid_with_only_a_log_row(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $DB->insert_record('local_briefingexpiry_log', (object)[
            'userid' => $user->id,
            'courseid' => $course->id,
            'timecompleted' => time() - YEARSECS,
            'timeexpires' => time(),
            'notificationtype' => 'expired',
            'timecreated' => time(),
        ]);

        $this->assertCount(1, provider::get_contexts_for_userid($user->id));
    }

    /**
     * Users are found in the system context, and nowhere else.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $bystander = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->add_data($userone->id, $course->id);
        $this->add_data($usertwo->id, $course->id);

        $userlist = new userlist(\context_system::instance(), 'local_briefingexpiry');
        provider::get_users_in_context($userlist);

        $this->assertEqualsCanonicalizing(
            [$userone->id, $usertwo->id],
            $userlist->get_userids()
        );
        $this->assertNotContains($bystander->id, $userlist->get_userids());

        // The data is site-wide, so no other context level holds any of it.
        $coursecontext = \context_course::instance($course->id);
        $courseuserlist = new userlist($coursecontext, 'local_briefingexpiry');
        provider::get_users_in_context($courseuserlist);
        $this->assertCount(0, $courseuserlist);
    }

    /**
     * The export contains the user's own notifications and resets.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->add_data($user->id, $course->id);
        $this->add_data($other->id, $course->id);

        $context = \context_system::instance();
        provider::export_user_data(new approved_contextlist(
            $user,
            'local_briefingexpiry',
            [$context->id]
        ));

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $logs = $writer->get_data([get_string('privacy:logpath', 'local_briefingexpiry')]);
        $this->assertCount(1, $logs->notifications);
        $this->assertEquals($course->id, $logs->notifications[0]->courseid);
        $this->assertSame('warning', $logs->notifications[0]->notificationtype);

        $archives = $writer->get_data([get_string('privacy:archpath', 'local_briefingexpiry')]);
        $this->assertCount(1, $archives->resets);
        $this->assertEquals($course->id, $archives->resets[0]->courseid);
        $this->assertEquals(82.5, (float)$archives->resets[0]->finalgrade);
    }

    /**
     * Nothing is exported when a context other than the system one is approved.
     */
    public function test_export_user_data_ignores_other_contexts(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->add_data($user->id, $course->id);

        $coursecontext = \context_course::instance($course->id);
        provider::export_user_data(new approved_contextlist(
            $user,
            'local_briefingexpiry',
            [$coursecontext->id]
        ));

        $this->assertFalse(writer::with_context($coursecontext)->has_any_data());
        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }

    /**
     * Deleting a context removes everybody's data from both tables.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();

        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->add_data($userone->id, $course->id);
        $this->add_data($usertwo->id, $course->id);

        // A course context holds none of this data and must leave it alone.
        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));
        $this->assertEquals(2, $DB->count_records('local_briefingexpiry_log'));

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(0, $DB->count_records('local_briefingexpiry_log'));
        $this->assertEquals(0, $DB->count_records('local_briefingexpiry_arch'));
    }

    /**
     * Deleting one user leaves everybody else's data untouched.
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->add_data($user->id, $course->id);
        $this->add_data($other->id, $course->id);

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'local_briefingexpiry',
            [\context_system::instance()->id]
        ));

        $this->assertFalse($DB->record_exists('local_briefingexpiry_log', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_briefingexpiry_arch', ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('local_briefingexpiry_arch', ['userid' => $other->id]));
    }

    /**
     * Approving a context the data does not live in deletes nothing.
     */
    public function test_delete_data_for_user_ignores_other_contexts(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->add_data($user->id, $course->id);

        provider::delete_data_for_user(new approved_contextlist(
            $user,
            'local_briefingexpiry',
            [\context_course::instance($course->id)->id]
        ));

        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists('local_briefingexpiry_arch', ['userid' => $user->id]));
    }

    /**
     * Deleting an approved list of users removes exactly those users.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $this->resetAfterTest();

        $deleteone = $this->getDataGenerator()->create_user();
        $deletetwo = $this->getDataGenerator()->create_user();
        $keep = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $this->add_data($deleteone->id, $course->id);
        $this->add_data($deletetwo->id, $course->id);
        $this->add_data($keep->id, $course->id);

        provider::delete_data_for_users(new approved_userlist(
            \context_system::instance(),
            'local_briefingexpiry',
            [$deleteone->id, $deletetwo->id]
        ));

        $this->assertFalse($DB->record_exists('local_briefingexpiry_log', ['userid' => $deleteone->id]));
        $this->assertFalse($DB->record_exists('local_briefingexpiry_arch', ['userid' => $deletetwo->id]));
        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', ['userid' => $keep->id]));
        $this->assertTrue($DB->record_exists('local_briefingexpiry_arch', ['userid' => $keep->id]));
    }

    /**
     * An approved userlist in another context level deletes nothing.
     */
    public function test_delete_data_for_users_ignores_other_contexts(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->add_data($user->id, $course->id);

        provider::delete_data_for_users(new approved_userlist(
            \context_course::instance($course->id),
            'local_briefingexpiry',
            [$user->id]
        ));

        $this->assertTrue($DB->record_exists('local_briefingexpiry_log', ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists('local_briefingexpiry_arch', ['userid' => $user->id]));
    }
}
