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
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
include_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

// array color
  $color = array('1' => 'bg-blue', 'bg-indigo', 'bg-purple', 'bg-pink', 'bg-red', 'bg-yellow', 'bg-green', 'bg-teal', 'bg-cyan', 'bg-grey', 'bg-light-blue');

  if (tikras_has_post('save')) {

    $suseradm = tikras_post('useradm');
    $spassadm = encrypt(tikras_post('passadm'));
    $logobt = tikras_post('logobt');
    $qrbt = tikras_post('qrbt', 'disable');

    $updatedConfig = is_array($data) ? $data : array();
    $updatedConfig['mikhmon'] = array(
      '1' => "mikhmon<|<$suseradm",
      "mikhmon>|>$spassadm",
    );
    if (!tikras_config_write_all($updatedConfig)) {
      tikras_log('admin config save failed', array('config' => tikras_config_local_path()));
    }

    if (!tikras_quickbt_write($qrbt)) {
      tikras_log('quick print config save failed', array('config' => tikras_quickbt_local_path()));
    }
    tikras_redirect('./admin.php?id=sessions');
  }

}
?>
<script>
  function Pass(id){
    var x = document.getElementById(id);
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
    }}
</script>

<?php
$routerTotal = 0;
$routerCards = '';
foreach ($data as $value => $routerConfig) {
  if ($value == "" || $value == "mikhmon") {
    continue;
  }
  $routerTotal++;
  $routerCards .= tikras_ui_router_card(
    $value,
    tikras_cfg_value($data, $value, 4, '%', ''),
    tikras_cfg_value($data, $value, 5, '^', ''),
    tikras_cfg_value($data, $value, 6, '&', '')
  );
}
?>

<?= tikras_ui_page_header('gear', $_admin_settings, $routerTotal . ' routeur(s) configuré(s)', tikras_ui_button('./admin.php?id=settings&router=new-' . rand(1111,9999), 'plus', $_add_router, 'primary') . tikras_ui_button('./admin.php?id=sessions', 'refresh', 'Actualiser', 'muted')); ?>

        <?php
        $connectFlash = tikras_session_get('connect_flash', '');
        if ($connectFlash != '') {
          unset($_SESSION['connect_flash']);
          echo tikras_ui_alert('danger', $connectFlash);
        }
        ?>

<div class="tikras-admin-grid">
  <section class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-server"></i> <?= $_router_list ?></h3>
      <span class="tikras-version-note"><?= $routerTotal; ?> session(s)</span>
    </div>
    <div class="tikras-panel-body">
      <div class="tikras-router-tools">
        <input id="adminRouterSearch" class="form-control" type="search" placeholder="<?= $_search ?> routeur, session, DNS">
      </div>
      <div class="tikras-router-list">
        <?= $routerCards; ?>
      </div>
    </div>
  </section>

  <section class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-user-circle"></i> <?= $_admin ?></h3>
    </div>
    <div class="tikras-panel-body">
      <form class="tikras-admin-form" autocomplete="off" method="post" action="">
        <div class="tikras-field">
          <label for="useradm"><?= $_user_name ?></label>
          <input class="form-control" id="useradm" type="text" name="useradm" title="User Admin" value="<?= tikras_h($useradm); ?>" required="1"/>
        </div>
        <div class="tikras-field">
          <label for="passadm"><?= $_password ?></label>
          <div class="input-group">
            <div class="input-group-11 col-box-10">
              <input class="group-item group-item-l" id="passadm" type="password" name="passadm" title="Password Admin" value="<?= tikras_h(decrypt($passadm)); ?>" required="1"/>
            </div>
            <div class="input-group-1 col-box-2">
              <div class="group-item group-item-r pd-2p5 text-center align-middle">
                <input title="Show/Hide Password" type="checkbox" onclick="Pass('passadm')">
              </div>
            </div>
          </div>
        </div>
        <div class="tikras-field">
          <label for="qrbt"><?= $_quick_print ?> QR</label>
          <select class="form-control" id="qrbt" name="qrbt">
            <option><?= $qrbt ?></option>
            <option>enable</option>
            <option>disable</option>
          </select>
        </div>
        <div class="tikras-form-actions">
          <button class="tikras-btn tikras-btn-primary" type="submit" name="save"><i class="fa fa-save"></i><span><?= $_save ?></span></button>
          <button class="tikras-btn tikras-btn-muted" type="button" onclick="location.reload();" title="Recharger les données"><i class="fa fa-refresh"></i><span>Recharger</span></button>
        </div>
      </form>
      <div class="tikras-version-note" id="loadV">v<?= tikras_h($_SESSION['v']); ?> </div>
      <div><b id="newVer" class="text-green"></b></div>
    </div>
  </section>
</div>
<script>
  $("#adminRouterSearch").on("input", function(){
    var term = $(this).val().toLowerCase();
    $(".tikras-router-row").each(function(){
      var router = $(this).data("router");
      $(this).toggle(router.indexOf(term) !== -1);
    });
  });

  var _0x7470=["\x68\x6F\x73\x74\x6E\x61\x6D\x65","\x6C\x6F\x63\x61\x74\x69\x6F\x6E","\x2E","\x73\x70\x6C\x69\x74","\x6D\x69\x6B\x68\x6D\x6F\x6E\x2E\x6F\x6E\x6C\x69\x6E\x65","\x78\x62\x61\x6E\x2E\x78\x79\x7A","\x6C\x6F\x67\x61\x6D\x2E\x69\x64","\x6D\x69\x6E\x69\x73\x2E\x69\x64","\x69\x6E\x64\x65\x78\x4F\x66","\x3C\x73\x70\x61\x6E\x20\x3E\x3C\x69\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x77\x68\x69\x74\x65\x20\x66\x61\x20\x66\x61\x2D\x69\x6E\x66\x6F\x2D\x63\x69\x72\x63\x6C\x65\x22\x3E\x3C\x2F\x69\x3E\x20\x3C\x61\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x62\x6C\x75\x65\x22\x20\x68\x72\x65\x66\x3D\x22\x2E\x2F\x61\x64\x6D\x69\x6E\x2E\x70\x68\x70\x3F\x69\x64\x3D\x61\x62\x6F\x75\x74\x22\x3E\x43\x68\x65\x63\x6B\x20\x55\x70\x64\x61\x74\x65\x3C\x2F\x61\x3E\x3C\x2F\x73\x70\x61\x6E\x3E","\x68\x74\x6D\x6C","\x23\x6E\x65\x77\x56\x65\x72","\x68\x74\x74\x70\x73\x3A\x2F\x2F\x72\x61\x77\x2E\x67\x69\x74\x68\x75\x62\x75\x73\x65\x72\x63\x6F\x6E\x74\x65\x6E\x74\x2E\x63\x6F\x6D\x2F\x6C\x61\x6B\x73\x61\x31\x39\x2F\x6D\x69\x6B\x68\x6D\x6F\x6E\x76\x33\x2F\x6D\x61\x73\x74\x65\x72\x2F\x76\x65\x72\x73\x6F\x6E\x2E\x74\x78\x74\x3F\x74\x3D","\x72\x61\x6E\x64\x6F\x6D","\x66\x6C\x6F\x6F\x72","\x76","\x76\x65\x72\x73\x69\x6F\x6E","","\x72\x65\x70\x6C\x61\x63\x65","\x69\x6E\x6E\x65\x72\x48\x54\x4D\x4C","\x6C\x6F\x61\x64\x56","\x67\x65\x74\x45\x6C\x65\x6D\x65\x6E\x74\x42\x79\x49\x64","\x20","\x75\x70\x64\x61\x74\x65\x64","\x2D","\x4E\x65\x77\x20\x56\x65\x72\x73\x69\x6F\x6E\x20","\x3C\x62\x72\x3E\x3C\x73\x70\x61\x6E\x20\x3E\x3C\x69\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x77\x68\x69\x74\x65\x20\x66\x61\x20\x66\x61\x2D\x69\x6E\x66\x6F\x2D\x63\x69\x72\x63\x6C\x65\x22\x3E\x3C\x2F\x69\x3E\x20\x3C\x61\x20\x63\x6C\x61\x73\x73\x3D\x22\x74\x65\x78\x74\x2D\x62\x6C\x75\x65\x22\x20\x68\x72\x65\x66\x3D\x22\x2E\x2F\x61\x64\x6D\x69\x6E\x2E\x70\x68\x70\x3F\x69\x64\x3D\x61\x62\x6F\x75\x74\x22\x3E\x43\x68\x65\x63\x6B\x20\x55\x70\x64\x61\x74\x65\x3C\x2F\x61\x3E\x3C\x2F\x73\x70\x61\x6E\x3E","\x67\x65\x74\x4A\x53\x4F\x4E"];var hname=window[_0x7470[1]][_0x7470[0]];var dom=hname[_0x7470[3]](_0x7470[2])[1]+ _0x7470[2]+ hname[_0x7470[3]](_0x7470[2])[2];var domArray=[_0x7470[4],_0x7470[5],_0x7470[6],_0x7470[7]];var a=domArray[_0x7470[8]](hname);var b=domArray[_0x7470[8]](dom);if(dom== _0x7470[4]){$(_0x7470[11])[_0x7470[10]](_0x7470[9])}else {if(a> 0|| b> 0){}else {$[_0x7470[27]](_0x7470[12]+ (Math[_0x7470[14]]((Math[_0x7470[13]]()* 999999999)+ 1))* 128,function(_0xc1b4x6){getNewVer= (_0xc1b4x6[_0x7470[16]])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4x7=parseInt(getNewVer[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4x8=document[_0x7470[21]](_0x7470[20])[_0x7470[19]];var _0xc1b4x9=(_0xc1b4x8[_0x7470[3]](_0x7470[22])[0])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4xa=parseInt(_0xc1b4x9[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4xb=(_0xc1b4x7- _0xc1b4xa);getNewVer= (_0xc1b4x6[_0x7470[16]])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4x7=parseInt(getNewVer[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4x8=document[_0x7470[21]](_0x7470[20])[_0x7470[19]];var _0xc1b4x9=(_0xc1b4x8[_0x7470[3]](_0x7470[22])[0])[_0x7470[3]](_0x7470[15])[1];var _0xc1b4xa=parseInt(_0xc1b4x9[_0x7470[18]](_0x7470[2],_0x7470[17]));var _0xc1b4xb=(_0xc1b4x7- _0xc1b4xa);getNewD= (_0xc1b4x6[_0x7470[23]])[_0x7470[3]](_0x7470[22])[0];newD= parseInt((getNewD)[_0x7470[3]](_0x7470[24])[2]+ (getNewD)[_0x7470[3]](_0x7470[24])[0]+ (getNewD)[_0x7470[3]](_0x7470[24])[1]);var _0xc1b4xc=parseInt((_0xc1b4x8[_0x7470[3]](_0x7470[22])[1])[_0x7470[3]](_0x7470[24])[2]+ (_0xc1b4x8[_0x7470[3]](_0x7470[22])[1])[_0x7470[3]](_0x7470[24])[0]+ (_0xc1b4x8[_0x7470[3]](_0x7470[22])[1][_0x7470[3]](_0x7470[24]))[1]);var _0xc1b4xd=(newD- _0xc1b4xc);if(_0xc1b4xb> 0|| _0xc1b4xd> 0){$(_0x7470[11])[_0x7470[10]](_0x7470[25]+ _0xc1b4x6[_0x7470[16]]+ _0x7470[22]+ _0xc1b4x6[_0x7470[23]]+ _0x7470[26])}})}}
</script>









