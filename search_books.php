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
 * Search books main script.
 *
 * @package    block_search_books
 * @copyright  2009 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('/home/chrisw/dev/uniceflocal/config.php');
require_once($CFG->dirroot . '/blocks/search_books/lib.php');

define('BOOKMAXRESULTSPERPAGE', get_config('search_books', 'maxresultsperpage'));  // Limit results per page.

$courseid = required_param('courseid', PARAM_INT);
$query    = required_param('bsquery', PARAM_NOTAGS);
$page     = optional_param('page', 0, PARAM_INT);

//////////////////////////////////////////////////////////
// The main part of this script

$PAGE->set_pagelayout('course');
$PAGE->set_url($FULLME);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);

$strbooks = get_string('modulenameplural', 'book');
$searchresults = get_string('searchresults', 'block_search_books');
$seconds = get_string('seconds', 'block_search_books');

$PAGE->navbar->add($strbooks, new moodle_url('/mod/book/index.php', array('id' => $course->id)));
$PAGE->navbar->add($searchresults);

$PAGE->set_title($searchresults);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

$start = (BOOKMAXRESULTSPERPAGE * $page);

// Process the query.
$query = trim(strip_tags($query));

// Launch the SQL quey.
$bookresults = search( $query, $course, $start, $countentries);

//echo search_form($course, $query); // printed in course theme header instead.

// Process $bookresults, if present.
$startindex = $start;
$endindex = $start + count($bookresults);

$countresults = $countentries;

// Iterate over results.
echo search_results($bookresults, $startindex, $endindex, $query, $countresults, $page, $course);

echo $OUTPUT->single_button(new moodle_url($CFG->wwwroot.'/course/view.php', array('id' => $COURSE->id)), get_string('returntocourse', 'block_search_books'));
echo $OUTPUT->footer();
