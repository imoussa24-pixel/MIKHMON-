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

// time zone
date_default_timezone_set(tikras_session_get('timezone', date_default_timezone_get()));
	
// load session MikroTik
$session = tikras_get('session');

$quickprint = tikras_get('quickprint');
$qty = 1;
// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');
// quick bt
include('../include/quickbt.php');
// load config
include('../include/config.php');
include('../include/readcfg.php');

// routeros api
include_once('../lib/tikras_routeros.php');
include_once('../lib/formatbytesbites.php');
$API = tikras_routeros_create();
tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);
	// get quick print
$getquickprint = $API->comm("/system/script/print", array("?name" => "$quickprint"));

  $quickprintdetails = isset($getquickprint[0]) ? $getquickprint[0] : array();
  $qpid = tikras_array_get($quickprintdetails, '.id', '');
  $quickprintsource = explode("#", tikras_array_get($quickprintdetails, 'source', ''));
  $package = tikras_array_get($quickprintsource, 1, '');
  $server = tikras_array_get($quickprintsource, 2, 'all');
  $usermode = tikras_array_get($quickprintsource, 3, 'up');
  $userl = tikras_array_get($quickprintsource, 4, '4');
  $prefix = tikras_array_get($quickprintsource, 5, '');
  $char = tikras_array_get($quickprintsource, 6, 'mix');
  $profile = tikras_array_get($quickprintsource, 7, '');
  $timelimit = tikras_array_get($quickprintsource, 8, '0');
  $datalimit = tikras_array_get($quickprintsource, 9, '0');
  $comment = tikras_array_get($quickprintsource, 10, '');
  $getvalid = tikras_array_get($quickprintsource, 11, '');
  $priceParts = explode("_", tikras_array_get($quickprintsource, 12, '0_0'), 2);
  $getprice = tikras_array_get($priceParts, 0, '0');
  $getsprice = tikras_array_get($priceParts, 1, '0');
  $userlock = tikras_array_get($quickprintsource, 13, '');

  if($getsprice == "" && $getprice != ""){
	  $price = $getprice;
  }else if($getsprice != ""){
	  $price = $getsprice;
  }else if ($getsprice == "") {
	$price = "";
  }

		$commt = $usermode . "-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $comment;

		$a = array("1" => "", "", 1, 2, 2, 3, 3, 4);

		if ($usermode == "up") {
			for ($i = 1; $i <= $qty; $i++) {
				if ($char == "lower") {
					$u[$i] = randLC($userl);
				} elseif ($char == "upper") {
					$u[$i] = randUC($userl);
				} elseif ($char == "upplow") {
					$u[$i] = randULC($userl);
				} elseif ($char == "mix") {
					$u[$i] = randNLC($userl);
				} elseif ($char == "mix1") {
					$u[$i] = randNUC($userl);
				} elseif ($char == "mix2") {
					$u[$i] = randNULC($userl);
				}
				if ($userl == 3) {
					$p[$i] = randN(3);
				} elseif ($userl == 4) {
					$p[$i] = randN(4);
				} elseif ($userl == 5) {
					$p[$i] = randN(5);
				} elseif ($userl == 6) {
					$p[$i] = randN(6);
				} elseif ($userl == 7) {
					$p[$i] = randN(7);
				} elseif ($userl == 8) {
					$p[$i] = randN(8);
				}

				$u[$i] = "$prefix$u[$i]";
			}

			for ($i = 1; $i <= $qty; $i++) {
				$API->comm("/ip/hotspot/user/add", array(
					"server" => "$server",
					"name" => "$u[$i]",
					"password" => "$p[$i]",
					"profile" => "$profile",
					"limit-uptime" => "$timelimit",
					"limit-bytes-total" => "$datalimit",
					"comment" => "$commt",
				));
			}
		}

		if ($usermode == "vc") {
			$shuf = ($userl - $a[$userl]);
			for ($i = 1; $i <= $qty; $i++) {
				if ($char == "lower") {
					$u[$i] = randLC($shuf);
				} elseif ($char == "upper") {
					$u[$i] = randUC($shuf);
				} elseif ($char == "upplow") {
					$u[$i] = randULC($shuf);
				}
				if ($userl == 3) {
					$p[$i] = randN(1);
				} elseif ($userl == 4 || $userl == 5) {
					$p[$i] = randN(2);
				} elseif ($userl == 6 || $userl == 7) {
					$p[$i] = randN(3);
				} elseif ($userl == 8) {
					$p[$i] = randN(4);
				}

				$u[$i] = "$prefix$u[$i]$p[$i]";

				if ($char == "num") {
					if ($userl == 3) {
						$p[$i] = randN(3);
					} elseif ($userl == 4) {
						$p[$i] = randN(4);
					} elseif ($userl == 5) {
						$p[$i] = randN(5);
					} elseif ($userl == 6) {
						$p[$i] = randN(6);
					} elseif ($userl == 7) {
						$p[$i] = randN(7);
					} elseif ($userl == 8) {
						$p[$i] = randN(8);
					}

					$u[$i] = "$prefix$p[$i]";
				}
				if ($char == "mix") {
					$p[$i] = randNLC($userl);


					$u[$i] = "$prefix$p[$i]";
				}
				if ($char == "mix1") {
					$p[$i] = randNUC($userl);


					$u[$i] = "$prefix$p[$i]";
				}
				if ($char == "mix2") {
					$p[$i] = randNULC($userl);


					$u[$i] = "$prefix$p[$i]";
				}

			}
			for ($i = 1; $i <= $qty; $i++) {
				$API->comm("/ip/hotspot/user/add", array(
					"server" => "$server",
					"name" => "$u[$i]",
					"password" => "$u[$i]",
					"profile" => "$profile",
					"limit-uptime" => "$timelimit",
					"limit-bytes-total" => "$datalimit",
					"comment" => "$commt",
				));
			}
		}

        $getuser = $API->comm("/ip/hotspot/user/print", array(
            "?name" => "$u[1]",
          ));
          $userdetails = $getuser[0];
          $uid = $userdetails['.id'];
          $uname = $userdetails['name'];
          $upass = $userdetails['password'];
          $uprofile = $userdetails['profile'];
					$uuptime = formatDTM($userdetails['uptime']);
					$utimelimit = $userdetails['limit-uptime'];
          $udatalimit = $userdetails['limit-bytes-total'];
          $ucomment = $userdetails['comment'];
        
          if (substr(formatBytes2($udatalimit, 2), -2) == "MB") {
            $udatalimit = $udatalimit / 1048576;
            $MG = "MB";
          } elseif (substr(formatBytes2($udatalimit, 2), -2) == "GB") {
            $udatalimit = $udatalimit / 1073741824;
            $MG = "GB";
          } elseif ($udatalimit == "") {
            $udatalimit = "";
            $MG = "MB";
          }
          $_SESSION['sss'] = $uname;
         
// Print BT
  $chl = urlencode("http://$dnsname/login?username=$uname&password=$upass");
	$qrcode = 'https://chart.googleapis.com/chart?cht=qr&chs=100x100&chld=L|0&chl=' . $chl . '&choe=utf-8';
	//$qrcode = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.$chl;

if ($currency == in_array($currency, $cekindo['indo'])) {
  $pricebt = $currency . " " . number_format($price, 0, ",", ".");
  if (substr($getvalid, -1) == "d") {
    $validity = substr($getvalid, 0, -1) . "Hari";
  } else if (substr($getvalid, -1) == "h") {
    $validity = substr($getvalid, 0, -1) . "Jam";
  }
  if (substr($utimelimit, -1) == "d" & strlen($utimelimit) > 3) {
    $timelimit = ((substr($utimelimit, 0, -1) * 7) + substr($utimelimit, 2, 1)) . "Hari";
  } else if (substr($utimelimit, -1) == "d") {
    $timelimit = substr($utimelimit, 0, -1) . "Hari";
  } else if (substr($utimelimit, -1) == "h") {
    $timelimit = substr($utimelimit, 0, -1) . "Jam";
  } else if (substr($utimelimit, -1) == "w") {
    $timelimit = (substr($utimelimit, 0, -1) * 7) . "Hari";
  }

  } else {
    $pricebt = $currency . " " . number_format($price);
    $timelimit = $utimelimit;
    $validity = $getvalid;
  }
	if($qrbt == "enable"){$qr = "yes";}	
	include('../voucher/printbt.php');
?>

<script>
    $(document).ready(function(){
			var w = window.innerWidth;
  			if (w < 800) {
					sendToQuickPrinterChrome();
  			} else if (w > 800) {

					window.open('./voucher/print.php?user=<?= $usermode ?>-<?= $uname ?>&qr=<?= $qr ?>&session=<?= $session ?>','_blank','width=310,height=450').print();
					//window.location.href="./?hotspot-user=<?= $u[1] ?>&session=<?= $session ?>";
  			}
    //sendToQuickPrinterChrome();
});
</script>
<?php } ?>
