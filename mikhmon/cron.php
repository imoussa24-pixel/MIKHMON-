<?php
/*
 * TIKRAS IT automation endpoint.
 *
 * Three entry paths:
 *   1. CLI          : php cron.php [--force]
 *   2. External cron: GET /cron.php?token=XXXX  (token = env TIKRAS_CRON_TOKEN)
 *                     -> Render cron job, cron-job.org, UptimeRobot...
 *   3. Soft cron    : GET /cron.php?soft=1 by a logged-in admin browser
 *                     (fired periodically by tikras-modern.js)
 */
require_once(__DIR__ . '/lib/tikras_core.php');
require_once(__DIR__ . '/lib/tikras_config_store.php');
require_once(__DIR__ . '/lib/tikras_storage.php');
require_once(__DIR__ . '/lib/tikras_routeros.php');
require_once(__DIR__ . '/lib/routeros_api.class.php');
require_once(__DIR__ . '/lib/tikras_automation.php');

$isCli = (php_sapi_name() == 'cli');
$force = false;
$authorized = false;
$mode = 'cron';

if ($isCli) {
  $authorized = true;
  $mode = 'cli';
  $force = in_array('--force', isset($argv) && is_array($argv) ? $argv : array());
} else {
  tikras_start_session();
  tikras_bootstrap_errors(false);

  $token = getenv('TIKRAS_CRON_TOKEN');
  $given = tikras_get('token');
  $header = tikras_server('HTTP_X_CRON_TOKEN');
  if ($token !== false && trim((string) $token) != '') {
    if (($given != '' && hash_equals((string) $token, (string) $given)) ||
        ($header != '' && hash_equals((string) $token, (string) $header))) {
      $authorized = true;
    }
  }

  // Soft cron: an authenticated admin session may trigger a scheduled tick.
  if (!$authorized && tikras_has_session('mikhmon') && tikras_get('soft') != '') {
    $authorized = true;
    $mode = 'soft';
    // Bouton "Exécuter maintenant" du panneau admin.
    $force = tikras_get('force') != '';
  }

  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
}

if (!$authorized) {
  if (!$isCli) {
    http_response_code(403);
    echo json_encode(array('ok' => false, 'error' => 'Non autorise.'));
  } else {
    fwrite(STDERR, "Non autorise.\n");
  }
  exit(1);
}

// Load router config (config.php already applies the local writable overrides).
$data = array();
$session = '';
include(__DIR__ . '/include/config.php');

ini_set('max_execution_time', 110);
$report = tikras_automation_tick($data, $force);
$report['mode'] = $mode;

if ($isCli) {
  echo json_encode($report, JSON_PRETTY_PRINT) . PHP_EOL;
  exit(!empty($report['ok']) ? 0 : 1);
}

echo json_encode($report);
exit;
?>
