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
 * Privacy Subsystem implementation for local_briefingexpiry.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_briefingexpiry\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the notification log and the completion reset archive.
 *
 * Both tables are site-wide bookkeeping, so the data lives in the system context.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data held by this plugin.
     *
     * @param collection $items The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table('local_briefingexpiry_log', [
            'userid' => 'privacy:metadata:log:userid',
            'courseid' => 'privacy:metadata:log:courseid',
            'timecompleted' => 'privacy:metadata:log:timecompleted',
            'timeexpires' => 'privacy:metadata:log:timeexpires',
            'notificationtype' => 'privacy:metadata:log:notificationtype',
            'timecreated' => 'privacy:metadata:log:timecreated',
        ], 'privacy:metadata:log');

        $items->add_database_table('local_briefingexpiry_arch', [
            'userid' => 'privacy:metadata:arch:userid',
            'courseid' => 'privacy:metadata:arch:courseid',
            'timecompleted' => 'privacy:metadata:arch:timecompleted',
            'timeexpires' => 'privacy:metadata:arch:timeexpires',
            'timereset' => 'privacy:metadata:arch:timereset',
            'finalgrade' => 'privacy:metadata:arch:finalgrade',
        ], 'privacy:metadata:arch');

        return $items;
    }

    /**
     * Get the contexts containing data for a user.
     *
     * @param int $userid The user to look up.
     * @return contextlist The system context, when the user has any data at all.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $haslog = $DB->record_exists('local_briefingexpiry_log', ['userid' => $userid]);
        $hasarch = $DB->record_exists('local_briefingexpiry_arch', ['userid' => $userid]);

        if ($haslog || $hasarch) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get the users holding data in a given context.
     *
     * @param userlist $userlist The userlist to add users to.
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_briefingexpiry_log}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_briefingexpiry_arch}', []);
    }

    /**
     * Export the user's notification log and reset archive.
     *
     * @param approved_contextlist $contextlist The approved contexts to export for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        if (!self::has_system_context($contextlist)) {
            return;
        }

        $context = \context_system::instance();

        $logs = $DB->get_records('local_briefingexpiry_log', ['userid' => $userid]);
        if (!empty($logs)) {
            $logdata = [];
            foreach ($logs as $log) {
                $logdata[] = (object)[
                    'courseid' => $log->courseid,
                    'timecompleted' => transform::datetime($log->timecompleted),
                    'timeexpires' => transform::datetime($log->timeexpires),
                    'notificationtype' => $log->notificationtype,
                    'timecreated' => transform::datetime($log->timecreated),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:logpath', 'local_briefingexpiry')],
                (object)['notifications' => $logdata]
            );
        }

        $archives = $DB->get_records('local_briefingexpiry_arch', ['userid' => $userid]);
        if (!empty($archives)) {
            $archdata = [];
            foreach ($archives as $archive) {
                $archdata[] = (object)[
                    'courseid' => $archive->courseid,
                    'timecompleted' => transform::datetime($archive->timecompleted),
                    'timeexpires' => transform::datetime($archive->timeexpires),
                    'timereset' => transform::datetime($archive->timereset),
                    'finalgrade' => $archive->finalgrade,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:archpath', 'local_briefingexpiry')],
                (object)['resets' => $archdata]
            );
        }
    }

    /**
     * Delete data for all users in a context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('local_briefingexpiry_log');
        $DB->delete_records('local_briefingexpiry_arch');
    }

    /**
     * Delete the data of one user.
     *
     * @param approved_contextlist $contextlist The approved contexts and user to delete for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (!self::has_system_context($contextlist)) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        $DB->delete_records('local_briefingexpiry_log', ['userid' => $userid]);
        $DB->delete_records('local_briefingexpiry_arch', ['userid' => $userid]);
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist The approved context and users to delete for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_briefingexpiry_log', "userid {$insql}", $params);
        $DB->delete_records_select('local_briefingexpiry_arch', "userid {$insql}", $params);
    }

    /**
     * Check whether the approved contexts include the system context.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return bool True if the system context was approved.
     */
    private static function has_system_context(approved_contextlist $contextlist): bool {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                return true;
            }
        }

        return false;
    }
}
