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
require_once(__DIR__ . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
tikras_session_defaults(array(
  'timezone' => date_default_timezone_get(),
  'theme' => '',
  'themecolor' => '',
  'ubn' => '',
  'ubp' => '',
  'ubc' => '',
  'hua' => '',
  'vcr' => '',
));
// check url

tikras_start_gzip();


$url = tikras_server('REQUEST_URI');

// load session MikroTik

$session = tikras_get('session');

if (!isset($_SESSION["mikhmon"])) {
  tikras_redirect('./admin.php?id=login');
} elseif (empty($session)) {
  tikras_redirect('./admin.php?id=sessions');
} else {
  $_SESSION["$session"] = $session;
  $setsession = $_SESSION["$session"];

  $_SESSION["connect"] = "";

// time zone
  tikras_apply_session_timezone();

// lang
  include('./include/lang.php');
  include('./lang/'.$langid.'.php');

// quick bt
  include('./include/quickbt.php');

// load config
  include('./include/config.php');
  include('./include/readcfg.php');

// theme  
  include('./include/theme.php');
  include('./settings/settheme.php');
  if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
  }

// routeros api
  include_once('./lib/tikras_routeros.php');
  include_once('./lib/formatbytesbites.php');
  $API = tikras_routeros_create();
  if (!tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
    $_SESSION["connect"] = "<b class='text-red'>Not Connected</b>";
    tikras_redirect('./admin.php?id=sessions');
  }

  $getidentity = tikras_routeros_comm($API, "/system/identity/print");
  $identity = isset($getidentity[0]['name']) ? $getidentity[0]['name'] : "";
  

// get variable
  $hotspot = tikras_get('hotspot');
  $hotspotuser = tikras_get('hotspot-user');
  $userbyname = tikras_get('hotspot-user');
  $removeuseractive = tikras_get('remove-user-active');
  $removehost = tikras_get('remove-host');
  $removecookie = tikras_get('remove-cookie');
  $removeipbinding = tikras_get('remove-ip-binding');
  $removehotspotuser = tikras_get('remove-hotspot-user');
  $removehotspotusers = tikras_get('remove-hotspot-users');
  $removeuserprofile = tikras_get('remove-user-profile');
  $resethotspotuser = tikras_get('reset-hotspot-user');
  $removehotspotuserbycomment = tikras_get('remove-hotspot-user-by-comment');
  $removeexpiredhotspotuser = tikras_get('remove-hotspot-user-expired');
  $enablehotspotuser = tikras_get('enable-hotspot-user');
  $disablehotspotuser = tikras_get('disable-hotspot-user');
  $enableipbinding = tikras_get('enable-ip-binding');
  $disableipbinding = tikras_get('disable-ip-binding');
  $userprofile = tikras_get('user-profile');
  $userprofilebyname = tikras_get('user-profile');
  $sys = tikras_get('system');
  $enablesch = tikras_get('enable-scheduler');
  $disablesch = tikras_get('disable-scheduler');
  $removesch = tikras_get('remove-scheduler');
  $macbinding = tikras_get('mac');
  $ipbinding = tikras_get('addr');
  $ppp = tikras_get('ppp');
  $secretbyname = tikras_get('secret');
  $enablesecr = tikras_get('enable-pppsecret');
  $disablesecr = tikras_get('disable-pppsecret');
  $removesecr = tikras_get('remove-pppsecret');
  $removepprofile = tikras_get('remove-pprofile');
  $removepactive = tikras_get('remove-pactive');
  $srv = tikras_get('srv');
  $prof = tikras_get('profile');
  $comm = tikras_get('comment');
  $serveractive = tikras_get('server');
  $serviceactive = tikras_get('service');
  $report = tikras_get('report');
  $removereport = tikras_get('remove-report');
  $minterface = tikras_get('interface');


  $pagehotspot = array('users','hosts','ipbinding','cookies','log','dhcp-leases');
  $pageppp = array('secrets','profiles','active',);
  $pagereport = array('userlog','selling','routeros-log');

  include_once('./include/headhtml.php');

  include_once('./include/menu.php');

  $disable_sci = '<script>
  document.getElementById("comment").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,.~".indexOf(chr) >= 0)
        return false;
};
</script>';


// logout
  if ($hotspot == "logout") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";

    session_destroy();
    echo "<script>sessionStorage.clear();</script>";
    tikras_redirect('./admin.php?id=login');
  }
// redirect to home
  elseif (substr(tikras_array_get(explode("=", $url, 2), 0, ''), -9) == "/?session") {

    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";
  }

// redirect to home
  elseif ($hotspot == "dashboard") {
    include_once('./dashboard/home.php');
    $_SESSION['ubn'] = "";

  }

// hotspot log
  elseif ($hotspot == "log") {
    include_once('./hotspot/log.php');
  }

// hotspot log
  elseif ($report == "userlog") {
    include_once('./report/userlog.php');
  }

// routeros log
  elseif ($report == "routeros-log") {
    include_once('./report/routeroslog.php');
  }

// about
  elseif ($hotspot == "about") {
    include_once('./include/about.php');
  }

// bad request
  elseif (substr($url, -1) == "=") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Bad request! redirect to Home......</b>";

    tikras_redirect('./');
  }

// hotspot add users
  elseif ($hotspot == "add-user") {
    $_SESSION['hua'] = "";
    include_once('./hotspot/adduser.php');
  }

// hotspot users
  elseif ($hotspot == "users" && $prof == "all") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by profile
  elseif ($hotspot == "users" && $prof != "") {
    $_SESSION['ubp'] = $prof;
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot users filter by comment
  elseif ($hotspot == "users" && $comm != "") {
    $_SESSION['ubc'] = $comm;
    $_SESSION['hua'] = "";
    $_SESSION['ubp'] = "";
    $_SESSION['vcr'] = "";
    include_once('./hotspot/users.php');
  }

// hotspot by profile
  elseif ($hotspot == "users-by-profile") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "";
    $_SESSION['ubc'] = "";
    $_SESSION['vcr'] = "active";
    include_once('./hotspot/userbyprofile.php');
  }
// export hotspot users
  elseif ($hotspot == "export-users") {
    include_once('./hotspot/exportusers.php');
  }

// quick print
  elseif ($hotspot == "quick-print") {
    include_once('./hotspot/quickprint.php');
  }

// quick print
elseif ($hotspot == "list-quick-print") {
  include_once('./hotspot/listquickprint.php');
}  

// add hotspot user
  elseif ($hotspotuser == "add") {
    include_once('./hotspot/adduser.php');
    echo $disable_sci;
  }

// add hotspot user
  elseif ($hotspotuser == "generate") {
    include_once('./hotspot/generateuser.php');
    echo $disable_sci;
  }

// roaming ticket generator
  elseif ($hotspotuser == "generate-roaming") {
    $tikrasTicketRoamingPage = true;
    include_once('./hotspot/generateuser.php');
    echo $disable_sci;
  }

// hotspot users filter by name
  elseif (substr($hotspotuser, 0, 1) == "*") {
    $_SESSION['ubn'] = $hotspotuser;
    $_SESSION['hua'] = "";
    include_once('./hotspot/userbyname.php');
  } elseif ($hotspotuser != "") {
    $_SESSION['ubn'] = $hotspotuser;
    include_once('./hotspot/userbyname.php');
  }

// remove hotspot user
  elseif ($removehotspotuser != "" || $removehotspotusers != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuser.php');
  }

// remove hotspot user by comment
  elseif ($removehotspotuserbycomment != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehotspotuserbycomment.php');
  }

// remove expired hotspot user
elseif ($removeexpiredhotspotuser != "") {
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

  include_once('./process/removeexpiredhotspotuser.php');
}  

// reset hotspot user
  elseif ($resethotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/resethotspotuser.php');
  }

// enable hotspot user
  elseif ($enablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/enablehotspotuser.php');
  }

// disable hotspot user
  elseif ($disablehotspotuser != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/disablehotspotuser.php');
  }

// user profile
  elseif ($hotspot == "user-profiles") {
    include_once('./hotspot/userprofile.php');
  }

// add  user profile
  elseif ($userprofile == "add") {
    include_once('./hotspot/adduserprofile.php');
  }

// User profile by name
  elseif (substr($userprofile, 0, 1) == "*") {
    include_once('./hotspot/userprofilebyname.php');
  } elseif ($userprofile != "") {
    include_once('./hotspot/userprofilebyname.php');
  }


// remove user profile
  elseif ($removeuserprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuserprofile.php');
  }

// hotspot active
  elseif ($hotspot == "active") {
    $_SESSION['ubp'] = "";
    $_SESSION['hua'] = "hotspotactive";
    $_SESSION['ubc'] = "";
    include_once('./hotspot/hotspotactive.php');
  }

// dhcp leases
  elseif ($hotspot == "dhcp-leases") {
    include_once('./dhcp/dhcpleases.php');
  }

// traffic monitor
  elseif ($minterface == "traffic-monitor") {
  include_once('./traffic/trafficmonitor.php');
}

// hotspot hosts
  elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta") {
    include_once('./hotspot/hosts.php');
  }

// hotspot bindings
  elseif ($hotspot == "binding") {
    include_once('./hotspot/binding.php');
  }

// template editor
  elseif ($hotspot == "template-editor") {
    include_once('./settings/vouchereditor.php');
  }

// upload logo
  elseif ($hotspot == "uplogo") {
    include_once('./settings/uplogo.php');
  }

// hotspot Cookies
  elseif ($hotspot == "cookies") {
    include_once('./hotspot/cookies.php');
  }

// remove hotspot Cookies
  elseif ($removecookie != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removecookie.php');
  }

// hotspot Ip Bindings
  elseif ($hotspot == "ipbinding") {
    include_once('./hotspot/ipbinding.php');
  }

// remove enable disable ipbinding
  elseif ($removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pipbinding.php');
  }


// remove user active
  elseif ($removeuseractive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removeuseractive.php');
  }

// remove host
  elseif ($removehost != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removehost.php');
  }


// makebinding
  elseif ($macbinding != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/makebinding.php');
  }

// selling
  elseif ($report == "selling") {
    include_once('./report/selling.php');
  }

// selling
elseif ($report == "resume-report") {
  include_once('./report/resumereport.php');
}

// selling
elseif ($report == "export") {
  include_once('./report/export.php');
}

// selling
  elseif ($removereport != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removereport.php');
  }

// ppp secret
  elseif ($ppp == "secrets") {
    include_once('./ppp/pppsecrets.php');
  }

// ppp addsecret
  elseif ($ppp == "addsecret") {
    include_once('./ppp/addsecret.php');
  }

// ppp invoice
  elseif ($ppp == "invoice" && $secretbyname != "") {
    include_once('./ppp/invoice.php');
  }

// ppp secretbyname
  elseif ($secretbyname != "") {
    include_once('./ppp/secretbyname.php');
  }

// remove enable disable secret
  elseif ($removesecr != "" || $enablesecr != "" || $disablesecr != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/psecret.php');
  }


// ppp profile
  elseif ($ppp == "profiles") {
    include_once('./ppp/pppprofile.php');
  }

// add ppp profile
  elseif ($ppp == "add-profile") {
    include_once('./ppp/addpppprofile.php');
  }

// add ppp profile
elseif ($ppp == "edit-profile") {
  include_once('./ppp/profilebyname.php');
}
// remove enable disable profile
  elseif ($removepprofile != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepprofile.php');
  }

// ppp active connection
  elseif ($ppp == "active") {
    include_once('./ppp/pppactive.php');
  }

// remove ppp active connection
  elseif ($removepactive != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/removepactive.php');
  }

// sys scheduler
  elseif ($sys == "scheduler") {
    include_once('./system/scheduler.php');
  }
// router script generator
  elseif ($sys == "script-generator") {
    include_once('./system/scriptgenerator.php');
  }
// remove enable disable scheduler
  elseif ($removesch != "" || $enablesch != "" || $disablesch != "") {
    echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Processing...</b>";

    include_once('./process/pscheduler.php');
  }

  ?>

</div>
</div>
</div>
<script src="./js/highcharts/highcharts.js"></script>
<script src="./js/highcharts/themes/hc.<?= $theme; ?>.js"></script>
<script src="./js/mikhmon-ui.<?= $theme; ?>.min.js"></script>
<script src="./js/mikhmon.js?t=<?= str_replace(" ","_",date("Y-m-d H:i:s")); ?>"></script>
<script src="./js/tikras-modern.js?v=<?= @filemtime(__DIR__ . '/js/tikras-modern.js'); ?>"></script>

<?php
$urlParts = explode("/", $url);
$urlTail = end($urlParts);
/*
 * Les noms de session et de serveur viennent de la configuration et
 * atterrissent dans des URL a l'interieur de chaines JavaScript. Sans
 * encodage, un nom porteur d'un espace, d'une apostrophe ou d'un accent
 * casse la chaine et arrete d'un coup tous les rafraichissements de la page.
 */
$sessionUrl = rawurlencode($session);
$serveractiveUrl = rawurlencode($serveractive);
$serviceactiveUrl = rawurlencode($serviceactive);
/*
 * Plancher a 10 s. tikras_cfg_value rend la valeur enregistree meme vide,
 * et une valeur vide donnait un intervalle de 0 ms, soit un martelage
 * continu du routeur.
 */
$reloadMs = max(10, (int) $areload) * 1000;
if ($hotspot == "dashboard" || substr($urlTail, 0, 8) == "?session") {
  echo '<script>
    var dashboardPollers = [];
    dashboardPollers.push(tikrasPoll("#r_3", "./dashboard/aload.php?session=' . $sessionUrl . '&load=logs #r_3", ' . $reloadMs . ', true));
    dashboardPollers.push(tikrasPoll("#r_1", "./dashboard/aload.php?session=' . $sessionUrl . '&load=sysresource #r_1", ' . $reloadMs . '));
    dashboardPollers.push(tikrasPoll("#r_2", "./dashboard/aload.php?session=' . $sessionUrl . '&load=hotspot #r_2", ' . $reloadMs . '));
';
if ($livereport == "enable" || $livereport == "") {
  $sessionDate = tikras_session_get($session.'sdate', '');
  $sessionHourId = tikras_session_get($session.'idhr', '');
  if($sessionDate != $sessionHourId){
    $_SESSION[$session.'totalHr'] = "0";
    }
  /*
   * Le releve horaire est lourd et change lentement: il garde sa cadence
   * propre (~65 s) au lieu de suivre celle du tableau de bord.
   */
  echo '
    dashboardPollers.push(tikrasPoll("#r_4", "./report/livereport.php?session=' . $sessionUrl . ' #r_4", 65432, true));
 ';}
  echo '
  function cancelPage(){
    window.stop();
    tikrasStopPolling();
  }
</script>';

} elseif ($hotspot == "active" && $serveractive != "") {
  echo '<script>
  $(document).ready(function(){
    tikrasPoll("#reloadHotspotActive", "./hotspot/hotspotactive.php?server=' . $serveractiveUrl . '&session=' . $sessionUrl . '", ' . $reloadMs . ');
  })
</script>
';
} elseif ($hotspot == "active" && $serveractive == "") {
  echo '<script>
  $(document).ready(function(){
    tikrasPoll("#reloadHotspotActive", "./hotspot/hotspotactive.php?session=' . $sessionUrl . '", ' . $reloadMs . ');
  })
</script>
';
} elseif ($ppp == "active" && $serviceactive != "") {
  echo '<script>
  $(document).ready(function(){
    tikrasPoll("#reloadPPPActive", "./ppp/pppactive.php?service=' . $serviceactiveUrl . '&session=' . $sessionUrl . '", ' . $reloadMs . ');
  })
</script>
';
} elseif ($ppp == "active" && $serviceactive == "") {
  echo '<script>
  $(document).ready(function(){
    tikrasPoll("#reloadPPPActive", "./ppp/pppactive.php?session=' . $sessionUrl . '", ' . $reloadMs . ');
  })
</script>
';
} elseif ($userprofile == "add" || substr($userprofile, 0, 1) == "*" || $userprofile != "") {
  echo "<script>
  //enable disable input on ready
$(document).ready(function(){
    var exp = document.getElementById('expmode').value;
    var val = document.getElementById('validity').style;
    var vali = document.getElementById('validi');
    if (exp === 'rem' || exp === 'remc') {
      val.display= 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else if (exp === 'ntf' || exp === 'ntfc') {
      val.display = 'table-row';
      vali.type = 'text';
      $('#validi').focus();
    } else {
      val.display = 'none';
      vali.type = 'hidden';
    }
});
</script>";

} elseif (in_array($hotspot, $pagehotspot) || in_array($ppp, $pageppp) || $report == "userlog" || $sys == "scheduler") {
echo '
<script>
$(document).ready(function(){
  makeAllSortable();
  $("#filterTable:not(.tikras-data-search)").on("input", function() {
    var value = $(this).val().toLowerCase();
    $("#dataTable tbody tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
});

</script>
';
}
}
?>
</body>
</html>

