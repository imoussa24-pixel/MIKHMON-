<?php
/*
 * Shared runtime metadata for TIKRAS IT.
 * Kept intentionally quiet because this file is included in multiple layouts.
 */
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -8) == "info.php") {
  header("Location:../");
  exit;
}

if (!defined("TIKRAS_IT_APP_NAME")) {
  define("TIKRAS_IT_APP_NAME", "TIKRAS IT");
}
?>
