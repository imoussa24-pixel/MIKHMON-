<?php
/*
 * Add an IP binding from a hotspot host entry.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

if (!function_exists("tikras_makebinding_clean")) {
  function tikras_makebinding_clean($value, $pattern, $max)
  {
    $value = preg_replace($pattern, "", (string) $value);
    return substr($value, 0, $max);
  }
}

$redirectSession = tikras_makebinding_clean(isset($session) ? $session : "", "/[^0-9A-Za-z_.-]/", 80);
$bindingMac = tikras_makebinding_clean(isset($macbinding) ? $macbinding : "", "/[^0-9A-Fa-f:\.-]/", 32);
$bindingAddress = tikras_makebinding_clean(isset($ipbinding) ? $ipbinding : "", "/[^0-9A-Fa-f:\.\/-]/", 64);
$bindingServer = tikras_makebinding_clean(isset($serveractive) ? $serveractive : "", "/[^0-9A-Za-z_.:-]/", 80);

if ($bindingMac != "" && $bindingAddress != "" && isset($API)) {
  $params = array(
    "mac-address" => $bindingMac,
    "address" => $bindingAddress,
    "type" => "bypassed",
    "comment" => "TIKRAS IT",
  );
  if ($bindingServer != "") {
    $params["server"] = $bindingServer;
  }
  $API->comm("/ip/hotspot/ip-binding/add", $params);
}

tikras_redirect('./?hotspot=ipbinding&session=' . $redirectSession);
?>
