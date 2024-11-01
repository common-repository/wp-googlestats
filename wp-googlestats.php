<?php
/*
Plugin Name: WP-Googlestats
Version: 0.1c
Plugin URI: http://www.fabriziotarizzo.org/sw/sw-en/#wp-googlestats
Description: With this plugin you can display when and how often Googlebot visits your pages
Author: Fabrizio Tarizzo
Author URI: http://www.fabriziotarizzo.org/
*/

/*  Copyright (C) 2005 Fabrizio Tarizzo (email: software@fabriziotarizzo.org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/*
    CHANGELOG
    0.1c - 2005-06-08 - Corrects possible SQL-injection vulnerability
    0.1b - 2005-03-16 - First public release
*/

function wp_ftr_get_googlestats () {
	global $table_prefix, $wpdb;

	$page = mysql_real_escape_string($_SERVER['REQUEST_URI']);
	$table = $table_prefix . "googlestats";
	$row = $wpdb->get_row("SELECT lastvisit, visits, frequency FROM $table WHERE page='$page'");

	if ($row) {
		$results['lastvisit'] = $row->lastvisit;
		$results['visits'] = $row->visits;
		$results['frequency'] = $row->frequency;
	} else
		$results = null;

	return $results;
}

function wp_ftr_googlestats ($date_format = '', $visited_phrase = 'Googlebot visited this page ', $never_visited_phrase = 'Googlebot never visited this page') {
	if ($date_format == '')
		$date_format = get_settings('date_format');

	$stats = wp_ftr_get_googlestats();
	if ($stats) {
		$mysqldate = date('Y-m-d H:i:s', $stats['lastvisit']);
		$lastvisit = mysql2date($date_format, $mysqldate);
		echo $visited_phrase . $lastvisit;
	} else {
		echo $never_visited_phrase;
	}
}

function wp_ftr_googlestats_hook () {
	global $table_prefix, $wpdb;

	$useragent = mysql_real_escape_string(getenv ("HTTP_USER_AGENT"));
	if (strpos($useragent, "+http://www.googlebot.com/bot.html") || strpos($useragent, "+http://www.google.com/bot.html")) {
		$page = mysql_real_escape_string($_SERVER['REQUEST_URI']);
		$table = $table_prefix . "googlestats";
		$now = time();
		$row = $wpdb->get_row("SELECT lastvisit, visits, frequency FROM $table WHERE page = '$page'");
		if ($row) {
			$lastvisit = $row->lastvisit;
			$visits = $row->visits;
			$frequency = $row->frequency;
			if ($visits == 1) {
				/* This is the SECOND time Google visits this page */
				$frequency = $now - $lastvisit;
				$visits++;
				$lastvisit = $now;
			} else {
				$frequency = (($frequency * ($visits - 1)) + ($now - $lastvisit)) / $visits;
				$visits++;
				$lastvisit = $now;
			}
			$wpdb->query("UPDATE $table SET frequency = $frequency, lastvisit = $lastvisit, visits = $visits WHERE page = '$page'");
		} else {
			/* This is the FIRST time Google visits this page */
			$result = $wpdb->query("INSERT INTO $table (page, lastvisit, visits) VALUES ('$page', $now, 1)");
		}
	}
}
add_action ('shutdown', 'wp_ftr_googlestats_hook');


function wp_ftr_googlestats_adminpage() {
	global $table_prefix, $wpdb;

	$table = $table_prefix . "googlestats";
	if (isset ($_GET['googlestats_resetstats'])) {
		$sql = "UPDATE $table SET visits = 1, frequency = 0";
		//echo "<p>$sql</p>";
		$wpdb->query ($sql);
	}
	$records = 20;
	$searchtype = "mrv";
	if (isset($_POST['googlestats_records']))
		$records = $_POST['googlestats_records'];
	if (isset($_POST['googlestats_searchtype']))
		$searchtype = $_POST['googlestats_searchtype'];

?>
	<div class="wrap">
	<h2>Googlebot</h2>
	<form method="post" action="">
	<p>
		Display the <input type="text" name="googlestats_records" value="<?php echo $records; ?>" /> pages
		<select name="googlestats_searchtype">
			<option value="mrv" <?php if ($searchtype=='mrv') {echo 'selected="selected"';}?>>more recently visited</option>
			<option value="lrv" <?php if ($searchtype=='lrv') {echo 'selected="selected"';}?>>least recently visited</option>
			<option value="mov" <?php if ($searchtype=='mov') {echo 'selected="selected"';}?>>more often visited</option>
			<option value="lov" <?php if ($searchtype=='lov') {echo 'selected="selected"';}?>>least often visited</option>
		</select> by Googlebot <input type="submit" value="Submit" />
	</p>
	</form>
	<p><a href="?page=wp-googlestats.php&amp;googlestats_resetstats=1">Reset statistics</a> (frequency data wil be erased, last visit date will be preserved).</p>
<?php
	$sql = "SELECT page, lastvisit, visits, frequency FROM $table ";
	
	if (($searchtype == 'mov') || ($searchtype == 'lov'))
		$sql .= "WHERE frequency > 0 AND visits >= 3"; //Data about visit frequency are statistically meaningful after at least three visits
		
	$sql .= " ORDER BY ";
	
	switch ($searchtype) {
		case "mrv":
			$sql .= "lastvisit DESC";
			break;
		case "lrv":
			$sql .= "lastvisit";
			break;
		case "mov":
			$sql .= "frequency";
			break;
		case "lov":
			$sql .= "frequency DESC";
			break;
		default:
			$sql .= "lastvisit DESC";
			break;
	}
	$sql .= " LIMIT $records";

	//echo "<p>$sql</p>";   // For debugging purposes
	$rows = $wpdb->get_results ($sql);
	if ($rows) {
	echo '<table style="width:100%"><thead><tr><th>Page</th><th>Visits (*)</th><th>Frequency (*)</th><th>Last visit</th></tr></thead><tbody>';
	$count = 0;
	$rows = (Array)$rows;
	foreach ($rows as $row) {
		$freq = $row->frequency;
		if ($freq && $row->visits >= 3)
			$freq = (int)($freq/3600) . "h " .  (int)(($freq % 3600) / 60) . 'm ' .  ($freq % 3600) % 60 . 's';
		else
			$freq = 'n/a';

		if ($count % 2)
			$row_class = "";
		else
			$row_class = "alternate";

		echo "<tr class=\"$row_class\"><td><a href=\"" . $row->page . '">' . $row->page . '</a></td><td>' .$row->visits . '</td><td>' . $freq . '</td><td>' . date('Y-m-d H:i:s',$row->lastvisit) . '</td></tr>';
		$count++;
	}
	echo '</tbody></table><p>(*) Data about visit frequency are statistically meaningful after at least three visits</p>';
	} else {
		echo '<p>No Google visits yet!</p>';
	} /* $rows */

	echo '</div> <!-- wrap -->';
}

function wp_ftr_googlestats_add_adminpage() {
	add_management_page ("Googlebot", "Googlebot", 8, __FILE__, 'wp_ftr_googlestats_adminpage');
}

add_action ('admin_menu', 'wp_ftr_googlestats_add_adminpage');
?>
