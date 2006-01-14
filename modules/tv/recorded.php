<?php
/**
 * view and manipulate recorded programs.
 *
 * @url         $URL$
 * @date        $Date$
 * @version     $Revision$
 * @author      $Author$
 * @license     GPL
 *
 * @package     MythWeb
 * @subpackage  TV
 *
/**/

// Make sure the recordings directory exists
    if (file_exists('data/recordings')) {
    // File is not a directory or a symlink
        if (!is_dir('data/recordings') && !is_link('data/recordings')) {
            $Error = 'An invalid file exists at data/recordings.  Please remove it in'
                    .' order to use the tv portions of MythWeb.';
            require_once 'templates/_error.php';
        }
    }
// Create the symlink, if possible.
//
// NOTE:  Errors have been disabled because if I turn them on, people hosting
//        MythWeb on Windows machines will have issues.  I will turn the errors
//        back on when I find a clean way to do so.
//
    else {
        $dir = $db->query_col('SELECT data
                                 FROM settings
                                WHERE value="RecordFilePrefix" AND hostname=?',
                              hostname
                             );
        if ($dir) {
            $ret = @symlink($dir, 'data/recordings');
            if (!$ret) {
                #$Error = "Could not create a symlink to $dir, the local recordings directory"
                #        .' for this hostname ('.hostname.').  Please create a symlink to your'
                #        .' recordings directory at data/recordings in order to use the tv'
                #        .' portions of MythWeb.';
                #require_once 'templates/_error.php';
            }
        }
        else {
            #$Error = 'Could not find a value in the database for the recordings directory'
            #        .' for this hostname ('.hostname.').  Please create a symlink to your'
            #        .' recordings directory at data/recordings in order to use the tv'
            #        .' portions of MythWeb.';
            #require_once 'templates/_error.php';
        }
    }

// Load the sorting routines
    require_once "includes/sorting.php";

// Delete a program?
    isset($_GET['forget_old']) or $_GET['forget_old'] = $_POST['forget_old'];
    isset($_GET['delete'])     or $_GET['delete']     = $_POST['delete'];
    isset($_GET['file'])       or $_GET['file']       = $_POST['file'];
    if ($_GET['delete']) {
    // Keep a previous-row counter to return to after deleting
        $prev_row = -2;
    // We need to scan through the available recordings to get at the additional information required by the DELETE_RECORDING query
        foreach (get_backend_rows('QUERY_RECORDINGS Delete') as $row) {
        // increment if row has the same title as the show we're deleting or if viewing 'all recordings'
            if (($_SESSION['recorded_title'] == $row[0]) || ($_SESSION['recorded_title'] == ''))
                $prev_row++;
        // This row isn't the one we're looking for
            if ($row[8] != $_GET['file'])
                continue;
        // Forget all knowledge of old recordings
            if (isset($_GET['forget_old'])) {
                $show = new Program($row);
                $show->rec_forget_old();
            // Delay a second so the backend can catch up
                sleep(1);
            }
        // Delete the recording
            backend_command(array('DELETE_RECORDING', implode(backend_sep, $row), '0'));
        // Exit early if we're in AJAX mode.
            if (isset($_GET['ajax'])) {
                echo 'success';
                exit;
            }
        // No need to scan the rest of the items, so leave early
            break;
        }
    // Redirect back to the page again, but without the query string, so reloads are cleaner
    // WML browser often require a fully qualified URL for redirects to work. Also, set content type
        if ($_SESSION['Theme'] == 'wml') {
            header('Content-type: text/vnd.wap.wml');
            redirect_browser('http://'.$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'].'?refresh');
        }
    // Return to the row just prior to the one deleted
    //  (with some fuzz to account for normal screen height
    //   -- remember that rows are numbered starting at zero)
        else {
            redirect_browser(root.'tv/recorded?refresh'.($prev_row > 0 ? "#$prev_row" : ''));
        }
    // redirect_browser calls exit() on its own
    }

// Queries for a specific program title
    isset($_GET['title'])    or $_GET['title']    = $_POST['title'];
    isset($_GET['recgroup']) or $_GET['recgroup'] = $_POST['recgroup'];
    isset($_GET['title'])    or $_GET['title']    = isset($_GET['refresh']) ? '' : $_SESSION['recorded_title'];
    isset($_GET['recgroup']) or $_GET['recgroup'] = isset($_GET['refresh']) ? '' : $_SESSION['recorded_recgroup'];

// Parse the program list
    $recordings     = get_backend_rows('QUERY_RECORDINGS Delete');
    $Total_Used     = 0;
    $Total_Time     = 0;
    $Total_Programs = 0;
    $All_Shows      = array();
    $Programs       = array();
    $Groups         = array();
    while (true) {
        $Program_Titles = array();
        foreach ($recordings as $key => $record) {
        // Skip the offset
            if ($key === 'offset')  // WHY IN THE WORLD DOES 0 == 'offset'?!?!?  so we use ===
                continue;
        // Create a new program object
            $show = new Program($record);
        // Make sure this is a valid show
            if (!$show->chanid || $show->length < 1)
                continue;
        // Keep track of the total time and disk space used
            $Total_Time += $show->length;
            $Total_Used += $show->filesize;
        // Skip programs the user doesn't want to look at, but keep track of their names and how many episodes we have recorded
            $Total_Programs++;
            $Program_Titles[$record[0]]++;
            $Groups[$record[30]]++;
            if ($_GET['title'] && $_GET['title'] != $record[0])
                continue;
            if ($_GET['recgroup'] && $_GET['recgroup'] != $record[30])
                continue;
        // Make sure that everything we're dealing with is an array
            if (!is_array($Programs[$show->title]))
                $Programs[$show->title] = array();
        // Generate any thumbnail images we might need
            if (show_recorded_pixmaps) {
                generate_preview_pixmap($show);
            }
        // Assign a reference to this show to the various arrays
            $All_Shows[]                         =& $show;
            $Programs[$show->title][]            =& $show;
            $Channels[$show->chanid]->programs[] =& $show;
            unset($show);
        }
    // Did we try to view a program that we don't have recorded?  Revert to showing all programs
        if ($_GET['title'] && !count($Programs) && !isset($_GET['refresh'])) {
            $Warnings[] = 'No matching programs found.  Showing all programs.';
            unset($_GET['title']);
        }
    // Found some programs, let's move on
        else
            break;
    }

// Sort the program titles
    ksort($Program_Titles);

// Keep track of the program/title the user wants to view
    $_SESSION['recorded_title']    = $_GET['title'];
    $_SESSION['recorded_recgroup'] = $_GET['recgroup'];

// The default sorting choice isn't so good for recorded programs, so we'll set our own default
    if (!is_array($_SESSION['recorded_sortby']) || !count($_SESSION['recorded_sortby']))
        $_SESSION['recorded_sortby'] = array(array('field' => 'airdate',
                                                   'reverse' => true),
                                             array('field' => 'title',
                                                   'reverse' => false));

// Sort the programs
    if (count($All_Shows))
        sort_programs($All_Shows, 'recorded_sortby');

// How much free disk space on the backend machine?
    list($size_high, $size_low, $used_high, $used_low) = explode(backend_sep, backend_command('QUERY_FREE_SPACE'));
    define(disk_size, (($size_high + ($size_low < 0)) * 4294967296 + $size_low) * 1024);
    define(disk_used, (($used_high + ($used_low < 0)) * 4294967296 + $used_low) * 1024);

// Load the class for this page
    require_once theme_dir.'tv/recorded.php';

// Exit
    exit;

