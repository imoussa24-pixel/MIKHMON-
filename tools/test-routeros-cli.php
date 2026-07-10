<?php
/*
 * PHP 8/CLI RouterOS smoke test.
 * Usage: php8/php.exe tools/test-routeros-cli.php <session-name>
 */
$root = dirname(__DIR__);
$session = isset($argv[1]) ? $argv[1] : '';

require_once($root . '/mikhmon/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if ($session == '') {
  fwrite(STDERR, "Usage: php8/php.exe tools/test-routeros-cli.php <session-name>\n");
  exit(2);
}

require_once($root . '/mikhmon/include/config.php');
require_once($root . '/mikhmon/include/readcfg.php');
require_once($root . '/mikhmon/lib/tikras_routeros.php');

if (!isset($data[$session])) {
  fwrite(STDERR, "Session not found: " . $session . "\n");
  exit(2);
}

$api = tikras_routeros_create();
$api->attempts = 1;
$api->delay = 1;
$api->timeout = 5;

if (!tikras_routeros_connect($api, $iphost, $userhost, decrypt($passwdhost), $session)) {
  fwrite(STDERR, "RouterOS connection failed for session: " . $session . "\n");
  exit(1);
}

$checks = array(
  'identity' => array('/system/identity/print', array()),
  'resource' => array('/system/resource/print', array()),
  'hotspot_profiles' => array('/ip/hotspot/user/profile/print', array('count-only' => '')),
  'hotspot_users' => array('/ip/hotspot/user/print', array('count-only' => '')),
  'ppp_profiles' => array('/ppp/profile/print', array('count-only' => '')),
);

$result = array(
  'session' => $session,
  'host' => $iphost,
  'ok' => true,
  'checks' => array(),
);

foreach ($checks as $name => $check) {
  $started = microtime(true);
  $response = tikras_routeros_comm($api, $check[0], $check[1], array());
  $result['checks'][$name] = array(
    'ok' => $response !== false,
    'rows' => is_array($response) ? count($response) : (string) $response,
    'ms' => round((microtime(true) - $started) * 1000, 2),
  );
}

tikras_routeros_disconnect($api);
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
?>
