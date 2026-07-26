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
  'theme' => '',
  'themecolor' => '',
  'connect' => '',
));

tikras_start_gzip();

// check url
$url = tikras_server('REQUEST_URI');

// load session MikroTik
$session = tikras_get('session');
$id = tikras_get('id');
$c = tikras_get('c');
$router = tikras_get('router');
$logo = tikras_get('logo');

$ids = array(
  "editor",
  "uplogo",
  "settings",
);

// lang
include('./lang/isocodelang.php');
include('./include/lang.php');
include('./lang/'.$langid.'.php');

// quick bt
include('./include/quickbt.php');

// theme
include('./include/theme.php');
include('./settings/settheme.php');
include('./settings/setlang.php');
if ($_SESSION['theme'] == "") {
    $theme = $theme;
    $themecolor = $themecolor;
  } else {
    $theme = $_SESSION['theme'];
    $themecolor = $_SESSION['themecolor'];
}


// load config
include_once('./include/headhtml.php');
include('./include/config.php');
include('./include/readcfg.php');

include_once('./lib/tikras_routeros.php');
include_once('./lib/tikras_storage.php');
include_once('./lib/formatbytesbites.php');

$tikrasLoginRequest = ($id == "login" || preg_match('/(?:\?|&)p(?:=1)?$/', $url));
?>
    
<?php
if ($tikrasLoginRequest) {

  if (tikras_has_post('login')) {
    $retryAfter = tikras_login_throttle();
    if ($retryAfter > 0) {
      $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger"><i class="fa fa-ban"></i> Alert!<br>Trop de tentatives. Réessayez dans ' . (int) $retryAfter . ' s.</div>';
    } else {
      $user = tikras_post('user');
      $pass = tikras_post('pass');
      // Le compte enregistre dans la configuration fait foi. Les variables
      // d'environnement ne servent qu'a ouvrir un serveur neuf, tant qu'aucun
      // mot de passe n'a ete defini: sans cela, un changement fait depuis
      // l'interface resterait sans effet.
      $configUser = (string) $useradm;
      $configPass = (string) decrypt($passadm);
      if ($configPass !== '') {
        $effectiveUser = $configUser;
        $effectivePass = $configPass;
      } else {
        $envAdminUser = getenv('TIKRAS_ADMIN_USER');
        $envAdminPass = getenv('TIKRAS_ADMIN_PASS');
        $effectiveUser = ($envAdminUser !== false && $envAdminUser !== '') ? (string) $envAdminUser : $configUser;
        $effectivePass = ($envAdminPass !== false && $envAdminPass !== '') ? (string) $envAdminPass : '';
      }
      // Constant-time credential check (avoids type juggling + timing leaks).
      $userOk = hash_equals($effectiveUser, (string) $user);
      $passOk = ($effectivePass !== '') && hash_equals($effectivePass, (string) $pass);
      if ($userOk && $passOk) {
        tikras_login_throttle('success');
        tikras_session_regenerate();
        $_SESSION["mikhmon"] = $effectiveUser;
        tikras_redirect('./admin.php?id=sessions');
      } else {
        tikras_login_throttle('failure');
        $error = '<div style="width: 100%; padding:5px 0px 5px 0px; border-radius:5px;" class="bg-danger"><i class="fa fa-ban"></i> Alert!<br>Invalid username or password.</div>';
      }
    }
  }
  

  include_once('./include/login.php');
} elseif (!isset($_SESSION["mikhmon"])) {
  tikras_redirect('./admin.php?id=login');
} elseif (substr($url, -1) == "/" || substr($url, -4) == ".php") {
  tikras_redirect('./admin.php?id=sessions');

} elseif ($id == "sessions") {
  $_SESSION["connect"] = "";
  include_once('./include/menu.php');
  include_once('./settings/sessions.php');
  /*echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';*/
} elseif ($id == "settings" && !empty($session) || $id == "settings" && !empty($router)) {
  include_once('./include/menu.php');
  include_once('./settings/settings.php');
  echo '
  <script type="text/javascript">
    document.getElementById("sessname").onkeypress = function(e) {
    var chr = String.fromCharCode(e.which);
    if (" _!@#$%^&*()+=;|?,~".indexOf(chr) >= 0)
        return false;
    };
    </script>';
} elseif ($id == "connect"  && !empty($session)) {
  ini_set("max_execution_time",20);  
  $API = tikras_routeros_create();
  if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session, array('force' => true))){
    $_SESSION["connect"] = "<b class='text-green'>Connected</b>";
    tikras_redirect('./?session=' . $session);
  } else {
    $_SESSION["connect"] = "<b class='text-red'>Not Connected</b>";
    $_SESSION["connect_flash"] = "Routeur non connecte. Verifiez IP, utilisateur, mot de passe, port API 8728 et VPN.";
    if($c == "settings"){
      tikras_redirect('./admin.php?id=settings&session=' . $session);
    }else{
      tikras_redirect('./admin.php?id=sessions');
    }
  }
} elseif ($id == "uplogo"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/uplogo.php');
} elseif ($id == "reboot"  && !empty($session)) {
  include_once('./process/reboot.php');
} elseif ($id == "shutdown"  && !empty($session)) {
  include_once('./process/shutdown.php');
} elseif ($id == "remove-session" && $session != "" && preg_match('/^[A-Za-z0-9_.-]+$/', $session)) {
  include_once('./include/menu.php');
  $updatedConfig = is_array($data) ? $data : array();
  if (isset($updatedConfig[$session])) {
    unset($updatedConfig[$session]);
  }
  if (!tikras_config_write_all($updatedConfig)) {
    tikras_log('router config remove failed', array('session' => $session, 'config' => tikras_config_local_path()));
  }
  tikras_redirect('./admin.php?id=sessions');
} elseif ($id == "storage") {
  include_once('./include/menu.php');
  include_once('./settings/storage.php');
} elseif ($id == "tickets") {
  include_once('./include/menu.php');
  include_once('./settings/tickets.php');
} elseif ($id == "radius") {
  include_once('./include/menu.php');
  include_once('./settings/radius.php');
} elseif ($id == "wireguard") {
  include_once('./include/menu.php');
  include_once('./settings/wireguard.php');
} elseif ($id == "radius-serveur") {
  include_once('./include/menu.php');
  include_once('./settings/radius-serveur.php');
} elseif ($id == "planning") {
  include_once('./include/menu.php');
  include_once('./settings/planning.php');
} elseif ($id == "backup") {
  include_once('./include/menu.php');
  include_once('./settings/backup.php');
} elseif ($id == "audit") {
  include_once('./include/menu.php');
  include_once('./settings/audit.php');
} elseif ($id == "about") {
  include_once('./include/menu.php');
  include_once('./include/about.php');
} elseif ($id == "logout") {
  include_once('./include/menu.php');
  echo "<b class='cl-w'><i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i> Logout...</b>";
  session_destroy();
  tikras_redirect('./admin.php?id=login');
} elseif ($id == "remove-logo" && $logo != ""  && !empty($session)) {
  include_once('./include/menu.php');
  $logopath = "./img/";
  $logo = basename($logo);
  $remlogo = $logopath . $logo;
  if (preg_match('/\.(png|jpg|jpeg|gif|ico)$/i', $logo) && is_file($remlogo)) {
    unlink($remlogo);
  }
  tikras_redirect('./admin.php?id=uplogo&session=' . $session);
} elseif ($id == "editor"  && !empty($session)) {
  include_once('./include/menu.php');
  include_once('./settings/vouchereditor.php');
} elseif (empty($id)) {
  tikras_redirect('./admin.php?id=sessions');
} elseif(in_array($id, $ids) && empty($session)){
	tikras_redirect('./admin.php?id=sessions');
}
?>
<?php if (!$tikrasLoginRequest) { ?>
<script src="js/mikhmon-ui.<?= $theme; ?>.min.js"></script>
<script src="js/mikhmon.js?t=<?= str_replace(" ","_",date("Y-m-d H:i:s")); ?>"></script>
<script src="js/tikras-modern.js?v=<?= @filemtime(__DIR__ . '/js/tikras-modern.js'); ?>"></script>
<?php } ?>
<?php include('./include/info.php'); ?>
</body>
</html>
