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
 * Course archive
 *
 * @package    enrol
 * @subpackage coursemeta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Add new instance of the plugin to all existing courses.
function xmldb_enrol_coursemeta_install() {
    global $CFG, $DB;

    // Get all courses.
    $courses = get_courses();

    // Loop through courses and add record into enrol table for each course.
    foreach ($courses as $course) {
        // Check to see if record exists.
        $recordcheck = $DB->get_records('enrol', array('courseid' => $course->id, 'enrol' => 'coursemeta'));

        // If record does not exist, add it.
        if (!$recordcheck) {
            $record = new stdClass();
            $record->enrol                  = 'coursemeta';
            $record->status                 = 1;
            $record->customint1             = 0;
            $record->courseid               = $course->id;
            $record->timecreated            = time();
            $record->timemodified           = time();
            // Insert record to enrol table. 
            $DB->insert_record('enrol', $record, false);
        }
    }
}
