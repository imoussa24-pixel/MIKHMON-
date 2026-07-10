<?php
/*
 *  Add PPP Secret page for Mikhmon.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  include_once(dirname(__FILE__) . '/helpers.php');

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

    $params = array(
      "name" => "$name",
      "password" => "$password",
      "service" => "$service",
      "profile" => "$profile",
      "disabled" => "$disabled",
      "limit-bytes-total" => "$limitTotal",
      "comment" => "$comment",
    );

    if ($local != "") {
      $params["local-address"] = "$local";
    }
    if ($remote != "") {
      $params["remote-address"] = "$remote";
    }
    if ($caller != "") {
      $params["caller-id"] = "$caller";
    }

    $API->comm("/ppp/secret/add", $params);
    $getsecret = $API->comm("/ppp/secret/print", array("?name" => "$name"));
    $sid = $getsecret[0]['.id'];
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
<div class="col-8">
<div class="card">
<div class="card-header">
  <h3><i class="fa fa-user-plus"></i> <?= $_add ?> <?= $_ppp_secrets ?> <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3>
</div>
<div class="card-body">
<form autocomplete="new-password" method="post" action="">
  <div>
    <a class="btn bg-warning" href="./?ppp=secrets&profile=all&session=<?= $session; ?>"><i class="fa fa-close"></i> <?= $_close ?></a>
    <button type="submit" name="save" class="btn bg-primary" onclick="loader();"><i class="fa fa-save"></i> <?= $_save ?></button>
  </div>
  <table class="table">
    <tr>
      <td class="align-middle">Enabled</td>
      <td>
        <select class="form-control" name="disabled" required="1">
          <option value="no">Yes</option>
          <option value="yes">No</option>
        </select>
      </td>
    </tr>
    <tr>
      <td class="align-middle">Service</td>
      <td>
        <select class="form-control" name="service" required="1">
          <?= ppp_service_options('any'); ?>
        </select>
      </td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_name ?></td>
      <td><input class="form-control" type="text" autocomplete="off" name="name" required="1" autofocus></td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_password ?></td>
      <td>
        <div class="input-group">
          <div class="input-group-11 col-box-10">
            <input class="group-item group-item-l" id="passPPP" type="password" name="pass" autocomplete="new-password" required="1">
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
          <?php
          for ($i = 0; $i < count($getprofile); $i++) {
            echo "<option>" . ppp_h($getprofile[$i]['name']) . "</option>";
          }
          ?>
        </select>
      </td>
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
      <td class="align-middle">Caller ID</td>
      <td><input class="form-control" type="text" autocomplete="off" name="caller" placeholder="Optional"></td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_data_limit ?></td>
      <td>
        <div class="input-group">
          <div class="input-group-10 col-box-9">
            <input class="group-item group-item-l" type="number" min="0" max="999999" name="datalimit" value="">
          </div>
          <div class="input-group-2 col-box-3">
            <select style="padding:4.2px;" class="group-item group-item-r" name="mbgb" required="1">
              <option value="1048576">MB</option>
              <option value="1073741824">GB</option>
            </select>
          </div>
        </div>
      </td>
    </tr>
    <tr>
      <td class="align-middle"><?= $_comment ?></td>
      <td><input class="form-control" type="text" title="No special characters" autocomplete="off" name="comment"></td>
    </tr>
  </table>
</form>
</div>
</div>
</div>
<div class="col-4">
  <div class="card">
    <div class="card-header">
      <h3><i class="fa fa-book"></i> PPP</h3>
    </div>
    <div class="card-body">
      <p>Use PPP Secrets for PPPoE, PPTP, L2TP, OVPN and SSTP accounts. Choose a PPP profile to apply speed, local/remote address and connection policy.</p>
      <p>Remote Address can be an IP address or a MikroTik pool name.</p>
    </div>
  </div>
</div>
</div>
