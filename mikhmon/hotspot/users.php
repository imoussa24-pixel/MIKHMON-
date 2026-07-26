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
tikras_bootstrap_errors(false);
ini_set('max_execution_time', 300);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  if ($prof == "all") {
    $getuser = $API->comm("/ip/hotspot/user/print");
    $TotalReg = count($getuser);

    $counttuser = $API->comm("/ip/hotspot/user/print", array(
      "count-only" => ""
    ));

  } elseif ($prof != "all") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?profile" => "$prof",
    ));
    $TotalReg = count($getuser);

    $counttuser = $API->comm("/ip/hotspot/user/print", array(
      "count-only" => "",
      "?profile" => "$prof",
    ));

  }
  if ($comm != "") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?comment" => "$comm",
    //"?uptime" => "00:00:00"
    ));
    $TotalReg = count($getuser);

    $counttuser = $API->comm("/ip/hotspot/user/print", array(
      "count-only" => "",
      "?comment" => "$comm",
    ));
    
  }
  $exp = tikras_get('exp');
  if ($exp != "") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?limit-uptime" => "1s",
    ));
    
    $counttuser = $API->comm("/ip/hotspot/user/print", array(
      "count-only" => "",
      "?limit-uptime" => "1s",
    ));
    
  }
  $getprofile = $API->comm("/ip/hotspot/user/profile/print");
  $TotalReg2 = count($getprofile);

  if ($counttuser == 0 && ($prof != "all" || $comm != "" || $exp != "")) {
    tikras_redirect("./?hotspot=users&profile=all&session=" . urlencode($session));
  }
}
?>

<?php
$usersHeaderActions = tikras_ui_button('./?hotspot-user=add&session=' . rawurlencode($session), 'user-plus', $_add, 'primary')
  . tikras_ui_button('./?hotspot-user=generate&session=' . rawurlencode($session), 'ticket', $_generate, 'muted')
  . tikras_ui_button(str_replace("=users", "=export-users", $url) . '&export=script', 'download', 'Script', 'muted')
  . tikras_ui_button(str_replace("=users", "=export-users", $url) . '&export=csv', 'download', 'CSV', 'muted');
$usersSubtitle = $counttuser . ' utilisateur(s) Hotspot';
if ($prof != '' && $prof != 'all') {
  $usersSubtitle .= ' | Profil ' . $prof;
}
if ($comm != '') {
  $usersSubtitle .= ' | Commentaire ' . $comm;
}
if ($exp != '') {
  $usersSubtitle .= ' | Expires';
}
echo tikras_ui_page_header('users', $_users, $usersSubtitle, $usersHeaderActions);
?>

<div class="row tikras-data-page">
<div class="col-12">
<div class="card tikras-data-card">
<div class="card-header">
    <h3><i class="fa fa-users"></i> <?= $_users ?>
      <span class="tikras-data-count" id="hotspotUsersVisible"><?= $counttuser; ?></span>
        <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small>
    </h3>
    
</div>
<div class="card-body">
  <div class="tikras-data-toolbar">
   <div>
  <div class="tikras-data-filters">
    <div class="input-group-4 col-box-4">
      <input id="filterTable" type="search" style="padding:5.8px;" class="group-item group-item-l tikras-data-search" data-table="#dataTable" data-counter="#hotspotUsersVisible" placeholder="<?= $_search ?>">
    </div>
    <div class="input-group-4 col-box-4">
      <select style="padding:5px;" class="group-item group-item-m" onchange="location = this.value; loader()" title="Filter by Profile">
        <option><?= $_profile ?> </option>
        <option value="./?hotspot=users&profile=all&session=<?= $session; ?>"><?= $_show_all ?></option>
      <?php
      for ($i = 0; $i < $TotalReg2; $i++) {
        $profile = $getprofile[$i];
        $profileName = tikras_array_get($profile, 'name', '');
        echo "<option value='./?hotspot=users&profile=" . $profileName . "&session=" . $session . "'>" . $profileName . "</option>";
      }
      ?>
    </select>
  </div>
  <div class="input-group-4 col-box-4">
    <select style="padding:5px;" class="group-item group-item-r" id="comment" name="comment" onchange="location = './?hotspot=users&comment='+ this.value +'&session=<?= $session;?>';">
    <?php
    if ($comm != "") {
    } else {
      echo "<option value=''>".$_comment."</option>";
    }
    $TotalReg = count($getuser);
    $acomment = "";
    for ($i = 0; $i < $TotalReg; $i++) {
      $ucomment = tikras_array_get($getuser[$i], 'comment', '');
      $uprofile = tikras_array_get($getuser[$i], 'profile', '');
      $acomment .= ",".$ucomment."#". $uprofile;
    }

    $ocomment=  explode(",",$acomment);
    
    $comments=array_count_values($ocomment) ;
    foreach ($comments as $tcomment=>$value) {

      if (is_numeric(substr($tcomment, 3, 3))) {
        $commentParts = explode("#", $tcomment, 2);
        $commentValue = tikras_array_get($commentParts, 0, '');
        $commentProfile = tikras_array_get($commentParts, 1, '');
        echo "<option value='" . $commentValue . "' >". $commentValue." ".$commentProfile. " [".$value. "]</option>";
       }
 
    }

    ?>
    </select>
  </div>
  </div>
  </div>
 
  <div class="tikras-data-actions">
    <?php if ($comm != "") { ?>
  <button class="btn bg-red" onclick="if(confirm('Are you sure to delete username by comment (<?= $comm; ?>)?')){loadpage('./?remove-hotspot-user-by-comment=<?= $comm; ?>&session=<?= $session; ?>');loader();}else{}" title="Remove user by comment <?= $comm; ?>">  <i class="fa fa-trash"></i> <?= $_by_comment ?></button>
    <?php ; }else if ($exp == "1"){ ?>
  <button class="btn bg-red" onclick="if(confirm('Are you sure to delete users?')){loadpage('./?remove-hotspot-user-expired=1&session=<?= $session; ?>');loader();}else{}" title="Remove user expired">  <i class="fa fa-trash"></i> Expired Users</button>
      <?php } ?>
  <script>
    function printV(a,b){
    var comm = document.getElementById('comment').value;
    var url = "./voucher/print.php?id="+comm+"&"+a+"="+b+"&session=<?= $session; ?>";
    if (comm === "" ){
      <?php if (in_array($currency, $cekindo['indo'])) { ?>
      alert('Silakan pilih salah satu Comment terlebih dulu!');
      <?php
    } else { ?>
      alert('Please choose one of the Comments first!');
      <?php
    } ?>
    }else{
      var win = window.open(url, '_blank');
      win.focus();
    }}
  </script>
  <button class="btn bg-primary" title='Print' onclick="printV('qr','no');"><i class="fa fa-print"></i> <?= $_print_default ?></button>
  <button class="btn bg-primary" title='Print QR' onclick="printV('qr','yes');"><i class="fa fa-print"></i> <?= $_print_qr ?></button>
  <button class="btn bg-primary" title='Print Small'onclick="printV('small','yes');"><i class="fa fa-print"></i> <?= $_print_small ?></button>
  </div>
</div>
<div class="overflow mr-t-10 box-bordered tikras-data-table-wrap">
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th style="min-width:50px;" class="align-middle text-center" id="cuser"><?= $counttuser; ?></th>
    <th style="min-width:50px;" class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Server</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
    <th>Print</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_profile ?></th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Mac Address</th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_uptime_user ?></th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> Bytes In</th>
    <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> Bytes Out</th>
    <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
    </tr>
  </thead>
  <tbody id="tbody">
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  // L'API MikroTik omet les champs vides: on lit chaque cle avec un defaut.
  $userdetails = $getuser[$i];
  $uid = tikras_array_get($userdetails, '.id', '');
  $userver = tikras_array_get($userdetails, 'server', '');
  $uname = tikras_array_get($userdetails, 'name', '');
  $upass = tikras_array_get($userdetails, 'password', '');
  $uprofile = tikras_array_get($userdetails, 'profile', '');
  $umacadd = tikras_array_get($userdetails, 'mac-address', '');
  $uuptime = formatDTM(tikras_array_get($userdetails, 'uptime', '0s'));
  $ubytesi = formatBytes(tikras_array_get($userdetails, 'bytes-in', 0), 2);
  $ubyteso = formatBytes(tikras_array_get($userdetails, 'bytes-out', 0), 2);

  $ucomment = tikras_array_get($userdetails, 'comment', '');
  $udisabled = tikras_array_get($userdetails, 'disabled', 'false');
  $utimelimit = tikras_array_get($userdetails, 'limit-uptime', '');
  if ($utimelimit == '1s') {
    $utimelimit = ' expired';
  } else {
    $utimelimit = ' ' . $utimelimit;
  }
  $udatalimit = tikras_array_get($userdetails, 'limit-bytes-total', '');
  if ($udatalimit == '') {
    $udatalimit = '';
  } else {
    $udatalimit = ' ' . formatBytes($udatalimit, 2);
  }

  echo "<tr>";
  ?>
  <?php $u_confirm = "if(confirm('Are you sure to delete username (" . tikras_js_string($uname) . ")?')){loadpage('./?remove-hotspot-user=" . rawurlencode($uid) . "&session=" . rawurlencode($session) . "')}else{}"; ?>
  <td style='text-align:center;'>  <i class='fa fa-minus-square text-danger pointer' onclick="<?= tikras_h($u_confirm) ?>" title="Remove <?= tikras_h($uname) ?>"></i>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp
  <?php
  if ($udisabled == "true") {
    $uriprocess = "'./?enable-hotspot-user=" . rawurlencode($uid) . "&session=" . rawurlencode($session) . "'";
    echo '<span class="text-warning pointer" title="Enable User ' . tikras_h($uname) . '"  onclick="' . tikras_h('loadpage(' . $uriprocess . ')') . '"><i class="fa fa-lock "></i></span></td>';
  } else {
    $uriprocess = "'./?disable-hotspot-user=" . rawurlencode($uid) . "&session=" . rawurlencode($session) . "'";
    echo '<span class="pointer" title="Disable User ' . tikras_h($uname) . '"  onclick="' . tikras_h('loadpage(' . $uriprocess . ')') . '"><i class="fa fa-unlock "></i></span></td>';
  }
  echo "<td>" . tikras_h($userver) . "</td>";
  if ($uname == $upass) {
    $usermode = "vc";
  } else {
    $usermode = "up";
  }
  $popup = "javascript:window.open('./voucher/print.php?user=" . tikras_js_string($usermode . "-" . $uname) . "&qr=no&session=" . tikras_js_string($session) . "','_blank','width=320,height=550').print();";
  $popupQR = "javascript:window.open('./voucher/print.php?user=" . tikras_js_string($usermode . "-" . $uname) . "&qr=yes&session=" . tikras_js_string($session) . "','_blank','width=320,height=550').print();";
  echo "<td><a title='Open User " . tikras_h($uname) . "' href=./?hotspot-user=" . rawurlencode($uid) . "&session=" . rawurlencode($session) . "><i class='fa fa-edit'></i> " . tikras_h($uname) . " </a>";
  echo '</td><td class"text-center"><a title="Print ' . tikras_h($uname) . '" href="' . tikras_h($popup) . '"><i class="fa fa-print"></i></a> &nbsp <a title="Print ' . tikras_h($uname) . '" href="' . tikras_h($popupQR) . '"><i class="fa fa-qrcode"></i> </a></td>';
  echo "<td>" . tikras_h($uprofile) . "</td>";
  echo "<td style=' text-align:left'>" . tikras_h($umacadd) . "</td>";
  echo "<td style=' text-align:right'>" . $uuptime . "</td>";
  echo "<td style=' text-align:right'>" . $ubytesi . "</td>";
  echo "<td style=' text-align:right'>" . $ubyteso . "</td>";
  echo "<td>";
  if ($uname == "default-trial") {
  } else if (substr($ucomment,0,3) == "vc-" || substr($ucomment,0,3) == "up-") {
    echo "<a href=./?hotspot=users&comment=" . rawurlencode($ucomment) . "&session=" . rawurlencode($session) . " title='Filter by " . tikras_h($ucomment) . "'><i class='fa fa-search'></i> ". tikras_h($ucomment)." ". tikras_h($udatalimit) ." ".tikras_h($utimelimit) . "</a>";
  } else if ($utimelimit == ' expired') {
    echo "<a href=./?hotspot=users&profile=all&exp=1&session=" . rawurlencode($session) . " title='Filter by expired'><i class='fa fa-search'></i> " . tikras_h($ucomment)." ". tikras_h($udatalimit) ." ".tikras_h($utimelimit) . "</a>";
  }else{
    echo tikras_h($ucomment).' ';
  }
  echo  "</td>";


}
?>
  </tr>
  </tbody>
</table>
</div>
</div>
</div>
</div>
</div>

	
	
