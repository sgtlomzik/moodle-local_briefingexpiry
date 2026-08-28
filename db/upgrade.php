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
 * Upgrade steps for local_briefingexpiry.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_briefingexpiry plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_briefingexpiry_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026071000) {
        // Add the quarterly option to the briefing_period custom field. It is appended
        // to the end of the options list so existing stored intvalues keep their meaning.
        // The label is the Russian one on purpose: before 1.2.0 the install script created
        // the options with hardcoded Russian text, and this step only ever runs on such a site.
        $fields = $DB->get_records('customfield_field', ['shortname' => 'briefing_period']);
        foreach ($fields as $field) {
            $configdata = json_decode($field->configdata, true);
            if (is_array($configdata) && isset($configdata['options'])
                    && strpos($configdata['options'], '3 месяца') === false) {
                $configdata['options'] .= "\n3 месяца";
                $DB->set_field('customfield_field', 'configdata', json_encode($configdata), ['id' => $field->id]);
            }
        }
        \cache_helper::purge_by_event('changesincoursecat');

        upgrade_plugin_savepoint(true, 2026071000, 'local', 'briefingexpiry');
    }

    return true;
}
