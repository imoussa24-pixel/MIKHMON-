<?php
/*
 * TIKRAS IT automation engine.
 * Periodic server-side jobs: router health checks, roaming queue retry,
 * automatic backups with rotation, log pruning and SQLite sync.
 * Triggered by cron.php (external cron / Render cron / UptimeRobot)
 * or by the soft in-app scheduler while an admin browses the panel.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_config_store.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');
include_once(dirname(__FILE__) . '/tikras_backup.php');
include_once(dirname(__FILE__) . '/tikras_notify.php');

if (!function_exists('tikras_automation_env_int')) {
  function tikras_automation_env_int($name, $default)
  {
    $value = getenv($name);
    if ($value === false || trim((string) $value) == '' || !is_numeric($value)) {
      return (int) $default;
    }
    return max(0, (int) $value);
  }
}

if (!function_exists('tikras_automation_config')) {
  /*
   * Intervals in seconds. 0 disables a job.
   * Overridable per environment without touching the code.
   */
  function tikras_automation_config()
  {
    return array(
      'sync_routers'  => tikras_automation_env_int('TIKRAS_AUTO_SYNC_INTERVAL', 3600),
      'health_check'  => tikras_automation_env_int('TIKRAS_AUTO_HEALTH_INTERVAL', 300),
      'roaming_retry' => tikras_automation_env_int('TIKRAS_AUTO_ROAMING_INTERVAL', 600),
      'auto_backup'   => tikras_automation_env_int('TIKRAS_AUTO_BACKUP_INTERVAL', 86400),
      'prune'         => tikras_automation_env_int('TIKRAS_AUTO_PRUNE_INTERVAL', 604800),
      'backup_keep'   => max(1, tikras_automation_env_int('TIKRAS_AUTO_BACKUP_KEEP', 10)),
      'audit_keep_days' => max(7, tikras_automation_env_int('TIKRAS_AUTO_AUDIT_KEEP_DAYS', 90)),
      'health_timeout' => max(1, tikras_automation_env_int('TIKRAS_AUTO_HEALTH_TIMEOUT', 2)),
      'health_batch'  => max(5, tikras_automation_env_int('TIKRAS_AUTO_HEALTH_BATCH', 30)),
      'notify_down'   => tikras_automation_env_int('TIKRAS_AUTO_NOTIFY_DOWN', 1),
    );
  }
}

if (!function_exists('tikras_automation_meta_get')) {
  function tikras_automation_meta_get($key, $default = '')
  {
    if (!tikras_storage_available()) {
      return $default;
    }
    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare('SELECT meta_value FROM app_meta WHERE meta_key = ?');
      $stmt->execute(array($key));
      $value = $stmt->fetchColumn();
      return $value === false ? $default : (string) $value;
    } catch (Exception $e) {
      return $default;
    }
  }
}

if (!function_exists('tikras_automation_meta_set')) {
  function tikras_automation_meta_set($key, $value)
  {
    if (!tikras_storage_available()) {
      return false;
    }
    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare('INSERT OR REPLACE INTO app_meta (meta_key, meta_value, updated_at) VALUES (?, ?, ?)');
      $stmt->execute(array($key, (string) $value, tikras_storage_now()));
      return true;
    } catch (Exception $e) {
      return false;
    }
  }
}

if (!function_exists('tikras_automation_due')) {
  function tikras_automation_due($job, $interval, $now)
  {
    if ($interval < 1) {
      return false;
    }
    $last = (int) tikras_automation_meta_get('auto.last.' . $job, '0');
    return ($now - $last) >= $interval;
  }
}

if (!function_exists('tikras_automation_mark')) {
  function tikras_automation_mark($job, $now, $summary = '')
  {
    tikras_automation_meta_set('auto.last.' . $job, (string) $now);
    if ($summary !== '') {
      tikras_automation_meta_set('auto.summary.' . $job, $summary);
    }
  }
}

if (!function_exists('tikras_automation_parse_host')) {
  /* Split "ip[:port]" into address + API port (default 8728). */
  function tikras_automation_parse_host($iphost)
  {
    $host = trim((string) $iphost);
    $port = 8728;
    if (strpos($host, ':') !== false) {
      $parts = explode(':', $host, 2);
      $host = $parts[0];
      if (isset($parts[1]) && is_numeric($parts[1])) {
        $port = (int) $parts[1];
      }
    }
    return array($host, $port);
  }
}

if (!function_exists('tikras_automation_probe_router')) {
  /* Cheap TCP reachability probe against the RouterOS API port. */
  function tikras_automation_probe_router($iphost, $timeout)
  {
    list($host, $port) = tikras_automation_parse_host($iphost);
    if ($host == '') {
      return array('state' => 'unknown', 'latency' => 0, 'error' => 'Adresse vide');
    }
    $start = microtime(true);
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $latency = (int) round((microtime(true) - $start) * 1000);
    if ($fp) {
      fclose($fp);
      return array('state' => 'online', 'latency' => $latency, 'error' => '');
    }
    return array(
      'state' => 'offline',
      'latency' => $latency,
      'error' => $errstr != '' ? $errstr : ('Connexion impossible (errno ' . (int) $errno . ')'),
    );
  }
}

if (!function_exists('tikras_automation_job_health')) {
  function tikras_automation_job_health($data, $config)
  {
    $report = array('checked' => 0, 'online' => 0, 'offline' => 0, 'changed' => array());
    if (!is_array($data)) {
      return $report;
    }

    // Batch tournant: on sonde d'abord les routeurs verifies le moins recemment,
    // avec un plafond par cycle pour garder chaque tick court (< ~60 s).
    $statuses = function_exists('tikras_storage_all_router_statuses') ? tikras_storage_all_router_statuses() : array();
    $candidates = array();
    foreach ($data as $sesname => $sessionConfig) {
      if ($sesname == '' || $sesname == 'mikhmon' || !is_array($sessionConfig)) {
        continue;
      }
      $iphost = tikras_cfg_value($data, $sesname, 1, '!', '');
      if ($iphost == '') {
        continue;
      }
      $lastChecked = isset($statuses[$sesname]['updated_at']) ? (string) $statuses[$sesname]['updated_at'] : '';
      $candidates[] = array('session' => $sesname, 'iphost' => $iphost, 'checked_at' => $lastChecked);
    }
    usort($candidates, function ($a, $b) {
      return strcmp($a['checked_at'], $b['checked_at']);
    });
    $candidates = array_slice($candidates, 0, $config['health_batch']);
    $report['pool'] = count($candidates);

    foreach ($candidates as $candidate) {
      $sesname = $candidate['session'];
      $iphost = $candidate['iphost'];
      $previous = isset($statuses[$sesname]) ? $statuses[$sesname] : null;
      $prevState = is_array($previous) && isset($previous['last_state']) ? $previous['last_state'] : 'unknown';
      $probe = tikras_automation_probe_router($iphost, $config['health_timeout']);
      $report['checked']++;
      if ($probe['state'] == 'online') {
        $report['online']++;
      } else {
        $report['offline']++;
      }
      tikras_storage_update_router_status($sesname, $probe['state'], $probe['error'], $probe['latency']);
      if ($prevState != $probe['state'] && $prevState != 'unknown') {
        $report['changed'][] = $sesname . ': ' . $prevState . ' -> ' . $probe['state'];
        tikras_storage_audit('auto.health', 'router', $sesname, '',
          'Etat routeur: ' . $prevState . ' -> ' . $probe['state'],
          array('latency_ms' => $probe['latency'], 'error' => $probe['error']));
        if ($config['notify_down'] && $probe['state'] == 'offline' && function_exists('tikras_notify_send_telegram')) {
          $msg = "TIKRAS IT - Alerte routeur\n" . $sesname . " est HORS LIGNE\n" . $iphost . "\n" . date('d/m/Y H:i');
          @tikras_notify_send_telegram($sesname, $msg, '');
        }
        if ($config['notify_down'] && $probe['state'] == 'online' && $prevState == 'offline' && function_exists('tikras_notify_send_telegram')) {
          $msg = "TIKRAS IT - Retour en ligne\n" . $sesname . " est de nouveau JOIGNABLE\n" . date('d/m/Y H:i');
          @tikras_notify_send_telegram($sesname, $msg, '');
        }
      }
    }
    return $report;
  }
}

if (!function_exists('tikras_automation_job_roaming')) {
  function tikras_automation_job_roaming($data)
  {
    if (!function_exists('tikras_roaming_retry_queue')) {
      include_once(dirname(__FILE__) . '/tikras_roaming.php');
    }
    if (!function_exists('tikras_roaming_retry_queue')) {
      return array('retried' => 0, 'note' => 'roaming indisponible');
    }
    $summary = function_exists('tikras_roaming_queue_summary') ? tikras_roaming_queue_summary() : array();
    $pending = is_array($summary) && isset($summary['pending']) ? (int) $summary['pending'] : 0;
    if ($pending < 1) {
      return array('retried' => 0, 'note' => 'file vide');
    }
    // No current session in cron context: every target opens its own API link.
    $result = tikras_roaming_retry_queue($data, '', null, 5);
    tikras_storage_audit('auto.roaming', 'queue', 'roaming', '', 'Reprise automatique file roaming.', $result);
    return array('retried' => $pending, 'result' => $result);
  }
}

if (!function_exists('tikras_automation_job_backup')) {
  function tikras_automation_job_backup($config)
  {
    $result = tikras_backup_create(tikras_backup_dir() . DIRECTORY_SEPARATOR . tikras_backup_file_name('auto-backup'));
    $removed = 0;
    if ($result['ok']) {
      // Rotation: keep the most recent automatic backups only.
      $files = glob(tikras_backup_dir() . DIRECTORY_SEPARATOR . 'auto-backup-*.zip');
      if (is_array($files) && count($files) > $config['backup_keep']) {
        sort($files);
        $excess = array_slice($files, 0, count($files) - $config['backup_keep']);
        foreach ($excess as $old) {
          if (@unlink($old)) {
            $removed++;
          }
        }
      }
      tikras_storage_audit('auto.backup', 'backup', basename($result['path']), '',
        'Sauvegarde automatique creee.', array('files' => $result['files'], 'rotation_removed' => $removed));
    }
    return array('ok' => $result['ok'], 'path' => $result['path'], 'error' => $result['error'], 'rotation_removed' => $removed);
  }
}

if (!function_exists('tikras_automation_job_prune')) {
  function tikras_automation_job_prune($config)
  {
    $report = array('audit_removed' => 0, 'logs_removed' => 0);
    if (!tikras_storage_available()) {
      return $report;
    }
    try {
      $pdo = tikras_storage_pdo();
      $limit = date('Y-m-d H:i:s', time() - ($config['audit_keep_days'] * 86400));
      $stmt = $pdo->prepare('DELETE FROM audit_logs WHERE created_at < ?');
      $stmt->execute(array($limit));
      $report['audit_removed'] = (int) $stmt->rowCount();
      $stmt = $pdo->prepare('DELETE FROM routeros_logs WHERE captured_at < ?');
      $stmt->execute(array($limit));
      $report['logs_removed'] = (int) $stmt->rowCount();
      $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    } catch (Exception $e) {
      $report['error'] = $e->getMessage();
    }
    return $report;
  }
}

if (!function_exists('tikras_automation_tick')) {
  /*
   * Runs every due job once. $force runs everything regardless of schedule.
   * Returns a structured report for cron.php / the dashboard.
   */
  function tikras_automation_tick($data, $force = false)
  {
    $config = tikras_automation_config();
    $now = time();
    $report = array('ok' => true, 'ran' => array(), 'skipped' => array(), 'at' => date('c', $now));

    if (!tikras_storage_available()) {
      $report['ok'] = false;
      $report['error'] = 'SQLite indisponible: automatisations limitees.';
      return $report;
    }

    // Serialize ticks so overlapping cron hits do not double-run jobs.
    $lock = (int) tikras_automation_meta_get('auto.lock', '0');
    if ($lock > 0 && ($now - $lock) < 120) {
      $report['skipped'][] = 'tick (verrou actif)';
      return $report;
    }
    tikras_automation_meta_set('auto.lock', (string) $now);

    try {
      if ($force || tikras_automation_due('sync_routers', $config['sync_routers'], $now)) {
        $sync = tikras_storage_sync_routers($data);
        tikras_automation_mark('sync_routers', $now, json_encode($sync));
        $report['ran']['sync_routers'] = $sync;
      } else {
        $report['skipped'][] = 'sync_routers';
      }

      if ($force || tikras_automation_due('health_check', $config['health_check'], $now)) {
        $health = tikras_automation_job_health($data, $config);
        tikras_automation_mark('health_check', $now, json_encode($health));
        $report['ran']['health_check'] = $health;
      } else {
        $report['skipped'][] = 'health_check';
      }

      if ($force || tikras_automation_due('roaming_retry', $config['roaming_retry'], $now)) {
        $roaming = tikras_automation_job_roaming($data);
        tikras_automation_mark('roaming_retry', $now, json_encode($roaming));
        $report['ran']['roaming_retry'] = $roaming;
      } else {
        $report['skipped'][] = 'roaming_retry';
      }

      if ($force || tikras_automation_due('auto_backup', $config['auto_backup'], $now)) {
        $backup = tikras_automation_job_backup($config);
        tikras_automation_mark('auto_backup', $now, json_encode($backup));
        $report['ran']['auto_backup'] = $backup;
      } else {
        $report['skipped'][] = 'auto_backup';
      }

      if ($force || tikras_automation_due('prune', $config['prune'], $now)) {
        $prune = tikras_automation_job_prune($config);
        tikras_automation_mark('prune', $now, json_encode($prune));
        $report['ran']['prune'] = $prune;
      } else {
        $report['skipped'][] = 'prune';
      }
    } catch (Exception $e) {
      $report['ok'] = false;
      $report['error'] = $e->getMessage();
      tikras_log('automation tick failed', array('error' => $e->getMessage()));
    }

    tikras_automation_meta_set('auto.lock', '0');
    tikras_automation_meta_set('auto.last_tick', (string) $now);
    return $report;
  }
}

if (!function_exists('tikras_automation_status')) {
  /* Snapshot for the dashboard: last runs + summaries per job. */
  function tikras_automation_status()
  {
    $config = tikras_automation_config();
    $jobs = array('sync_routers', 'health_check', 'roaming_retry', 'auto_backup', 'prune');
    $status = array('last_tick' => (int) tikras_automation_meta_get('auto.last_tick', '0'), 'jobs' => array());
    foreach ($jobs as $job) {
      $status['jobs'][$job] = array(
        'interval' => isset($config[$job]) ? (int) $config[$job] : 0,
        'last_run' => (int) tikras_automation_meta_get('auto.last.' . $job, '0'),
        'summary' => tikras_automation_meta_get('auto.summary.' . $job, ''),
      );
    }
    return $status;
  }
}
?>
