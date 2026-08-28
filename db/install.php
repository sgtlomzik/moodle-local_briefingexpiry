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
 * Installation script for local_briefingexpiry.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create the course custom fields the plugin drives its expiry logic from.
 *
 * The fields are created directly rather than through the customfield API because
 * that API has no supported way of seeding a category from an install script.
 */
function xmldb_local_briefingexpiry_install() {
    global $DB;

    $syscontext = context_system::instance();
    $now = time();

    $categoryname = get_string('customfieldcategory', 'local_briefingexpiry');

    // Releases before 1.2.0 created the category under a hardcoded Russian name.
    // Reuse it if it is there, so an existing site does not end up with two.
    $category = $DB->get_record('customfield_category', [
        'component' => 'core_course',
        'area' => 'course',
        'name' => $categoryname,
    ]);

    if (!$category) {
        $category = $DB->get_record('customfield_category', [
            'component' => 'core_course',
            'area' => 'course',
            'name' => 'Инструктажи',
        ]);
    }

    if ($category) {
        $categoryid = $category->id;
    } else {
        $maxsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {customfield_category}
              WHERE component = :component AND area = :area AND itemid = :itemid',
            ['component' => 'core_course', 'area' => 'course', 'itemid' => 0]
        );

        $categoryid = $DB->insert_record('customfield_category', (object)[
            'name' => $categoryname,
            'component' => 'core_course',
            'area' => 'course',
            'itemid' => 0,
            'contextid' => $syscontext->id,
            'sortorder' => ($maxsort !== false && $maxsort !== null) ? $maxsort + 1 : 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    // The order of the period options is part of the stored data: the select field
    // saves a 1-based index, so options may only ever be appended.
    $periodoptions = implode("\n", [
        get_string('period_6months', 'local_briefingexpiry'),
        get_string('period_1year', 'local_briefingexpiry'),
        get_string('period_2years', 'local_briefingexpiry'),
        get_string('period_3years', 'local_briefingexpiry'),
        get_string('period_3months', 'local_briefingexpiry'),
    ]);

    $fields = [
        'briefing_enabled' => [
            'name' => get_string('field_enabled', 'local_briefingexpiry'),
            'type' => 'checkbox',
            'sortorder' => 0,
            'config' => ['defaultvalue' => 0],
        ],
        'briefing_period' => [
            'name' => get_string('field_period', 'local_briefingexpiry'),
            'type' => 'select',
            'sortorder' => 1,
            'config' => ['options' => $periodoptions, 'defaultvalue' => ''],
        ],
        'briefing_autoreset' => [
            'name' => get_string('field_autoreset', 'local_briefingexpiry'),
            'type' => 'checkbox',
            'sortorder' => 2,
            'config' => ['defaultvalue' => 0],
        ],
    ];

    foreach ($fields as $shortname => $field) {
        if ($DB->record_exists('customfield_field', ['categoryid' => $categoryid, 'shortname' => $shortname])) {
            continue;
        }

        $DB->insert_record('customfield_field', (object)[
            'shortname' => $shortname,
            'name' => $field['name'],
            'type' => $field['type'],
            'categoryid' => $categoryid,
            'sortorder' => $field['sortorder'],
            'configdata' => json_encode($field['config'] + [
                'required' => 0,
                'uniquevalues' => 0,
                // 0 keeps the field off the course listing; it is administrative data.
                'visibility' => 0,
                'locked' => 0,
            ]),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
