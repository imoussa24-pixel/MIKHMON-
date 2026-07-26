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
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

/*
 * Marqueurs de surbrillance du menu et parametres de page.
 * Chaque branche ci-dessous n'en renseigne qu'une poignee: sans valeur par
 * defaut, toutes les autres etaient lues alors qu'elles n'existaient pas,
 * ce qui remplissait le journal a chaque affichage de page.
 */
$tikrasMenuFlags = array(
  'cmenu', 'hamenu', 'hmenu', 'ibmenu', 'lmenu', 'log', 'mppp', 'pppmenu',
  'sabout', 'sactive', 'sadduser', 'sadduserprof', 'saudit', 'sbackup',
  'schmenu', 'scookies', 'seditor', 'settmenu', 'sgenuser', 'shome', 'shosts',
  'sipbind', 'slease', 'slog', 'snsettings', 'spactive', 'spprofile', 'squick',
  'sradius', 'sroaminguser', 'srouterlog', 'ssch', 'sscriptgenerator', 'swireguard',
  'ssecrets', 'sselling', 'ssesslist', 'ssett', 'ssettings', 'sstorage',
  'stickets', 'strafficmonitor', 'sulog', 'suplogo', 'suserprof',
  'suserprofiles', 'susers', 'susersbp', 'susersl', 'sysmenu', 'teditor',
  'umenu', 'uplogo', 'upmenu',
);
foreach ($tikrasMenuFlags as $tikrasMenuFlag) {
  if (!isset($$tikrasMenuFlag)) {
    $$tikrasMenuFlag = '';
  }
}

$tikrasMenuInputs = array(
  'id', 'hotspot', 'hotspotuser', 'ppp', 'sys', 'report', 'minterface',
  'session', 'router', 'userbyname', 'userbyprofile', 'userprofile',
  'userprofilebyname', 'secretbyname', 'resethotspotuser',
  'removecookie', 'removehost', 'removehotspotuser', 'removehotspotusers',
  'removehotspotuserbycomment', 'removeipbinding', 'removepactive',
  'removepprofile', 'removesch', 'removesecr', 'removeuseractive',
  'enablehotspotuser', 'enableipbinding', 'enablesch', 'enablesecr',
  'disablehotspotuser', 'disableipbinding', 'disablesch', 'disablesecr',
);
foreach ($tikrasMenuInputs as $tikrasMenuInput) {
  if (!isset($$tikrasMenuInput)) {
    $$tikrasMenuInput = '';
  }
}

$mpage = isset($mpage) ? $mpage : '';
$idleto = isset($idleto) ? $idleto : '';
$url = isset($url) ? $url : tikras_server('REQUEST_URI');
$identity = isset($identity) ? $identity : '';
$hotspotname = isset($hotspotname) ? $hotspotname : '';

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  include ('./include/version.php');

  $tikrasThemeLabels = array(
    'light' => 'Clair',
    'dark' => 'Sombre',
    'blue' => 'Clair',
  );
  $tikrasCurrentThemeLabel = isset($tikrasThemeLabels[$theme]) ? $tikrasThemeLabels[$theme] : ucfirst($theme);

  $btnmenuactive = "font-weight: bold;background-color: #f9f9f9; color: #000000";
  $tikrasMenuUrlParts = explode("/", $url);
  $tikrasMenuUrlTail = end($tikrasMenuUrlParts);
if ($hotspot == "dashboard" || substr($tikrasMenuUrlTail, 0, 8) == "?session") {
    $shome = "active";
    $mpage = $_dashboard;
  } elseif ($hotspot == "quick-print" || $hotspot == "list-quick-print") {
    $squick = "active";
    $mpage = $_quick_print;   
  } elseif ($hotspot == "users" || $userbyprofile != "" || $hotspot == "export-users" || $removehotspotuserbycomment != "" || $removehotspotuser != "" || $removehotspotusers != "" || $disablehotspotuser || $enablehotspotuser != "") {
    $susersl = "active";
    $susers = "active";
    $mpage = $_users;
    $umenu = "menu-open";
  } elseif ($hotspotuser == "add") {
    $sadduser = "active";
    $mpage = $_users;
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($hotspotuser == "generate") {
    $sgenuser = "active";
    $mpage = $_users;
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($hotspotuser == "generate-roaming") {
    $sroaminguser = "active";
    $mpage = "Tickets roaming";
    $susers = "active";
    $umenu = "menu-open";
  } elseif ($userbyname != ""  || $resethotspotuser != "") {
    $susers = "active";
    $mpage = $_users;
    $umenu = "menu-open";
  } elseif ($hotspot == "user-profiles") {
    $suserprofiles = "active";
    $suserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($hotspot == "active" || $removeuseractive != "") {
    $sactive = "active";
    $mpage = $_hotspot_active;
    $hamenu = "menu-open";
  } elseif ($hotspot == "hosts" || $hotspot == "hostp" || $hotspot == "hosta" || $removehost != "") {
    $shosts = "active";
    $mpage = $_hosts;
    $hmenu = "menu-open";
  } elseif ($hotspot == "dhcp-leases") {
    $slease = "active";
    $mpage = $_dhcp_leases;
  } elseif ($minterface == "traffic-monitor") {
    $strafficmonitor = "active";
    $mpage = $_traffic_monitor;  
  } elseif ($hotspot == "ipbinding" || $hotspot == "binding" || $removeipbinding != "" || $enableipbinding != "" || $disableipbinding != "") {
    $sipbind = "active";
    $mpage = $_ip_bindings;
    $ibmenu = "menu-open";
  } elseif ($hotspot == "template-editor") {
    $ssett = "active";
    $teditor = "active";
    $mpage = $_template_editor;
    $settmenu = "menu-open";
  } elseif ($hotspot == "uplogo") {
    $ssett = "active";
    $uplogo = "active";
    $mpage = $_upload_logo;
    $settmenu = "menu-open";
  } elseif ($hotspot == "cookies" || $removecookie != "") {
    $scookies = "active";
    $mpage = $_hotspot_cookies;
    $cmenu = "menu-open";
  } elseif ($hotspot == "log") {
    $log = "active";
    $slog = "active";
    $mpage = $_hotspot_log;
    $lmenu = "menu-open";
  } elseif ($report == "userlog") {
    $log = "active";
    $sulog = "active";
    $mpage = $_user_log;
    $lmenu = "menu-open";
  } elseif ($report == "routeros-log") {
    $log = "active";
    $srouterlog = "active";
    $mpage = "Journal RouterOS";
    $lmenu = "menu-open";
  } elseif ($ppp == "secrets" || $ppp == "addsecret" || $ppp == "invoice" || $enablesecr != "" || $disablesecr != "" || $removesecr != "" || $secretbyname != "") {
    $mppp = "active";
    $ssecrets = "active";
    $mpage = $_ppp_secrets;
    $pppmenu = "menu-open";
  } elseif ($ppp == "profiles" || $removepprofile != "" || $ppp == "add-profile" || $ppp == "edit-profile"  ) {
    $mppp = "active";
    $spprofile = "active";
    $mpage = $_ppp_profiles;
    $pppmenu = "menu-open";
  } elseif ($ppp == "active" || $removepactive != "") {
    $mppp = "active";
    $spactive = "active";
    $mpage = $_ppp_active;
    $pppmenu = "menu-open";
  } elseif ($sys == "scheduler" || $enablesch != "" || $disablesch != "" || $removesch != "") {
    $sysmenu = "active";
    $ssch = "active";
    $mpage = $_system_scheduler;
    $schmenu = "menu-open";
  } elseif ($sys == "script-generator") {
    $sysmenu = "active";
    $sscriptgenerator = "active";
    $mpage = "Générateur de scripts";
    $schmenu = "menu-open";
  } elseif ($report == "selling" || $report == "resume-report") {
    $sselling = "active";
    $mpage = $_report;
  } elseif ($userprofile == "add") {
    $suserprof = "active";
    $sadduserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($userprofilebyname != "") {
    $suserprof = "active";
    $mpage = $_user_profile;
    $upmenu = "menu-open";
  } elseif ($hotspot == "users-by-profile") {
    $susersbp = "active";
    $mpage = $_vouchers;
  } elseif ($userbyname != "") {
    $mpage = $_users;
    $susers = "active";
  } elseif ($hotspot == "about") {
    $mpage = $_about;
    $sabout = "active";
  } elseif ($id == "sessions" || $id == "remove" || $router == "new") {
    $ssesslist = "active";
    $mpage = $_admin_settings;
  } elseif ($id == "settings" && $session == "new") {
    $snsettings = "active";
    $mpage = $_add_router;
  } elseif ($id == "settings" || $id == "connect") {
    $ssettings = "active";
    $mpage = $_session_settings;
  } elseif ($id == "storage") {
    $sstorage = "active";
    $mpage = "Base locale";
  } elseif ($id == "tickets") {
    $stickets = "active";
    $mpage = "Tickets roaming";
  } elseif ($id == "radius") {
    $sradius = "active";
    $mpage = "RADIUS central";
  } elseif ($id == "wireguard") {
    $swireguard = "active";
    $mpage = "Acces de secours";
  } elseif ($id == "backup") {
    $sbackup = "active";
    $mpage = "Sauvegarde";
  } elseif ($id == "audit") {
    $saudit = "active";
    $mpage = "Journal systeme";
  } elseif ($id == "about") {
    $sabout = "active";
    $mpage = $_about;
  } elseif ($id == "uplogo") {
    $suplogo = "active";
    $mpage = $_upload_logo;
  } elseif ($id == "editor") {
    $seditor = "active";
    $mpage = $_template_editor;
  }
}

if($idleto != "disable"){
  $didleto = 'display:block;';
}else{
  $didleto = 'display:none;';
}
?>
<span style="display:none;" id="idto"><?= $idleto ;?></span>


<?php if ($id != "") { ?>

<div id="navbar" class="navbar">
  <div class="navbar-left">
    <a id="brand" class="text-center" href="javascript:void(0)">MIKHMON</a>

<a id="openNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
<a id="closeNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
<a id="cpage" class="navbar-left" href="javascript:void(0)"><?= $mpage; ?></a>
</div>
 <div class="navbar-right">
  <a id="routerList" href="./admin.php?id=sessions" title="Sélection du routeur"><i class="fa fa-server"></i></a>
  <a id="logout" href="./admin.php?id=logout" ><i class="fa fa-sign-out mr-1"></i> <?= $_logout ?></a>
  <select class="stheme ses text-right mr-t-10 pd-5">
    <option value="">Mode : <?= $tikrasCurrentThemeLabel; ?></option>
    <?php for ($i = 0; $i < count($mtheme); $i++) {
      $themeName = $mtheme[$i];
      $themeLabel = isset($tikrasThemeLabels[$themeName]) ? $tikrasThemeLabels[$themeName] : ucfirst($themeName);
      echo '<option value="'.$url.'&set-theme='.$themeName.'">'.$themeLabel.'</option>';
    }
    ?>
  </select>
  <select class="slang ses text-right mr-t-10 pd-5">
    <option> <?= $language ?></option>
    <?php 
      $fileList = glob('lang/*');
      foreach($fileList as $filename){
        if(is_file($filename)){
          $filename = substr(basename($filename), 0, -4);
          if($filename == "isocodelang"){}else{
            echo '<option value="'.$url.'&setlang=' . $filename . '">'. $isocodelang[$filename]. '</option>'; 
         }   
        }
      }
    ?>
  </select>
  <a title="Idle Timeout" style="<?= $didleto; ?>"><span style="width:70px;" class="pd-5 radius-3"><i class="fa fa-clock-o mr-1"></i>  <span class="mr-1" id="timer"></span></span></a>
</div>
</div>

<div id="sidenav" class="sidenav">
<?php if (($id == "settings" && $session == "new") || $id == "settings" && $router == "new") {
}else if ($id == "settings" || $id == "editor"|| $id == "uplogo" || $id == "connect"){
?>  
  <div class="menu text-center align-middle card-header" style="border-radius:0;"><h3 id="MikhmonSession"><?= $session; ?></h3></div>
  <a class="connect menu <?= $shome; ?>" id="<?= $session; ?>&c=settings"><i class='fa fa-tachometer'></i> <?= $_dashboard ?></a>
  <a href="./?system=script-generator&session=<?= $session; ?>" class="menu <?= $sscriptgenerator; ?>"><i class="fa fa-code"></i> Générateur de scripts</a>
  <a  href="./admin.php?id=settings&session=<?= $session; ?>" class="menu <?= $ssettings; ?>" title="TIKRAS IT Settings"><i class='fa fa-gear'></i> <?= $_session_settings ?></a>
  <a href="./admin.php?id=uplogo&session=<?= $session; ?>" class="menu <?= $suplogo; ?>"><i class="fa fa-upload "></i> <?= $_upload_logo ?></a>
  <a href="./admin.php?id=editor&template=default&session=<?= $session; ?>" class="menu <?= $seditor; ?>"><i class="fa fa-edit"></i> <?= $_template_editor ?></a>
  <div class="menu spa"></div>
<?php 
} ?>  
  <a href="./admin.php?id=sessions" class="menu <?= $ssesslist; ?>"><i class="fa fa-gear"></i> <?= $_admin_settings ?></a>
  <a href="./admin.php?id=settings&router=new-<?= rand(1111,9999) ?>" class="menu <?= $snsettings ?>"><i class="fa fa-plus"></i> <?= $_add_router ?></a>
  <a href="./admin.php?id=tickets" class="menu <?= $stickets; ?>"><i class="fa fa-ticket"></i> Tickets roaming</a>
  <a href="./admin.php?id=radius" class="menu <?= $sradius; ?>"><i class="fa fa-key"></i> RADIUS central</a>
  <a href="./admin.php?id=wireguard" class="menu <?= $swireguard; ?>"><i class="fa fa-shield"></i> Accès de secours</a>
  <a href="./admin.php?id=storage" class="menu <?= $sstorage; ?>"><i class="fa fa-database"></i> Base locale</a>
  <a href="./admin.php?id=backup" class="menu <?= $sbackup; ?>"><i class="fa fa-save"></i> Sauvegarde</a>
  <a href="./admin.php?id=audit" class="menu <?= $saudit; ?>"><i class="fa fa-history"></i> Journal systeme</a>
  <a href="./admin.php?id=about" class="menu <?= $sabout; ?>"><i class="fa fa-info-circle"></i> <?= $_about ?></a>

</div>

<script>
$(document).ready(function(){
  $(".connect").click(function(){
    notify("<?= $_connecting ?>");
    connect(this.id)
  });
  $(".stheme").change(function(){
    if (this.value === "") { return; }
    notify("<?= $_loading_theme ?>");
    stheme(this.value)
  });
  $(".slang").change(function(){
    notify("<?= $_loading ?>");
    stheme(this.value)
  });
});
</script>
<div id="notify"><div class="message"></div></div>
<div id="temp"></div>
<?php 
include('./info.php');
} else { ?>

<div id="navbar" class="navbar">
  <div class="navbar-left">
    <a id="brand" class="text-center" href="./?session=<?= $session; ?>">MIKHMON</a>

<a id="openNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
<a id="closeNav" class="navbar-hover" href="javascript:void(0)"><i class="fa fa-bars"></i></a>
<a id="cpage" class="navbar-left" href="javascript:void(0)"><?= $mpage; ?></a>
</div>
 <div class="navbar-right">
  <a id="routerList" href="./admin.php?id=sessions" title="Sélection du routeur"><i class="fa fa-server"></i></a>
  <a id="logout" href="./?hotspot=logout&session=<?= $session; ?>" ><i class="fa fa-sign-out mr-1"></i> <?= $_logout ?></a>
  <select class="stheme ses text-right mr-t-10 pd-5">
    <option value="">Mode : <?= $tikrasCurrentThemeLabel; ?></option>
    <?php for ($i = 0; $i < count($mtheme); $i++) {
      $themeName = $mtheme[$i];
      $themeLabel = isset($tikrasThemeLabels[$themeName]) ? $tikrasThemeLabels[$themeName] : ucfirst($themeName);
      echo '<option value="'.$url.'&set-theme='.$themeName.'">'.$themeLabel.'</option>';
    }
    ?>
  </select>
  <input id="routerSearch" class="router-search ses mr-t-10 pd-5" type="search" placeholder="<?= $_search ?> routeur">
  <select class="connect optfa ses router-select text-right mr-t-10 pd-5">
    <option id="MikhmonSession" value="<?= $session; ?>"><?= $hotspotname; ?></option>
      <?php
      foreach ($data as $sesname => $sessionConfig) {
        if ($sesname == "" || $sesname== "mikhmon") {
        } else {
        if($sesname == $session){
          echo '<option value="' . $sesname. '">'.$sesname. ' &#x2666;</option>';
        }else{
          echo '<option value="' . $sesname. '">'.$sesname. '</option>';
        }
        }
      }
      ?>
    
  </select>
  <a title="Idle Timeout" style="<?= $didleto; ?>"><span style="width:70px;" class="pd-5 radius-3"><i class="fa fa-clock-o mr-1"></i>  <span class="mr-1" id="timer"></span></span></a>
</div>
</div>

<div id="sidenav" class="sidenav">
  <div class="menu text-center align-middle card-header" style="border-radius:0;"><h3><?= $identity; ?></h3></div>
  <a href="./?session=<?= $session; ?>" class="menu <?= $shome; ?>"><i class="fa fa-dashboard"></i> <?= $_dashboard ?></a>
  <a href="./?system=script-generator&session=<?= $session; ?>" class="menu <?= $sscriptgenerator; ?>"><i class="fa fa-code"></i> Générateur de scripts</a>
  <!--hotspot-->
  <div class="dropdown-btn <?= $susers . $suserprof . $sactive . $shosts . $sipbind . $scookies; ?>"><i class="fa fa-wifi"></i> Hotspot
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $umenu . $upmenu . $hamenu . $hmenu . $ibmenu . $cmenu; ?>">
   <!--users--> 
  <div class="dropdown-btn <?= $susers; ?>"><i class="fa fa-users"></i> <?= $_users ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $umenu; ?>">
    <a href="./?hotspot=users&profile=all&session=<?= $session; ?>" class="<?= $susersl; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-list "></i> <?= $_user_list ?> </a>
    <a href="./?hotspot-user=add&session=<?= $session; ?>" class="<?= $sadduser; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-user-plus "></i> <?= $_add_user ?> </a>
    <a href="./?hotspot-user=generate&session=<?= $session; ?>" class="<?= $sgenuser; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-user-plus"></i> <?= $_generate ?> </a>
    <a href="./?hotspot-user=generate-roaming&session=<?= $session; ?>" class="<?= $sroaminguser; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-random"></i> Tickets roaming </a>
  </div>
  <!--profile-->
  <div class="dropdown-btn <?= $suserprof; ?>"><i class=" fa fa-pie-chart"></i>  <?= $_user_profile ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $upmenu; ?>">
    <a href="./?hotspot=user-profiles&session=<?= $session; ?>" class=" <?= $suserprofiles; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-list "></i> <?= $_user_profile_list ?> </a>
    <a href="./?user-profile=add&session=<?= $session; ?>" class=" <?= $sadduserprof; ?>"> &nbsp;&nbsp;&nbsp;<i class="fa fa-plus-square "></i> <?= $_add_user_profile ?> </a>

  </div>
  <!--active-->
  <a href="./?hotspot=active&session=<?= $session; ?>" class="menu <?= $sactive; ?>"><i class=" fa fa-wifi"></i> <?= $_hotspot_active ?></a>
  <!--hosts-->
  <a href="./?hotspot=hosts&session=<?= $session; ?>" class="menu <?= $shosts; ?>"><i class=" fa fa-laptop"></i> <?= $_hosts ?></a>
  <!--ip bindings-->
  <a href="./?hotspot=ipbinding&session=<?= $session; ?>" class="menu <?= $sipbind; ?>"><i class=" fa fa-address-book"></i> <?= $_ip_bindings ?></a>
  <!--cookies-->
   <a href="./?hotspot=cookies&session=<?= $session; ?>" class="menu <?= $scookies; ?>"><i class=" fa fa-hourglass"></i> <?= $_hotspot_cookies ?></a>
  </div>
  <!--quick print-->
  <a href="./?hotspot=quick-print&session=<?= $session; ?>" class="menu <?= $squick; ?>"> <i class="fa fa-print"></i> <?= $_quick_print ?> </a>
  <!--vouchers-->
  <a href="./?hotspot=users-by-profile&session=<?= $session; ?>" class="menu <?= $susersbp; ?>"> <i class="fa fa-ticket"></i> <?= $_vouchers ?> </a>
  <!--ppp-->
  <div class="dropdown-btn <?= $mppp; ?>"><i class="fa fa-exchange"></i> PPP
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $pppmenu; ?>">
    <a href="./?ppp=active&session=<?= $session; ?>" class="<?= $spactive; ?>"> <i class="fa fa-plug"></i> <?= $_ppp_active ?> </a>
    <a href="./?ppp=secrets&profile=all&session=<?= $session; ?>" class="<?= $ssecrets; ?>"> <i class="fa fa-key"></i> <?= $_ppp_secrets ?> </a>
    <a href="./?ppp=addsecret&session=<?= $session; ?>" class="<?= $ssecrets; ?>"> <i class="fa fa-user-plus"></i> <?= $_add ?> <?= $_ppp_secrets ?> </a>
    <a href="./?ppp=profiles&session=<?= $session; ?>" class="<?= $spprofile; ?>"> <i class="fa fa-pie-chart"></i> <?= $_ppp_profiles ?> </a>
    <a href="./?ppp=add-profile&session=<?= $session; ?>" class="<?= $spprofile; ?>"> <i class="fa fa-plus-square"></i> <?= $_add ?> <?= $_ppp_profiles ?> </a>
  </div>
   <!--log-->
  <div class="dropdown-btn <?= $log; ?>"><i class=" fa fa-align-justify"></i> <?= $_log ?>
    <i class="fa fa-caret-down"></i>
  </div>
  <div class="dropdown-container <?= $lmenu; ?>">
    <a href="./?hotspot=log&session=<?= $session; ?>" class="<?= $slog; ?>"> <i class="fa fa-wifi "></i> <?= $_hotspot_log ?> </a>
    <a href="./?report=userlog&idbl=<?= strtolower(date("M")) . date("Y"); ?>&session=<?= $session; ?>" class=" <?= $sulog; ?>"> <i class="fa fa-users "></i> <?= $_user_log ?> </a>
    <a href="./?report=routeros-log&session=<?= $session; ?>" class=" <?= $srouterlog; ?>"> <i class="fa fa-terminal "></i> Journal RouterOS </a>
  </div>
  <!--system-->
  <div class="dropdown-btn <?= $sysmenu; ?>"><i class=" fa fa-gear"></i> <?= $_system ?>
    <i class="fa fa-caret-down"></i> &nbsp;
  </div>
  <div class="dropdown-container <?= $schmenu; ?>">
    <a href="./?system=scheduler&session=<?= $session; ?>" class="<?= $ssch; ?>"> <i class="fa fa-clock-o "></i> <?= $_system_scheduler ?> </a>
    <a href="./?system=script-generator&session=<?= $session; ?>" class="<?= $sscriptgenerator; ?>"> <i class="fa fa-code "></i> Générateur de scripts </a>
    <a href="./admin.php?id=reboot&session=<?= $session; ?>" class=""> <i class="fa fa-power-off "></i> <?= $_system_reboot ?> </a>            
    <a href="./admin.php?id=shutdown&session=<?= $session; ?>" class=""> <i class="fa fa-power-off "></i> <?= $_system_off ?> </a> 
  </div>
  <!--dhcp leases-->
  <a href="./?hotspot=dhcp-leases&session=<?= $session; ?>" class="menu <?= $slease; ?>"><i class=" fa fa-sitemap"></i> <?= $_dhcp_leases ?></a>
  <!--traffic monitor-->
  <a href="./?interface=traffic-monitor&session=<?= $session; ?>" class="menu <?= $strafficmonitor; ?>"><i class=" fa fa-area-chart"></i> <?= $_traffic_monitor ?></a>
  <!--report-->
  <a href="./?report=selling&idbl=<?= date("m") . date("Y"); ?>&session=<?= $session; ?>" class="menu <?= $sselling; ?>"><i class="nav-icon fa fa-money"></i> <?= $_report ?></a>
  <!--settings-->
  <div class="dropdown-btn <?= $ssett; ?>"><i class=" fa fa-gear"></i> <?= $_settings ?> 
    <i class="fa fa-caret-down"></i> &nbsp;
  </div>
  <div class="dropdown-container <?= $settmenu; ?>">
  <a href="./admin.php?id=settings&session=<?= $session; ?>" class="menu "> <i class="fa fa-gear "></i> <?= $_session_settings ?> </a>
  <a href="./admin.php?id=sessions" class="menu "> <i class="fa fa-gear "></i> <?= $_admin_settings ?> </a>
  <a href="./admin.php?id=tickets" class="menu <?= $stickets; ?>"> <i class="fa fa-ticket "></i> Tickets roaming </a>
  <a href="./admin.php?id=radius" class="menu <?= $sradius; ?>"> <i class="fa fa-key "></i> RADIUS central </a>
  <a href="./admin.php?id=storage" class="menu <?= $sstorage; ?>"> <i class="fa fa-database "></i> Base locale </a>
  <a href="./admin.php?id=backup" class="menu <?= $sbackup; ?>"> <i class="fa fa-save "></i> Sauvegarde </a>
  <a href="./admin.php?id=audit" class="menu <?= $saudit; ?>"> <i class="fa fa-history "></i> Journal systeme </a>
  <a href="./?hotspot=uplogo&session=<?= $session; ?>" class="menu <?= $uplogo; ?>"> <i class="fa fa-upload "></i> <?= $_upload_logo ?> </a>
  <a href="./?hotspot=template-editor&template=default&session=<?= $session; ?>" class="menu <?= $teditor; ?>"> <i class="fa fa-edit "></i> <?= $_template_editor ?> </a>          
  </div>
  <!--about-->
  <a href="./?hotspot=about&session=<?= $session; ?>" class="menu <?= $sabout; ?>"><i class="fa fa-info-circle"></i> <?= $_about ?></a>

</div>
<script>
$(document).ready(function(){
  $(".connect").change(function(){
    notify("<?= $_connecting ?>");
    connect(this.value)
  });
  var routerOptions = $(".router-select option").clone();
  $("#routerSearch").on("input", function(){
    var term = $(this).val().toLowerCase();
    var current = $(".router-select").val();
    $(".router-select").empty();
    routerOptions.each(function(){
      var label = $(this).text().toLowerCase();
      var value = $(this).val().toLowerCase();
      if (term === "" || label.indexOf(term) !== -1 || value.indexOf(term) !== -1 || $(this).val() === current) {
        $(".router-select").append($(this).clone());
      }
    });
    $(".router-select").val(current);
  });
  $(".stheme").change(function(){
    if (this.value === "") { return; }
    notify("<?= $_loading_theme ?>");
    stheme(this.value)
  });
});
</script>
<div id="notify"><div class="message"></div></div>
<div id="temp"></div>
<?php 
include('./include/info.php');
} ?>

<div id="main">  
<div id="loading" class="lds-dual-ring"></div>
<?php if($hotspot == 'template-editor' || $id == 'editor'){
echo '<div class="main-container">';
}else{
  echo '<div class="main-container" style="display:none">';
}
?>
