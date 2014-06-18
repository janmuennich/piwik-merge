<?php
/**
 * piwik_merge.php
 *
 * Version: 1.8.2 / 20.06.2012
 * Author:  Jan-Kaspar Münnich <jan@dotplex.de>
 * Version: 1.9.2 / 21.12.2012
 * Author:  Alan Ivey <alan@echoditto.com>
 * Version: 1.11.1 / 14.03.2013
 * Author:  Christian Hanne <hanne@laborb.de>
 * Version: 2.0.0 / 18.05.2014
 * Author:  Christian Hanne <hanne@laborb.de>
 * Version: 2.3.0 / 18.06.2014
 * Author:  Manuel Frei <manu@defect.ch>
 *
 * Description:
 * Script to import sites, visits and conversions from one Piwik installation into another. Users and user permissions will not be touched.
 * This version works with Piwik versions 2.3.0, probably there will be database structure changes in later versions.
 * You should run this script in the shell with `php piwik_merge.php` since it could run very long.
 * If the imported data doesn't show in Piwik, make sure that the created date of the site is not after the first (imported) visit.
 *
 * Important notice:
 * I've written this script because I needed to merge two Piwik installations.
 * It worked for me and this task, but maybe it won't work for you.
 * The script is just quick work and may contain bugs or security issues.
 * I just wanted to share this script in case it could help somebody.
 *
 * Changes in version 1.11.1:
 * - added some further comments
 * - fixed the creation date issue by automatically updating the site's creation date
 * - exchanged some die-calls with echoes, to make sure we can import tables with buggy entries
 * - did some syntax changes to improve readability
 * - added missing database fields
 *
 * Changes in version 2.3.0:
 * - add function to escape null values
 * - add missing fields of new piwik version
 *
 *
 * VERY IMPORTANT notice: You should really backup your database before running the script!
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
 */

/**
 * Enter the database credentials in $db_old and $db_new.
 */

$db_old = array(
  'server' => 'localhost',
  'user' => '',
  'pass' => '',
  'db' => '',
  'prefix' => 'piwik_',
);

$db_new = array(
  'server' => 'localhost',
  'user' => '',
  'pass' => '',
  'db' => '',
  'prefix' => 'piwik_',
);

/**
 * $import_sites defines the sites that should be imported from the old installation.
 *
 * Specify the id from the old installation as the array key. You find the ids in Piwik at "Settings" -> "Websites".
 *
 * If you set the value to 0, the whole site including urls, goals and reports will be copied to the new database. If you set the value to the id of a site existing in the new installation, data will be imported there. Obviously, old and new site should be identical regarding urls and goals.
 *
 * Example:
 * $import_sites = array (
 * 1 => 2, // Site with id 1 in the old installation will be imported in site with id 2 in the new installation
 * 2 => 0  // Site with id 2 in the old installation will be imported a new site in the new installation
 * );
 */

$import_sites = array();

/**
 * Database fields, update the following arrays in case future versions of piwik add or remove fields for these tables.
 *
 * The name of the arrays correspond with the database tables:
 * $[TABLE]_fields
 */

$log_action_fields = array(
  'name',
  'hash',
  'type',
  'url_prefix',
);

$site_fields = array(
  'idsite',
  'name',
  'main_url',
  'ts_created',
  'ecommerce',
  'sitesearch',
  'sitesearch_keyword_parameters',
  'sitesearch_category_parameters',
  'timezone',
  'currency',
  'excluded_ips',
  'excluded_parameters',
  'excluded_user_agents',
  '`group`',
  'type',
  'keep_url_fragment',
);

$site_url_fields = array(
  'idsite',
  'url',
);

$goal_fields = array(
  'idsite',
  'idgoal',
  'name',
  'match_attribute',
  'pattern',
  'pattern_type',
  'case_sensitive',
  'allow_multiple',
  'revenue',
  'deleted',
);

$report_fields = array(
  'idreport',
  'idsite',
  'login',
  'description',
  'iidsegment',
  'hour',
  'period',
  'type',
  'format',
  'reports',
  'parameters',
  'ts_created',
  'ts_last_sent',
  'deleted',
);

$log_visit_fields = array(
  'idvisitor',
  'visitor_localtime',
  'visitor_returning',
  'visitor_count_visits',
  'visitor_days_since_last',
  'visitor_days_since_order',
  'visitor_days_since_first',
  'visit_first_action_time',
  'visit_last_action_time',
  'visit_exit_idaction_url',
  'visit_exit_idaction_name',
  'visit_entry_idaction_url',
  'visit_entry_idaction_name',
  'visit_total_actions',
  'visit_total_searches',
  'visit_total_events',
  'visit_total_time',
  'visit_goal_converted',
  'visit_goal_buyer',
  'referer_type',
  'referer_name',
  'referer_url',
  'referer_keyword',
  'config_id',
  'config_os',
  'config_browser_name',
  'config_browser_version',
  'config_resolution',
  'config_pdf',
  'config_flash',
  'config_java',
  'config_director',
  'config_quicktime',
  'config_realplayer',
  'config_windowsmedia',
  'config_gears',
  'config_silverlight',
  'config_cookie',
  'location_ip',
  'location_browser_lang',
  'location_country',
  'location_region',
  'location_city',
  'location_latitude',
  'location_longitude',
  'location_provider',
  'custom_var_k1',
  'custom_var_v1',
  'custom_var_k2',
  'custom_var_v2',
  'custom_var_k3',
  'custom_var_v3',
  'custom_var_k4',
  'custom_var_v4',
  'custom_var_k5',
  'custom_var_v5',
);

$log_link_visit_action_fields = array(
  'idsite',
  'idvisitor',
  'server_time',
  'idvisit',
  'idaction_url',
  'idaction_url_ref',
  'idaction_name',
  'idaction_name_ref',
  'idaction_event_category',
  'idaction_event_action',
  'time_spent_ref_action',
  'custom_float',
  'custom_var_k1',
  'custom_var_v1',
  'custom_var_k2',
  'custom_var_v2',
  'custom_var_k3',
  'custom_var_v3',
  'custom_var_k4',
  'custom_var_v4',
  'custom_var_k5',
  'custom_var_v5',
);

$log_conversion_fields = array(
  'idvisit',
  'idsite',
  'idvisitor',
  'server_time',
  'idaction_url',
  'idlink_va',
  'referer_visit_server_date',
  'referer_type',
  'referer_name',
  'referer_keyword',
  'visitor_returning',
  'visitor_count_visits',
  'visitor_days_since_first',
  'visitor_days_since_order',
  'location_country',
  'location_region',
  'location_city',
  'location_latitude',
  'location_longitude',
  'url',
  'idgoal',
  'buster',
  'idorder',
  'items',
  'revenue',
  'revenue_subtotal',
  'revenue_tax',
  'revenue_shipping',
  'revenue_discount',
  'custom_var_k1',
  'custom_var_v1',
  'custom_var_k2',
  'custom_var_v2',
  'custom_var_k3',
  'custom_var_v3',
  'custom_var_k4',
  'custom_var_v4',
  'custom_var_k5',
  'custom_var_v5',
);

$log_conversion_item_fields = array(
  'idsite',
  'idvisitor',
  'server_time',
  'idvisit',
  'idorder',
  'idaction_sku',
  'idaction_name',
  'idaction_category',
  'idaction_category2',
  'idaction_category3',
  'idaction_category4',
  'idaction_category5',
  'price',
  'quantity',
  'deleted',
);

function escape_array($array) {
  foreach ($array as $name => $value) {
    $array[$name] = mysql_real_escape_string($value);
  }
  return $array;
}

function array_to_sql_values($array) {
  $str = '';
  foreach ($array as $value) {
    // decide if empty string, NULL, or real value (=== to check type an value)
    if($value === '') {
        $str .= "'',";
    } elseif($value === null) {
        $str .= "NULL,";
    } else {
        $str .= "'" . mysql_real_escape_string($value) . "',";
    }
  }
 
  // remove last , 
  return rtrim($str, ",");
}

// connect databases
$db1 = mysql_connect($db_old['server'], $db_old['user'], $db_old['pass']);
if (!$db1) {
  die("Error: " . mysql_error($db1) . "\n");
}
if (!mysql_select_db($db_old['db'], $db1)) {
  die("Error: " . mysql_error($db1) . "\n");
}

$db2 = mysql_connect($db_new['server'], $db_new['user'], $db_new['pass'], true);
if (!$db2) {
  die("Error: " . mysql_error($db2) . "\n");
}
if (!mysql_select_db($db_new['db'], $db2)) {
  die("Error: " . mysql_error($db2) . "\n");
}

// import actions
echo "Importing actions...\n";
$action_mapping = array();
// read actions from old piwik
$query = "SELECT idaction, " . implode(', ', $log_action_fields) . " FROM " . $db_old['prefix'] . "log_action";
$res = mysql_query($query, $db1);
if ($res) {
  $actions_counter = 0;
  while ($action = mysql_fetch_assoc($res)) {
    $action_id_old = $action['idaction'];
    unset($action['idaction']);

    // if action already exists in new piwik, then learn it's id, otherwise import
    $query = "SELECT idaction FROM " . $db_new['prefix'] . "log_action WHERE name='" . mysql_real_escape_string($action['name']) . "' AND hash='" . $action['hash'] . "' AND type='" . $action['type'] . "'";
    $res2 = mysql_query($query, $db2);
    if ($res2) {
      if (mysql_num_rows($res2)) {
        $action_new = mysql_fetch_assoc($res2);
        $action_id_new = $action_new['idaction'];
      }
      else {
        $query = "INSERT INTO " . $db_new['prefix'] . "log_action (" . implode(', ', $log_action_fields) . ") VALUES (" . array_to_sql_values($action) . ")";

        $res2 = mysql_query($query, $db2);
        if ($res2) {
          $action_id_new = mysql_insert_id($db2);
          $actions_counter++;
        }
        else {
          die("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
        }
      }
    }
    else {
      die("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
    }
    $action_mapping[$action_id_old] = $action_id_new;
  }
  echo "Imported " . $actions_counter . " actions.\n";
}
else {
  die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
}

// walk through sites
foreach ($import_sites as $site_id_old => $site_id_new) {
  // read site
  $query = "SELECT " . implode(', ', $site_fields) . " FROM " . $db_old['prefix'] . "site WHERE idsite=" . $site_id_old;
  $res = mysql_query($query, $db1);
  if ($res) {
    $site = mysql_fetch_assoc($res);
    if ($site) {

      // import site if no new id specified
      if (!$site_id_new) {
        echo "Importing site " . $site_id_old . "...\n";

        $query = "INSERT INTO " . $db_new['prefix'] . "site (" . implode(', ', $site_fields) . ") VALUES (" . array_to_sql_values($site) . ")";

        $res = mysql_query($query, $db2);
        if ($res) {
          $site_id_new = mysql_insert_id($db2);
        }
        else {
          die("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
        }

        // import additional site urls
        $query = "SELECT " . implode(', ', $site_url_fields) . " FROM " . $db_old['prefix'] . "site_url WHERE idsite=" . $site_id_old;
        $res = mysql_query($query, $db1);
        if ($res) {
          if (mysql_num_rows($res)) {
            while ($site_url = mysql_fetch_assoc($res)) {
              $site_url['idsite'] = $site_id_new;

              $query = "INSERT INTO " . $db_new['prefix'] . "site_url (" . implode(', ', $site_url_fields) . ") VALUES (" . array_to_sql_values($site_url) . ")";

              if (!mysql_query($query, $db2)) {
                die("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
              }
            }
          }
        }
        else {
          die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
        }

        // import goals
        $query = "SELECT " . implode(', ', $goal_fields) . " FROM " . $db_old['prefix'] . "goal WHERE idsite=" . $site_id_old;
        $res = mysql_query($query, $db1);
        if ($res) {
          if (mysql_num_rows($res)) {
            while ($goal = mysql_fetch_assoc($res)) {
              $goal['idsite'] = $site_id_new;

              $query = "INSERT INTO " . $db_new['prefix'] . "goal (" . implode(', ', $goal_fields) . ") VALUES (" . array_to_sql_values($goal) . ")";

              if (!mysql_query($query, $db2)) {
                die("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
              }
            }
          }
        }
        else {
          die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
        }

        // import reports for site
        $query = "SELECT " . implode(', ', $report_fields) . " FROM " . $db_old['prefix'] . "report WHERE idsite=" . $site_id_old;
        $res = mysql_query($query, $db1);
        if ($res) {
          if (mysql_num_rows($res)) {
            while ($report = mysql_fetch_assoc($res)) {
              $report['idsite'] = $site_id_new;

              $query = "INSERT INTO " . $db_new['prefix'] . "report (" . implode(', ', $report_fields) . ") VALUES (" . array_to_sql_values($action) . ")";

              if (!mysql_query($query, $db2)) {
                die("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
              }
            }
          }
        }
        else {
          die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
        }

        echo "Imported site " . $site_id_old . " with new id " . $site_id_new . "\n";
      }

      // make sure all visits are shown by setting the site's creation date to the first imported visits date
      $query = "SELECT visit_first_action_time FROM " . $db_old['prefix'] . "log_visit WHERE idsite=" . $site_id_old . " ORDER BY visit_first_action_time ASC";
      $res = mysql_query($query, $db1);
      if ($res) {
        if (mysql_num_rows($res)) {
          $first_visit = mysql_fetch_assoc($res);
          $query = "UPDATE " . $db_new['prefix'] . "site SET `ts_created` = '" . $first_visit['visit_first_action_time'] . "' WHERE idsite=" . $site_id_new;
          if (!mysql_query($query, $db2)) {
            die("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
          }

          echo "Changed creation date of site " . $site_id_old . " to " . $first_visit['visit_first_action_time'] . "\n";
        }
      }
      else {
        die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
      }

      // read visits
      echo "Importing visits for site " . $site_id_old . "...\n";
      $query = "SELECT idvisit, " . implode(', ', $log_visit_fields) . " FROM " . $db_old['prefix'] . "log_visit WHERE idsite=" . $site_id_old . " ORDER BY idvisit";
      $res = mysql_query($query, $db1);
      if ($res) {
        $visit_mapping = array();
        $visit_action_mapping = array();

        // walk through visits
        $visits_counter = 0;
        while ($visit = mysql_fetch_assoc($res)) {
          $visit_id_old = $visit['idvisit'];
          unset($visit['idvisit']);

          // update ids
          $visit['visit_exit_idaction_url'] = isset($action_mapping[$visit['visit_exit_idaction_url']]) ? $action_mapping[$visit['visit_exit_idaction_url']] : 0;
          $visit['visit_exit_idaction_name'] = isset($action_mapping[$visit['visit_exit_idaction_name']]) ? $action_mapping[$visit['visit_exit_idaction_name']] : 0;
          $visit['visit_entry_idaction_url'] = isset($action_mapping[$visit['visit_entry_idaction_url']]) ? $action_mapping[$visit['visit_entry_idaction_url']] : 0;
          $visit['visit_entry_idaction_name'] = isset($action_mapping[$visit['visit_entry_idaction_name']]) ? $action_mapping[$visit['visit_entry_idaction_name']] : 0;

          $query = "INSERT INTO " . $db_new['prefix'] . "log_visit (idsite, " . implode(', ', $log_visit_fields) . ") VALUES (" . $site_id_new . ", " . array_to_sql_values($visit) . ")";

          $res2 = mysql_query($query, $db2);
          if ($res2) {
            $visit_id_new = mysql_insert_id($db2);
            $visit_mapping[$visit_id_old] = $visit_id_new;
            $visits_counter++;

            // import visit_action
            $query = "SELECT idlink_va, " . implode(', ', $log_link_visit_action_fields) . " FROM " . $db_old['prefix'] . "log_link_visit_action WHERE idvisit=" . $visit_id_old;
            $res2 = mysql_query($query, $db1);
            if ($res2) {
              if (mysql_num_rows($res2)) {
                while ($visit_action = mysql_fetch_assoc($res2)) {
                  $visit_action_id_old = $visit_action['idlink_va'];
                  unset($visit_action['idlink_va']);
                  $visit_action['idsite'] = $site_id_new;
                  $visit_action['idvisit'] = $visit_id_new;
                  $visit_action['idaction_url'] = isset($action_mapping[$visit_action['idaction_url']]) ? $action_mapping[$visit_action['idaction_url']] : 0;
                  $visit_action['idaction_url_ref'] = isset($action_mapping[$visit_action['idaction_url_ref']]) ? $action_mapping[$visit_action['idaction_url_ref']] : 0;
                  $visit_action['idaction_name'] = isset($action_mapping[$visit_action['idaction_name']]) ? $action_mapping[$visit_action['idaction_name']] : 0;
                  $visit_action['idaction_name_ref'] = isset($action_mapping[$visit_action['idaction_name_ref']]) ? $action_mapping[$visit_action['idaction_name_ref']] : 0;

                  $query = "INSERT INTO " . $db_new['prefix'] . "log_link_visit_action (" . implode(', ', $log_link_visit_action_fields) . ") VALUES (" . array_to_sql_values($visit_action) . ")";
                  $res3 = mysql_query($query, $db2);
                  if ($res3) {
                    $visit_action_mapping[$visit_action_id_old] = mysql_insert_id($db2);
                  }
                  else {
                    echo("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
                  }
                }
              }
            }
            else {
              die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
            }

            // import conversions
            $query = "SELECT " . implode(', ', $log_conversion_fields) . " FROM " . $db_old['prefix'] . "log_conversion WHERE idvisit=" . $visit_id_old;
            $res2 = mysql_query($query, $db1);
            if ($res2) {
              if (mysql_num_rows($res2)) {
                while ($conversion = mysql_fetch_assoc($res2)) {
                  $conversion['idvisit'] = $visit_id_new;
                  $conversion['idsite'] = $site_id_new;
                  $conversion['idaction_url'] = isset($action_mapping[$conversion['idaction_url']]) ? $action_mapping[$conversion['idaction_url']] : 0;
                  $conversion['idlink_va'] = isset($visit_action_mapping[$conversion['idlink_va']]) ? $visit_action_mapping[$conversion['idlink_va']] : 0;

                  $query = "INSERT INTO " . $db_new['prefix'] . "log_conversion (" . implode(', ', $log_conversion_fields) . ") VALUES (" . array_to_sql_values($conversion) . ")";

                  $query = str_replace("''", "NULL", $query);
                  if (!mysql_query($query, $db2)) {
                    echo("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
                  }
                }
              }
            }
            else {
              die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
            }

            // import conversion items
            $query = "SELECT " . implode(', ', $log_conversion_item_fields) . " FROM " . $db_old['prefix'] . "log_conversion_item WHERE idvisit=" . $visit_id_old;
            $res2 = mysql_query($query, $db1);
            if ($res2) {
              if (mysql_num_rows($res2)) {
                while ($conversion_item = mysql_fetch_assoc($res2)) {
                  $conversion_item['idvisit'] = $visit_id_new;
                  $conversion_item['idsite'] = $site_id_new;
                  $conversion_item['idaction_sku'] = isset($action_mapping[$conversion_item['idaction_sku']]) ? $action_mapping[$conversion_item['idaction_sku']] : 0;
                  $conversion_item['idaction_name'] = isset($action_mapping[$conversion_item['idaction_name']]) ? $action_mapping[$conversion_item['idaction_name']] : 0;
                  $conversion_item['idaction_category'] = isset($action_mapping[$conversion_item['idaction_category']]) ? $action_mapping[$conversion_item['idaction_category']] : 0;
                  $conversion_item['idaction_category2'] = isset($action_mapping[$conversion_item['idaction_category2']]) ? $action_mapping[$conversion_item['idaction_category2']] : 0;
                  $conversion_item['idaction_category3'] = isset($action_mapping[$conversion_item['idaction_category3']]) ? $action_mapping[$conversion_item['idaction_category3']] : 0;
                  $conversion_item['idaction_category4'] = isset($action_mapping[$conversion_item['idaction_category4']]) ? $action_mapping[$conversion_item['idaction_category4']] : 0;
                  $conversion_item['idaction_category5'] = isset($action_mapping[$conversion_item['idaction_category5']]) ? $action_mapping[$conversion_item['idaction_category5']] : 0;

                  $query = "INSERT INTO " . $db_new['prefix'] . "log_conversion_item (" . implode(', ', $log_conversion_item_fields) . ") VALUES (" . array_to_sql_values($conversion_item) . ")";
                  if (!mysql_query($query, $db2)) {
                    echo("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
                  }
                }
              }
            }
            else {
              die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
            }
          }
          else {
            die("Error: " . mysql_error($db2) . "\nQuery: " . $query . "\n");
          }
        }
        if ($visits_counter > 0) {
          echo "Imported " . $visits_counter . " visits from site " . $site_id_old . "\n";
        }
      }
      else {
        die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
      }
    }
    else {
      echo "Site " . $site_id_old . " not found in old database. Skipping...\n";
    }
  }
  else {
    die("Error: " . mysql_error($db1) . "\nQuery: " . $query . "\n");
  }
}
