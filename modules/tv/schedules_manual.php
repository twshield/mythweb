<?php
/**
 * Schedule a custom recording by manually specifying starttime and length
 *
 * @url         $URL$
 * @date        $Date$
 * @version     $Revision$
 * @author      $Author$
 *
 * @package     MythWeb
 * @subpackage  TV
 *
/**/

// Populate the $Channels array
    load_all_channels();

// Load an existing schedule?
    if ($_GET['recordid'] && $Schedules[$_GET['recordid']])
        $schedule =& $Schedules[$_GET['recordid']];
// Create a new, empty schedule
    else
        $schedule = new Schedule(NULL);

// The user tried to update the recording settings - update the database and the variable in memory
    if (isset($_POST['save'])) {
    // Which type of recording is this?  Make sure an illegal one isn't specified
        switch ($_POST['record']) {
        // Only certain rectypes are allowed
            case rectype_once:        $type = rectype_once;        break;
            case rectype_daily:       $type = rectype_daily;       break;
            case rectype_weekly:      $type = rectype_weekly;      break;
        // Can override
            case rectype_override:    $type = rectype_override;    break;
            case rectype_dontrec:     $type = rectype_dontrec;     break;
        // Everything else gets ignored
            default:                  $type = 0;
        }
    // Cancelling a schedule?
        if ($type == 0) {
        // Cancel this schedule
            if ($schedule && $schedule->recordid) {
            // Delete the schedule
                $schedule->delete();
            // Redirect back to the schedule list
                add_warning(t('The requested recording schedule has been deleted.'));
                save_session_errors();
                header('Location: recording_schedules.php');
                exit;
            }
        }
    // Adding a new schedule
        else {
        // Make sure we have channel info
            $channel = $Channels[$_POST['channel']];
        // Set things as the user requested
            $schedule->profile      = $_POST['profile'];
            $schedule->recgroup     = $_POST['recgroup'];
            $schedule->autoexpire   = $_POST['autoexpire']   ? 1 : 0;
            $schedule->autocommflag = $_POST['autocommflag'] ? 1 : 0;
            $schedule->autouserjob1 = $_POST['autouserjob1'] ? 1 : 0;
            $schedule->autouserjob2 = $_POST['autouserjob2'] ? 1 : 0;
            $schedule->autouserjob3 = $_POST['autouserjob3'] ? 1 : 0;
            $schedule->autouserjob4 = $_POST['autouserjob4'] ? 1 : 0;
            $schedule->maxnewest    = $_POST['maxnewest']    ? 1 : 0;
            $schedule->dupin        = _or($_POST['dupin'],    15);
            $schedule->dupmethod    = _or($_POST['dupmethod'], 6);
            $schedule->recpriority  = intval($_POST['recpriority']);
            $schedule->maxepisodes  = intval($_POST['maxepisodes']);
            $schedule->startoffset  = intval($_POST['startoffset']);
            $schedule->endoffset    = intval($_POST['endoffset']);
        // Some settings specific to manual recordings (since we have no program to match against)
            $schedule->chanid      = $_POST['channel'];
            $schedule->station     = $Channels[$schedule->chanid]->callsign;
            $schedule->starttime   = strtotime($_POST['startdate'].' '.$_POST['starttime']);
            $schedule->endtime     = $schedule->starttime + ($_POST['length'] * 60);
            $schedule->description = 'Manually scheduled';
            $schedule->category    = 'Manual recording';
            $schedule->search      = searchtype_manual;
            $schedule->findday     = date('w',     $schedule->starttime);
            $schedule->findtime    = date('H:m:s', $schedule->starttime);
            $schedule->autotranscode = $_POST['autotranscode'] ? 1 : 0;
            $schedule->transcoder  = $_POST['transcoder'];
        // Figure out the title
            if (strcasecmp($_POST['title'], 'use callsign') == 0) {
                if (prefer_channum)
                    $schedule->title = $channel->channum.' ('.$channel->callsign.')';
                else
                    $schedule->title = $channel->callsign.' ('.$channel->channum.')';
            }
            else
                $schedule->title = $_POST['title'];
        // Now the subtitle
            if (strcasecmp($_POST['subtitle'], 'use datetime') == 0)
                $schedule->subtitle = date('Y-m-d H:i:s', $schedule->starttime)
                                     .' ('.tn('$1 min', '$1 mins', $_POST['length']).')';
            else
                $schedule->subtitle = $_POST['subtitle'];
        // Save the schedule
            $schedule->save($type);
        // Redirect to the new schedule
            header('Location: schedule_manually.php?recordid='.$schedule->recordid);
            exit;
        }
    }
// Load default settings for recpriority, autoexpire etc
    else {
    // Default title/subtitle
        if (!$schedule->title) {
            $schedule->title    = 'use callsign';
            $schedule->subtitle = 'use datetime';
        }
    // Make sure we have a default rectype
        if (!$schedule->type)
            $schedule->type = rectype_once;
    // Date/time/etc
        if (!$schedule->starttime)
            $schedule->starttime = time();
    // auto-commercial-flag
        if (!isset($schedule->autocommflag))
            $schedule->autocommflag = get_backend_setting('AutoCommercialFlag');
    // auto-user-jobs
        if (!isset($schedule->autouserjob1))
            $schedule->autouserjob1 = get_backend_setting('AutoRunUserJob1');
        if (!isset($schedule->autouserjob2))
            $schedule->autouserjob2 = get_backend_setting('AutoRunUserJob2');
        if (!isset($schedule->autouserjob3))
            $schedule->autouserjob3 = get_backend_setting('AutoRunUserJob3');
        if (!isset($schedule->autouserjob4))
            $schedule->autouserjob4 = get_backend_setting('AutoRunUserJob4');
    // auto-transcode
        if (!isset($schedule->autotranscode))
            $schedule->autotranscode = get_backend_setting('AutoTranscode');
    // transcoder
        if (!isset($schedule->transcoder))
            $schedule->transcoder = get_backend_setting('DefaultTranscoder');
    // recpriority
        if (!isset($schedule->recpriority)) {
            $result = mysql_query('SELECT recpriority from channel where chanid='.escape($program->chanid));
            list($schedule->recpriority) = mysql_fetch_row($result);
            mysql_free_result($result);
        }
    // autoexpire
        if (!isset($schedule->autoexpire)) {
            $result = mysql_query("SELECT data from settings where value='AutoExpireDefault'");
            list($schedule->autoexpire) = mysql_fetch_row($result);
            mysql_free_result($result);
        }
    }

// Calculate the length
    $schedule->length = intval(($schedule->endtime - $schedule->starttime) / 60);
    if ($schedule->length < 1)
        $schedule->length = 120;

// Load the class for this page
    require_once theme_dir.'tv/schedules_manual.php';

// Exit
    exit;

/**
 * prints a <select> of the available channels
/**/
    function channel_select($chanid) {
        global $Channels;
        echo '<select name="channel">';
        foreach ($Channels as $channel) {
        // Ignore invisible channels
            if ($channel->visible == 0)
                continue;
        // Print the option
            echo '<option value="'.$channel->chanid.'"';
        // Selected?
            if ($channel->chanid == $chanid)
                echo ' SELECTED';
        // Print ther est of the content
            echo '>';
            if (prefer_channum)
                echo $channel->channum.'&nbsp;&nbsp;('.htmlentities($channel->callsign).')';
            else
                echo htmlentities($channel->callsign).'&nbsp;&nbsp;('.$channel->channum.')';
            echo '</option>';
        }
        echo '</select>';
    }
