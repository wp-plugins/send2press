<?php
/*
Plugin Name: Send2Press
Description: Displays a selectable Send2Press RSS feed, inline, widget or in theme.
Version:     2.4
Author:      Olav Kolbu
Author URI:  http://www.kolbu.com/
Plugin URI:  http://wordpress.org/extend/plugins/send2press/
License:     GPL

Minor parts of WordPress-specific code from various other GPL plugins.

TODO: Multiple widget instances support (possibly)
      Internationalize more output
      See if nofollow should be added on links
*/
/*
Copyright (C) 2008 kolbu.com (olav AT kolbu DOT com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

include_once(ABSPATH . WPINC . '/rss.php');

global $send2press_instance;

if ( ! class_exists('send2press_plugin')) {
    class send2press_plugin {

        // So we don't have to query database on every replacement
        var $settings;

	// Limitation imposed by Send2Press
        var $MIN_CACHETIME = 120;

        var $newstypes = array(
		'Full Feed' => 1,
		'Advertising and Marketing' => 42,
		'Business' => 50,
		'Business: Management Changes' => 51,
		'Business: Awards and Honors' => 175,
		'Business: New Locations and Growth' => 174,
		'Business: Product Launches' => 87,
		'Business: Public Companies' => 69,
		'Business: Reports and Studies' => 54,
		'Communication Arts' => 70,
		'Arts: Fine Art and Artists' => 71,
		'Arts: Photography' => 73,
		'Computing' => 40,
		'Computing: Biometrics' => 136,
		'Computing: Hardware' => 68,
		'Computing: Software' => 41,
		'Construction and Building' => 23,
		'Construction: Architecture' => 98,
		'Construction: Interior Design and Furniture' => 100,
		'Corporate Social Responsibility (CSR)' => 367,
		'Education and Schools' => 44,
		'Electronics Trade' => 24,
		'Employment, HR, Outsourcing' => 108,
		'Energy, Oil and Gas' => 55,
		'Entertainment ' => 25,
		'Ent: Books and Publishing' => 26,
		'Ent: Consumer Eletronics and Gadgets' => 43,
		'Ent: Fashion' => 33,
		'Ent: Movies and Filmmaking' => 36,
		'Ent: Music and Recording Industry' => 27,
		'Ent: Radio and Internet Radio' => 114,
		'Ent: Television and Cable' => 35,
		'Ent: Video and DVD' => 34,
		'Environment and Ecology' => 97,
		'Facilities and Bldg Maintenance' => 249,
		'Family, Parenting and Children' => 104,
		'Food and Beverages' => 28,
		'General Editorial' => 60,
		'Gen: Holistic and Spiritual' => 246,
		'Gen: Women\'s Interest' => 62,
		'Government' => 57,
		'Gov: Elections and Politics' => 58,
		'Green Business' => 372,
		'Health, Diet and Fitness' => 30,
		'Home and Garden' => 119,
		'Insurance' => 31,
		'Internet and Websites' => 45,
		'Jewelry and Diamond' => 279,
		'Manufacturing' => 101,
		'Medical' => 32,
		'Med: Biotech' => 22,
		'Med: Private Practice and Medical Groups' => 133,
		'NonProfit and Charity' => 88,
		'Real Estate' => 99,
		'Restaurant, Hotel, Hospitality' => 117,
		'Safety and Security Solutions' => 86,
		'Sports' => 75,
		'Tax, Accounting, Personal Finance' => 96,
		'Telecom and VoIP' => 79,
		'Transportation' => 78,
		'Travel and Tourism' => 107,
		'Arizona' => 213,
		'California' => 193,
		'CA: Hollywood' => 215,
		'CA: Los Angeles' => 194,
		'CA: San Diego' => 195,
		'CA: San Francisco' => 196,
		'Colorado' => 207,
		'Florida' => 209,
		'FL: Miami' => 210,
		'Georgia' => 260,
		'Illinois' => 216,
		'Maryland' => 274,
		'Massachusetts' => 222,
		'Nevada' => 349,
		'New Jersey' => 205,
		'New York' => 206,
		'North Carolina' => 250,
		'Ohio' => 258,
		'Pennsylvania' => 247,
		'Texas' => 197,
		'TX: Austin' => 203,
		'TX: Dallas' => 198,
		'TX: Houston' => 199,
		'Washington D.C.' => 259,
		'Washington State' => 231,
		'World News' => 218,
		'Canada News' => 219,
            );

        var $desctypes = array(
            'Links' => 0,
            'Summary' => 1,
# Not a valid display type at the moment
#            'Long' => 2,
        );

        // Constructor
        function send2press_plugin() {

            // Form POSTs dealt with elsewhere
            if ( is_array($_POST) ) {
                if ( $_POST['send2press-widget-submit'] ) {
                    $tmp = $_POST['send2press-widget-feed'];
                    $alloptions = get_option('send2press');
                    if ( $alloptions['widget-1'] != $tmp ) {
                        if ( $tmp == '*DEFAULT*' ) {
                            $alloptions['widget-1'] = '';
                        } else {
                            $alloptions['widget-1'] = $tmp;
                        }
                        update_option('send2press', $alloptions);
                    }
                } else if ( $_POST['send2press-options-submit'] ) {
                    // noop
                } else if ( $_POST['send2press-submit'] ) {
                    // noop
                }
            }

	    add_filter('the_content', array(&$this, 'insert_news')); 
            add_action('admin_menu', array(&$this, 'admin_menu'));
            add_action('plugins_loaded', array(&$this, 'widget_init'));

            // Hook for theme coders/hackers
            add_action('send2press', array(&$this, 'display_feed'));

            // Makes it backwards compat pre-2.5 I hope
            if ( function_exists('add_shortcode') ) {
                add_shortcode('send2press', array(&$this, 'my_shortcode_handler'));
             }

        }

        // *************** Admin interface ******************

        // Callback for admin menu
        function admin_menu() {
            add_options_page('Send2Press Options', 'Send2Press',
                             'administrator', __FILE__, 
                              array(&$this, 'plugin_options'));
            add_management_page('Send2Press', 'Send2Press', 
                                'administrator', __FILE__,
                                array(&$this, 'admin_manage'));
               
        }

        // Settings -> Send2Press
        function plugin_options() {

           if (get_bloginfo('version') >= '2.7') {
               $manage_page = 'tools.php';
            } else {
               $manage_page = 'edit.php';
            }
            print <<<EOT
            <div class="wrap">
            <h2>Send2Press&reg; Newswire</h2>
            <p>This plugin allows you to define a number of Send2Press 
               feeds and have them displayed anywhere in content, in a widget
               or in a theme. Any number of inline replacements or theme
               inserts can be made, but only one widget instance is
               permitted in this release. To use the feeds insert one or more
               of the following special html comments or Shortcodes 
               anywhere in user content. Note that Shortcodes, i.e. the
               ones using square brackets, are only available in 
               WordPress 2.5 and above.<p>
               <ul><li><b>&lt;!--send2press--&gt</b> (for default feed)</li>
               <li><b>&lt;!--send2press#feedname--&gt</b></li>
               <li><b>[send2press]</b> (also for default feed)</li>
               <li><b>[send2press name="feedname"]</b></li></ul><p>
               To insert in a theme call <b>do_action('send2press');</b> or 
               alternatively <b>do_action('send2press', 'feedname');</b><p>
               To manage feeds, go to <a href="$manage_page?page=send2press/send2press.php">Manage -> Send2Press</a>, where you will also find more information.<p>
               <a href="http://www.kolbu.com/donations/">Donations Page</a>... ;-)<p>
               <a href="http://www.kolbu.com/2008/10/13/send2press-plugin/">Widget Home Page</a>, leave a comment if you have questions etc.<p>


	       For additional feed options and help information,
	       visit <a href="http://feeds.send2press.com">http://feeds.send2press.com</a> .<p>

	       Send2Press&reg; is a U.S. registered trademark and service
	       mark of Neotrope&reg;. Use of this plug-in on sites which
	       sell press release or newswire services is prohibited.
	       This plug-in may not be altered, re-posted with
	       changes, reverse engineered, or added to any "bundle"
	       of plug-ins without express written permission of
	       Neotrope (<a href="http://www.neotrope.com">www.Neotrope.com</a>) and/or the plug-in author.<p>

               <a href="http://feeds.send2press.com/terms.shtml">Send2Press&reg; Newswire Terms Of Use</a><p>

EOT;
        }

        // Manage -> Send2Press
        function admin_manage() {

            // Edit/delete links
            $mode = trim($_GET['mode']);
            $id = trim($_GET['id']);

            $this->upgrade_options();

            $alloptions = get_option('send2press');

            $flipnewstypes   = array_flip($this->newstypes);
            $flipdesctypes   = array_flip($this->desctypes);

            if ( is_array($_POST) && $_POST['send2press-submit'] ) {

                $newoptions = array();
                $id                       = $_POST['send2press-id'];

                $newoptions['name']       = $_POST['send2press-name'];
                $newoptions['title']      = $_POST['send2press-title'];
                $newoptions['newstype']   = $_POST['send2press-newstype'];
                $newoptions['desctype']   = $_POST['send2press-desctype'];
                $newoptions['numnews']    = $_POST['send2press-numnews'];
                $newoptions['feedtype']   = $flipnewstypes[$newoptions['newstype']];

                if ( $alloptions['feeds'][$id] == $newoptions ) {
                    $text = 'No change...';
                    $mode = 'main';
                } else {
                    $alloptions['feeds'][$id] = $newoptions;
                    update_option('send2press', $alloptions);
 
                    $mode = 'save';
                }
            } else if ( is_array($_POST) && $_POST['send2press-options-cachetime-submit'] ) {
                if ( $_POST['send2press-options-cachetime'] != $alloptions['cachetime'] ) {
                    $alloptions['cachetime'] = $_POST['send2press-options-cachetime'];
		    # Hard limit set by Send2Press
		    if ( $alloptions['cachetime'] < $this->MIN_CACHETIME ) {
		    	$alloptions['cachetime'] = $this->MIN_CACHETIME;
		    }
                    update_option('send2press', $alloptions);
                    $text = "Cache time changed to {$alloptions[cachetime]} minutes.";
                } else {
                    $text = "No change in cache time...";
                }
                $mode = 'main';
            }

            if ( $mode == 'newfeed' ) {
                $newfeed = 0;
                foreach ($alloptions['feeds'] as $k => $v) {
                    if ( $k > $newfeed ) {
                        $newfeed = $k;
                    }
                }
                $newfeed += 1;

                $text = "Please configure new feed and press Save.";
                $mode = 'main';
            }

            if ( $mode == 'save' ) {
                $text = "Saved feed {$alloptions[feeds][$id][name]} [$id].";
                $mode = 'main';
            }

            if ( $mode == 'edit' ) {
                if ( ! empty($text) ) {
                     echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>';
                }
                $text = "Editing feed {$alloptions[feeds][$id][name]} [$id].";

                $edit_id = $id;
                $mode = 'main';
            }

            if ( $mode == 'delete' ) {

                $text = "Deleted feed {$alloptions[feeds][$id][name]} [$id].";
                
                unset($alloptions['feeds'][$id]);

                update_option('send2press', $alloptions);
 
                $mode = 'main';
            }

            // main
            if ( empty($mode) or ($mode == 'main') ) {

                if ( ! empty($text) ) {
                     echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>';
                }
                print '<div class="wrap">';
                print ' <h2>';
                print _e('Manage Send2Press&reg; Newswire Feeds','send2press');
                print '</h2>';
                print ' <table id="the-list-x" width="100%" cellspacing="3" cellpadding="3">';
                print '  <thead>';
                print '   <tr>';
                print '    <th scope="col">';
                print _e('Key','send2press');
                print '</th>';
                print '    <th scope="col">';
                print _e('Name','send2press');
                print '</th>';
                print '    <th scope="col">';
                print _e('Admin-defined title','send2press');
                print '</th>';
                print '    <th scope="col">';
                print _e('Type','send2press');
                print '</th>';
                print '    <th scope="col">';
                print _e('Output','send2press');
                print '</th>';
                print '    <th scope="col">';
                print _e('Max items','send2press');
                print '</th>';
                print '    <th scope="col" colspan="3">';
                print _e('Action','send2press');
                print '</th>';
                print '   </tr>';
                print '  </thead>';

                if (get_bloginfo('version') >= '2.7') {
                    $manage_page = 'tools.php';
                } else {
                    $manage_page = 'edit.php';
                }

                if ( $alloptions['feeds'] || $newfeed ) {
                    $i = 0;

                    foreach ($alloptions['feeds'] as $key => $val) {
                        if ( $i % 2 == 0 ) {
                            print '<tr class="alternate">';
                        } else {
                            print '<tr>';
                        }
                        if ( isset($edit_id) && $edit_id == $key ) {
                            print "<form name=\"send2press_options\" action=\"".
                                  htmlspecialchars($_SERVER['REQUEST_URI']).
                                  "\" method=\"post\" id=\"send2press_options\">";
                                    
                            print "<th scope=\"row\">".$key."</th>";
                            print '<td><input size="10" maxlength="20" id="send2press-name" name="send2press-name" type="text" value="'.$val['name'].'" /></td>';
                            print '<td><input size="20" maxlength="20" id="send2press-title" name="send2press-title" type="text" value="'.$val['title'].'" /></td>';
                            print '<td><select name="send2press-newstype">';
                            $newstype = $val['newstype'];
                            foreach ($this->newstypes as $k => $v) {
                                print '<option '.(strcmp($v,$newstype)?'':'selected').' value="'.$v.'" >'.$k.'</option>';
                            }
                            print '</select></td>';
                            print '<td><select name="send2press-desctype">';
                            $desctype = $val['desctype'];
                            foreach ($this->desctypes as $k => $v) {
                                print '<option '.(strcmp($v,$desctype)?'':'selected').' value="'.$v.'" >'.$k.'</option>';
                            }
                            print '</select></td>';
                            print '<td><input size="3" maxlength="3" id="send2press-numnews" name="send2press-numnews" type="text" value="'.$val['numnews'].'" /></td>';
                            print '<td><input type="submit" value="Save  &raquo;">';
                            print "</td>";
                            print "<input type=\"hidden\" id=\"send2press-id\" name=\"send2press-id\" value=\"$edit_id\" />";
                            print "<input type=\"hidden\" id=\"send2press-submit\" name=\"send2press-submit\" value=\"1\" />";
                            print "</form>";
                        } else {
                            print "<th scope=\"row\">".$key."</th>";
                            print "<td>".$val['name']."</td>";
                            print "<td>".$val['title']."</td>";
                            print "<td>".$flipnewstypes[$val['newstype']]."</td>";
                            print "<td>".$flipdesctypes[$val['desctype']]."</td>";
                            print "<td>".$val['numnews']."</td>";
                            print "<td><a href=\"$manage_page?page=send2press/send2press.php&amp;mode=edit&amp;id=$key\" class=\"edit\">";
                            print __('Edit','send2press');
                            print "</a></td>\n";
                            print "<td><a href=\"$manage_page?page=send2press/send2press.php&amp;mode=delete&amp;id=$key\" class=\"delete\" onclick=\"javascript:check=confirm( '".__("This feed entry will be erased. Delete?",'send2press')."');if(check==false) return false;\">";
                            print __('Delete', 'send2press');
                            print "</a></td>\n";
                        }
                        print '</tr>';

                        $i++;
                    }
                    if ( $newfeed ) {

                        print "<form name=\"send2press_options\" action=\"".
                              htmlspecialchars($_SERVER['REQUEST_URI']).
                              "\" method=\"post\" id=\"send2press_options\">";
                                
                        print "<th scope=\"row\">".$newfeed."</th>";
                        print '<td><input size="10" maxlength="20" id="send2press-name" name="send2press-name" type="text" value="NEW" /></td>';
                        print '<td><input size="20" maxlength="20" id="send2press-title" name="send2press-title" type="text" value="" /></td>';
                        print '<td><select name="send2press-newstype">';
                        foreach ($this->newstypes as $k => $v) {
                            print '<option value="'.$v.'" >'.$k.'</option>';
                        }
                        print '</select></td>';
                        print '<td><select name="send2press-desctype">';
                        foreach ($this->desctypes as $k => $v) {
                            print '<option value="'.$v.'" >'.$k.'</option>';
                        }
                        print '</select></td>';
                        print '<td><input size="3" maxlength="3" id="send2press-numnews" name="send2press-numnews" type="text" value="10" /></td>';
                        print '<td><input type="submit" value="Save  &raquo;">';
                        print "</td>";
                        print "<input type=\"hidden\" id=\"send2press-id\" name=\"send2press-id\" value=\"$newfeed\" />";
                        print "<input type=\"hidden\" id=\"send2press-newfeed\" name=\"send2press-newfeed\" value=\"1\" />";
                        print "<input type=\"hidden\" id=\"send2press-submit\" name=\"send2press-submit\" value=\"1\" />";
                        print "</form>";
                    } else {
                        print "</tr><tr><td colspan=\"12\"><a href=\"$manage_page?page=send2press/send2press.php&amp;mode=newfeed\" class=\"newfeed\">";
                        print __('Add extra feed','send2press');
                        print "</a></td></tr>";

                    }
                } else {
                    print '<tr><td colspan="12" align="center"><b>';
                    print __('No feeds found(!)','send2press');
                    print '</b></td></tr>';
                    print "</tr><tr><td colspan=\"12\"><a href=\"$manage_page?page=send2press/send2press.php&amp;mode=newfeed\" class=\"newfeed\">";
                    print __('Add feed','send2press');
                    print "</a></td></tr>";
                }
                print ' </table>';
                print '<h2>';
                print _e('Global configuration parameters','send2press');
                print '</h2>';
                print ' <form method="post">';
                print ' <table id="the-cachetime" cellspacing="3" cellpadding="3">';
                print '<tr><td><b>Cache time:</b></td>';
                print '<td><input size="6" maxlength="6" id="send2press-options-cachetime" name="send2press-options-cachetime" type="text" value="'.$alloptions['cachetime'].'" /> minutes</td>';
                print '<input type="hidden" id="send2press-options-cachetime-submit" name="send2press-options-cachetime-submit" value="1" />';
                print '<td><input type="submit" value="Save  &raquo;"></td></tr>';
                print ' </table>';
                print '</form>'; 

                print '<h2>';
                print _e('Information','send2press');
                print '</h2>';
                print ' <table id="the-list-x" width="100%" cellspacing="3" cellpadding="3">';
                print '<tr><td><b>Key</b></td><td>Unique identifier used internally.</td></tr>';
                print '<tr><td><b>Name</b></td><td>Optional name to be able to reference a specific feed as e.g. ';
                print ' <b>&lt;!--send2press#myname--&gt;</b>. ';
                print ' If more than one feed shares the same name, a random among these will be picked each time. ';
                print ' The one(s) without a name will be treated as the default feed(s), i.e. used for <b>&lt;!--send2press--&gt;</b> ';
                print ' or widget feed type <b>*DEFAULT*</b>. If you have Wordpress 2.5 ';
                print ' or above, you can also use Shortcodes on the form <b>[send2press]</b> ';
                print ' (for default feed) or <b>[send2press name="feedname"]</b>. And finally ';
                print ' you can use <b>do_action(\'send2press\');</b> or <b>do_action(\'send2press\', \'feedname\');</b> ';
                print ' in themes.</td></tr>';
                print '<tr><td><b>Admin-defined title</b></td><td>Optional feed title. If not set, a reasonable title based on ';
                print 'Type will be used.</td></tr>';
                print '<tr><td><b>Type</b></td><td>The type of news to present.</td></tr>';
                print '<tr><td><b>Output</b></td><td>Links only, or links and summary.</td></tr>';
                print '<tr><td><b>Max items</b></td><td>Maximum number of news items to show for this feed. If the feed contains ';
                print 'less than the requested items, only the number of items in the feed will obviously be displayed. 0 means all items, and 10 is the default.</td></tr>';
                print "<tr><td><b>Cache time</b></td><td>Minimum number of minutes that WordPress should cache a Send2Press feed before fetching it again. Hard limit of minimum $this->MIN_CACHETIME minutes.</td></tr>";
                print ' </table>';
                print '</div>';
            }
        }

        // ************* Output *****************

       // The function that gets called from themes
       function display_feed($data) {
           global $settings;
           $settings = get_option('send2press');
           print $this->random_feed($data);
           unset($settings);
       }


        // Callback for inline replacement
        function insert_news($data) {
            global $settings;

            // Allow for multi-feed sites
            $tag = '/<!--send2press(|#.*?)-->/';

            // We may have old style options
            $this->upgrade_options();

            // Avoid getting this for each callback
            $settings   = get_option('send2press');

            $result = preg_replace_callback($tag, 
                              array(&$this, 'inline_replace_callback'), $data);

            unset($settings);

            return $result;
        }


        // *********** Widget support **************
        function widget_init() {

            // Check for the required plugin functions. This will prevent fatal
            // errors occurring when you deactivate the dynamic-sidebar plugin.
            if ( !function_exists('register_sidebar_widget') )
                return;

            register_widget_control('Send2Press', 
                                   array(&$this, 'widget_control'), 200, 100);

            // wp_* has more features, presumably fixed at a later date
            register_sidebar_widget('Send2Press',
                                   array(&$this, 'widget_output'));

        }

        function widget_control() {

            // We may have old style options
            $this->upgrade_options();

            $alloptions = get_option('send2press');
            $thisfeed = $alloptions['widget-1'];

            print '<p><label for="send2press-feed">Select feed:</label>';
            print '<select style="vertical-align:middle;" name="send2press-widget-feed">';

            $allfeeds = array();
            foreach ($alloptions['feeds'] as $k => $v) {
                $allfeeds[strlen($v['name'])?$v['name']:'*DEFAULT*'] = 1;
            } 
            foreach ($allfeeds as $k => $v) {
                print '<option '.($k==$thisfeed?'':'selected').' value="'.$k.'" >'.$k.'</option>';
            }
            print '</select><p>';
            print '<input type="hidden" id="send2press-widget-submit" name="send2press-widget-submit" value="1" />';


        }

        // Called every time we want to display ourselves as a sidebar widget
        function widget_output($args) {
            extract($args); // Gives us $before_ and $after_ I presume
                        
            // We may have old style options
            $this->upgrade_options();

            $alloptions = get_option('send2press');
            $matching_feeds = array();
            foreach ($alloptions['feeds'] as $k => $v) {
                if ( (string)$v['name'] == $alloptions['widget-1'] ) { 
                    $matching_feeds[] = $k;
                } 
            }
            if ( ! count($matching_feeds) ) {
                if ( ! strlen($alloptions['widget-1']) ) {
                    $content = '<ul><b>No default feed available</b></ul>';
                } else {
                    $content = "<ul>Unknown feed name <b>{$alloptions[widget-1]}</b> used</ul>";
                }
                echo $before_widget;
                echo $before_title . __('Send2Press<br>Error','send2press') . $after_title . '<div>';
                echo $content;
                echo '</div>' . $after_widget;
                return;
            }
            $feed_id = $matching_feeds[rand(0, count($matching_feeds)-1)];
            $options = $alloptions['feeds'][$feed_id];

            $feedtype   = $options['feedtype'];
            $cachetime  = $alloptions['cachetime'];

            if ( strlen($options['title']) ) {
                $title = $options['title'];
            } else {
                $title = 'Send2Press<br>'.$feedtype;
            }

            echo $before_widget;
            echo $before_title . $title . $after_title . '<div>';
            echo $this->get_feed($options, $cachetime);
            echo '</div>' . $after_widget;
        }

        // ************** The actual work ****************
        function get_feed(&$options, $cachetime) {

            if ( ! isset($options['newstype']) ) {
                return 'Options not set, visit plugin configuation screen.'; 
            }

            $newstype   = $options['newstype'];
            $numnews    = $options['numnews'] ? $options['numnews'] : 0;
            $desctype   = $options['desctype'];

            $result = '<ul>';

	    # Last minute sanity checks
	    if ( $newstype < 1 ) {
		$newstype = 1;
	    }
            $feedurl = sprintf("http://www.send2press.com/XML/s2p-feed%d.xml",
			      $newstype);

            // Using the WP RSS fetcher (MagpieRSS). It has serious
            // GC problems though.
            define('MAGPIE_CACHE_AGE', $cachetime * 60);
            define('MAGPIE_CACHE_ON', 1);
            define('MAGPIE_DEBUG', 1);

            $rss = fetch_rss($feedurl);

            if ( ! is_object($rss) ) {
                return '<ul>Send2Press unavailable</ul>';
            }
            # Zero means all items
            if ( $numnews ) {
                $rss->items = array_slice($rss->items, 0, $numnews);
            }
            foreach ( $rss->items as $item ) {
                $title       = $item['title'];
                $date        = $item['pubdate'];
                $link        = $item['link'];
                $category    = $item['category'];
                $description = $item['description'];
                $content     = $item['content']['encoded'];

		if ( $desctype == 1 ) { // Medium, title and short
		    $result .= "<li><a href=\"$link\" target=\"_blank\">$title</a><br>$description</li>";
# Not a valid option at the moment
#		} else if ( $desctype == 2 ) { // Long, the works
#		    $result .= "<li><a href=\"$link\" target=\"_blank\">$title</a><br>$content</li>";
		} else { // Short, title and desc as tooltip
                    // Absolutely no tags in tooltips
                    $tooltip = preg_replace('/<[^>]+>/','',$description);

		    $result .= "<li><a href=\"$link\" target=\"_blank\" ".
                               "title=\"$tooltip\">$title</a></li>";
		}
            } 
            return $result.'</ul>';
        }

        // *********** Shortcode support **************
        function my_shortcode_handler($atts, $content=null) {
            global $settings;
            $settings = get_option('send2press');
            return $this->random_feed($atts['name']);
            unset($settings);
        }

        
        // *********** inline replacement callback support **************
        function inline_replace_callback($matches) {

            if ( ! strlen($matches[1]) ) { // Default
                $feedname = '';
            } else {
                $feedname = substr($matches[1], 1); // Skip #
            }
            return $this->random_feed($feedname);
        }

        // ************** Support functions ****************

        function random_feed($name) {
            global $settings;

            $matching_feeds = array();
            foreach ($settings['feeds'] as $k => $v) {
                if ( (string)$v['name'] == $name ) { 
                    $matching_feeds[] = $k;
                } 
            }
            if ( ! count($matching_feeds) ) {
                if ( ! strlen($name) ) {
                    return '<ul><b>No default feed available</b></ul>';
                } else {
                    return "<ul>Unknown feed name <b>$name</b> used</ul>";
                }
            }
            $feed_id = $matching_feeds[rand(0, count($matching_feeds)-1)];
            $feed = $settings['feeds'][$feed_id];

            if ( strlen($feed['title']) ) {
                $title = $feed['title'];
            } else {
                $title = 'Send2Press : '.$feed['feedtype'];
            }

            $result = '<!-- Start Send2Press code -->';
            $result .= "<div id=\"send2press-inline\"><h3>$title</h4>";
            $result .= $this->get_feed($feed, $settings['cachetime']);
            $result .= '</div><!-- End Send2Press code -->';
            return $result;
        }

        function html_decode($in) {
            $patterns = array(
                '/&amp;/',
                '/&quot;/',
                '/&lt;/',
                '/&gt;/',
            );
            $replacements = array(
                '&',
                '"',
                '<',
                '>',
            );
            $tmp = preg_replace($patterns, $replacements, $in);
            return preg_replace('/&#39;/','\'',$tmp);

        }

        // if needed
        function upgrade_options() {
            $options = get_option('send2press');

            if ( !is_array($options) ) {
                // First time ever
                $options = array();
                $options['feeds']     = array( $this->default_feed() );
                $options['widget-1']  = 0;
                $options['cachetime'] = $this->MIN_CACHETIME;
                update_option('send2press', $options);
            }
        }

        function default_feed() {
            return array( 'numnews' => 10,
                          'name' => '',
                          'title' => 'National News',
                          'newstype' => 1,
                          'desctype' => 0,
                          'feedtype' => 'Full Feed');
        }
    }

    // Instantiate
    $send2press_instance &= new send2press_plugin();

}
?>
