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
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Install database and create course custom fields.
 */
function xmldb_local_briefingexpiry_install() {
    global $DB;

    $syscontext = \context_system::instance();

    // Custom field category shared by all briefing fields.
    $category = $DB->get_record('customfield_category', [
        'component' => 'core_course',
        'area' => 'course',
        'name' => 'Инструктажи'
    ]);

    if (!$category) {
        $cat = new stdClass();
        $cat->name = 'Инструктажи';
        $cat->component = 'core_course';
        $cat->area = 'course';
        $cat->itemid = 0;
        $cat->contextid = $syscontext->id;
        $cat->timecreated = time();
        $cat->timemodified = time();

        $maxsort = $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {customfield_category} WHERE component = :component AND area = :area AND itemid = :itemid',
            ['component' => 'core_course', 'area' => 'course', 'itemid' => 0]
        );
        $cat->sortorder = ($maxsort !== false && $maxsort !== null) ? $maxsort + 1 : 0;

        $categoryid = $DB->insert_record('customfield_category', $cat);
    } else {
        $categoryid = $category->id;
    }

    $field1 = $DB->get_record('customfield_field', ['categoryid' => $categoryid, 'shortname' => 'briefing_enabled']);
    if (!$field1) {
        $f1 = new stdClass();
        $f1->shortname = 'briefing_enabled';
        $f1->name = 'Является инструктажем';
        $f1->type = 'checkbox';
        $f1->categoryid = $categoryid;
        $f1->configdata = json_encode([
            'required' => 0,
            'uniquevalues' => 0,
            'visibility' => 0, // 0 = not visible on course page
            'locked' => 0,
            'defaultvalue' => 0
        ]);
        $f1->sortorder = 0;
        $f1->timecreated = time();
        $f1->timemodified = time();
        $DB->insert_record('customfield_field', $f1);
    }

    $field2 = $DB->get_record('customfield_field', ['categoryid' => $categoryid, 'shortname' => 'briefing_period']);
    if (!$field2) {
        $f2 = new stdClass();
        $f2->shortname = 'briefing_period';
        $f2->name = 'Срок действия инструктажа';
        $f2->type = 'select';
        $f2->categoryid = $categoryid;
        $f2->configdata = json_encode([
            'required' => 0,
            'uniquevalues' => 0,
            'visibility' => 0, // 0 = not visible on course page
            'locked' => 0,
            'options' => "6 месяцев\n1 год\n2 года\n3 года\n3 месяца",
            'defaultvalue' => ''
        ]);
        $f2->sortorder = 1;
        $f2->timecreated = time();
        $f2->timemodified = time();
        $DB->insert_record('customfield_field', $f2);
    }

    $field3 = $DB->get_record('customfield_field', ['categoryid' => $categoryid, 'shortname' => 'briefing_autoreset']);
    if (!$field3) {
        $f3 = new stdClass();
        $f3->shortname = 'briefing_autoreset';
        $f3->name = 'Автоматически сбрасывать прохождение по истечении срока';
        $f3->type = 'checkbox';
        $f3->categoryid = $categoryid;
        $f3->configdata = json_encode([
            'required' => 0,
            'uniquevalues' => 0,
            'visibility' => 0, // 0 = not visible on course page
            'locked' => 0,
            'defaultvalue' => 0
        ]);
        $f3->sortorder = 2;
        $f3->timecreated = time();
        $f3->timemodified = time();
        $DB->insert_record('customfield_field', $f3);
    }
}
