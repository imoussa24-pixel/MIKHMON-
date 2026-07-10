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
if (!function_exists('tikras_get')) {
	require_once(dirname(__DIR__) . '/lib/tikras_core.php');
}
tikras_start_session();
tikras_bootstrap_errors(false);
$session = tikras_get('session');
$uname = tikras_get('name');

include_once('../include/config.php');
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
$iphost=tikras_cfg_value($data, $session, 1, '!', ''); 
$userhost=tikras_cfg_value($data, $session, 2, '@|@', '');
$passwdhost=tikras_cfg_value($data, $session, 3, '#|#', ''); 
$hotspotname=tikras_cfg_value($data, $session, 4, '%', ''); 
$dnsname=tikras_cfg_value($data, $session, 5, '^', ''); 
$curency=tikras_cfg_value($data, $session, 6, '&', '');


include_once('../lib/tikras_routeros.php');

$API = tikras_routeros_create();
tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);

if($uname != ""){
	$getname = $API->comm("/ip/hotspot/user/print", array("?name" => "$uname"));
  	$exp = isset($getname[0]['comment']) ? $getname[0]['comment'] : '';
	if(substr($exp,3,1) == "/" && substr($exp,6,1) == "/"){
		$exp = $exp;
	}else{
	$getname = $API->comm("/sys/sch/print", array("?name" => "$uname"));
	  $exp = isset($getname[0]['next-run']) ? $getname[0]['next-run'] : '';
	}
  
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Voucher-<?= $hotspotname."-".$uname;?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta http-equiv="pragma" content="no-cache" />
		<style>
		body {
  			font-family: 'Helvetica', arial, sans-serif;
			font-size: 15px;
			margin:0px;
  		}
		</style>
	</head>
	<body>
		<div style="padding:5px;" id="exp" ><?= $exp;?></div>	
	</body>
</html>
