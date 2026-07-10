<?php
/*
 *  PPP Active page for Mikhmon.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  include_once(dirname(__FILE__) . '/helpers.php');

  if (!isset($API)) {
    $session = tikras_get('session');
    include('../include/config.php');
    include('../include/readcfg.php');
    include('../include/lang.php');
    include('../lang/'.$langid.'.php');
    include_once('../lib/tikras_routeros.php');
    include_once('../lib/formatbytesbites.php');
    $API = tikras_routeros_create();
    tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);
  }

  $serviceactive = tikras_get('service');
  if ($serviceactive != "") {
    $getpppactive = $API->comm("/ppp/active/print", array("?service" => "$serviceactive"));
  } else {
    $getpppactive = $API->comm("/ppp/active/print");
  }
  $TotalReg = count($getpppactive);
}
?>

<div class="row">
<div id="reloadPPPActive">
<div class="col-12">
<div class="card">
<div class="card-header">
  <h3><i class="fa fa-plug"></i> <?= $_ppp_active ?>
    <?php
    if ($serviceactive != "") {
      echo ppp_h($serviceactive) . " ";
    }
    echo $TotalReg . " " . ppp_unit($TotalReg);
    if ($serviceactive != "") {
      echo " | <a href='./?ppp=active&session=" . ppp_h($session) . "'><i class='fa fa-search'></i> " . $_show_all . "</a>";
    }
    ?>
  </h3>
</div>
<div class="card-body overflow">
<table id="dataTable" class="table table-bordered table-hover text-nowrap">
  <thead>
  <tr>
    <th></th>
    <th>Service</th>
    <th><?= $_name ?></th>
    <th>Caller ID</th>
    <th>Address</th>
    <th class="text-right"><?= $_uptime ?></th>
    <th class="text-right">Bytes In</th>
    <th class="text-right">Bytes Out</th>
    <th>Encoding</th>
    <th>Session ID</th>
  </tr>
  </thead>
  <tbody>
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  $active = $getpppactive[$i];
  $id = ppp_v($active, '.id');
  $service = ppp_v($active, 'service');
  $name = ppp_v($active, 'name');
  $caller = ppp_v($active, 'caller-id');
  $address = ppp_v($active, 'address');
  $uptime = formatDTM(ppp_v($active, 'uptime'));
  $bytesi = formatBytes(ppp_v($active, 'bytes-in'), 2);
  $byteso = formatBytes(ppp_v($active, 'bytes-out'), 2);
  $encoding = ppp_v($active, 'encoding');
  $sessionid = ppp_v($active, 'session-id');
  $uriprocess = "'./?remove-pactive=" . ppp_h($id) . "&session=" . ppp_h($session) . "'";

  echo "<tr>";
  echo "<td style='text-align:center;'><span class='pointer' title='Remove " . ppp_h($name) . "' onclick=loadpage(" . $uriprocess . ")><i class='fa fa-minus-square text-danger'></i></span></td>";
  echo "<td><a title='Filter " . ppp_h($service) . "' href='./?ppp=active&service=" . rawurlencode($service) . "&session=" . rawurlencode($session) . "'><i class='fa fa-server'></i> " . ppp_h($service) . "</a></td>";
  echo "<td><a title='Open PPP Secret " . ppp_h($name) . "' href='./?secret=" . rawurlencode($name) . "&session=" . rawurlencode($session) . "'><i class='fa fa-edit'></i> " . ppp_h($name) . "</a></td>";
  echo "<td>" . ppp_h($caller) . "</td>";
  echo "<td>" . ppp_h($address) . "</td>";
  echo "<td style='text-align:right;'>" . ppp_h($uptime) . "</td>";
  echo "<td style='text-align:right;'>" . ppp_h($bytesi) . "</td>";
  echo "<td style='text-align:right;'>" . ppp_h($byteso) . "</td>";
  echo "<td>" . ppp_h($encoding) . "</td>";
  echo "<td>" . ppp_h($sessionid) . "</td>";
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
