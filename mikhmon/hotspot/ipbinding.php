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

	$getbinding = $API->comm("/ip/hotspot/ip-binding/print");
	$TotalReg = count($getbinding);

	$countbinding = $API->comm("/ip/hotspot/ip-binding/print", array(
		"count-only" => "",
	));
}

?>
<div class="row">
<div id="reloadbinding">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-address-book"></i> <?= $_ip_bindings ?> 
<?php
if ($countbinding < 2) {
	echo "$countbinding item";
} elseif ($countbinding > 1) {
	echo "$countbinding items";
};
?>
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
    <th></th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> MAC Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> To Address</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
  </tr>
  </thead>
  <tbody> 
<?php
for ($i = 0; $i < $TotalReg; $i++) {
	$binding = $getbinding[$i];
	$id = $binding['.id'];

	$maca = $binding['mac-address'];
	$addr = $binding['address'];
	$toaddr = $binding['to-address'];
	$server = $binding['server'];
	$commt = $binding['comment'];
	$bdisabled = $binding['disabled'];

	echo "<tr>";
	$onclickbinding = "if(confirm('Are you sure to delete (" . tikras_js_string($maca) . ")?')){loadpage('./?remove-ip-binding=" . tikras_js_string($id) . "&mac=" . tikras_js_string(rawurlencode($maca)) . "&addr=" . tikras_js_string(rawurlencode($addr)) . "&session=" . tikras_js_string(rawurlencode($session)) . "')}else{}";
	?>
  	<td style='text-align:center;'><i class='fa fa-minus-square text-danger pointer' onclick="<?= tikras_h($onclickbinding); ?>" title='Remove <?= tikras_h($maca); ?>'></i>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
  	<?php

		if ($bdisabled == "true") {
			$onclicktoggle = "loadpage('./?enable-ip-binding=" . tikras_js_string($id) . "&session=" . tikras_js_string(rawurlencode($session)) . "')";
			echo "<span class='text-warning btnsmall pointer' title='Enable Binding " . tikras_h($addr) . "' onclick=\"" . tikras_h($onclicktoggle) . "\"><i class='fa fa-lock'></span></td>";
		} else {
			$onclicktoggle = "loadpage('./?disable-ip-binding=" . tikras_js_string($id) . "&session=" . tikras_js_string(rawurlencode($session)) . "')";
			echo "<span title='Disable Binding " . tikras_h($addr) . "' class='btnsmall pointer' onclick=\"" . tikras_h($onclicktoggle) . "\"><i class='fa fa-unlock '></span></td>";
		}
		echo "<td style='text-align:center;'>";
		if ($binding['bypassed'] == "true") {
			echo "<b style='color:#0091EA;'>P</b>";
		} else {
		}
		echo "</td>";
		echo "<td>" . tikras_h($commt) . "</td>";
		echo "<td>" . tikras_h($maca) . "</td>";
		echo "<td>" . tikras_h($addr) . "</td>";
		echo "<td>" . tikras_h($toaddr) . "</td>";
		echo "<td>" . tikras_h($server) . "</td>";
		echo "</tr>";
	}
	?>
  </tbody>
</table>
</div>
</div>
</div>
</div>
<div class="modal-window" id="help" aria-hidden="true">
  <div>
  	<header><h1>Help</h1></header>
  	<a style="font-weight:bold;" href="#" title="Close" class="modal-close">X</a>
	<p> 
		    <?php if ($currency == in_array($currency, $cekindo['indo'])) { ?>
		      <ul>
		        <li>Masuk ke menu Hosts.</li>
		        <li>Klik IP Address yang ingin di binding.</li>
		      </ul>
		    <?php 
				} else { ?>
		      <ul>
		        <li>Go to Hosts menu.</li>
		        <li>Click the IP Address that you want to binding.</li>
		      </ul>
		    <?php 
				} ?>
	</p>
  </div>
</div>
</div>
</div>
