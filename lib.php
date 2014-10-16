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

function search($query, $course, $offset, &$countentries) {

    global $CFG, $USER, $DB;

    // TODO: Use the search style @ sql.php and use placeholders!

    // Some differences in syntax for PostgreSQL.
    // TODO: Modify this to support also MSSQL and Oracle.
    if ($CFG->dbfamily == "postgres") {
        $LIKE = "ILIKE";   // Case-insensitive.
        $NOTLIKE = "NOT ILIKE";   // Case-insensitive.
        $REGEXP = "~*";
        $NOTREGEXP = "!~*";
    } else {
        $LIKE = "LIKE";
        $NOTLIKE = "NOT LIKE";
        $REGEXP = "REGEXP";
        $NOTREGEXP = "NOT REGEXP";
    }

    // Perform the search only in books fulfilling mod/book:read and (visible or moodle/course:viewhiddenactivities)
    $bookids = array();
    if (! $books = get_all_instances_in_course('book', $course)) {
        notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'book')), "../../course/view.php?id=$course->id");
        die;
    }
    foreach ($books as $book) {
        $cm = get_coursemodule_from_instance("book", $book->id, $course->id);
        $context = context_module::instance($cm->id);
        if ($cm->visible || has_capability('moodle/course:viewhiddenactivities', $context)) {
            if (has_capability('mod/book:read', $context)) {
                $bookids[] = $book->id;
            }
        }
    }

    // Seach starts.
    $titlesearch = "";
    $contentsearch = "";

    $searchterms = explode(" ",$query);

    foreach ($searchterms as $searchterm) {

        if ($titlesearch) {
            $titlesearch .= " AND ";
        }
        if ($contentsearch) {
            $contentsearch .= " AND ";
        }

        if (substr($searchterm,0,1) == "+") {
            $searchterm = substr($searchterm,1);
            $titlesearch .= " bc.title $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $contentsearch .= " bc.content $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
        } else if (substr($searchterm,0,1) == "-") {
            $searchterm = substr($searchterm,1);
            $titlesearch .= " bc.title $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $contentsearch .= " bc.content $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
        } else {
            $titlesearch .= " bc.title $LIKE '%$searchterm%' ";
            $contentsearch .= " bc.content $LIKE '%$searchterm%' ";
        }
    }

    // Add seach conditions in titles and contents.
    $where = "AND (( $titlesearch) OR ($contentsearch) ) ";

    // Main query, only to allowed books and not hidden chapters.
    $sqlselect  = "SELECT DISTINCT bc.*";
    $sqlfrom    = "FROM {$CFG->prefix}book_chapters bc,
                        {$CFG->prefix}book b";
    $sqlwhere   = "WHERE b.course = $course->id AND
                         b.id IN (" . implode($bookids, ', ') . ") AND
                         bc.bookid = b.id AND
                         bc.hidden = 0
                         $where";
    $sqlorderby = "ORDER BY bc.bookid, bc.pagenum";

    // Set page limits.
    $limitfrom = $offset;
    $limitnum = 0;
    if ( $offset >= 0 ) {
        $limitnum = BOOKMAXRESULTSPERPAGE;
    }

    $countentries = $DB->count_records_sql("select count(*) $sqlfrom $sqlwhere", array());
    $allentries = $DB->get_records_sql("$sqlselect $sqlfrom $sqlwhere $sqlorderby", array(), $limitfrom, $limitnum);

    return $allentries;
}

function search_form() {
$coursefield = '<input type="hidden" name="courseid" value="'.$course->id.'"/>';
$pagefield = '<input type="hidden" name="page" value="0"/>';
$searchbox = '<input type="text" name="bsquery" size="20" maxlength="255" value="'.s($query).'"/>';
$submitbutton = '<input type="submit" name="submit" value="'.$searchbooks.'"/>';

$content = $coursefield.$pagefield.$searchbox.$submitbutton;

$form = '<form method="get" action="'.$CFG->wwwroot.'/blocks/search_books/search_books.php" name="form" id="form">'.$content.'</form>';

return $form;'<div style="margin-left: auto; margin-right: auto; width: 100%; text-align: center">' . $form . '</div>';


}
