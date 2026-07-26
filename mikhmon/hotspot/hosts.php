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
if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {

	if ($hotspot == "hostp") {
		$gethosts = $API->comm("/ip/hotspot/host/print", array(
			"?bypassed" => "yes",
		));
		$TotalReg = count($gethosts);

		$counthosts = $API->comm("/ip/hotspot/host/print", array(
			"?bypassed" => "yes",
			"count-only" => "",
		));

	} elseif ($hotspot == "hosta") {
		$gethosts = $API->comm("/ip/hotspot/host/print", array(
			"?authorized" => "yes",
		));
		$TotalReg = count($gethosts);

		$counthosts = $API->comm("/ip/hotspot/host/print", array(
			"?authorized" => "yes",
			"count-only" => "",
		));

	} else {
		$gethosts = $API->comm("/ip/hotspot/host/print");
		$TotalReg = count($gethosts);

		$counthosts = $API->comm("/ip/hotspot/host/print", array(
			"count-only" => "",
		));
	}
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
	<h3><i class="fa fa-laptop"></i> Hosts  
		<?php
	if ($counthosts < 2) {
		echo "$counthosts item";
	} elseif ($counthosts > 1) {
		echo "$counthosts items ";
	};
	?>&nbsp;
		 | <a class="text-info" title="All" href="./?hotspot=hosts&session=<?= $session; ?>">&nbsp;&nbsp;All&nbsp;&nbsp;</a>
		 | <a class="text-success" title="Authorized" href="./?hotspot=hosta&session=<?= $session; ?>">&nbsp;&nbsp;A&nbsp;&nbsp;</a>
		 | <a class="text-primary" title="Bypassed" href="./?hotspot=hostp&session=<?= $session; ?>">&nbsp;&nbsp;P&nbsp;&nbsp;</a>
		 | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer " title="Reload data"></i>
    </h3>
</div>
<!-- /.card-header -->
<div class="card-body">	
  <div class="w-6">
    <input id="filterTable" type="text" class="form-control" placeholder="Search..">
  </div>
<div class="overflow box-bordered mr-t-10" style="max-height: 75vh"> 	   
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th></th>
    <th></th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> To Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
	<th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
  </thead>
  <tbody>  	
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$hosts = $gethosts[$i];
	// Les hotes sans bail DHCP ou sans commentaire n'exposent pas ces cles.
	$id = tikras_array_get($hosts, '.id', '');

	$maca = tikras_array_get($hosts, 'mac-address', '');
	$addr = tikras_array_get($hosts, 'address', '');
	$toaddr = tikras_array_get($hosts, 'to-address', '');
	$server = tikras_array_get($hosts, 'server', '');
	$commt = tikras_array_get($hosts, 'comment', '');
	$hostAuthorized = tikras_array_get($hosts, 'authorized', 'false');
	$hostDhcp = tikras_array_get($hosts, 'DHCP', 'false');
	$hostDynamic = tikras_array_get($hosts, 'dynamic', 'false');
	$hostBypassed = tikras_array_get($hosts, 'bypassed', 'false');

	$onclickhost = "loadpage('./?remove-host=" . tikras_js_string($id) . "&session=" . tikras_js_string(rawurlencode($session)) . "')";

	echo "<tr>";
	echo "<td style='text-align:center;'><span class='pointer'  title='Remove " . tikras_h($maca) . "' onclick=\"" . tikras_h($onclickhost) . "\"><i class='fa fa-minus-square text-danger'></i></span></td>";
	echo "<td style='text-align:center;'>";
	if ($hostAuthorized == "true" && $hostDhcp == "true") {
		echo "<b class='text-success' title='A - authorized, H - DHCP'>A H</b>";
	} elseif ($hostAuthorized == "true" && $hostDynamic == "true") {
		echo "<b class='text-success' title='A - Authorized, D - dynamic'>A D</b>";
	} elseif ($hostAuthorized == "true") {
		echo "<b class='text-success' title='A - authorized'>A</b>";
	} elseif ($hostDhcp == "true") {
		echo "<b class='text-success' title='H - DHCP'>H</b>";
	} elseif ($hostDynamic == "true") {
		echo "<b class='text-success' title='D - dynamic'>D</b>";
	} elseif ($hostBypassed == "true") {
		echo "<b class='text-primary' title='P - Bypassed'>P</b>";
	} else {
	}
	echo "</td>";
	echo "<td>" . tikras_h($maca) . "</td>";
	echo "<td>" . tikras_h($addr) . "</td>";
	echo "<td>" . tikras_h($toaddr) . "</td>";
	echo "<td>" . tikras_h($server) . "</td>";
	echo "<td>" . tikras_h($commt) . "</td>";
	echo "</tr>";
}
?>
  </tbody>
</table>
</div>
</div>
</tr>
</div>
</div>
