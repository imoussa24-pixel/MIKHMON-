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

	$getcookies = $API->comm("/ip/hotspot/cookie/print");
	$TotalReg = count($getcookies);

	$countcookies = $API->comm("/ip/hotspot/cookie/print", array(
		"count-only" => "",
	));

}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-sitemap"></i> Hotspot Cookies 
<?php
if ($countcookies < 2) {
	echo "$countcookies item";
} elseif ($countcookies > 1) {
	echo "$countcookies items";
};
echo "</th>";
?>
&nbsp;&nbsp; | &nbsp;&nbsp;<i onclick="location.reload();" class="fa fa-refresh pointer" title="Reload data"></i>
    </h3>
</div>
<div class="card-body">	   
<div class="w-6">
    <input id="filterTable" type="text" class="form-control" placeholder="Search..">
  </div>
<div class="overflow box-bordered mr-t-10" style="max-height: 75vh">   	   
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
  	<th></th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> User</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Domain</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Expires In</th>
  </tr>
  </thead>
  <tbody> 
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$cookies = $getcookies[$i];
	$id = $cookies['.id'];
	$user = $cookies['user'];
	$maca = $cookies['mac-address'];
	$domain = $cookies['domain'];
	$exp = formatDTM($cookies['expires-in']);

	$onclickcookie = "loadpage('./?remove-cookie=" . tikras_js_string($id) . "&session=" . tikras_js_string(rawurlencode($session)) . "')";

	echo "<tr>";
	echo "<td style='text-align:center;'><span class='pointer'  title='Remove " . tikras_h($user) . "' onclick=\"" . tikras_h($onclickcookie) . "\"><i class='fa fa-minus-square text-danger'></i></span></td>";
	echo "<td>" . tikras_h($user) . "</td>";
	echo "<td>" . tikras_h($maca) . "</td>";
	echo "<td>" . tikras_h($domain) . "</td>";
	echo "<td>" . $exp . "</td>";
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
