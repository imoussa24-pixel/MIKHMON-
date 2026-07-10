<?php
/*
 *  Edit PPP Secret page for Mikhmon.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  include_once(dirname(__FILE__) . '/helpers.php');

  $secret = tikras_get('secret');
  if (substr($secret, 0, 1) == "*") {
    $getsecret = $API->comm("/ppp/secret/print", array("?.id" => "$secret"));
  } else {
    $getsecret = $API->comm("/ppp/secret/print", array("?name" => "$secret"));
  }

  $secretdetails = $getsecret[0];
  $sid = ppp_v($secretdetails, '.id');
  $sname = ppp_v($secretdetails, 'name');
  $spass = ppp_v($secretdetails, 'password');
  $sservice = ppp_v($secretdetails, 'service', 'any');
  $sprofile = ppp_v($secretdetails, 'profile');
  $slocal = ppp_v($secretdetails, 'local-address');
  $sremote = ppp_v($secretdetails, 'remote-address');
  $scaller = ppp_v($secretdetails, 'caller-id');
  $scomment = ppp_v($secretdetails, 'comment');
  $sdisabled = ppp_v($secretdetails, 'disabled');
  $limitData = ppp_limit_value(ppp_v($secretdetails, 'limit-bytes-total'));

  $getprofile = $API->comm("/ppp/profile/print");

  if (tikras_has_post('save')) {
    $name = tikras_post('name');
    $password = tikras_post('pass');
    $service = tikras_post('service');
    $profile = tikras_post('profile');
    $local = tikras_post('local');
    $remote = tikras_post('remote');
    $caller = tikras_post('caller');
    $disabled = tikras_post('disabled');
    $comment = tikras_post('comment');
    $limit = tikras_post('datalimit');
    $mbgb = tikras_post('mbgb');
    $limitTotal = ($limit == '') ? '0' : ($limit * $mbgb);

    $API->comm("/ppp/secret/set", array(
      ".id" => "$sid",
      "name" => "$name",
      "password" => "$password",
      "service" => "$service",
      "profile" => "$profile",
      "local-address" => "$local",
      "remote-address" => "$remote",
      "caller-id" => "$caller",
      "disabled" => "$disabled",
      "limit-bytes-total" => "$limitTotal",
      "comment" => "$comment",
    ));
    tikras_redirect('./?secret=' . $sid . '&session=' . $session);
  }
}
?>

<script>
function PassPPP(){
  var x = document.getElementById('passPPP');
  if (x.type === 'password') {
    x.type = 'text';
  } else {
    x.type = 'password';
  }
}
</script>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
  <h3><i class="fa fa-edit"></i> <?= $_edit ?> <?= $_ppp_secrets ?> <?= ppp_h($sname); ?></h3>
</div>
<div class="card-body">
<form autocomplete="new-password" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?ppp=secrets&profile=all&session=<?= $session; ?>"><i class="fa fa-close"></i> <?= $_close ?></a>
    <button type="submit" name="save" class="btn bg-primary"><i class="fa fa-save"></i> <?= $_save ?></button>
    <div class="btn bg-danger" onclick="if(confirm('Are you sure to delete PPP secret (<?= ppp_h($sname); ?>)?')){loadpage('./?remove-pppsecret=<?= ppp_h($sid); ?>&session=<?= $session; ?>')}else{}" title="Remove <?= ppp_h($sname); ?>"><i class="fa fa-minus-square"></i> <?= $_remove ?></div>
    <a class="btn bg-info" href="./?ppp=active&session=<?= $session; ?>"><i class="fa fa-plug"></i> <?= $_ppp_active ?></a>
  </div>
  <table class="table">
    <tr>
      <td class="align-middle">Enabled</td>
      <td>
        <select class="form-control" name="disabled" required="1">
          <option value="<?= ppp_disabled_value($sdisabled); ?>"><?= ppp_bool_label($sdisabled); ?></option>
          <option value="no">Yes</option>
          <option value="yes">No</option>
        </select>
      </td>
    </tr>
    <tr>
      <td class="align-middle">Service</td>
      <td>
        <select class="form-control" name="service" required="1">
          <?= ppp_service_options($sservice); ?>
        </select>
      </td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_name ?></td>
      <td><input class="form-control" type="text" autocomplete="off" name="name" value="<?= ppp_h($sname); ?>" required="1"></td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_password ?></td>
      <td>
        <div class="input-group">
          <div class="input-group-11 col-box-10">
            <input class="group-item group-item-l" id="passPPP" type="password" name="pass" autocomplete="new-password" value="<?= ppp_h($spass); ?>" required="1">
          </div>
          <div class="input-group-1 col-box-2">
            <div class="group-item group-item-r pd-2p5 text-center">
              <input title="Show/Hide Password" type="checkbox" onclick="PassPPP()">
            </div>
          </div>
        </div>
      </td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_profile ?></td>
      <td>
        <select class="form-control" name="profile" required="1">
          <option><?= ppp_h($sprofile); ?></option>
          <?php
          for ($i = 0; $i < count($getprofile); $i++) {
            if ($getprofile[$i]['name'] != $sprofile) {
              echo "<option>" . ppp_h($getprofile[$i]['name']) . "</option>";
            }
          }
          ?>
        </select>
      </td>
    </tr>
    <tr>
      <td class="align-middle">Local Address</td>
      <td><input class="form-control" type="text" autocomplete="off" name="local" value="<?= ppp_h($slocal); ?>"></td>
    </tr>
    <tr>
      <td class="align-middle">Remote Address</td>
      <td><input class="form-control" type="text" autocomplete="off" name="remote" value="<?= ppp_h($sremote); ?>"></td>
    </tr>
    <tr>
      <td class="align-middle">Caller ID</td>
      <td><input class="form-control" type="text" autocomplete="off" name="caller" value="<?= ppp_h($scaller); ?>"></td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_data_limit ?></td>
      <td>
        <div class="input-group">
          <div class="input-group-10 col-box-9">
            <input class="group-item group-item-l" type="number" min="0" max="999999" name="datalimit" value="<?= ppp_h($limitData['value']); ?>">
          </div>
          <div class="input-group-2 col-box-3">
            <select style="padding:4.2px;" class="group-item group-item-r" name="mbgb" required="1">
              <option value="<?= ppp_h($limitData['multiplier']); ?>"><?= ppp_h($limitData['unit']); ?></option>
              <option value="1048576">MB</option>
              <option value="1073741824">GB</option>
            </select>
          </div>
        </div>
      </td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_comment ?></td>
      <td><input class="form-control" type="text" title="No special characters" autocomplete="off" name="comment" value="<?= ppp_h($scomment); ?>"></td>
    </tr>
  </table>
</form>
</div>
</div>
</div>
</div>
