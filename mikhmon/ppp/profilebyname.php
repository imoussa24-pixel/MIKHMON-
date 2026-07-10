<?php
/*
 *  Edit PPP Profile page for Mikhmon.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  include_once(dirname(__FILE__) . '/helpers.php');

  $profileName = tikras_get('name');
  if (substr($profileName, 0, 1) == "*") {
    $getprofile = $API->comm("/ppp/profile/print", array("?.id" => "$profileName"));
  } else {
    $getprofile = $API->comm("/ppp/profile/print", array("?name" => "$profileName"));
  }

  $profile = $getprofile[0];
  $pid = ppp_v($profile, '.id');
  $pname = ppp_v($profile, 'name');
  $local = ppp_v($profile, 'local-address');
  $remote = ppp_v($profile, 'remote-address');
  $rate = ppp_v($profile, 'rate-limit');
  $onlyOne = ppp_v($profile, 'only-one');
  $dns = ppp_v($profile, 'dns-server');
  $comment = ppp_v($profile, 'comment');

  if (tikras_has_post('save')) {
    $name = tikras_post('name');
    $local = tikras_post('local');
    $remote = tikras_post('remote');
    $rate = tikras_post('rate');
    $onlyOne = tikras_post('onlyone');
    $dns = tikras_post('dns');
    $comment = tikras_post('comment');

    $API->comm("/ppp/profile/set", array(
      ".id" => "$pid",
      "name" => "$name",
      "local-address" => "$local",
      "remote-address" => "$remote",
      "rate-limit" => "$rate",
      "only-one" => "$onlyOne",
      "dns-server" => "$dns",
      "comment" => "$comment",
    ));
    tikras_redirect('./?ppp=edit-profile&name=' . rawurlencode($name) . '&session=' . $session);
  }
}
?>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
  <h3><i class="fa fa-edit"></i> <?= $_edit ?> <?= $_ppp_profiles ?> <?= ppp_h($pname); ?></h3>
</div>
<div class="card-body">
<form autocomplete="off" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?ppp=profiles&session=<?= $session; ?>"><i class="fa fa-close"></i> <?= $_close ?></a>
    <button type="submit" name="save" class="btn bg-primary"><i class="fa fa-save"></i> <?= $_save ?></button>
    <div class="btn bg-danger" onclick="if(confirm('Are you sure to delete PPP profile (<?= ppp_h($pname); ?>)?')){loadpage('./?remove-pprofile=<?= ppp_h($pid); ?>&name=<?= rawurlencode($pname); ?>&session=<?= $session; ?>')}else{}" title="Remove <?= ppp_h($pname); ?>"><i class="fa fa-minus-square"></i> <?= $_remove ?></div>
    <a class="btn bg-info" href="./?ppp=secrets&profile=<?= rawurlencode($pname); ?>&session=<?= $session; ?>"><i class="fa fa-users"></i> <?= $_ppp_secrets ?></a>
  </div>
  <table class="table">
    <tr>
      <td class="align-middle"><?= $_name ?></td>
      <td><input class="form-control" type="text" autocomplete="off" name="name" value="<?= ppp_h($pname); ?>" required="1"></td>
    </tr>
    <tr>
      <td class="align-middle">Local Address</td>
      <td><input class="form-control" type="text" autocomplete="off" name="local" value="<?= ppp_h($local); ?>"></td>
    </tr>
    <tr>
      <td class="align-middle">Remote Address</td>
      <td><input class="form-control" type="text" autocomplete="off" name="remote" value="<?= ppp_h($remote); ?>"></td>
    </tr>
    <tr>
      <td class="align-middle">Rate Limit</td>
      <td><input class="form-control" type="text" autocomplete="off" name="rate" value="<?= ppp_h($rate); ?>"></td>
    </tr>
    <tr>
      <td class="align-middle">Only One</td>
      <td>
        <select class="form-control" name="onlyone">
          <?= ppp_only_one_options($onlyOne); ?>
        </select>
      </td>
    </tr>
    <tr>
      <td class="align-middle">DNS Server</td>
      <td><input class="form-control" type="text" autocomplete="off" name="dns" value="<?= ppp_h($dns); ?>"></td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_comment ?></td>
      <td><input class="form-control" type="text" autocomplete="off" name="comment" value="<?= ppp_h($comment); ?>"></td>
    </tr>
  </table>
</form>
</div>
</div>
</div>
</div>
