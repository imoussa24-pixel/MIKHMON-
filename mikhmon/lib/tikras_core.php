<?php
/*
 * TIKRAS IT compatibility helpers.
 * Small PHP 5.4 -> PHP 8+ bridge for legacy Mikhmon pages.
 */
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -15) == "tikras_core.php") {
  header("Location:../");
  exit;
}

if (!function_exists('tikras_is_https')) {
  function tikras_is_https()
  {
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
      return true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
      return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
      return true;
    }
    return false;
  }
}

if (!function_exists('tikras_security_headers')) {
  function tikras_security_headers()
  {
    if (headers_sent() || PHP_SAPI === 'cli') {
      return;
    }
    // Defensive HTTP headers (transparent to the app, mitigate clickjacking / MIME sniffing / referer leaks).
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0');
    header_remove('X-Powered-By');
  }
}

if (!function_exists('tikras_start_session')) {
  function tikras_start_session()
  {
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
      return;
    }
    // Harden session handling before the session cookie is emitted.
    if (!headers_sent()) {
      @ini_set('session.use_strict_mode', '1');
      @ini_set('session.use_only_cookies', '1');
      @ini_set('session.cookie_httponly', '1');
      if (PHP_VERSION_ID >= 70300) {
        @session_set_cookie_params(array(
          'lifetime' => 0,
          'path'     => '/',
          'httponly' => true,
          'secure'   => tikras_is_https(),
          'samesite' => 'Lax',
        ));
      } else {
        @ini_set('session.cookie_httponly', '1');
        if (tikras_is_https()) {
          @ini_set('session.cookie_secure', '1');
        }
      }
    }

    if (function_exists('session_status')) {
      if (session_status() === PHP_SESSION_NONE) {
        @session_start();
      }
    } elseif (!isset($_SESSION)) {
      @session_start();
    }

    tikras_security_headers();
  }
}

if (!function_exists('tikras_session_regenerate')) {
  // Call right after a successful authentication to defeat session fixation.
  function tikras_session_regenerate()
  {
    if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
      return;
    }
    if (!headers_sent() && function_exists('session_regenerate_id')) {
      @session_regenerate_id(true);
    }
  }
}

if (!function_exists('tikras_csrf_token')) {
  // Returns (creating if needed) the per-session CSRF token.
  function tikras_csrf_token()
  {
    if (empty($_SESSION['tikras_csrf'])) {
      if (function_exists('random_bytes')) {
        try {
          $_SESSION['tikras_csrf'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
          $_SESSION['tikras_csrf'] = hash('sha256', uniqid('tikras', true) . mt_rand());
        }
      } else {
        $_SESSION['tikras_csrf'] = hash('sha256', uniqid('tikras', true) . mt_rand());
      }
    }
    return $_SESSION['tikras_csrf'];
  }
}

if (!function_exists('tikras_csrf_field')) {
  // Hidden input to drop inside a <form>.
  function tikras_csrf_field()
  {
    return '<input type="hidden" name="tikras_csrf" value="' . tikras_h(tikras_csrf_token()) . '">';
  }
}

if (!function_exists('tikras_csrf_check')) {
  // Verify a submitted token (constant-time). Returns bool.
  function tikras_csrf_check($token)
  {
    $expected = isset($_SESSION['tikras_csrf']) ? (string) $_SESSION['tikras_csrf'] : '';
    if ($expected === '' || !is_string($token) || $token === '') {
      return false;
    }
    return hash_equals($expected, $token);
  }
}

if (!function_exists('tikras_bootstrap_errors')) {
  function tikras_bootstrap_errors($debug)
  {
    $debug = ($debug || tikras_debug_enabled()) ? true : false;
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
    ini_set('log_errors', '1');

    $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'share' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
      @mkdir($logDir, 0775, true);
    }
    if (is_dir($logDir) && is_writable($logDir)) {
      ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'php-error.log');
    }

    tikras_bootstrap_timezone();
    error_reporting($debug ? E_ALL : (E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED));
  }
}

if (!function_exists('tikras_bootstrap_timezone')) {
  function tikras_bootstrap_timezone()
  {
    if (!function_exists('date_default_timezone_set')) {
      return;
    }

    $timezone = ini_get('date.timezone');
    if ($timezone == '') {
      $timezone = getenv('TZ');
    }
    if ($timezone == '') {
      $timezone = 'Africa/Niamey';
    }
    if (!@date_default_timezone_set($timezone)) {
      @date_default_timezone_set('UTC');
    }
  }
}

if (!function_exists('tikras_debug_enabled')) {
  function tikras_debug_enabled()
  {
    static $enabled = null;
    if ($enabled !== null) {
      return $enabled;
    }

    $enabled = false;
    $debugConfig = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'debug.php';
    if (is_file($debugConfig)) {
      $tikras_debug = false;
      include($debugConfig);
      $enabled = !empty($tikras_debug);
    }

    $envDebug = getenv('TIKRAS_DEBUG');
    if ($envDebug !== false) {
      $envDebug = strtolower(trim((string) $envDebug));
      if ($envDebug == '1' || $envDebug == 'true' || $envDebug == 'yes' || $envDebug == 'on') {
        $enabled = true;
      }
    }

    return $enabled;
  }
}

if (!function_exists('tikras_log')) {
  function tikras_log($message, $context = array())
  {
    if (!is_array($context)) {
      $context = array('context' => $context);
    }
    $line = '[TIKRAS] ' . (string) $message;
    if (!empty($context)) {
      $line .= ' ' . json_encode($context);
    }
    error_log($line);
  }
}

if (!function_exists('tikras_start_gzip')) {
  function tikras_start_gzip()
  {
    if (ob_get_level() < 1 && function_exists('ob_gzhandler')) {
      @ob_start('ob_gzhandler');
    } elseif (ob_get_level() < 1) {
      @ob_start();
    }
  }
}

if (!function_exists('tikras_array_get')) {
  function tikras_array_get($source, $key, $default)
  {
    if (is_array($source) && isset($source[$key])) {
      return $source[$key];
    }
    return $default;
  }
}

if (!function_exists('tikras_get')) {
  function tikras_get($key, $default = '')
  {
    return tikras_array_get($_GET, $key, $default);
  }
}

if (!function_exists('tikras_post')) {
  function tikras_post($key, $default = '')
  {
    return tikras_array_get($_POST, $key, $default);
  }
}

if (!function_exists('tikras_request')) {
  function tikras_request($key, $default = '')
  {
    return tikras_array_get($_REQUEST, $key, $default);
  }
}

if (!function_exists('tikras_cookie')) {
  function tikras_cookie($key, $default = '')
  {
    return tikras_array_get($_COOKIE, $key, $default);
  }
}

if (!function_exists('tikras_has_get')) {
  function tikras_has_get($key)
  {
    return isset($_GET[$key]);
  }
}

if (!function_exists('tikras_has_post')) {
  function tikras_has_post($key)
  {
    return isset($_POST[$key]);
  }
}

if (!function_exists('tikras_has_request')) {
  function tikras_has_request($key)
  {
    return isset($_REQUEST[$key]);
  }
}

if (!function_exists('tikras_has_session')) {
  function tikras_has_session($key)
  {
    return isset($_SESSION[$key]);
  }
}

if (!function_exists('tikras_post_array')) {
  function tikras_post_array($key)
  {
    $value = tikras_array_get($_POST, $key, array());
    if (!is_array($value)) {
      return array($value);
    }
    return $value;
  }
}

if (!function_exists('tikras_server')) {
  function tikras_server($key, $default = '')
  {
    return tikras_array_get($_SERVER, $key, $default);
  }
}

if (!function_exists('tikras_file')) {
  function tikras_file($field, $key, $default = '')
  {
    if (isset($_FILES[$field]) && is_array($_FILES[$field]) && isset($_FILES[$field][$key])) {
      return $_FILES[$field][$key];
    }
    return $default;
  }
}

if (!function_exists('tikras_session_get')) {
  function tikras_session_get($key, $default = '')
  {
    return tikras_array_get($_SESSION, $key, $default);
  }
}

if (!function_exists('tikras_session_default')) {
  function tikras_session_default($key, $default)
  {
    if (!isset($_SESSION[$key])) {
      $_SESSION[$key] = $default;
    }
  }
}

if (!function_exists('tikras_session_defaults')) {
  function tikras_session_defaults($defaults)
  {
    if (!is_array($defaults)) {
      return;
    }
    foreach ($defaults as $key => $value) {
      tikras_session_default($key, $value);
    }
  }
}

if (!function_exists('tikras_h')) {
  function tikras_h($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('tikras_js_string')) {
  function tikras_js_string($value)
  {
    return str_replace(
      array('\\', "'", "\r", "\n", '</'),
      array('\\\\', "\\'", '', '', '<\\/'),
      (string) $value
    );
  }
}

if (!function_exists('tikras_redirect')) {
  function tikras_redirect($url, $exit = true)
  {
    $url = (string) $url;
    if (!headers_sent()) {
      header('Location: ' . $url);
      if ($exit) {
        exit;
      }
      return;
    }
    echo "<script>window.location='" . tikras_js_string($url) . "'</script>";
    if ($exit) {
      exit;
    }
  }
}

if (!function_exists('tikras_cfg_value')) {
  function tikras_cfg_value($data, $session, $index, $separator, $default)
  {
    if (!is_array($data) || !isset($data[$session]) || !isset($data[$session][$index])) {
      return $default;
    }
    $parts = explode($separator, (string) $data[$session][$index], 2);
    return isset($parts[1]) ? $parts[1] : $default;
  }
}

if (!function_exists('tikras_client_ip')) {
  function tikras_client_ip()
  {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    return $ip === '' ? '0.0.0.0' : $ip;
  }
}

if (!function_exists('tikras_login_guard_path')) {
  function tikras_login_guard_path()
  {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'share' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }
    return $dir . DIRECTORY_SEPARATOR . 'login_guard.json';
  }
}

if (!function_exists('tikras_login_guard_read')) {
  function tikras_login_guard_read()
  {
    $path = tikras_login_guard_path();
    if (!is_file($path)) {
      return array();
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
      return array();
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : array();
  }
}

if (!function_exists('tikras_login_guard_write')) {
  function tikras_login_guard_write($state)
  {
    $path = tikras_login_guard_path();
    // Prune stale entries (> 1h) so the file never grows without bound.
    $now = time();
    foreach ($state as $ip => $entry) {
      $last = isset($entry['last']) ? (int) $entry['last'] : 0;
      if (($now - $last) > 3600) {
        unset($state[$ip]);
      }
    }
    @file_put_contents($path, json_encode($state), LOCK_EX);
  }
}

if (!function_exists('tikras_login_throttle')) {
  /*
   * Brute-force guard. Returns 0 when a login attempt is allowed, or the
   * number of seconds the caller must wait before retrying. Never locks the
   * admin out permanently: the window rolls and clears on success.
   */
  function tikras_login_throttle($record = null)
  {
    $threshold = 6;      // failures allowed before cooldown kicks in
    $window    = 900;    // 15 min rolling window
    $baseDelay = 15;     // seconds; grows with excess failures

    $ip    = tikras_client_ip();
    $now   = time();
    $state = tikras_login_guard_read();
    $entry = isset($state[$ip]) && is_array($state[$ip]) ? $state[$ip] : array('count' => 0, 'last' => 0);

    // Reset the counter once the window has elapsed since the last failure.
    if (($now - (int) $entry['last']) > $window) {
      $entry['count'] = 0;
    }

    if ($record === 'success') {
      unset($state[$ip]);
      tikras_login_guard_write($state);
      return 0;
    }

    if ($record === 'failure') {
      $entry['count'] = (int) $entry['count'] + 1;
      $entry['last']  = $now;
      $state[$ip]     = $entry;
      tikras_login_guard_write($state);
    }

    $count = (int) $entry['count'];
    if ($count < $threshold) {
      return 0;
    }
    // Cooldown grows with the number of failures beyond the threshold, capped.
    $delay   = min($baseDelay * (1 + ($count - $threshold)), 300);
    $elapsed = $now - (int) $entry['last'];
    $retry   = $delay - $elapsed;
    return $retry > 0 ? $retry : 0;
  }
}
?>
