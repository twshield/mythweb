<?php
/***                                                                        ***\
    theme.php                             Last Updated: 2004.10.25 (jbuckshin)

    This is the main theme class for the Default MythWeb theme.  It should
    not be instantiated directly, but will most likely contain methods
    called from its child classes via the parent:: construct.
\***                                                                        ***/

class Theme {

    function print_header($page_title = 'MythWeb') {
        // Print the appropriate header information
        header("Content-Type: text/vnd.wap.wml");
        //header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        //header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");    

        echo "<?xml version=\"1.0\"?>"
?>
<!DOCTYPE wml PUBLIC "-//WAPFORUM//DTD WML 1.1//EN" "http://www.wapforum.org/DTD/wml_1.1.xml">

<wml>

<card id="main" title="MythWeb">
<p><img src="<?php echo theme_dir?>img/myth.wbmp" alt="mythtv"></img></p> 
<?php
    }

    function print_menu_content() {
?>
<p><a href="program_listing.php"><?php echo _LANG_LISTINGS; ?></a></p>
<p><a href="scheduled_recordings.php"><?php echo _LANG_SCHEDULE; ?></a></p>
<p><a href="recorded_programs.php"><?php echo _LANG_RECORDED_PROGRAMS;?></a></p>
<p><a href="search.php"><?php echo _LANG_SEARCH;?></a></p>
<p><a href="<?php echo theme_dir?>status.php"><?php echo ucfirst(_LANG_BACKEND_STATUS);?></a></p>
<?php
    }

    function print_footer() {
?>
</wml>
<?php
    }
}
?>