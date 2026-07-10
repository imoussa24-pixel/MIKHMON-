<?php
/*
 * CLI bootstrap for the local SQLite database.
 */
$root = dirname(__DIR__);
require_once($root . '/mikhmon/lib/tikras_core.php');
require_once($root . '/mikhmon/lib/tikras_storage.php');

$session = '';
include($root . '/mikhmon/include/config.php');
include($root . '/mikhmon/include/readcfg.php');

$sync = tikras_storage_sync_routers($data);
$health = tikras_storage_health();

echo "SQLite: " . ($health['available'] ? "enabled" : "disabled") . PHP_EOL;
echo "Path: " . $health['path'] . PHP_EOL;
echo "Schema: " . $health['version'] . PHP_EOL;
echo "Routers: " . $sync['total'] . " total, " . $sync['inserted'] . " inserted, " . $sync['updated'] . " updated, " . $sync['unchanged'] . " unchanged" . PHP_EOL;
if ($health['error'] != '') {
  echo "Error: " . $health['error'] . PHP_EOL;
  exit(1);
}
exit($sync['ok'] ? 0 : 1);
?>
