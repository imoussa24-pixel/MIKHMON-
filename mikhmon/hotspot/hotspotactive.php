<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {

// load session MikroTik
	$session = tikras_get('session');
	$serveractive = tikras_get('server');

// load config
	include('../include/config.php');
	include('../include/readcfg.php');
	
// lang
  include('../include/lang.php');
  include('../lang/'.$langid.'.php');

// routeros api
	include_once('../lib/tikras_routeros.php');
	include_once('../lib/formatbytesbites.php');
	$API = tikras_routeros_create();
	tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);

	if ($serveractive != "") {
		$gethotspotactive = $API->comm("/ip/hotspot/active/print", array("?server" => "" . $serveractive . ""));
		$TotalReg = count($gethotspotactive);

		$counthotspotactive = $API->comm("/ip/hotspot/active/print", array(
			"count-only" => "", "?server" => "" . $serveractive . ""
		));

	} else {
		$gethotspotactive = $API->comm("/ip/hotspot/active/print");
		$TotalReg = count($gethotspotactive);

		$counthotspotactive = $API->comm("/ip/hotspot/active/print", array(
			"count-only" => "",
		));
	}
}
?>
<?php
$activeSubtitle = $counthotspotactive . ' client(s) actif(s)';
if ($serveractive != '') {
	$activeSubtitle .= ' | Serveur ' . $serveractive;
}
$activeActions = '';
if ($serveractive != '') {
	$activeActions = tikras_ui_button('./?hotspot=active&session=' . rawurlencode($session), 'search', $_show_all, 'muted');
}
echo tikras_ui_page_header('wifi', $_hotspot_active, $activeSubtitle, $activeActions);
?>
<div class="row tikras-data-page">
<div id="reloadHotspotActive">
<div class="col-12">
	<div class="card tikras-data-card">
		<div class="card-header">
    		<h3><i class="fa fa-wifi"></i> <?= $_hotspot_active ?> <?php
				if ($serveractive == "") {
				} else {
					echo $serveractive . " ";
				}
				if ($counthotspotactive < 2) {
					echo "$counthotspotactive item";
				} elseif ($counthotspotactive > 1) {
					echo "$counthotspotactive items";
				};
				if ($serveractive == "") {
				} else {
					echo " | <a href='./?hotspot=active&session=" . $session . "'> <i class='fa fa-search'></i> Show all</a>";
				}
				?>			</h3>
        </div>
         <div class="card-body">
		<div class="tikras-data-toolbar">
			<div class="tikras-data-filters">
				<input id="filterHotspotActive" type="search" class="group-item tikras-data-search" data-table="#tFilter" data-counter="#hotspotActiveVisible" placeholder="<?= $_search ?> utilisateur, IP, MAC, serveur">
			</div>
			<div class="tikras-data-actions">
				<span class="tikras-data-count" id="hotspotActiveVisible"><?= $counthotspotactive; ?></span>
			</div>
		</div>
		<div class="overflow tikras-data-table-wrap">
<table id="tFilter" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th></th>
    <th>Server</th>
    <th>User</th>
    <th>Address</th>
    <th>Mac Address</th>
    <th class="text-right">Uptime</th>
    <th class="text-right">Bytes In</th>
    <th class="text-right">Bytes Out</th>
    <th class="text-right">Time Left</th>
    <th>Login By</th>
    <th><?= $_comment ?></th>
  </tr>
  </thead>
  <tbody>
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$hotspotactive = $gethotspotactive[$i];
	$id = $hotspotactive['.id'];
	$server = $hotspotactive['server'];
	$user = $hotspotactive['user'];
	$address = $hotspotactive['address'];
	$mac = $hotspotactive['mac-address'];
	$uptime = formatDTM($hotspotactive['uptime']);
	$usesstime = formatDTM($hotspotactive['session-time-left']);
	$bytesi = formatBytes($hotspotactive['bytes-in'], 2);
	$byteso = formatBytes($hotspotactive['bytes-out'], 2);
	$loginby = $hotspotactive['login-by'];
	$comment = $hotspotactive['comment'];
	$onclickactive = "loadpage('./?remove-user-active=" . tikras_js_string($id) . "&session=" . tikras_js_string(rawurlencode($session)) . "')";
	echo "<tr>";
	echo "<td style='text-align:center;'><span class='pointer'  title='Remove " . tikras_h($user) . "' onclick=\"" . tikras_h($onclickactive) . "\"><i class='fa fa-minus-square text-danger'></i></span></td>";
	echo "<td><a  title='filter " . tikras_h($server) . "' href='./?hotspot=active&server=" . tikras_h(rawurlencode($server)) . "&session=" . tikras_h(rawurlencode($session)) . "'><i class='fa fa-server'></i> " . tikras_h($server) . "</a></td>";
	echo "<td><a title='Open User " . tikras_h($user) . "' href='./?hotspot-user=" . tikras_h(rawurlencode($user)) . "&session=" . tikras_h(rawurlencode($session)) . "'><i class='fa fa-edit'></i> " . tikras_h($user) . "</a></td>";
	echo "<td>" . tikras_h($address) . "</td>";
	echo "<td>" . tikras_h($mac) . "</td>";
	echo "<td style='text-align:right;'>" . $uptime . "</td>";
	echo "<td style='text-align:right;'>" . $bytesi . "</td>";
	echo "<td style='text-align:right;'>" . $byteso . "</td>";
	echo "<td style='text-align:right;'>" . $usesstime . "</td>";
	echo "<td>" . tikras_h($loginby) . "</td>";
	echo "<td>" . tikras_h($comment) . "</td>";
	echo "</tr>";
}
?>
  </tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>
