<?php
/**
 * Upgrade Hotaru CMS
 * 
 * Steps through the set-up process, creating database tables and registering 
 * the Admin user. Note: You must delete this file after installation as it 
 * poses a serious security risk if left.
 *
 * PHP version 5
 *
 * LICENSE: Hotaru CMS is free software: you can redistribute it and/or 
 * modify it under the terms of the GNU General Public License as 
 * published by the Free Software Foundation, either version 3 of 
 * the License, or (at your option) any later version. 
 *
 * Hotaru CMS is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or 
 * FITNESS FOR A PARTICULAR PURPOSE. 
 *
 * You should have received a copy of the GNU General Public License along 
 * with Hotaru CMS. If not, see http://www.gnu.org/licenses/.
 * 
 * @category  Content Management System
 * @package   HotaruCMS
 * @author    Nick Ramsay <admin@hotarucms.org>
 * @copyright Copyright (c) 2010, Hotaru CMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link      http://www.hotarucms.org/
 */


$h = new Hotaru('start'); // must come before language inclusion

global $lang;

$cage = init_inspekt_cage();
$step = $cage->get->getInt('step');        // Installation steps.

        $show_next = true;    
    
        $db = new ezSQL_mysql(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
        $db->show_errors = false;
	$database_exists = $db->quick_connect(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);	
	if (!$database_exists) {
	    $h->messages[$lang['install_step1_no_db_exists_failure']] = 'red';
            $old_version = '';
            $show_next = false;
            $step = 1;  // rest to step if db not available
	} else {        
            $table_exists = $db->table_exists('miscdata');
            if (!$table_exists) {
                $h->messages[$lang['install_step1_no_table_exists_failure']] = 'red';
                $old_version = '';
                $show_next = false;
                $step = 1;  // rest to step if db not available
            } else {
                $h = new Hotaru();  // replace start one with full one
                $sql = "SELECT miscdata_value FROM " . TABLE_MISCDATA . " WHERE miscdata_key = %s";
                $old_version = $h->db->get_var($h->db->prepare($sql, "hotaru_version"));
            }
        }
        



switch ($step) {
	case 0:		
		break;
	case 1:
		upgrade_check($h, $old_version, $show_next);
		break;
	case 2:
		do_upgrade($h, $old_version);
		upgrade_complete($h);    // Delete "install" folder. Visit your site"
		break;
	case 3:
		upgrade_plugins($h);
		break;
	default:		
		upgrade_welcome();
		break;
}

exit;



/**
 * Step 1 of upgrade - checks existing version available and confirms details
 */
function upgrade_check($h, $old_version, $show_next)         
{               
        // delete existing cache
        $h->deleteFiles(CACHE . 'db_cache');
        $h->deleteFiles(CACHE . 'css_js_cache');
        $h->deleteFiles(CACHE . 'rss_cache');
        $h->deleteFiles(CACHE . 'lang_cache');
        $h->deleteFiles(CACHE . 'html_cache');

        template($h, 'upgrade/upgrade_step_1.php', array(
            'old_version' => $old_version,
            'show_next' => $show_next
            ));	
}

    
/**
 * Step 2 of upgrade - shows completion.
 */
function upgrade_complete($h)
{
        template($h, 'upgrade/upgrade_step_2.php');          
}



/**
 * Do Upgrade
 */
function do_upgrade($h, $old_version)
{
	// can't upgrade from pre-1.0 versions of Hotaru.
	
	// 1.0 to 1.0.1
	if ($old_version == "1.0") {

		// Change "positive" to 10
		$sql = "UPDATE " . TABLE_POSTVOTES . " SET vote_rating = %d WHERE vote_rating = %s";
		$h->db->query($h->db->prepare($sql, 10, 'positive'));
		
		// Change "negative" to -10
		$sql = "UPDATE " . TABLE_POSTVOTES . " SET vote_rating = %d WHERE vote_rating = %s";
		$h->db->query($h->db->prepare($sql, -10, 'negative'));
		
		// Change "alert" to -999
		$sql = "UPDATE " . TABLE_POSTVOTES . " SET vote_rating = %d WHERE vote_rating = %s";
		$h->db->query($h->db->prepare($sql, -999, 'alert'));
		
		// Alter the PostVotes table so the vote rating is an INT
		$sql = "ALTER TABLE " . TABLE_POSTVOTES . " CHANGE vote_rating vote_rating smallint(11) NOT NULL DEFAULT %d";
		$h->db->query($h->db->prepare($sql, 0));
		
		// check there are default permissions present and add if necessary
		$sql = "SELECT miscdata_id FROM " . TABLE_MISCDATA . " WHERE miscdata_key = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'permissions'));
		if (!$result) {
			// Default permissions
			$perms['options']['can_access_admin'] = array('yes', 'no');
			$perms['can_access_admin']['admin'] = 'yes';
			$perms['can_access_admin']['supermod'] = 'yes';
			$perms['can_access_admin']['default'] = 'no';
			$perms = serialize($perms);
			
			$sql = "INSERT INTO " . TABLE_MISCDATA . " (miscdata_key, miscdata_value, miscdata_default) VALUES (%s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'permissions', $perms, $perms));
		}
		
		// check there are default user_settings present and add if necessary
		$sql = "SELECT miscdata_id FROM " . TABLE_MISCDATA . " WHERE miscdata_key = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'user_settings'));
		if (!$result) {
			// default settings
			$sql = "INSERT INTO " . TABLE_MISCDATA . " (miscdata_key, miscdata_value, miscdata_default) VALUES (%s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'user_settings', '', ''));
		}
		
		// update "old version" for next set of upgrades
		$old_version = "1.0.1";
	}


	// 1.0.1 to 1.0.2
	if ($old_version == "1.0.1") {
		
		// Add new user_lastactivity field
		$exists = $h->db->column_exists('users', 'user_lastvisit');
		if (!$exists) {
			// Alter the Users table to include user_lastvisit
			$sql = "ALTER TABLE " . TABLE_USERS . " ADD user_lastvisit TIMESTAMP NULL AFTER user_lastlogin";
			$h->db->query($h->db->prepare($sql));
		}
		
		// Add site announcement record
		$sql = "SELECT miscdata_id FROM " . TABLE_MISCDATA . " WHERE miscdata_key = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'site_announcement'));
		if (!$result) {
			// site announcement
			$sql = "INSERT INTO " . DB_PREFIX . $table_name . " (miscdata_key, miscdata_value, miscdata_default) VALUES (%s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'site_announcement', '', ''));
		}
		
		// update "old version" for next set of upgrades
		$old_version = "1.0.2";
	}


	// 1.0.2 to 1.0.3
	if ($old_version == "1.0.2") {
		// nothing to do...
		
		// update "old version" for next set of upgrades
		$old_version = "1.0.3";
	}


	// 1.0.3 to 1.0.4
	if ($old_version == "1.0.3") {
		
		// remove language pack option from settings
		$sql = "DELETE FROM " . TABLE_SETTINGS . " WHERE settings_name = %s";
		$h->db->query($h->db->prepare($sql, 'LANGUAGE_PACK'));
		
		// Drop temporary cvotes_temp table if it already exists
		$sql = 'DROP TABLE IF EXISTS `' . DB_PREFIX . 'cvotes_temp`;';
		$h->db->query($sql);
		
		// create a temp table to store old comment votes
		$sql = "CREATE TABLE `" . DB_PREFIX . "cvotes_temp` (
			`cvote_archived` enum('Y','N') NOT NULL DEFAULT 'N',
			`cvote_updatedts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
			`cvote_post_id` int(11) NOT NULL DEFAULT '0',
			`cvote_comment_id` int(11) NOT NULL DEFAULT '0',
			`cvote_user_id` int(11) NOT NULL DEFAULT '0',
			`cvote_user_ip` varchar(32) NOT NULL DEFAULT '0',
			`cvote_date` timestamp NOT NULL,
			`cvote_rating` smallint(11) NOT NULL DEFAULT '0',
			`cvote_reason` tinyint(3) NOT NULL DEFAULT 0,
			`cvote_updateby` int(20) NOT NULL DEFAULT 0
		) ENGINE=" . DB_ENGINE . " DEFAULT CHARSET=" . DB_CHARSET . " COLLATE=" . DB_COLLATE . " COMMENT='Comment Votes';";
		$h->db->query($sql);

		// get old cvote data
		$sql = "SELECT * FROM " . TABLE_COMMENTVOTES;
		$old_cvotes = $h->db->get_results($h->db->prepare($sql));
		if ($old_cvotes) {
			$columns    = "cvote_post_id, cvote_comment_id, cvote_user_id, cvote_user_ip, cvote_date, cvote_rating, cvote_reason, cvote_updateby";
			foreach ($old_cvotes as $cvote) {
				if ($cvote->cvote_rating == 'negative') { $rating = -10; } else { $rating = 10; }
				$sql = "INSERT INTO " . DB_PREFIX . "cvotes_temp (" . $columns . ") VALUES(%d, %d, %d, %s, %s, %d, %d, %d)";
				$h->db->query($h->db->prepare($sql, $cvote->cvote_post_id, $cvote->cvote_comment_id, $cvote->cvote_user_id, $cvote->cvote_user_ip, $cvote->cvote_date, $rating, $cvote->cvote_reason, $cvote->cvote_updateby));
			}
		}
		
		// drop old commentvotes table
		$h->db->query("DROP TABLE " . TABLE_COMMENTVOTES);
		
		// rename new table
		$h->db->query("RENAME TABLE " . DB_PREFIX . "cvotes_temp TO " . DB_PREFIX . "commentvotes");
		
		// add new comment_votes_down column to comments table
		$exists = $h->db->column_exists('comments', 'comment_votes_down');
		if (!$exists) {
			// Alter the Users table to include user_lastvisit
			$sql = "ALTER TABLE " . TABLE_COMMENTS . " ADD comment_votes_down smallint(11) NOT NULL DEFAULT '0' AFTER comment_votes";
			$h->db->query($h->db->prepare($sql));
		}
		
		// rename comment_votes column to comments_votes_up
		$exists = $h->db->column_exists('comments', 'comment_votes_up');
		if (!$exists) {
			// Alter the Users table to include user_lastvisit
			$sql = "ALTER TABLE " . TABLE_COMMENTS . " CHANGE comment_votes comment_votes_up smallint(11) NOT NULL DEFAULT '0'";
			$h->db->query($h->db->prepare($sql));
		}
		
		// move any negative comment vote counts to the down column
		$sql = "SELECT comment_id, comment_votes_up FROM " . TABLE_COMMENTS . " WHERE comment_votes_up < %d";
		$negatives = $h->db->get_results($h->db->prepare($sql, 0));
		if ($negatives) {
			foreach ($negatives as $neg) {
				$sql = "UPDATE " . TABLE_COMMENTS . " SET comment_votes_up = %d, comment_votes_down = %d WHERE comment_id = %d";
				$h->db->query($h->db->prepare($sql, 0, abs($neg->comment_votes_up), $neg->comment_id));
			}
		}

		// update "old version" for next set of upgrades
		$old_version = "1.0.4";
	}

	// 1.0.4 to 1.0.5
	if ($old_version == "1.0.4") {

		// remove true/false "Notes" from admin settings
		$sql = "UPDATE " . TABLE_SETTINGS . " SET settings_note = %s WHERE settings_note = %s";
		$h->db->query($h->db->prepare($sql, '', 'true/false'));
		
		// update "old version" for next set of upgrades
		$old_version = "1.0.5";
	}

	// 1.0.5 to 1.1
	if ($old_version == "1.0.5") { $old_version = "1.1"; } // update "old version" for next set of upgrades
	
	// 1.1 to 1.1.1
	if ($old_version == "1.1") { $old_version = "1.1.1"; } // update "old version" for next set of upgrades
	
	// 1.1 to 1.1.2
	if ($old_version == "1.1.1") { 

		// SMTP on
		$sql = "SELECT settings_name FROM " . TABLE_SETTINGS . " WHERE settings_name = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'SMTP_ON'));
		if(!$result) {
			$sql = "INSERT INTO " . TABLE_SETTINGS . " (settings_name, settings_value, settings_default, settings_note) VALUES (%s, %s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'SMTP_ON', 'false', 'false', 'Email auth'));
		}

		// SMTP host
		$sql = "SELECT settings_name FROM " . TABLE_SETTINGS . " WHERE settings_name = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'SMTP_HOST'));
		if(!$result) {
			$sql = "INSERT INTO " . TABLE_SETTINGS . " (settings_name, settings_value, settings_default, settings_note) VALUES (%s, %s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'SMTP_HOST', 'mail.example.com', 'mail.example.com', ''));
		}

		// SMTP port
		$sql = "SELECT settings_name FROM " . TABLE_SETTINGS . " WHERE settings_name = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'SMTP_PORT'));
		if(!$result) {
			$sql = "INSERT INTO " . TABLE_SETTINGS . " (settings_name, settings_value, settings_default, settings_note) VALUES (%s, %s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'SMTP_PORT', '25', '25', ''));
		}

		// SMTP username
		$sql = "SELECT settings_name FROM " . TABLE_SETTINGS . " WHERE settings_name = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'SMTP_USERNAME'));
		if(!$result) {
			$sql = "INSERT INTO " . TABLE_SETTINGS . " (settings_name, settings_value, settings_default, settings_note) VALUES (%s, %s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'SMTP_USERNAME', '', '', ''));
		}

		// SMTP password
		$sql = "SELECT settings_name FROM " . TABLE_SETTINGS . " WHERE settings_name = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'SMTP_PASSWORD'));
		if(!$result) {
			$sql = "INSERT INTO " . TABLE_SETTINGS . " (settings_name, settings_value, settings_default, settings_note) VALUES (%s, %s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'SMTP_PASSWORD', '', '', ''));
		}

		// update "old version" for next set of upgrades
		$old_version = "1.1.2"; 
	}

	 // 1.1.2 to 1.1.3
	if ($old_version == "1.1.2") {

		// Remove ON from constant names
		$sql = "UPDATE " . TABLE_SETTINGS . " SET settings_name = %s WHERE settings_name = %s";
		$h->db->query($h->db->prepare($sql, 'DB_CACHE', 'DB_CACHE_ON'));
		
		$sql = "UPDATE " . TABLE_SETTINGS . " SET settings_name = %s WHERE settings_name = %s";
		$h->db->query($h->db->prepare($sql, 'RSS_CACHE', 'RSS_CACHE_ON'));
		
		$sql = "UPDATE " . TABLE_SETTINGS . " SET settings_name = %s WHERE settings_name = %s";
		$h->db->query($h->db->prepare($sql, 'CSS_JS_CACHE', 'CSS_JS_CACHE_ON'));
		
		$sql = "UPDATE " . TABLE_SETTINGS . " SET settings_name = %s WHERE settings_name = %s";
		$h->db->query($h->db->prepare($sql, 'HTML_CACHE', 'HTML_CACHE_ON'));
		
		$sql = "UPDATE " . TABLE_SETTINGS . " SET settings_name = %s WHERE settings_name = %s";
		$h->db->query($h->db->prepare($sql, 'SMTP', 'SMTP_ON'));
		
		// update "old version" for next set of upgrades
		$old_version = "1.1.3"; 
	}
	
	 // 1.1.3 to 1.1.4
	if ($old_version == "1.1.3") {
	
	// copy post_date column to post_pub_date
		$exists = $h->db->column_exists('posts', 'post_pub_date');
		if (!$exists) {
			// Create a post_pub_date column
			$sql = "ALTER TABLE " . TABLE_POSTS . " ADD post_pub_date timestamp NOT NULL AFTER post_date";
			$h->db->query($h->db->prepare($sql));
			
			// Copy post_date to post_pub_date
			$sql = "UPDATE " . TABLE_POSTS . " SET post_pub_date = post_date";
			$h->db->query($h->db->prepare($sql));
		}
		
		// update "old version" for next set of upgrades
		$old_version = "1.1.4"; 
	}

	 // 1.1.4 to 1.2
	if ($old_version == "1.1.4") {

		// check whether table exists first
		$exists = $h->db->table_exists('friends');
		if (!$exists) {
			// create a Friends table
			$sql = "CREATE TABLE `" . DB_PREFIX . "friends` (
				`follower_user_id` int(20) NOT NULL default '0',
				`following_user_id` int(20) NOT NULL default '0',
				`friends_updatedts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
				PRIMARY KEY (follower_user_id, following_user_id)
			) ENGINE=" . DB_ENGINE . " DEFAULT CHARSET=" . DB_CHARSET . " COLLATE=" . DB_COLLATE . " COMMENT='Friends';";
			$h->db->query($sql);
		}
		
		// check whether table exists first
		$exists = $h->db->table_exists('messaging');
		if (!$exists) {
			// create a Messaging table
			$sql = "CREATE TABLE `" . DB_PREFIX . "messaging` (
				`message_id` int(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`message_updatedts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				`message_archived` enum('Y','N') NOT NULL DEFAULT 'N',
				`message_from` int(20) NOT NULL DEFAULT 0,
				`message_to` int(20) NOT NULL DEFAULT 0,
				`message_date` timestamp NOT NULL,
				`message_subject` varchar(255) NOT NULL DEFAULT '',
				`message_content` text NULL,
				`message_read` tinyint(1) NOT NULL DEFAULT '0',
				`message_inbox` tinyint(1) NOT NULL DEFAULT '1',
				`message_outbox` tinyint(1) NOT NULL DEFAULT '1',
				`message_updateby` int(20) NOT NULL DEFAULT 0,
				INDEX  (`message_archived`)
			) ENGINE=" . DB_ENGINE . " DEFAULT CHARSET=" . DB_CHARSET . " COLLATE=" . DB_COLLATE . " COMMENT='Messaging';";
			$h->db->query($sql);
		}

		//Add indices to tables
		$tables = array( 'comments' => array('comment_archived', 'comment_status'),
				 'posts' => array('post_archived', 'post_status', 'post_type'),
				 'tags' => array('tags_archived'),
				 'useractivity' => array('useract_userid'),
				 'messaging' => array('message_archived'),
				 'usermeta' => array('usermeta_key')
			);

		foreach ($tables as $table => $indices) {
			if ($exists = $h->db->table_exists($table))
			{
				foreach ($indices as $index) {
					$sql = "SHOW INDEX FROM `" . DB_PREFIX . $table . "` WHERE KEY_NAME = '" . $index . "'";			    
					$result = $h->db->query($sql);			    
					if (!$result) {
						$sql = "ALTER TABLE `" . DB_PREFIX . $table . "` ADD INDEX (" . $index . ")";				
						$h->db->query($sql);
					}
				}
			}
		}		

		// update "old version" for next set of upgrades
		$old_version = "1.2.0";
	}

	 // 1.2.0 to 1.3.0
	if ($old_version == "1.2.0") {		
		
		$exists = $h->db->column_exists('plugins', 'plugin_latestversion');
		if (!$exists) {
			// Create a plugin_version column
			$sql = "ALTER TABLE " . TABLE_PLUGINS . " ADD plugin_latestversion varchar(8) NOT NULL DEFAULT '0.0'";
			$h->db->query($h->db->prepare($sql));
		}
		
		$exists = $h->db->column_exists('settings', 'settings_show');
		if (!$exists) {
			// Create a plugin_version column
			$sql = "ALTER TABLE " . TABLE_SETTINGS . " ADD settings_show enum(%s, %s) NOT NULL DEFAULT %s AFTER settings_note";
			$h->db->query($h->db->prepare($sql, 'Y', 'N', 'Y'));
		}
		
		// Hide Database duration. It's still technically used, but smartCache overrides the duration
		$sql = "UPDATE " . TABLE_SETTINGS . " SET settings_show = %s WHERE settings_name = %s";
		$h->db->query($h->db->prepare($sql, 'N', 'DB_CACHE_DURATION'));
		
		// Hide RSS duration. It's still technically used, but only by Admin "News" so not worth showing
		$sql = "UPDATE " . TABLE_SETTINGS . " SET settings_show = %s WHERE settings_name = %s";
		$h->db->query($h->db->prepare($sql, 'N', 'RSS_CACHE_DURATION'));
		
		// insert new lang_cache option in Admin Settings
		$sql = "SELECT settings_id FROM " . TABLE_SETTINGS . " WHERE settings_name = %s";
		$result = $h->db->get_var($h->db->prepare($sql, 'LANG_CACHE'));
		if (!$result) {
			$sql = "INSERT INTO " . TABLE_SETTINGS . " (settings_name, settings_value, settings_default, settings_show) VALUES (%s, %s, %s, %s)";
			$h->db->query($h->db->prepare($sql, 'LANG_CACHE', 'true', 'true', 'Y'));
		}

		// Add index to comment_post_id to speed up countComments function
		if ($exists = $h->db->table_exists('comments')) {
			$sql = "SHOW INDEX FROM `" . DB_PREFIX . "comments` WHERE KEY_NAME = %s";
			$result = $h->db->query($h->db->prepare($sql, 'comment_post_id'));
			if (!$result) {
				$sql = "ALTER TABLE `" . DB_PREFIX . "comments` ADD INDEX (comment_post_id)";
				$h->db->query($sql);
			}
		}

		// reorder the admin settings
		$desired_order = array('SITE_OPEN', 'SITE_NAME', 'THEME', 'ADMIN_THEME', 'DEBUG', 'FRIENDLY_URLS', 'DB_CACHE', 'DB_CACHE_DURATION', 'CSS_JS_CACHE', 'HTML_CACHE', 'LANG_CACHE', 'RSS_CACHE', 'RSS_CACHE_DURATION', 'SITE_EMAIL', 'SMTP', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD'); 
		$sql = "SELECT * FROM " . TABLE_SETTINGS;
		$results = $h->db->get_results($sql);
		$h->db->query("TRUNCATE TABLE " . TABLE_SETTINGS);

		if ($results) {
			$i = 0;
			while (!empty($desired_order)) {
				foreach ($results as $row) {
					if (count($desired_order) == 0) { break; }
					if ($row->settings_name == $desired_order[0]) {
						$sql = "INSERT INTO " . TABLE_SETTINGS . " (settings_name, settings_value, settings_default, settings_note, settings_show) VALUES(%s, %s, %s, %s, %s)";
						$h->db->query($h->db->prepare($sql, $row->settings_name, $row->settings_value, $row->settings_default, $row->settings_note, $row->settings_show));
						array_shift($desired_order);
					}
					$i++;
					if ($i > 10000) { break; } // got stuck in a loop
				}
			}
		}

		// update "old version" for next set of upgrades
		$old_version = "1.3.0";
	}

	 // 1.3.0 to 1.4.0
	if ($old_version == "1.3.0") {

	    // Drop token_id column from the tokens table
	    if ($h->db->column_exists('tokens', 'token_id')) {
		    $h->db->query("ALTER TABLE " . DB_PREFIX . "tokens DROP token_id");
	    }

		// update "old version" for next set of upgrades
		$old_version = "1.4.0";
	}

	 // 1.4.0 to 1.4.1
	if ($old_version == "1.4.0") {
		// update "old version" for next set of upgrades
		$old_version = "1.4.1";
	}

	 // 1.4.1 to 1.4.2
	if ($old_version == "1.4.1") {

		// Change post_title column from `post_title` varchar(255) NULL, to `post_title` text NULL,
		$exists = $h->db->column_exists('posts', 'post_title');
		if ($exists) {
			$sql = "ALTER TABLE " . TABLE_POSTS . " MODIFY post_title text NULL";
			$h->db->query($h->db->prepare($sql));
		}

		// remove multi-site option from settings 
		$sql = "DELETE FROM " . TABLE_SETTINGS . " WHERE settings_name = %s"; 
		$h->db->query($h->db->prepare($sql, 'MULTI_SITE'));

		//tables to remove site_id from:
		$tables = array(
			'blocked'=>'blocked','categories'=>'category',
			'comments'=>'comment', 'plugins'=>'plugin',
			'miscdata'=>'miscdata','pluginsettings'=>'pluginsetting',
			'posts'=>'post','settings'=>'settings',
			'tags'=>'tag', 'users'=>'user', 'widgets'=>'widget',
		);

		// Remove site_id columns
		foreach ($tables as $table => $column)
		{
			if ($exists = $h->db->column_exists($table, $column . '_siteid')) {
				// Remove column
				$sql = "ALTER TABLE " . DB_PREFIX . $table . " DROP " . $column . "_siteid";
				$h->db->query($sql);
			}

			// Remove site_id indices
			$sql = "SHOW INDEX FROM `" . DB_PREFIX . $table . "` WHERE KEY_NAME = '" . $column . "_siteid'";
			$result = $h->db->query($sql);
			if ($result) {
				$sql = "DROP INDEX " . $column . "_siteid ON " . DB_PREFIX . $table;
				$h->db->query($sql);
			}
		}

		// Drop unique site_id keys
		$sql = "ALTER TABLE `" . TABLE_CATEGORIES . "` DROP INDEX `key`";
		$h->db->query($sql);
		$sql = "ALTER TABLE `" . TABLE_CATEGORIES . "` ADD UNIQUE KEY `key` (`category_name`)";
		$h->db->query($sql);
		
		$sql = "ALTER TABLE `" . TABLE_PLUGINS . "` DROP INDEX `key`";
		$h->db->query($sql);
		$sql = "ALTER TABLE `" . TABLE_PLUGINS . "` ADD UNIQUE KEY `key` (`plugin_folder`)";
		$h->db->query($sql);
		
		$sql = "ALTER TABLE `" . TABLE_SETTINGS . "` DROP INDEX `key`";
		$h->db->query($sql);
		$sql = "ALTER TABLE `" . TABLE_SETTINGS . "` ADD UNIQUE KEY `key` (`settings_name`)";
		$h->db->query($sql);
		
		$sql = "ALTER TABLE `" . TABLE_TAGS . "` DROP INDEX `key`";
		$h->db->query($sql);
		$sql = "ALTER TABLE `" . TABLE_TAGS . "` ADD UNIQUE KEY `key` (`tags_post_id`, `tags_word`)";
		$h->db->query($sql);
		
		$sql = "ALTER TABLE `" . TABLE_USERS . "` DROP INDEX `key`";
		$h->db->query($sql);
		$sql = "ALTER TABLE `" . TABLE_USERS . "` ADD UNIQUE KEY `key` (`user_username`)";
		$h->db->query($sql);

		// Drop Site table
		$sql = 'DROP TABLE IF EXISTS `' . DB_PREFIX . 'site`;';
		$h->db->query($sql);

		// Drop Relates table
		$sql = 'DROP TABLE IF EXISTS `' . DB_PREFIX . 'relates`;';
		$h->db->query($sql);

		// remove the "1" cache folder if permissions allow, otherwise error messages supressed.
		if (is_dir(CACHE . '1'))
		{
			if (is_dir(CACHE . '1/db_cache')) {
				$h->deleteFiles(CACHE . '1/db_cache');
				@rmdir(CACHE . '1/db_cache');
			}

			if (is_dir(CACHE . '1/css_js_cache')) {
				$h->deleteFiles(CACHE . '1/css_js_cache');
				@rmdir(CACHE . '1/css_js_cache');
			}

			if (is_dir(CACHE . '1/rss_cache')) {
				$h->deleteFiles(CACHE . '1/rss_cache');
				@rmdir(CACHE . '1/rss_cache');
			}

			if (is_dir(CACHE . '1/lang_cache')) {
				$h->deleteFiles(CACHE . '1/lang_cache');
				@rmdir(CACHE . '1/lang_cache');
			}

			if (is_dir(CACHE . '1/html_cache')) {
				$h->deleteFiles(CACHE . '1/html_cache');
				@rmdir(CACHE . '1/html_cache');
			}

			if (is_dir(CACHE . '1/debug_logs')) {
				$h->deleteFiles(CACHE . '1/debug_logs');
				@rmdir(CACHE . '1/debug_logs');
			}

			if (file_exists(CACHE . '1/smartloader_cache.php')) {
				@unlink(CACHE . '1/smartloader_cache.php');
			}

			@rmdir(CACHE . '1');
		}

		// update "old version" for next set of upgrades
		$old_version = "1.4.2";

	}
        
        // 1.4.2 to 1.5.0
	if ($old_version == "1.4.2") {                            

                // update "old version" for next set of upgrades
		$old_version = "1.5.0";
        }
        
        // 1.5.0, 1.5.1, 1.5.2
	if (version_compare("1.4.2", $old_version) < 1) {                                                    
                         
                // Need to cover all of the RCx verson as well
                // Add a few new settings
		$exists = $h->db->column_exists('settings', 'settings_id');
		if ($exists) {
                    $newSettings = array('FTP_SITE', 'FTP_USERNAME', 'FTP_PASSWORD');
                    foreach($newSettings as $setting) {                        
                        $sql = "SELECT settings_name FROM " . TABLE_SETTINGS . " WHERE settings_name = %s";
                        $result = $h->db->get_var($h->db->prepare($sql, $setting));
                        
                        if(!$result) {
                            $sql = "INSERT INTO " . TABLE_SETTINGS . " (settings_name, settings_value, settings_default, settings_note, settings_show) VALUES(%s, %s, %s, %s, %s)";                        
                            $h->db->query($h->db->prepare($sql, $setting, ' ', ' ', ' ', 1));
                        }
                        
                    }						
		}
                
                // drop joint primary key to postvotes table if exists
                // should not be there
                $sql = "SHOW INDEX FROM " . TABLE_POSTVOTES . " WHERE KEY_NAME = %s";
		$result = $h->db->query($h->db->prepare($sql, 'PRIMARY'));
                if ($result) {                   
			$sql = "ALTER TABLE " . TABLE_POSTVOTES . " DROP PRIMARY KEY";
			$h->db->query($h->db->prepare($sql));
		}                
                
                // update "old version" for next set of upgrades
		$old_version = "1.5.2";
        }
        
        if ($old_version == "1.5.2") {
            
            
                // update "old version" for next set of upgrades
		//$old_version = "1.5.2";
        }

        
        /*
         * 
         * Update Hotaru version number to the database (referred to when upgrading)
         * This is always the final step of the upgrade
         * 
         */ 
	$sql = "UPDATE " . TABLE_MISCDATA . " SET miscdata_key = %s, miscdata_value = %s, miscdata_default = %s WHERE miscdata_key = %s";
	$h->db->query($h->db->prepare($sql, 'hotaru_version', $h->version, $h->version, 'hotaru_version'));
}

?>