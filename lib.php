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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

function search($query, $course, $offset, &$countentries) {

    global $CFG, $USER, $DB;
    if (empty($query)) {
        return false;
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

    // build an array of named parameters to use in the sql query
    $searchparams = array();
    $i = 0;
    foreach ($searchterms as $searchterm) {

        if ($titlesearch) {
            $titlesearch .= " AND ";
        }
        if ($contentsearch) {
            $contentsearch .= " AND ";
        }

        $searchparams['param'.++$i] = "%$searchterm%";
        $titlesearch .= $DB->sql_like('bc.title', ":param$i", false);
        $searchparams['param'.++$i] = "%$searchterm%";
        $contentsearch .= $DB->sql_like('bc.content', ":param$i", false);
    }

    // Add search conditions in titles and contents.
    $where = "AND (( $titlesearch) OR ($contentsearch) ) ";

    list($insql, $inparams) = $DB->get_in_or_equal($bookids, SQL_PARAMS_NAMED);

    // Main query, only to allowed books and not hidden chapters.
    $sqlselect  = "SELECT DISTINCT bc.*";
    $sqlfrom    = "FROM {book_chapters} bc,
                        {book} b";
    $sqlwhere   = "WHERE b.course = :courseid AND
                         b.id $insql AND
                         bc.bookid = b.id AND
                         bc.hidden = :hidden
                         $where";
    $sqlorderby = "ORDER BY bc.bookid, bc.pagenum";

    $queryparams = array(
        'courseid' => $course->id,
        'hidden' => 0,
    );
    $sqlparams = array_merge($inparams, $queryparams, $searchparams);

    // Set page limits.
    $limitfrom = $offset;
    $limitnum = 0;
    if ( $offset >= 0 ) {
        $limitnum = BOOKMAXRESULTSPERPAGE;
    }

    $sqlcount = "SELECT COUNT(*) $sqlfrom $sqlwhere";
    $sqlallentries = "$sqlselect $sqlfrom $sqlwhere $sqlorderby";
    $countentries = $DB->count_records_sql($sqlcount, $sqlparams);
    $allentries = $DB->get_recordset_sql($sqlallentries, $sqlparams, $limitfrom, $limitnum);

    return $allentries;
}

function search_form($course, $query) {
    global $CFG;

    $coursefield = '<input type="hidden" name="courseid" value="'.$course->id.'"/>';
    $pagefield = '<input type="hidden" name="page" value="0"/>';
    $searchbox = '<input type="text" name="bsquery" size="20" maxlength="255" value="'.s($query).'"/>';
    $submitbutton = '<input type="submit" name="submit" value="'.get_string('search', 'moodle').'"/>';

    $content = $coursefield.$pagefield.$searchbox.$submitbutton;

    $form = '<form method="get" action="'.$CFG->wwwroot.'/blocks/search_books/search_books.php" name="form" id="form">'.$content.'</form>';
    $form = '<div style="margin-left: auto; margin-right: auto; width: 100%; text-align: center">' . $form . '</div>';

    return $form;
}

function search_results($bookresults, &$startindex, &$endindex, $query, $countresults, $page, $course) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/glossary/lib.php');

    $strresults = get_string('results', 'block_search_books');
    $of = get_string('of', 'block_search_books');
    $for = get_string('for', 'block_search_books');
    // Print results page tip.
    $page_bar = glossary_get_paging_bar($countresults, $page, BOOKMAXRESULTSPERPAGE, "search_books.php?bsquery=".urlencode(stripslashes($query))."&amp;courseid=$course->id&amp;");

    $results = html_writer::start_tag('div', array('class' => 'block_search_books results'));
    if (!empty($bookresults)) {
        // Print header
        $results .= $page_bar;
        // Prepare each entry (hilight, footer...)
        $results .= html_writer::start_tag('ul');
        foreach ($bookresults as $entry) {
            $book = $DB->get_record('book', array('id' => $entry->bookid));
            $cm = get_coursemodule_from_instance("book", $book->id, $course->id);

            //To show where each entry belongs to
            $result = html_writer::start_tag('li');
            $result.= html_writer::link(new moodle_url("/mod/book/view.php", array('id' => $cm->id)), format_string($book->name, true));
            $result.= "&nbsp;&raquo;&nbsp;";
            $result.= html_writer::link(new moodle_url("/mod/book/view.php", array('id' => $cm->id, 'chapterid' => $entry->id)), format_string($entry->title, true));
            $result.= html_writer::end_tag('li');

            $results .= $result;
            $endindex++;
        }
        $bookresults->close();
        $results .= html_writer::end_tag('ul');
        $results .= html_writer::start_tag('div', array('class' => 'results-count'));
        $results .= html_writer::tag('p', $strresults.' <b>'.($startindex+1).'</b> - <b>'.($endindex-1).'</b> '.$of.'<b> '.$countresults.' </b>'.$for.'<b> "'.s($query).'"</b></p>');
        $results .= html_writer::end_tag('div');
        $results .= $page_bar;
        $results .= html_writer::end_tag('div');
    } else {
        $results .= html_writer::empty_tag('br');
        $results .= get_string("norecordsfound","block_search_books");
    }
    return $results;
}
