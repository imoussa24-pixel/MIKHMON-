<?php
/*
 *  Add PPP Profile page for Mikhmon.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  include_once(dirname(__FILE__) . '/helpers.php');

  if (tikras_has_post('save')) {
    $name = tikras_post('name');
    $local = tikras_post('local');
    $remote = tikras_post('remote');
    $rate = tikras_post('rate');
    $onlyOne = tikras_post('onlyone');
    $dns = tikras_post('dns');
    $comment = tikras_post('comment');

    $params = array(
      "name" => "$name",
      "only-one" => "$onlyOne",
      "comment" => "$comment",
    );

    if ($local != "") {
      $params["local-address"] = "$local";
    }
    if ($remote != "") {
      $params["remote-address"] = "$remote";
    }
    if ($rate != "") {
      $params["rate-limit"] = "$rate";
    }
    if ($dns != "") {
      $params["dns-server"] = "$dns";
    }

    $API->comm("/ppp/profile/add", $params);
    tikras_redirect('./?ppp=profiles&session=' . $session);
  }
}
?>

<div class="row">
<div class="col-8">
<div class="card">
<div class="card-header">
  <h3><i class="fa fa-plus-square"></i> <?= $_add ?> <?= $_ppp_profiles ?></h3>
</div>
<div class="card-body">
<form autocomplete="off" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?ppp=profiles&session=<?= $session; ?>"><i class="fa fa-close"></i> <?= $_close ?></a>
    <button type="submit" name="save" class="btn bg-primary"><i class="fa fa-save"></i> <?= $_save ?></button>
  </div>
  <table class="table">
    <tr>
      <td class="align-middle"><?= $_name ?></td>
      <td><input class="form-control" type="text" autocomplete="off" name="name" required="1" autofocus></td>
    </tr>
    <tr>
      <td class="align-middle">Local Address</td>
      <td><input class="form-control" type="text" autocomplete="off" name="local" placeholder="Optional"></td>
    </tr>
    <tr>
      <td class="align-middle">Remote Address</td>
      <td><input class="form-control" type="text" autocomplete="off" name="remote" placeholder="IP or pool"></td>
    </tr>
    <tr>
      <td class="align-middle">Rate Limit</td>
      <td><input class="form-control" type="text" autocomplete="off" name="rate" placeholder="2M/2M"></td>
    </tr>
    <tr>
      <td class="align-middle">Only One</td>
      <td>
        <select class="form-control" name="onlyone">
          <?= ppp_only_one_options('default'); ?>
        </select>
      </td>
    </tr>
    <tr>
      <td class="align-middle">DNS Server</td>
      <td><input class="form-control" type="text" autocomplete="off" name="dns" placeholder="8.8.8.8,1.1.1.1"></td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_comment ?></td>
      <td><input class="form-control" type="text" autocomplete="off" name="comment"></td>
    </tr>
  </table>
</form>
</div>
</div>
</div>
<div class="col-4">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-book"></i> PPP Profile</h3>
    </div>
    <div class="card-body">
      <p>PPP Profiles centralize speed limits, local and remote address assignment, DNS and one-session policy for PPP secrets.</p>
      <p>Rate Limit example: <b>2M/2M</b> or <b>512k/1M</b>.</p>
    </div>
  </div>
</div>
</div>
