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

	$getlease = $API->comm("/ip/dhcp-server/lease/print");
	$TotalReg = count($getlease);

	$countlease = $API->comm("/ip/dhcp-server/lease/print", array(
		"count-only" => "",
	));

}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-sitemap"></i> DHCP Leases 
<?php
if ($countlease < 2) {
	echo "$countlease item";
} elseif ($countlease > 1) {
	echo "$countlease items";
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
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Active Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Active MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Active Host Name</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Status</th>
  </tr>
  </thead>
  <tbody> 
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$lease = $getlease[$i];
	// Un bail sans client connecte n'expose ni nom d'hote ni adresse active.
	$id = tikras_array_get($lease, '.id', '');

	$addr = tikras_array_get($lease, 'address', '');
	$maca = tikras_array_get($lease, 'mac-address', '');
	$server = tikras_array_get($lease, 'server', '');
	$aaddr = tikras_array_get($lease, 'active-address', '');
	$amaca = tikras_array_get($lease, 'active-mac-address', '');
	$ahostname = tikras_array_get($lease, 'host-name', '');
	$status = tikras_array_get($lease, 'status', '');


	echo "<tr>";
	echo "<td style='text-align:center;'>";
	if (tikras_array_get($lease, 'dynamic', 'false') == "true") {
		echo "<b title='D - dynamic'>D</b>";
	} else {
		echo "<b title='S - static'>S</b>";
	}
	echo "</td>";
	echo "<td>" . tikras_h($addr) . "</td>";
	echo "<td>" . tikras_h($maca) . "</td>";
	echo "<td>" . tikras_h($server) . "</td>";
	echo "<td>" . tikras_h($aaddr) . "</td>";
	echo "<td>" . tikras_h($amaca) . "</td>";
	echo "<td>" . tikras_h($ahostname) . "</td>";
	echo "<td>" . tikras_h($status) . "</td>";
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
