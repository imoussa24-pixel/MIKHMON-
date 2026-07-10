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
if(!isset($_SESSION["mikhmon"])){
  header("Location:../admin.php?id=login");
}else{
// load session MikroTik
$session = tikras_get('session');
$interface = tikras_get('iface');
//echo $interface
// load config
include('../include/config.php');
include('../include/readcfg.php');

// routeros api
include_once('../lib/tikras_routeros.php');
include_once('../lib/formatbytesbites.php');
$API = tikras_routeros_create();

  if (function_exists('set_time_limit')) {
    @set_time_limit(6);
  }
  header('Content-Type: application/json; charset=utf-8');

  $rows = array('name' => 'Tx', 'data' => array(0), 'state' => 'offline');
  $rows2 = array('name' => 'Rx', 'data' => array(0), 'state' => 'offline');
  
  if(tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session, array('timeout' => 2, 'cooldown' => 15))){

//$getinterface = tikras_routeros_comm($API, "/interface/print");
    //$interface = $getinterface[$iface-1]['name'];
    $getinterfacetraffic = tikras_routeros_comm($API, "/interface/monitor-traffic", array(
      "interface" => "$interface",
      "once" => "",
      ), array());

    $ftx = isset($getinterfacetraffic[0]['tx-bits-per-second']) ? $getinterfacetraffic[0]['tx-bits-per-second'] : 0;
    $frx = isset($getinterfacetraffic[0]['rx-bits-per-second']) ? $getinterfacetraffic[0]['rx-bits-per-second'] : 0;

      $rows = array('name' => 'Tx', 'data' => array($ftx), 'state' => 'online');
      $rows2 = array('name' => 'Rx', 'data' => array($frx), 'state' => 'online');
      
  }
  
  tikras_routeros_disconnect($API);
  
  $result = array();

	array_push($result,$rows);
	array_push($result,$rows2);
  print json_encode($result);
}
?>
