<?php
$qrbt = "disable";
include_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
$tikrasQuickPrintQr = tikras_quickbt_read();
if ($tikrasQuickPrintQr !== null) {
  $qrbt = $tikrasQuickPrintQr;
}
?>
