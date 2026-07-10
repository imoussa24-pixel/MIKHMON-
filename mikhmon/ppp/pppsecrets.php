<?php
/*
 *  PPP Secrets page for Mikhmon.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  include_once(dirname(__FILE__) . '/helpers.php');

  $profileFilter = tikras_get('profile');
  $commentFilter = tikras_get('comment');
  $disabledFilter = tikras_get('disabled');

  $getprofile = $API->comm("/ppp/profile/print");
  $getsecret = $API->comm("/ppp/secret/print");

  $filteredSecrets = array();
  for ($i = 0; $i < count($getsecret); $i++) {
    $secret = $getsecret[$i];
    $profile = ppp_v($secret, 'profile');
    $comment = ppp_v($secret, 'comment');
    $disabled = ppp_v($secret, 'disabled');

    if ($profileFilter != '' && $profileFilter != 'all' && $profile != $profileFilter) {
      continue;
    }
    if ($commentFilter != '' && stripos($comment, $commentFilter) === false) {
      continue;
    }
    if ($disabledFilter != '' && $disabled != $disabledFilter) {
      continue;
    }

    $filteredSecrets[] = $secret;
  }

  $TotalReg = count($filteredSecrets);
  $counttuser = $TotalReg;
}
?>

<?php
$pppSubtitle = $counttuser . ' secret(s) PPP';
if ($profileFilter != '' && $profileFilter != 'all') {
  $pppSubtitle .= ' | Profil ' . $profileFilter;
}
if ($disabledFilter != '') {
  $pppSubtitle .= ' | Status ' . ($disabledFilter == 'true' ? 'Disabled' : 'Enabled');
}
if ($commentFilter != '') {
  $pppSubtitle .= ' | Commentaire ' . $commentFilter;
}
$pppHeaderActions = tikras_ui_button('./?ppp=addsecret&session=' . rawurlencode($session), 'user-plus', $_add, 'primary')
  . tikras_ui_button('./?ppp=profiles&session=' . rawurlencode($session), 'pie-chart', $_ppp_profiles, 'muted')
  . tikras_ui_button('./?ppp=active&session=' . rawurlencode($session), 'plug', $_ppp_active, 'muted');
echo tikras_ui_page_header('key', $_ppp_secrets, $pppSubtitle, $pppHeaderActions);
?>

<div class="row tikras-data-page">
<div class="col-12">
<div class="card tikras-data-card">
<div class="card-header">
  <h3><i class="fa fa-key"></i> <?= $_ppp_secrets ?>
    <span class="tikras-data-count" id="pppSecretsVisible"><?= $counttuser; ?></span>
    <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small>
  </h3>
</div>
<div class="card-body">
  <div class="tikras-data-toolbar">
    <div>
      <div class="tikras-data-filters">
        <div class="input-group-3 col-box-4">
          <input id="filterTable" type="search" style="padding:5.8px;" class="group-item group-item-l tikras-data-search" data-table="#dataTable" data-counter="#pppSecretsVisible" placeholder="<?= $_search ?>">
        </div>
        <div class="input-group-3 col-box-4">
          <select style="padding:5px;" class="group-item group-item-m" onchange="location = this.value; loader()" title="Filter by Profile">
            <option><?= $_profile ?></option>
            <option value="./?ppp=secrets&profile=all&session=<?= $session; ?>"><?= $_show_all ?></option>
            <?php
            for ($i = 0; $i < count($getprofile); $i++) {
              $profile = $getprofile[$i];
              echo "<option value='./?ppp=secrets&profile=" . rawurlencode($profile['name']) . "&session=" . rawurlencode($session) . "'>" . ppp_h($profile['name']) . "</option>";
            }
            ?>
          </select>
        </div>
        <div class="input-group-3 col-box-4">
          <select style="padding:5px;" class="group-item group-item-m" onchange="location = this.value; loader()" title="Filter by Status">
            <option>Status</option>
            <option value="./?ppp=secrets&profile=<?= rawurlencode($profileFilter == '' ? 'all' : $profileFilter); ?>&session=<?= rawurlencode($session); ?>"><?= $_show_all ?></option>
            <option value="./?ppp=secrets&profile=<?= rawurlencode($profileFilter == '' ? 'all' : $profileFilter); ?>&disabled=false&session=<?= rawurlencode($session); ?>">Enabled</option>
            <option value="./?ppp=secrets&profile=<?= rawurlencode($profileFilter == '' ? 'all' : $profileFilter); ?>&disabled=true&session=<?= rawurlencode($session); ?>">Disabled</option>
          </select>
        </div>
        <div class="input-group-3 col-box-4">
          <select style="padding:5px;" class="group-item group-item-r" onchange="if(this.value !== ''){location = './?ppp=secrets&comment='+ encodeURIComponent(this.value) +'&session=<?= $session;?>';}">
            <option value=""><?= $_comment ?></option>
            <?php
            $comments = array();
            for ($i = 0; $i < count($getsecret); $i++) {
              $comment = ppp_v($getsecret[$i], 'comment');
              if ($comment != '') {
                $comments[$comment] = isset($comments[$comment]) ? $comments[$comment] + 1 : 1;
              }
            }
            foreach ($comments as $comment => $value) {
              echo "<option value='" . ppp_h($comment) . "'>" . ppp_h($comment) . " [" . $value . "]</option>";
            }
            ?>
          </select>
        </div>
      </div>
    </div>
    <div class="tikras-data-actions">
      <?php if ($commentFilter != "") { ?>
      <button class="btn bg-red" onclick="if(confirm('Are you sure to delete PPP secrets by comment (<?= ppp_h($commentFilter); ?>)?')){loadpage('./?remove-pppsecret=comment:<?= rawurlencode($commentFilter); ?>&session=<?= $session; ?>');loader();}else{}" title="Remove PPP secrets by comment <?= ppp_h($commentFilter); ?>"><i class="fa fa-trash"></i> <?= $_by_comment ?></button>
      <?php } ?>
      <a class="btn bg-primary" href="./?ppp=secrets&profile=all&session=<?= $session; ?>"><i class="fa fa-list"></i> <?= $_all ?></a>
    </div>
  </div>

  <div class="overflow mr-t-10 box-bordered tikras-data-table-wrap">
  <table id="dataTable" class="table table-bordered table-hover text-nowrap">
    <thead>
    <tr>
      <th style="min-width:50px;" class="align-middle text-center"><?= $counttuser; ?></th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Service</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_name ?></th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_profile ?></th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Local Address</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> Remote Address</th>
      <th class="text-right align-middle pointer" title="Click to sort"><i class="fa fa-sort"></i> Limit Total</th>
      <th class="pointer" title="Click to sort"><i class="fa fa-sort"></i> <?= $_comment ?></th>
    </tr>
    </thead>
    <tbody id="tbody">
    <?php
    for ($i = 0; $i < $TotalReg; $i++) {
      $secret = $filteredSecrets[$i];
      $uid = ppp_v($secret, '.id');
      $name = ppp_v($secret, 'name');
      $service = ppp_v($secret, 'service', 'any');
      $profile = ppp_v($secret, 'profile');
      $local = ppp_v($secret, 'local-address');
      $remote = ppp_v($secret, 'remote-address');
      $comment = ppp_v($secret, 'comment');
      $disabled = ppp_v($secret, 'disabled');
      $limitTotal = ppp_format_bytes_value(ppp_v($secret, 'limit-bytes-total'));

      echo "<tr>";
      echo "<td style='text-align:center;'>";
      echo "<i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Are you sure to delete PPP secret (" . ppp_h($name) . ")?')){loadpage('./?remove-pppsecret=" . ppp_h($uid) . "&session=" . ppp_h($session) . "')}else{}\" title='Remove " . ppp_h($name) . "'></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
      if ($disabled == "true") {
        echo "<span class='text-warning pointer' title='Enable PPP Secret " . ppp_h($name) . "' onclick=\"loadpage('./?enable-pppsecret=" . ppp_h($uid) . "&session=" . ppp_h($session) . "')\"><i class='fa fa-lock'></i></span>";
      } else {
        echo "<span class='pointer' title='Disable PPP Secret " . ppp_h($name) . "' onclick=\"loadpage('./?disable-pppsecret=" . ppp_h($uid) . "&session=" . ppp_h($session) . "')\"><i class='fa fa-unlock'></i></span>";
      }
      echo "&nbsp;&nbsp;&nbsp;&nbsp;<a class='text-info' title='Facture PPP " . ppp_h($name) . "' href='./?ppp=invoice&secret=" . rawurlencode($uid) . "&session=" . rawurlencode($session) . "'><i class='fa fa-file-text-o'></i></a>";
      echo "</td>";
      echo "<td>" . ppp_h($service) . "</td>";
      echo "<td><a title='Open PPP Secret " . ppp_h($name) . "' href='./?secret=" . rawurlencode($uid) . "&session=" . rawurlencode($session) . "'><i class='fa fa-edit'></i> " . ppp_h($name) . "</a></td>";
      echo "<td><a href='./?ppp=secrets&profile=" . rawurlencode($profile) . "&session=" . rawurlencode($session) . "'><i class='fa fa-search'></i> " . ppp_h($profile) . "</a></td>";
      echo "<td>" . ppp_h($local) . "</td>";
      echo "<td>" . ppp_h($remote) . "</td>";
      echo "<td style='text-align:right;'>" . ppp_h($limitTotal) . "</td>";
      echo "<td>";
      if ($comment != '') {
        echo "<a href='./?ppp=secrets&comment=" . rawurlencode($comment) . "&session=" . rawurlencode($session) . "' title='Filter by " . ppp_h($comment) . "'><i class='fa fa-search'></i> " . ppp_h($comment) . "</a>";
      }
      echo "</td>";
      echo "</tr>";
    }
    ?>
    </tbody>
  </table>
  </div>
</div>
</div>
</div>
</div>
