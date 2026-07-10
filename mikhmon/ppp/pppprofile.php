<?php
/*
 *  PPP Profiles page for Mikhmon.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  include_once(dirname(__FILE__) . '/helpers.php');

  $getprofile = $API->comm("/ppp/profile/print");
  $TotalReg = count($getprofile);
}
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header align-middle">
  <h3><i class="fa fa-pie-chart"></i> <?= $_ppp_profiles ?>
    &nbsp; | &nbsp; <a href="./?ppp=add-profile&session=<?= $session; ?>" title="Add PPP Profile"><i class="fa fa-plus-square"></i> <?= $_add ?></a>
    &nbsp; | &nbsp; <a href="./?ppp=secrets&profile=all&session=<?= $session; ?>" title="PPP Secrets"><i class="fa fa-key"></i> <?= $_ppp_secrets ?></a>
  </h3>
</div>
<div class="card-body">
<div class="overflow box-bordered" style="max-height: 75vh">
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th style="min-width:50px;" class="text-center"><?= $TotalReg . " " . ppp_unit($TotalReg); ?></th>
    <th class="align-middle"><?= $_name ?></th>
    <th class="align-middle">Local Address</th>
    <th class="align-middle">Remote Address</th>
    <th class="align-middle">Rate Limit</th>
    <th class="align-middle">Only One</th>
    <th class="align-middle">DNS Server</th>
    <th class="align-middle"><?= $_comment ?></th>
  </tr>
  </thead>
  <tbody>
  <?php
  for ($i = 0; $i < $TotalReg; $i++) {
    $profile = $getprofile[$i];
    $pid = ppp_v($profile, '.id');
    $pname = ppp_v($profile, 'name');
    $local = ppp_v($profile, 'local-address');
    $remote = ppp_v($profile, 'remote-address');
    $rate = ppp_v($profile, 'rate-limit');
    $onlyOne = ppp_v($profile, 'only-one');
    $dns = ppp_v($profile, 'dns-server');
    $comment = ppp_v($profile, 'comment');

    echo "<tr>";
    echo "<td style='text-align:center;'>";
    echo "<i class='fa fa-minus-square text-danger pointer' onclick=\"if(confirm('Are you sure to delete PPP profile (" . ppp_h($pname) . ")?')){loadpage('./?remove-pprofile=" . ppp_h($pid) . "&name=" . rawurlencode($pname) . "&session=" . ppp_h($session) . "')}else{}\" title='Remove " . ppp_h($pname) . "'></i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
    echo "<a title='Open PPP Secrets by profile " . ppp_h($pname) . "' href='./?ppp=secrets&profile=" . rawurlencode($pname) . "&session=" . rawurlencode($session) . "'><i class='fa fa-users'></i></a>";
    echo "</td>";
    echo "<td><a title='Edit PPP Profile " . ppp_h($pname) . "' href='./?ppp=edit-profile&name=" . rawurlencode($pname) . "&session=" . rawurlencode($session) . "'><i class='fa fa-edit'></i> " . ppp_h($pname) . "</a></td>";
    echo "<td>" . ppp_h($local) . "</td>";
    echo "<td>" . ppp_h($remote) . "</td>";
    echo "<td>" . ppp_h($rate) . "</td>";
    echo "<td>" . ppp_h($onlyOne) . "</td>";
    echo "<td>" . ppp_h($dns) . "</td>";
    echo "<td>" . ppp_h($comment) . "</td>";
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
