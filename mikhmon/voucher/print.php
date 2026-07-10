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
require_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
tikras_start_session();
tikras_bootstrap_errors(false);
tikras_start_gzip();

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  
  date_default_timezone_set(tikras_session_get('timezone', date_default_timezone_get()));
  
// load session MikroTik
  $session = tikras_get('session');

// load config
  include('../include/config.php');
  include('../include/readcfg.php');

  include('../lib/formatbytesbites.php');

  $id = tikras_get('id');
  $qr = tikras_get('qr');
  $small = tikras_get('small');
  $userp = tikras_get('user');

  require('../lib/tikras_routeros.php');
  $API = tikras_routeros_create();
  $routerConnected = tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);

  if (!function_exists('tikras_voucher_part')) {
    function tikras_voucher_part($value, $separator, $index, $default)
    {
      $parts = explode($separator, (string) $value);
      return isset($parts[$index]) ? $parts[$index] : $default;
    }
  }

  if (!function_exists('tikras_voucher_generated_fallback')) {
    function tikras_voucher_generated_fallback($requestedId)
    {
      $fallback = array(
        'users' => array(),
        'profile' => '',
        'validity' => '',
        'price' => '0',
        'selling_price' => '0',
        'timelimit' => '0',
        'datalimit' => '0',
        'comment' => '',
      );
      $tempFile = tikras_config_local_dir() . DIRECTORY_SEPARATOR . 'voucher-temp.php';
      if (!is_file($tempFile)) {
        $tempFile = __DIR__ . DIRECTORY_SEPARATOR . 'temp.php';
      }
      if (!is_file($tempFile)) {
        return $fallback;
      }

      $genu = '';
      $genlist = '';
      include($tempFile);
      if ($genu == '' || $genlist == '') {
        return $fallback;
      }

      $genPlain = decrypt($genu);
      $comment = tikras_voucher_part($genPlain, '|', 0, '');
      if ($requestedId != '' && $comment != '' && $requestedId != $comment) {
        return $fallback;
      }

      $tickets = @unserialize(decrypt($genlist));
      if (!is_array($tickets)) {
        return $fallback;
      }

      $profile = tikras_voucher_part($genPlain, '~', 1, '');
      $timelimit = tikras_voucher_part($genPlain, '~', 4, '0');
      $datalimit = tikras_voucher_part($genPlain, '~', 5, '0');
      foreach ($tickets as $index => $ticket) {
        $username = isset($ticket['username']) ? (string) $ticket['username'] : '';
        $password = isset($ticket['password']) ? (string) $ticket['password'] : '';
        if ($username == '') {
          continue;
        }
        $fallback['users'][] = array(
          '.id' => '*' . substr(md5($username . ':' . $index), 0, 8),
          'name' => $username,
          'password' => $password,
          'profile' => $profile,
          'limit-uptime' => $timelimit == '0' ? '' : $timelimit,
          'limit-bytes-total' => $datalimit,
          'comment' => $comment,
        );
      }

      $pricePart = tikras_voucher_part($genPlain, '~', 3, '0!0');
      $fallback['profile'] = $profile;
      $fallback['validity'] = tikras_voucher_part($genPlain, '~', 2, '');
      $fallback['price'] = tikras_voucher_part($pricePart, '!', 0, '0');
      $fallback['selling_price'] = tikras_voucher_part($pricePart, '!', 1, '0');
      $fallback['timelimit'] = $timelimit;
      $fallback['datalimit'] = $datalimit;
      $fallback['comment'] = $comment;
      return $fallback;
    }
  }

  $getuser = array();
  $TotalReg = 0;
  $fallbackPrint = tikras_voucher_generated_fallback($id);

  if ($userp != "") {
    $pulluser = explode('-', $userp);
    $usermode = isset($pulluser[0]) ? $pulluser[0] : "";
    $iuser = count($pulluser);
    $prefix = isset($pulluser[$iuser - 2]) ? $pulluser[$iuser - 2] : "";
    $user = isset($pulluser[$iuser - 1]) ? $pulluser[$iuser - 1] : "";
    if ($iuser == 3) {
      $user = $prefix . "-" . $user;
    } else {
      $user = $user;
    }
    if ($routerConnected) {
      $getuser = $API->comm("/ip/hotspot/user/print", array("?name" => "$user"));
    }
    $TotalReg = count($getuser);
  } elseif ($id != "") {
    $idParts = explode('-', $id);
    $usermode = isset($idParts[0]) ? $idParts[0] : "";
    if ($routerConnected) {
      $getuser = $API->comm('/ip/hotspot/user/print', array("?comment" => "$id", "?uptime" => "0s"));
    }
    $TotalReg = count($getuser);
  }
  $getuser = is_array($getuser) ? $getuser : array();
  if ($id != "" && count($getuser) < 1 && count($fallbackPrint['users']) > 0) {
    $getuser = $fallbackPrint['users'];
    $TotalReg = count($getuser);
  }
  $getuprofile = isset($getuser[0]['profile']) ? $getuser[0]['profile'] : "";


  $getprofile = array();
  if ($routerConnected && $getuprofile != "") {
    $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$getuprofile"));
  }
  $getsharedu = isset($getprofile[0]['shared-users']) ? $getprofile[0]['shared-users'] : "";
  $ponlogin = isset($getprofile[0]['on-login']) ? $getprofile[0]['on-login'] : "";
  $ponloginParts = explode(",", $ponlogin);
  $validity = isset($ponloginParts[3]) ? $ponloginParts[3] : $fallbackPrint['validity'];
  $getprice = isset($ponloginParts[2]) ? $ponloginParts[2] : $fallbackPrint['price'];
  $getsprice = isset($ponloginParts[4]) ? $ponloginParts[4] : $fallbackPrint['selling_price'];

 
  
    if($getsprice == "0" && $getprice != "0"){
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
      } else {
        $price = $currency . " " . number_format((float)$getprice, 2);
      }
    }else if($getsprice != "0"){
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $price = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
      } else {
        $price = $currency . " " . number_format((float)$getsprice, 2);
      }
    }else if ($getsprice == "0") {
      $price = "";
    }

    
  

  $logo = "../img/logo-" . $session . ".png";
  if (file_exists($logo)) {
    $logo = "../img/logo-" . $session . ".png?t=". str_replace(" ","_",date("Y-m-d H:i:s"));
  } else {
    $logo = "../img/logo.png?t=". str_replace(" ","_",date("Y-m-d H:i:s"));
  }

}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Voucher-<?= $hotspotname . "-" . $getuprofile . "-" . $id; ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<meta http-equiv="pragma" content="no-cache" />
		<link rel="icon" href="../img/favicon.png" />
		<script src="../js/qrious.min.js"></script>
		<style>
body {
  color: #000000;
  background-color: #FFFFFF;
  font-size: 14px;
  font-family:  'Helvetica', arial, sans-serif;
  margin: 0px;
  -webkit-print-color-adjust: exact;
}
table.voucher {
  display: inline-block;
  border: 2px solid black;
  margin: 2px;
}
@page
{
  size: auto;
  margin-left: 7mm;
  margin-right: 3mm;
  margin-top: 9mm;
  margin-bottom: 3mm;
}
@media print
{
  table { page-break-after:auto }
  tr    { page-break-inside:avoid; page-break-after:auto }
  td    { page-break-inside:avoid; page-break-after:auto }
  thead { display:table-header-group }
  tfoot { display:table-footer-group }
}
#num {
  float:right;
  display:inline-block;
}
.qrc {
  width:30px;
  height:30px;
  margin-top:1px;
}
		</style>
	</head>
	<body onload="window.print()">

<?php for ($i = 0; $i < $TotalReg; $i++) {;
  $regtable = $getuser[$i];
  $uid = str_replace("=","",base64_encode($regtable['.id']));
  $idqr = str_replace("=","",base64_encode(($regtable['.id']."qr")));
  $username = $regtable['name'];
  $password = $regtable['password'];
  $profile = $regtable['profile'];
  $timelimit = $regtable['limit-uptime'];
  $getdatalimit = $regtable['limit-bytes-total'];
  $comment = $regtable['comment'];
  if ($getdatalimit == 0) {
    $datalimit = "";
  } else {
    $datalimit = formatBytes($getdatalimit, 2);
  }
  
  $urilogin = "http://$dnsname/login?username=$username&password=$password";
  $qrcode = "
	<canvas class='qrcode' id='".$uid."'></canvas>
    <script>
      (function() {
        var ".$uid." = new QRious({
          element: document.getElementById('".$uid."'),
          value: '".tikras_js_string($urilogin)."',
          size:'256'
        });

      })();
    </script>
	";
 
  $num = $i + 1;
  ?>
<?php
if ($userp != "") {
  include('./template-thermal.php');
} else {
  if ($small == "yes") {
    include('./template-small.php');
  } else {
    include('./template.php');
  }
}
?>
<?php 
} ?>

	
</body>
</html>
