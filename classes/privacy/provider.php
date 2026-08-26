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
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_briefingexpiry\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

/**
 * Privacy provider class.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Get the list of metadata.
     *
     * @param  collection  $items  The collection to add metadata to.
     * @return collection  The array of metadata
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
     * Get contexts for user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $userid = $user->id;

        $has_system = false;
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $has_system = true;
                break;
            }
        }

        if (!$has_system) {
            return;
        }

        $logs = $DB->get_records('local_briefingexpiry_log', ['userid' => $userid]);
        if (!empty($logs)) {
            $logdata = [];
            foreach ($logs as $log) {
                $logdata[] = [
                    'courseid' => $log->courseid,
                    'timecompleted' => transform::datetime($log->timecompleted),
                    'timeexpires' => transform::datetime($log->timeexpires),
                    'notificationtype' => $log->notificationtype,
                    'timecreated' => transform::datetime($log->timecreated),
                ];
            }
            writer::with_context(\context_system::instance())->export_data([
                get_string('privacy:logpath', 'local_briefingexpiry')
            ], (object)$logdata);
        }

        $archs = $DB->get_records('local_briefingexpiry_arch', ['userid' => $userid]);
        if (!empty($archs)) {
            $archdata = [];
            foreach ($archs as $arch) {
                $archdata[] = [
                    'courseid' => $arch->courseid,
                    'timecompleted' => transform::datetime($arch->timecompleted),
                    'timeexpires' => transform::datetime($arch->timeexpires),
                    'timereset' => transform::datetime($arch->timereset),
                    'finalgrade' => $arch->finalgrade,
                ];
            }
            writer::with_context(\context_system::instance())->export_data([
                get_string('privacy:archpath', 'local_briefingexpiry')
            ], (object)$archdata);
        }
    }

    /**
     * Delete data for all users in context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records('local_briefingexpiry_log');
            $DB->delete_records('local_briefingexpiry_arch');
        }
    }

    /**
     * Delete data for user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $user->id;

        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->delete_records('local_briefingexpiry_log', ['userid' => $userid]);
                $DB->delete_records('local_briefingexpiry_arch', ['userid' => $userid]);
            }
        }
    }
}
