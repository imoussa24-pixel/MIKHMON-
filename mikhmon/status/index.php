<?php
/*
 *  Copyright (C) 2026 TIKRAS IT.
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

// hide all error
if (!function_exists('tikras_get')) {
	require_once(dirname(__DIR__) . '/lib/tikras_core.php');
}
tikras_bootstrap_errors(false);
tikras_start_gzip();

$session = tikras_get('session');
require('../lib/tikras_routeros.php');
include('../lib/formatbytesbites.php');
include('../include/config.php');

// theme  
include('../include/theme.php');

if (!function_exists('tikras_cfg_value')) {
	function tikras_cfg_value($data, $session, $index, $separator, $default)
	{
		if (!is_array($data) || !isset($data[$session]) || !isset($data[$session][$index])) {
			return $default;
		}
		$parts = explode($separator, (string) $data[$session][$index], 2);
		return isset($parts[1]) ? $parts[1] : $default;
	}
}

$iphost = tikras_cfg_value($data, $session, 1, '!', '');
$userhost = tikras_cfg_value($data, $session, 2, '@|@', '');
$passwdhost = tikras_cfg_value($data, $session, 3, '#|#', '');
$hotspotname = tikras_cfg_value($data, $session, 4, '%', '');
$dnsname = tikras_cfg_value($data, $session, 5, '^', '');
$currency = tikras_cfg_value($data, $session, 6, '&', '');

$cekindo['indo'] = array('RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.', );

$API = tikras_routeros_create();
if ($currency == in_array($currency, $cekindo['indo'])) {
	$title = array("Status Voucher", "User/Kode Voucher", "Paket", "Lama Terhubung", "Pemakaian Data", "Sisa Data", "Masa Aktif", "Dari", "Sampai", "tidak terdaftar.", "sudah kadaluarsa.", "Tanggal", "Cek Status", " Hari", " Jam", "Aktif", "Expired");
} else {
	$title = array("Voucher Status", "User/Voucher Code", "Profile", "Uptime", "Data Usage", "Data Remaining", "Validity", "Start", "End", "not registered.", "expired.", "Date", "Check Status", " Day", " Hour", "Active", "Expired");
}
if ($currency == in_array($currency, $cekindo['indo'])) {
	$s = "";
} else {
	$s = "s";
}
?>
<!DOCTYPE html>
<html>
<head>
<title><?= $title[0] . " " . $hotspotname; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="pragma" content="no-cache" />
<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0;"/>
<!-- Font Awesome -->
<link rel="stylesheet" type="text/css" href="../css/font-awesome/css/font-awesome.min.css" />
<!-- TIKRAS IT UI -->
<link rel="stylesheet" href="../css/mikhmon-ui.<?= $theme; ?>.min.css">
<link rel="icon" href="../img/favicon.png" />
<script>
function goBack() {
    window.history.back();
}
</script>

</head>
<body >
<div class="login-box" style="padding-top: 10px;">
<h3 class="text-center">Status Voucher<br><?= $hotspotname; ?></h3>
<p class="text-center" id="date1"><?= $title[11] . " : " . date("d-m-Y") . "<br>"; ?></p>
<form autocomplete="off"class="form" method="post" action="">
	<div class="input-group">
        <div class="input-group-7">
			<input type="text" class="group-item group-item-l" name="nama" placeholder="<?= $title[1]; ?>" autofocus required="1" />
		</div>
		<div class="input-group-5">
			<button type="submit" style="cursor: pointer; padding: 2.5px;" class="group-item group-item-r"><i class="fa fa-search"></i> <?= " " . $title[12]; ?></button>
		</div>
</div>
</form>
<?php
if (tikras_has_post('nama')) {
	$name = tikras_post('nama');
	if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
		$getuser = $API->comm("/ip/hotspot/user/print", array("?name" => "$name"));
		if (!is_array($getuser) || !isset($getuser[0]['name'])) {
			$getuser = array();
		}
		$userRow = isset($getuser[0]) ? $getuser[0] : array();
		$user = isset($userRow['name']) ? $userRow['name'] : '';
		$profile = isset($userRow['profile']) ? $userRow['profile'] : '';
		$exp = isset($userRow['comment']) ? $userRow['comment'] : '';
		$uptime = formatDTM(isset($userRow['uptime']) ? $userRow['uptime'] : '0s');
		$getbytein = isset($userRow['bytes-in']) ? $userRow['bytes-in'] : 0;
		$getbyteo = isset($userRow['bytes-out']) ? $userRow['bytes-out'] : 0;
		$getbytetot = ($getbytein + $getbyteo);
		$bytetot = formatBytes($getbytetot, 2);
		$limitup = isset($userRow['limit-uptime']) ? $userRow['limit-uptime'] : '';
		$limitbyte = isset($userRow['limit-bytes-total']) ? $userRow['limit-bytes-total'] : '';
		if ($limitbyte == "") {
			$dataleft = "Unlimited";
		} elseif ($limitbyte < $getbytetot) {
			$dataleft = "0 Byte";
		} else {
			$dataleft = formatBytes($limitbyte - $getbytetot, 2);
		}

		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$profile", ));
		$ponlogin = isset($getprofile[0]['on-login']) ? $getprofile[0]['on-login'] : '';
		$getvalidParts = explode(",", $ponlogin);
		$getvalid = isset($getvalidParts[3]) ? $getvalidParts[3] : '';
		$unit = substr($getvalid, -1);
		if ($unit == "d") {
			$getvalid = substr($getvalid, 0, strlen($getvalid) - 1) . " " . $title[13];
		} elseif ($unit == "h") {
			$getvalid = substr($getvalid, 0, strlen($getvalid) - 1) . " " . $title[14];
		}


	}
  
	if ($user == "" || (substr($exp,3,1) != "/" && substr($exp,6,1) != "/")) {
		echo "<h3 class='text-center'>User <i style='color:#008CCA;'>$name</i> $title[9]</h3>";
	} elseif ($limitup == "1s" || $uptime == $limitup || $getbyteo == $limitbyte) {
		echo "<h3 class='text-center'>User <i style='color:#008CCA;'>$name</i> $title[10]</h3>";
	}
	if ($user == "" || (substr($exp,3,1) != "/" && substr($exp,6,1) != "/")) {
	} else {
		?>
<section>
<div class="card">
<div class="card-header">
    <h3>
      <i class="fa fa-user mr-1"></i>
        User Details
    </h3>
  </div>
  <div class="card-body">
  <?php
	echo "<div style='overflow-x:auto;'>";
	echo "<table class='table table-bordered table-hover text-nowrap'>";
	echo "	<tr>";
	echo "		<td >$title[1]</td>";
	echo "		<td > $user</td>";
	echo "	</tr>";
	echo "	<tr>";
	echo "		<td >$title[2]</td>";
	echo "		<td > $profile</td>";
	echo "	</tr>";
	echo "	<tr>";
	echo "		<td >$title[3]</td>";
	echo "		<td > $uptime</td>";
	echo "	</tr>";
	echo "	<tr>";
	echo "		<td >$title[4]</td>";
	echo "		<td > $bytetot</td>";
	echo "	</tr>";
	if ($limitup == "1s" || $uptime == $limitup || $getbyteo == $limitbyte) {
		echo "	<tr>";
		echo "		<td >Status</td>";
		echo "		<td >$title[16]</td>";
		echo "	</tr>";
		echo "</table>";
		echo "</div>";
	} else {
		echo "	<tr>";
		echo "		<td >$title[5]</td>";
		echo "		<td > $dataleft</td>";
		echo "	</tr>";
		echo "	<tr>";
		echo "		<td >$title[6]</td>";
		echo "		<td >$getvalid</td>";
		echo "	</tr>";
				echo "	<tr>";
		echo "		<td >$title[8]</td>";
		echo "		<td >$exp</td>";
		echo "	</tr>";
		echo "	<tr>";
		echo "		<td >Status</td>";
		echo "		<td >$title[15]</td>";
		echo "	</tr>";
		echo "</table>";
		echo "</div>";
	}
}
$API->disconnect();

}

?>
</div>
</div>
</section>
</div>
</div>
</body>
</html>
