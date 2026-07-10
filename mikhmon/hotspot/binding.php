<?php
/*
 * Compatibility route: the binding view is the IP binding list.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

include(dirname(__FILE__) . "/ipbinding.php");
?>
