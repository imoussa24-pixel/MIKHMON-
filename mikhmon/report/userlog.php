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
include_once(dirname(__DIR__) . '/lib/tikras_routeros.php');
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {

	$idhr = tikras_get('idhr');
	$idbl = tikras_get('idbl');
	$remdata = tikras_post('remdata');
	$ARRAY = array();
	$filedownload = "all";
	$shf = "text";
	$shd = "hidden";


	if (strlen($idhr) > "0") {
		if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
			// Seul le nom du script est exploite: .proplist evite de rapatrier
			// le code source de chaque enregistrement.
			$ARRAY = $API->comm("/system/script/print", array(
				"?source" => "$idhr",
				".proplist" => "name",
			));
			$API->disconnect();
		}
		$filedownload = $idhr;
		$shf = "hidden";
		$shd = "text";
	} elseif (strlen($idbl) > "0") {
		if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
			$ARRAY = $API->comm("/system/script/print", array(
				"?owner" => "$idbl",
				".proplist" => "name",
			));
			$API->disconnect();
		}
		$filedownload = $idbl;
		$shf = "hidden";
		$shd = "text";
	} elseif ($idhr == "" || $idbl == "") {
		if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
			$ARRAY = $API->comm("/system/script/print", array(
				"?comment" => "mikhmon",
				".proplist" => "name",
			));
			$API->disconnect();
		}
		$filedownload = "all";
		$shf = "text";
		$shd = "hidden";
	}
}
?>
		<script>
			function downloadCSV(csv, filename) {
			  var csvFile;
			  var downloadLink;
			  // CSV file
			  csvFile = new Blob([csv], {type: "text/csv"});
			  // Download link
			  downloadLink = document.createElement("a");
			  // File name
			  downloadLink.download = filename;
			  // Create a link to the file
			  downloadLink.href = window.URL.createObjectURL(csvFile);
			  // Hide download link
			  downloadLink.style.display = "none";
			  // Add the link to DOM
			  document.body.appendChild(downloadLink);
			  // Click download link
			  downloadLink.click();
			  }
			  
			  function exportTableToCSV(filename) {
			    var csv = [];
			    var rows = document.querySelectorAll("#dataTable tr");
			    
			   for (var i = 0; i < rows.length; i++) {
			      var row = [], cols = rows[i].querySelectorAll("td, th");
			   for (var j = 0; j < cols.length; j++)
            row.push(cols[j].innerText);
        csv.push(row.join(","));
        }
        // Download CSV file
        downloadCSV(csv.join("\n"), filename);
        }

		</script>
<?php
$userLogTotal = count($ARRAY);
$userLogActions = tikras_ui_button('./?report=userlog&session=' . rawurlencode($session), 'list', $_all, 'muted');
echo tikras_ui_page_header('align-justify', $_user_log, $userLogTotal . ' entree(s) | ' . $filedownload, $userLogActions);
?>
<div class="row tikras-data-page">
<div class="col-12">
<div class="card tikras-data-card">
<div class="card-header">
	<h3><i class=" fa fa-align-justify"></i> <?= $_user_log ?> <?= $idhr . $idbl; ?> <span class="tikras-data-count" id="userLogVisible"><?= $userLogTotal; ?></span></h3>
</div>
<div class="card-body">
	<div class="tikras-data-toolbar">
		<div class="tikras-data-filters">	   
		  <input id="filterTable" type="search" class="form-control tikras-data-search" data-table="#dataTable" data-counter="#userLogVisible" placeholder="<?= $_search ?>">
		  <button class="btn bg-primary " onclick="exportTableToCSV('user-log-tikras-it-<?= $filedownload; ?>.csv')" title="Télécharger le journal utilisateurs"><i class="fa fa-download"></i> CSV</button>
		  <button class="btn bg-primary " onclick="location.href='./?report=userlog&session=<?= $session; ?>';" title="Toutes les données"><i class="fa fa-search"></i> <?= $_all ?></button>
		</div>
		<div class="tikras-data-filters">  
			<div class="input-group-1 col-box-2">
			<select style="padding:5px;" class="group-item group-item-l" title="Jour" id="D">
        			<?php
										$idhrParts = explode("/", $idhr);
										$day = isset($idhrParts[1]) ? $idhrParts[1] : "";
										if ($day != "") {
											echo "<option value='" . $day . "'>" . $day . "</option>";
										}
										echo "<option value=''>Jour</option>";

										for ($x = 1; $x <= 31; $x++) {
											if (strlen($x) == 1) {
												$x = "0" . $x;
											} else {
												$x = $x;
											}
											echo "<option value='" . $x . "'>" . $x . "</option>";
										}
										?> 
    		</select>
			</div>
			<div class="input-group-2 col-box-4">
			<select style="padding:5px;" class="group-item group-item-md" title="Mois" id="M">
        			<?php 
										$idbls = array(1 => "jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "okt", "nov", "dec");
										$idblf = array(1 => "Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre");
										$month = isset($idhrParts[0]) ? $idhrParts[0] : "";
										$month1 = substr($idbl, 0, 3);

										if ($month != "") {
											$fm = array_search($month, $idbls);
											echo "<option value='" . $month . "'>" . $idblf[$fm] . "</option>";
										} elseif ($month1 != "") {
											$fm = array_search($month1, $idbls);
											echo "<option value=" . $month1 . ">" . $idblf[$fm] . "</option>";
										} else {
											echo "<option value=" . $idbls[date("n")] . ">" . $idblf[date("n")] . "</option>";
										}
										for ($x = 1; $x <= 12; $x++) {
											echo "<option value='" . $idbls[$x] . "''>" . $idblf[$x] . "</option>";
										}
										?> 
    		</select>
			</div>
			<div class="input-group-2 col-box-3">
			<select style="padding:5px;" class="group-item group-item-md" title="Année" id="Y">
        			<?php 
										$year = isset($idhrParts[2]) ? $idhrParts[2] : "";
										$year1 = substr($idbl, 3, 4);

										if ($year != "") {
											echo "<option>" . $year . "</option>";
										} elseif ($year1 != "") {
											echo "<option>" . $year1 . "</option>";
										} else {
											echo "<option>" . date("Y") . "</option>";
										}
										for ($Y = 2018; $Y <= date("Y"); $Y++) {
											if ($Y == date("Y")) {
											} else {
												echo "<option value='" . $Y . "''>" . $Y . "</option>";
											}
										}
										?> 
    		</select>
			</div>
            <div class="input-group-2 col-box-3">	
				<div style="padding:3.5px;"  class="group-item group-item-r text-center pointer" onclick="filterR();"><i class="fa fa-search"></i> Filtrer</div>
			</div>
			<script type="text/javascript">
				
				function filterR(){
					var D = document.getElementById('D').value;
					var M = document.getElementById('M').value;
					var Y = document.getElementById('Y').value;

					if(D !== ""){
						window.location='./?report=userlog&idhr='+M+'/'+D+'/'+Y+'&session=<?= $session; ?>';
					}else if(D === ""){
						window.location='./?report=userlog&idbl='+M+Y+'&session=<?= $session; ?>';
					}
				}
			</script>
		</div>
		</div>  
		  <div class="overflow box-bordered tikras-data-table-wrap">
			<table id="dataTable" class="table table-bordered table-hover text-nowrap">
				<thead>
				<tr>
				  <th colspan=6 ><?= $_user_log ?> <?= $filedownload; ?></th>
				</tr>
				<tr>
					<th ><?= $_date ?></th>
					<th ><?= $_time ?></th>
					<th ><?= $_user_name ?></th>
					<th >Adresse</th>
					<th >Adresse MAC</th>
					<th ><?= $_validity ?></th>
				</tr>
				</thead>
				<tbody>
				<?php
			$TotalReg = count($ARRAY);

			for ($i = 0; $i < $TotalReg; $i++) {
				$regtable = $ARRAY[$i];
				echo "<tr>";
				echo "<td>";
				$getname = explode("-|-", $regtable['name']);
				$tgl = tikras_array_get($getname, 0, "");
				echo tikras_h($tgl);
				echo "</td>";
				echo "<td>";
				$ltime = tikras_array_get($getname, 1, "");
				echo tikras_h($ltime);
				echo "</td>";
				echo "<td>";
				$username = tikras_array_get($getname, 2, "");
				echo tikras_h($username);
				echo "</td>";
				echo "<td>";
				$addr = tikras_array_get($getname, 4, "");
				echo tikras_h($addr);
				echo "</td>";
				echo "<td>";
				$mac = tikras_array_get($getname, 5, "");
				echo tikras_h($mac);
				echo "</td>";
				echo "<td>";
				$val = tikras_array_get($getname, 6, "");
				echo tikras_h($val);
				echo "</td>";
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
