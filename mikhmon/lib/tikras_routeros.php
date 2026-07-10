<?php
/*
 * RouterOS service facade for TIKRAS/Mikhmon.
 * Keeps RouterOS creation, connection and diagnostics in one place.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');
include_once(dirname(__FILE__) . '/routeros_api.class.php');

if (!function_exists('tikras_routeros_create')) {
  function tikras_routeros_create()
  {
    $api = new RouterosAPI();
    $api->debug = false;
    tikras_routeros_apply_defaults($api);
    return $api;
  }
}

if (!function_exists('tikras_routeros_apply_defaults')) {
  function tikras_routeros_apply_defaults($api)
  {
    if (!is_object($api)) {
      return;
    }

    $tikras_routeros_port = 8728;
    $tikras_routeros_timeout = 2;
    $tikras_routeros_attempts = 1;
    $tikras_routeros_delay = 0;
    $routerosConfig = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'routeros.php';
    if (is_file($routerosConfig)) {
      include($routerosConfig);
    }

    $envTimeout = getenv('TIKRAS_ROUTEROS_TIMEOUT');
    $envAttempts = getenv('TIKRAS_ROUTEROS_ATTEMPTS');
    $envDelay = getenv('TIKRAS_ROUTEROS_DELAY');
    if ($envTimeout !== false && is_numeric($envTimeout)) {
      $tikras_routeros_timeout = (int) $envTimeout;
    }
    if ($envAttempts !== false && is_numeric($envAttempts)) {
      $tikras_routeros_attempts = (int) $envAttempts;
    }
    if ($envDelay !== false && is_numeric($envDelay)) {
      $tikras_routeros_delay = (int) $envDelay;
    }

    $api->port = (int) $tikras_routeros_port;
    $api->timeout = max(1, (int) $tikras_routeros_timeout);
    $api->attempts = max(1, (int) $tikras_routeros_attempts);
    $api->delay = max(0, (int) $tikras_routeros_delay);
  }
}

if (!function_exists('tikras_routeros_cooldown_seconds')) {
  function tikras_routeros_cooldown_seconds()
  {
    $tikras_routeros_cooldown = 15;
    $routerosConfig = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'routeros.php';
    if (is_file($routerosConfig)) {
      include($routerosConfig);
    }

    $envCooldown = getenv('TIKRAS_ROUTEROS_COOLDOWN');
    if ($envCooldown !== false && is_numeric($envCooldown)) {
      $tikras_routeros_cooldown = (int) $envCooldown;
    }

    return max(0, (int) $tikras_routeros_cooldown);
  }
}

if (!function_exists('tikras_routeros_recent_failure')) {
  function tikras_routeros_recent_failure($session, $cooldownSeconds)
  {
    $session = (string) $session;
    if ($session == '' || $cooldownSeconds < 1 || !function_exists('tikras_storage_router_status')) {
      return false;
    }

    $status = tikras_storage_router_status($session);
    if (!$status || !isset($status['last_state']) || $status['last_state'] != 'offline') {
      return false;
    }

    $updatedAt = isset($status['updated_at']) ? strtotime($status['updated_at']) : false;
    if ($updatedAt === false) {
      return false;
    }

    return $updatedAt > (time() - $cooldownSeconds);
  }
}

if (!function_exists('tikras_routeros_connect')) {
  function tikras_routeros_connect($api, $iphost, $userhost, $password, $label = '', $options = array())
  {
    if (function_exists('set_time_limit')) {
      @set_time_limit(20);
    }

    if (!is_array($options)) {
      $options = array();
    }

    if (is_object($api) && isset($api->connected) && $api->connected) {
      return true;
    }

    if (is_object($api)) {
      if (isset($options['timeout']) && is_numeric($options['timeout'])) {
        $api->timeout = max(1, (int) $options['timeout']);
      }
      if (isset($options['attempts']) && is_numeric($options['attempts'])) {
        $api->attempts = max(1, (int) $options['attempts']);
      }
      if (isset($options['delay']) && is_numeric($options['delay'])) {
        $api->delay = max(0, (int) $options['delay']);
      }
    }

    $statusLabel = $label != '' ? (string) $label : (string) $iphost;
    $force = isset($options['force']) && $options['force'];
    $cooldown = isset($options['cooldown']) && is_numeric($options['cooldown']) ? (int) $options['cooldown'] : tikras_routeros_cooldown_seconds();
    if (!$force && tikras_routeros_recent_failure($statusLabel, $cooldown)) {
      if (tikras_debug_enabled()) {
        tikras_log('routeros.connect.skipped_cooldown', array(
          'host' => (string) $iphost,
          'user' => (string) $userhost,
          'label' => $statusLabel,
          'cooldown' => $cooldown,
        ));
      }
      return false;
    }

    $started = microtime(true);
    $ok = false;
    if (is_object($api)) {
      $ok = $api->connect($iphost, $userhost, $password) ? true : false;
    }

    $elapsedMs = round((microtime(true) - $started) * 1000, 2);
    if (tikras_debug_enabled() || !$ok) {
      tikras_log('routeros.connect', array(
        'ok' => $ok ? 1 : 0,
        'host' => (string) $iphost,
        'user' => (string) $userhost,
        'label' => (string) $label,
        'timeout' => is_object($api) && isset($api->timeout) ? (int) $api->timeout : 0,
        'attempts' => is_object($api) && isset($api->attempts) ? (int) $api->attempts : 0,
        'ms' => $elapsedMs,
      ));
    }

    tikras_storage_update_router_status(
      $statusLabel,
      $ok ? 'online' : 'offline',
      $ok ? '' : 'Connexion RouterOS impossible',
      $elapsedMs,
      $iphost,
      $userhost
    );

    return $ok;
  }
}

if (!function_exists('tikras_routeros_comm')) {
  function tikras_routeros_comm($api, $command, $params = array(), $default = array())
  {
    $started = microtime(true);
    if (!is_object($api)) {
      tikras_log('routeros.comm.invalid_api', array('command' => (string) $command));
      return $default;
    }
    if (isset($api->connected) && !$api->connected) {
      if (tikras_debug_enabled()) {
        tikras_log('routeros.comm.skipped_disconnected', array('command' => (string) $command));
      }
      return $default;
    }

    $result = $api->comm($command, $params);
    if (tikras_debug_enabled()) {
      $count = is_array($result) ? count($result) : 1;
      tikras_log('routeros.comm', array(
        'command' => (string) $command,
        'rows' => $count,
        'ms' => round((microtime(true) - $started) * 1000, 2),
      ));
    }

    return $result;
  }
}

if (!function_exists('tikras_routeros_disconnect')) {
  function tikras_routeros_disconnect($api)
  {
    if (is_object($api)) {
      $api->disconnect();
    }
  }
}
?>
