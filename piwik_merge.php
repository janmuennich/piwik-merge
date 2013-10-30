<?php

/*
 *
 * piwik_merge.php
 *
 * Version: 1.0 / 09.10.2010
 * Author:  Jan-Kaspar Münnich <jan@dotplex.de>
 *
 * Description:
 * Script to import data from one Piwik installation into another.
 * This version works with Piwik version 1.0, probably there will be database structure changes in later versions.
 * You should run this script in the shell with `php piwik_merge.php` since it could run very long.
 * If the imported data doesn't show in Piwik, make sure that the created date of the site is not after the first (imported) visit.
 *
 * Important notice:
 * I've written this script because I needed to merge two Piwik installations.
 * It worked for me and this task, but maybe it won't work for you.
 * The script is just quick work and may contain bugs or security issues.
 * I just wanted to share this script in case it could help somebody.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

// Enter configuration here

/*
Enter the database credentials in $db_old and $db_new.
*/
$db_old = array (
	'server'	=> 'localhost',
	'user'		=> '',
	'pass'		=> '',
	'db'		=> '',
	'prefix'	=> ''
);

$db_new = array (
	'server'	=> 'localhost',
	'user'		=> '',
	'pass'		=> '',
	'db'		=> '',
	'prefix'	=> ''
);

/*
$import_sites defines the sites that should be imported from the old installation. Specify the id from the old installation as the array key. If you set the value to 0, the site will be imported as a new one. If you set the id of a site existing in the new installation, data will be imported there.

Example:
$import_sites = array (
	1 => 2, // Site with id 1 in the old installation will be imported in site with id 2 in the new installation
	2 => 0  // Site with id 2 in the old installation will be imported a new site in the new installation
);
*/
$import_sites = array ();

// database fields
$site_fields = array ('name', 'main_url', 'ts_created', 'timezone', 'currency', 'excluded_ips', 'excluded_parameters', 'feedburnerName');
$log_visit_fields = array ('visitor_localtime', 'visitor_idcookie', 'visitor_returning', 'visit_first_action_time', 'visit_last_action_time', 'visit_server_date', 'visit_exit_idaction_url', 'visit_entry_idaction_url', 'visit_total_actions', 'visit_total_time', 'visit_goal_converted', 'referer_type', 'referer_name', 'referer_url', 'referer_keyword', 'config_md5config', 'config_os', 'config_browser_name', 'config_browser_version', 'config_resolution', 'config_pdf', 'config_flash', 'config_java', 'config_director', 'config_quicktime', 'config_realplayer', 'config_windowsmedia', 'config_gears', 'config_silverlight', 'config_cookie', 'location_ip', 'location_browser_lang', 'location_country', 'location_continent', 'location_provider');
$log_action_fields = array ('name', 'hash', 'type');
$log_link_visit_action_fields = array ('idvisit', 'idaction_url', 'idaction_url_ref', 'idaction_name', 'time_spent_ref_action');
$goal_fields = array ('idsite', 'idgoal', 'name', 'match_attribute', 'pattern', 'pattern_type', 'case_sensitive', 'revenue', 'deleted');
$log_conversion_fields = array ('idvisit', 'idsite', 'visitor_idcookie', 'server_time', 'idaction_url', 'idlink_va', 'referer_idvisit', 'referer_visit_server_date', 'referer_type', 'referer_name', 'referer_keyword', 'visitor_returning', 'location_country', 'location_continent', 'url', 'idgoal', 'revenue');

function escape_array ($array) {
	foreach ($array as $name => $value) {
		$array[$name] = mysql_real_escape_string ($value);
	}
	return $array;
}

// connect databases
$db1 = mysql_connect ($db_old['server'], $db_old['user'], $db_old['pass']);
if (!$db1) {
	die ("Error: " . mysql_error ($db1) . "\n");
}
if (!mysql_select_db ($db_old['db'], $db1)) {
	die ("Error: " . mysql_error ($db1) . "\n");
}

$db2 = mysql_connect ($db_new['server'], $db_new['user'], $db_new['pass'], true);
if (!$db2) {
	die ("Error: " . mysql_error ($db2) . "\n");
}
if (!mysql_select_db ($db_new['db'], $db2)) {
	die ("Error: " . mysql_error ($db2) . "\n");
}

// import actions
echo "Importing actions...\n";
$action_mapping = array ();
$res = mysql_query ("SELECT idaction, " . implode (', ', $log_action_fields) . " FROM " . $db_old['prefix'] . "log_action", $db1);
if ($res) {
	$actions_counter = 0;
	while ($action = mysql_fetch_assoc ($res)) {
		$action_id_old = $action['idaction'];
		unset ($action['idaction']);
		// check if action exists
		$res2 = mysql_query ("SELECT idaction FROM " . $db_new['prefix'] . "log_action WHERE name='" . mysql_real_escape_string ($action['name']) . "' AND hash='" . $action['hash'] . "' AND type='" . $action['type'] . "'", $db2);
		if ($res2) {
			if (mysql_num_rows ($res2)) {
				$action_new = mysql_fetch_assoc ($res2);
				$action_id_new = $action_new['idaction'];
			}
			else {
				$res2 = mysql_query ("INSERT INTO " . $db_new['prefix'] . "log_action (" . implode (', ', $log_action_fields) . ") VALUES ('" . implode ("', '", escape_array ($action)) . "')", $db2);
				if ($res2) {
					$action_id_new = mysql_insert_id ($db2);
					$actions_counter ++;
				}
				else {
					die ("Error: " . mysql_error ($db2) . "\n");
				}
			}
		}
		else {
			die ("Error: " . mysql_error ($db2) . "\n");
		}
		$action_mapping[$action_id_old] = $action_id_new;
	}
	if ($actions_counter > 0) {
		echo "Imported " . $actions_counter . " actions.\n";
	}
}
else {
	die ("Error: " . mysql_error ($db1) . "\n");
}

// walk through sites
foreach ($import_sites as $site_id_old => $site_id_new) {
	// read site
	$res = mysql_query ("SELECT " . implode (', ', $site_fields) . " FROM " . $db_old['prefix'] . "site WHERE idsite=" . $site_id_old, $db1);
	if ($res) {
		$site = mysql_fetch_assoc ($res);
		if ($site) {
			// import site if new id not specified
			if (!$site_id_new) {
				$res = mysql_query ("INSERT INTO " . $db_new['prefix'] . "site (" . implode (', ', $site_fields) . ") VALUES ('" . implode ("', '", escape_array ($site)) . "')", $db2);
				if ($res) {
					$site_id_new = mysql_insert_id ($db2);
					echo "Imported site " . $site_id_old . " with new id " . $site_id_new . "\n";
				}
				else {
					die ("Error: " . mysql_error ($db2) . "\n");
				}
				// import goals
				$res = mysql_query ("SELECT " . implode (', ', $goal_fields) . " FROM " . $db_old['prefix'] . "goal WHERE idsite=" . $site_id_old, $db1);
				if ($res) {
					if (mysql_num_rows ($res)) {
						while ($goal = mysql_fetch_assoc ($res)) {
							$goal['idsite'] = $site_id_new;
							if (!mysql_query ("INSERT INTO " . $db_new['prefix'] . "goal (" . implode (', ', $goal_fields) . ") VALUES ('" . implode ("', '", escape_array ($goal)) . "')", $db2)) {
								die ("Error: " . mysql_error ($db2) . "\n");
							}
						}
					}
				}
				else {
					die ("Error: " . mysql_error ($db1) . "\n");
				}
			}
			// read visits
			echo "Importing visits for site " . $site_id_old . "...\n";
			$res = mysql_query ("SELECT idvisit, " . implode (', ', $log_visit_fields) . " FROM " . $db_old['prefix'] . "log_visit WHERE idsite=" . $site_id_old . " ORDER BY idvisit", $db1);
			if ($res) {
				$visit_mapping = array ();
				$visit_action_mapping = array ();
				// walk throug visits
				$visits_counter = 0;
				while ($visit = mysql_fetch_assoc ($res)) {
					$visit_id_old = $visit['idvisit'];
					unset ($visit['idvisit']);
					// update ids
					$visit['visit_exit_idaction_url'] = isset ($action_mapping[$visit['visit_exit_idaction_url']]) ? $action_mapping[$visit['visit_exit_idaction_url']] : 0;
					$visit['visit_entry_idaction_url'] = isset ($action_mapping[$visit['visit_entry_idaction_url']]) ? $action_mapping[$visit['visit_entry_idaction_url']] : 0;
					// insert visit
					$res2 = mysql_query ("INSERT INTO " . $db_new['prefix'] . "log_visit (idsite, " . implode (', ', $log_visit_fields) . ") VALUES (" . $site_id_new . ", '" . implode ("', '", escape_array ($visit)) . "')", $db2);
					if ($res2) {
						$visit_id_new = mysql_insert_id ($db2);
						$visit_mapping[$visit_id_old] = $visit_id_new;
						$visits_counter ++;
						// import visit_action
						$res2 = mysql_query ("SELECT idlink_va, " . implode (', ', $log_link_visit_action_fields) . " FROM " . $db_old['prefix'] . "log_link_visit_action WHERE idvisit=" . $visit_id_old, $db1);
						if ($res2) {
							if (mysql_num_rows ($res2)) {
								while ($visit_action = mysql_fetch_assoc ($res2)) {
									$visit_action_id_old = $visit_action['idlink_va'];
									unset ($visit_action['idlink_va']);
									$visit_action['idvisit'] = $visit_id_new;
									$visit_action['idaction_url'] = isset ($action_mapping[$visit_action['idaction_url']]) ? $action_mapping[$visit_action['idaction_url']] : 0;
									$visit_action['idaction_url_ref'] = isset ($action_mapping[$visit_action['idaction_url_ref']]) ? $action_mapping[$visit_action['idaction_url_ref']] : 0;
									$visit_action['idaction_name'] = isset ($action_mapping[$visit_action['idaction_name']]) ? $action_mapping[$visit_action['idaction_name']] : 0;
									$res3 = mysql_query ("INSERT INTO " . $db_new['prefix'] . "log_link_visit_action (" . implode (', ', $log_link_visit_action_fields) . ") VALUES (" . implode (', ', $visit_action) . ")", $db2);
									if ($res3) {
										$visit_action_mapping[$visit_action_id_old] = mysql_insert_id ($db2);
									}
									else {
										die ("Error: " . mysql_error ($db2) . "\n");
									}
								}
							}
						}
						else {
							die ("Error: " . mysql_error ($db1) . "\n");
						}
						// import conversions
						$res2 = mysql_query ("SELECT " . implode (', ', $log_conversion_fields) . " FROM " . $db_old['prefix'] . "log_conversion WHERE idvisit=" . $visit_id_old, $db1);
						if ($res2) {
							if (mysql_num_rows ($res2)) {
								while ($conversion = mysql_fetch_assoc ($res2)) {
									$conversion['idvisit'] = $visit_id_new;
									$conversion['idsite'] = $site_id_new;
									$conversion['idaction_url'] = isset ($action_mapping[$conversion['idaction_url']]) ? $action_mapping[$conversion['idaction_url']] : 0;
									$conversion['idlink_va'] = isset ($visit_action_mapping[$conversion['idlink_va']]) ? $visit_action_mapping[$conversion['idlink_va']] : 0;
									$conversion['referer_idvisit'] = isset ($visit_mapping[$conversion['referer_idvisit']]) ? $visit_mapping[$conversion['referer_idvisit']] : 0;
									if (!mysql_query ("INSERT INTO " . $db_new['prefix'] . "log_conversion (" . implode (', ', $log_conversion_fields) . ") VALUES ('" . implode ("', '", escape_array ($conversion)) . "')", $db2)) {
										die ("Error: " . mysql_error ($db2) . "\n");
									}
								}
							}
						}
						else {
							die ("Error: " . mysql_error ($db1) . "\n");
						}
					}
					else {
						die ("Error: " . mysql_error ($db2) . "\n");
					}
				}
				if ($visits_counter > 0) {
					echo "Imported " . $visits_counter . " visits from site " . $site_id_old . "\n";
				}
			}
			else {
				die ("Error: " . mysql_error ($db1) . "\n");
			}
		}
		else {
			echo "Site " . $site_id_old . " not found in old database. Skipping...\n";
		}
	}
	else {
		die ("Error: " . mysql_error ($db1) . "\n");
	}
}

?>
