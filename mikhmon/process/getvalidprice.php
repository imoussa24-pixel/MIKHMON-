<?php
/*
 *  Copyright (C) 2019 Laksamadi Guko.
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
// load session MikroTik
  $session = tikras_get('session');

// load config
  include('../include/config.php');
  $iphost = tikras_cfg_value($data, $session, 1, '!', '');
  $userhost = tikras_cfg_value($data, $session, 2, '@|@', '');
  $passwdhost = tikras_cfg_value($data, $session, 3, '#|#', '');
  $curency = tikras_cfg_value($data, $session, 6, '&', '');

// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');

  include_once('../lib/tikras_routeros.php');

  $API = tikras_routeros_create();
  tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);

  $uprofname = tikras_get('name');
  if ($uprofname != "") {
    $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$uprofname"));
    $ponlogin = isset($getprofile[0]['on-login']) ? $getprofile[0]['on-login'] : "";
    $onLoginParts = explode(",", $ponlogin);
    $validity = tikras_array_get($onLoginParts, 3, "");
    $getprice = tikras_array_get($onLoginParts, 2, "0");
    $getsprice = tikras_array_get($onLoginParts, 4, "0");
    $lockUser = tikras_array_get($onLoginParts, 6, "");
    $getvalid = $_validity. " : " . $validity;
    $getlock = "| ".$_lock_user." : " . $lockUser;
    $price = "";
    $sprice = "";
    if ($getprice == 0) {
    } else {
      if ($curency == "Rp" || $curency == "rp" || $curency == "IDR" || $curency == "idr") {
        $price = "| ".$_price." : " . $curency . " " . number_format($getprice, 0, ",", ".");
      } else {
        $price = "| ".$_price." : " . $curency . " " . number_format($getprice);
      }
    }
    if ($getsprice == 0) {
    } else {
      if ($curency == "Rp" || $curency == "rp" || $curency == "IDR" || $curency == "idr") {
        $sprice = "| ".$_selling_price." : " . $curency . " " . number_format($getsprice, 0, ",", ".");
      } else {
        $sprice = "| ".$_selling_price." : " . $curency . " " . number_format($getsprice);
      }
    }
    echo '<b id="getdata">' . $getvalid . ' ' . $price . ' ' . $sprice . ' ' . $getlock . '</b>';
    echo '<span id="validity">' . $validity . '</span> ';
  }
}
?>
