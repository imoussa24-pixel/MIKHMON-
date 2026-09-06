<?php
/*
 * Local SQLite storage for TIKRAS/Mikhmon.
 * This layer is the foundation for faster reports, roaming tickets,
 * router health, and audit logs while keeping the legacy file config alive.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_config_store.php');

if (!function_exists('tikras_storage_dir')) {
  function tikras_storage_dir()
  {
    $dir = tikras_config_local_dir() . DIRECTORY_SEPARATOR . 'storage';
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }
    return $dir;
  }
}

if (!function_exists('tikras_storage_path')) {
  function tikras_storage_path()
  {
    $path = getenv('TIKRAS_DB_PATH');
    if ($path !== false && trim((string) $path) != '') {
      return (string) $path;
    }
    return tikras_storage_dir() . DIRECTORY_SEPARATOR . 'mikhmon-pro-admin.sqlite';
  }
}

if (!function_exists('tikras_storage_available')) {
  function tikras_storage_available()
  {
    return class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers());
  }
}

if (!function_exists('tikras_storage_now')) {
  function tikras_storage_now()
  {
    return date('Y-m-d H:i:s');
  }
}

if (!function_exists('tikras_storage_pdo')) {
  function tikras_storage_pdo()
  {
    static $pdo = null;
    if ($pdo instanceof PDO) {
      return $pdo;
    }

    if (!tikras_storage_available()) {
      throw new RuntimeException('SQLite n est pas active dans PHP.');
    }

    $path = tikras_storage_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
      throw new RuntimeException('Impossible de creer le dossier de stockage local.');
    }

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    tikras_storage_migrate($pdo);
    return $pdo;
  }
}

if (!function_exists('tikras_storage_migrate')) {
  function tikras_storage_migrate($pdo = null)
  {
    if (!$pdo instanceof PDO) {
      $pdo = tikras_storage_pdo();
    }

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS app_meta (
        meta_key TEXT PRIMARY KEY,
        meta_value TEXT NOT NULL,
        updated_at TEXT NOT NULL
      )
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS routers (
        session TEXT PRIMARY KEY,
        display_name TEXT NOT NULL,
        host TEXT NOT NULL,
        username TEXT NOT NULL,
        hotspot_name TEXT NOT NULL DEFAULT '',
        dns_name TEXT NOT NULL DEFAULT '',
        currency TEXT NOT NULL DEFAULT '',
        api_port INTEGER NOT NULL DEFAULT 8728,
        default_iface TEXT NOT NULL DEFAULT '1',
        live_report TEXT NOT NULL DEFAULT 'disable',
        enabled INTEGER NOT NULL DEFAULT 1,
        source_hash TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
      )
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS router_status (
        session TEXT PRIMARY KEY,
        last_state TEXT NOT NULL DEFAULT 'unknown',
        last_error TEXT NOT NULL DEFAULT '',
        last_latency_ms INTEGER NOT NULL DEFAULT 0,
        last_seen_at TEXT,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(session) REFERENCES routers(session) ON DELETE CASCADE
      )
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_code TEXT NOT NULL,
        username TEXT NOT NULL DEFAULT '',
        password TEXT NOT NULL DEFAULT '',
        profile TEXT NOT NULL DEFAULT '',
        price REAL NOT NULL DEFAULT 0,
        currency TEXT NOT NULL DEFAULT '',
        comment TEXT NOT NULL DEFAULT '',
        source_session TEXT NOT NULL DEFAULT '',
        batch_code TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'created',
        created_at TEXT NOT NULL,
        expires_at TEXT,
        sold_at TEXT,
        raw_json TEXT NOT NULL DEFAULT ''
      )
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS roaming_batches (
        batch_code TEXT PRIMARY KEY,
        profile TEXT NOT NULL DEFAULT '',
        quantity INTEGER NOT NULL DEFAULT 0,
        price REAL NOT NULL DEFAULT 0,
        currency TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'draft',
        created_by TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        note TEXT NOT NULL DEFAULT ''
      )
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS roaming_batch_routes (
        batch_code TEXT NOT NULL,
        session TEXT NOT NULL,
        sync_status TEXT NOT NULL DEFAULT 'pending',
        synced_at TEXT,
        error_message TEXT NOT NULL DEFAULT '',
        PRIMARY KEY(batch_code, session),
        FOREIGN KEY(batch_code) REFERENCES roaming_batches(batch_code) ON DELETE CASCADE,
        FOREIGN KEY(session) REFERENCES routers(session) ON DELETE CASCADE
      )
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS routeros_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session TEXT NOT NULL,
        topic TEXT NOT NULL DEFAULT '',
        severity TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL DEFAULT '',
        router_time TEXT NOT NULL DEFAULT '',
        captured_at TEXT NOT NULL,
        raw_json TEXT NOT NULL DEFAULT ''
      )
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS sales_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session TEXT NOT NULL,
        ticket_code TEXT NOT NULL DEFAULT '',
        username TEXT NOT NULL DEFAULT '',
        profile TEXT NOT NULL DEFAULT '',
        price REAL NOT NULL DEFAULT 0,
        currency TEXT NOT NULL DEFAULT '',
        sold_at TEXT NOT NULL,
        comment TEXT NOT NULL DEFAULT '',
        report_month TEXT NOT NULL DEFAULT '',
        source_hash TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
      )
    ");

    $pdo->exec("
      CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        actor TEXT NOT NULL DEFAULT '',
        action TEXT NOT NULL,
        entity_type TEXT NOT NULL DEFAULT '',
        entity_id TEXT NOT NULL DEFAULT '',
        session TEXT NOT NULL DEFAULT '',
        ip TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL DEFAULT '',
        context_json TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
      )
    ");

    $indexes = array(
      'CREATE UNIQUE INDEX IF NOT EXISTS idx_tickets_code ON tickets(ticket_code)',
      'CREATE INDEX IF NOT EXISTS idx_tickets_batch ON tickets(batch_code)',
      'CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)',
      'CREATE UNIQUE INDEX IF NOT EXISTS idx_routeros_logs_unique ON routeros_logs(session, router_time, topic, message)',
      'CREATE INDEX IF NOT EXISTS idx_routeros_logs_session_time ON routeros_logs(session, captured_at)',
      'CREATE INDEX IF NOT EXISTS idx_sales_cache_month ON sales_cache(report_month, session)',
      /*
       * Le tableau de bord interroge les ventes par date, et les dedoublonne
       * par (routeur, ticket, date) - une meme vente pouvant etre enregistree
       * sous la cle du mois et sous celle du jour. Sans ces index, chaque
       * chiffre affiche impose un parcours complet des dizaines de milliers
       * de lignes.
       */
      'CREATE INDEX IF NOT EXISTS idx_sales_dedup ON sales_cache(session, ticket_code, sold_at)',
      'CREATE INDEX IF NOT EXISTS idx_sales_date ON sales_cache(sold_at)',
      'CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at)'
    );
    foreach ($indexes as $sql) {
      $pdo->exec($sql);
    }

    $stmt = $pdo->prepare('INSERT OR REPLACE INTO app_meta(meta_key, meta_value, updated_at) VALUES(:k, :v, :u)');
    $stmt->execute(array(':k' => 'schema_version', ':v' => '1', ':u' => tikras_storage_now()));
    $stmt->execute(array(':k' => 'app_name', ':v' => 'TIKRAS Mikhmon Pro Admin', ':u' => tikras_storage_now()));
    $pdo->exec('PRAGMA user_version = 1');
  }
}

if (!function_exists('tikras_storage_parse_router')) {
  function tikras_storage_parse_router($data, $session)
  {
    return array(
      'session' => (string) $session,
      'display_name' => tikras_cfg_value($data, $session, 4, '%', (string) $session),
      'host' => tikras_cfg_value($data, $session, 1, '!', ''),
      'username' => tikras_cfg_value($data, $session, 2, '@|@', ''),
      'hotspot_name' => tikras_cfg_value($data, $session, 4, '%', ''),
      'dns_name' => tikras_cfg_value($data, $session, 5, '^', ''),
      'currency' => tikras_cfg_value($data, $session, 6, '&', ''),
      'api_port' => 8728,
      'default_iface' => tikras_cfg_value($data, $session, 8, '(', '1'),
      'live_report' => tikras_cfg_value($data, $session, 11, '@!@', 'disable'),
      'enabled' => 1,
    );
  }
}

if (!function_exists('tikras_storage_sync_routers')) {
  function tikras_storage_sync_routers($data)
  {
    $result = array('ok' => false, 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'total' => 0, 'error' => '');
    if (!is_array($data)) {
      $result['error'] = 'Configuration routeurs invalide.';
      return $result;
    }

    try {
      $pdo = tikras_storage_pdo();
      $select = $pdo->prepare('SELECT source_hash FROM routers WHERE session = :session');
      $insert = $pdo->prepare('
        INSERT INTO routers(session, display_name, host, username, hotspot_name, dns_name, currency, api_port, default_iface, live_report, enabled, source_hash, created_at, updated_at)
        VALUES(:session, :display_name, :host, :username, :hotspot_name, :dns_name, :currency, :api_port, :default_iface, :live_report, :enabled, :source_hash, :created_at, :updated_at)
      ');
      $update = $pdo->prepare('
        UPDATE routers
        SET display_name = :display_name, host = :host, username = :username, hotspot_name = :hotspot_name, dns_name = :dns_name,
            currency = :currency, api_port = :api_port, default_iface = :default_iface, live_report = :live_report,
            enabled = :enabled, source_hash = :source_hash, updated_at = :updated_at
        WHERE session = :session
      ');

      foreach ($data as $session => $row) {
        if ($session === 'mikhmon' || !is_array($row)) {
          continue;
        }
        $router = tikras_storage_parse_router($data, $session);
        if ($router['host'] == '') {
          continue;
        }
        $router['source_hash'] = sha1(json_encode($router));
        $router['created_at'] = tikras_storage_now();
        $router['updated_at'] = tikras_storage_now();
        $result['total']++;

        $select->execute(array(':session' => $router['session']));
        $existing = $select->fetch();
        if (!$existing) {
          $insert->execute(array(
            ':session' => $router['session'],
            ':display_name' => $router['display_name'],
            ':host' => $router['host'],
            ':username' => $router['username'],
            ':hotspot_name' => $router['hotspot_name'],
            ':dns_name' => $router['dns_name'],
            ':currency' => $router['currency'],
            ':api_port' => $router['api_port'],
            ':default_iface' => $router['default_iface'],
            ':live_report' => $router['live_report'],
            ':enabled' => $router['enabled'],
            ':source_hash' => $router['source_hash'],
            ':created_at' => $router['created_at'],
            ':updated_at' => $router['updated_at'],
          ));
          $result['inserted']++;
        } elseif ($existing['source_hash'] != $router['source_hash']) {
          $update->execute(array(
            ':session' => $router['session'],
            ':display_name' => $router['display_name'],
            ':host' => $router['host'],
            ':username' => $router['username'],
            ':hotspot_name' => $router['hotspot_name'],
            ':dns_name' => $router['dns_name'],
            ':currency' => $router['currency'],
            ':api_port' => $router['api_port'],
            ':default_iface' => $router['default_iface'],
            ':live_report' => $router['live_report'],
            ':enabled' => $router['enabled'],
            ':source_hash' => $router['source_hash'],
            ':updated_at' => $router['updated_at'],
          ));
          $result['updated']++;
        } else {
          $result['unchanged']++;
        }
      }

      tikras_storage_audit('storage.sync_routers', 'router', '', '', 'Routeurs synchronises dans la base locale.', $result);
      $result['ok'] = true;
    } catch (Exception $e) {
      $result['error'] = $e->getMessage();
      tikras_log('storage.sync_routers.failed', array('error' => $result['error']));
    }
    return $result;
  }
}

if (!function_exists('tikras_storage_count')) {
  function tikras_storage_count($table)
  {
    $allowed = array('routers', 'router_status', 'tickets', 'roaming_batches', 'roaming_batch_routes', 'routeros_logs', 'sales_cache', 'audit_logs');
    if (!in_array($table, $allowed)) {
      return 0;
    }
    try {
      $pdo = tikras_storage_pdo();
      return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    } catch (Exception $e) {
      return 0;
    }
  }
}

if (!function_exists('tikras_storage_router_status')) {
  function tikras_storage_router_status($session)
  {
    $session = (string) $session;
    if ($session == '' || !tikras_storage_available()) {
      return null;
    }

    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare('SELECT * FROM router_status WHERE session = :session');
      $stmt->execute(array(':session' => $session));
      $row = $stmt->fetch();
      return $row ? $row : null;
    } catch (Exception $e) {
      return null;
    }
  }
}

if (!function_exists('tikras_storage_update_router_status')) {
  function tikras_storage_update_router_status($session, $state, $error = '', $latencyMs = 0, $host = '', $username = '')
  {
    $session = (string) $session;
    if ($session == '' || !tikras_storage_available()) {
      return false;
    }

    try {
      $pdo = tikras_storage_pdo();
      $now = tikras_storage_now();
      $current = tikras_storage_router_status($session);
      if ($current && isset($current['updated_at']) && strtotime($current['updated_at']) > (time() - 15)) {
        $sameState = isset($current['last_state']) && $current['last_state'] == (string) $state;
        $sameError = isset($current['last_error']) && $current['last_error'] == (string) $error;
        if ($sameState && $sameError) {
          return true;
        }
      }

      $router = $pdo->prepare('
        INSERT OR IGNORE INTO routers(session, display_name, host, username, hotspot_name, dns_name, currency, api_port, default_iface, live_report, enabled, source_hash, created_at, updated_at)
        VALUES(:session, :display_name, :host, :username, :hotspot_name, :dns_name, :currency, 8728, "1", "disable", 1, :source_hash, :created_at, :updated_at)
      ');
      $router->execute(array(
        ':session' => $session,
        ':display_name' => $session,
        ':host' => (string) $host,
        ':username' => (string) $username,
        ':hotspot_name' => $session,
        ':dns_name' => '',
        ':currency' => '',
        ':source_hash' => sha1($session . '|' . $host . '|' . $username),
        ':created_at' => $now,
        ':updated_at' => $now,
      ));

      $exists = $pdo->prepare('SELECT session FROM router_status WHERE session = :session');
      $exists->execute(array(':session' => $session));
      if ($exists->fetch()) {
        $stmt = $pdo->prepare('
          UPDATE router_status
          SET last_state = :last_state, last_error = :last_error, last_latency_ms = :last_latency_ms,
              last_seen_at = :last_seen_at, updated_at = :updated_at
          WHERE session = :session
        ');
      } else {
        $stmt = $pdo->prepare('
          INSERT INTO router_status(session, last_state, last_error, last_latency_ms, last_seen_at, updated_at)
          VALUES(:session, :last_state, :last_error, :last_latency_ms, :last_seen_at, :updated_at)
        ');
      }
      $stmt->execute(array(
        ':session' => $session,
        ':last_state' => (string) $state,
        ':last_error' => (string) $error,
        ':last_latency_ms' => max(0, (int) $latencyMs),
        ':last_seen_at' => $state == 'online' ? $now : null,
        ':updated_at' => $now,
      ));
      return true;
    } catch (Exception $e) {
      tikras_log('storage.router_status.failed', array('session' => $session, 'error' => $e->getMessage()));
      return false;
    }
  }
}

if (!function_exists('tikras_storage_all_router_statuses')) {
  /* Map session -> derniere ligne router_status (health check automatique). */
  function tikras_storage_all_router_statuses()
  {
    if (!tikras_storage_available()) {
      return array();
    }
    try {
      $pdo = tikras_storage_pdo();
      $rows = $pdo->query('SELECT session, last_state, last_error, last_latency_ms, last_seen_at, updated_at FROM router_status')->fetchAll();
      $map = array();
      foreach ($rows as $row) {
        $map[(string) $row['session']] = $row;
      }
      return $map;
    } catch (Exception $e) {
      return array();
    }
  }
}

if (!function_exists('tikras_storage_batch_status')) {
  function tikras_storage_batch_status($syncRows)
  {
    if (!is_array($syncRows) || count($syncRows) < 1) {
      return 'created';
    }
    $ok = 0;
    $failed = 0;
    foreach ($syncRows as $row) {
      if (isset($row['ok']) && $row['ok']) {
        $ok++;
      } else {
        $failed++;
      }
    }
    if ($failed == 0) {
      return 'synced';
    }
    if ($ok > 0) {
      return 'partial';
    }
    return 'error';
  }
}

if (!function_exists('tikras_storage_record_ticket_batch')) {
  function tikras_storage_record_ticket_batch($sourceSession, $batchCode, $tickets, $params = array(), $syncRows = array(), $meta = array())
  {
    $result = array('ok' => false, 'tickets' => 0, 'routes' => 0, 'error' => '');
    if (!tikras_storage_available() || !is_array($tickets) || count($tickets) < 1) {
      return $result;
    }

    $sourceSession = (string) $sourceSession;
    $batchCode = trim((string) $batchCode);
    if ($batchCode == '') {
      $batchCode = 'batch-' . date('Ymd-His') . '-' . substr(sha1(json_encode($tickets)), 0, 8);
    }

    try {
      $pdo = tikras_storage_pdo();
      $now = tikras_storage_now();
      $profile = isset($params['profile']) ? (string) $params['profile'] : '';
      $price = isset($params['selling_price']) && $params['selling_price'] !== '' ? $params['selling_price'] : (isset($params['price']) ? $params['price'] : 0);
      $currency = isset($params['currency']) ? (string) $params['currency'] : '';
      $status = tikras_storage_batch_status($syncRows);

      $pdo->beginTransaction();
      $routerStub = $pdo->prepare('
        INSERT OR IGNORE INTO routers(session, display_name, host, username, hotspot_name, dns_name, currency, api_port, default_iface, live_report, enabled, source_hash, created_at, updated_at)
        VALUES(:session, :display_name, "", "", :hotspot_name, "", :currency, 8728, "1", "disable", 1, :source_hash, :created_at, :updated_at)
      ');
      $routerStub->execute(array(
        ':session' => $sourceSession,
        ':display_name' => $sourceSession,
        ':hotspot_name' => $sourceSession,
        ':currency' => $currency,
        ':source_hash' => sha1('stub|' . $sourceSession),
        ':created_at' => $now,
        ':updated_at' => $now,
      ));

      $batch = $pdo->prepare('
        INSERT OR REPLACE INTO roaming_batches(batch_code, profile, quantity, price, currency, status, created_by, created_at, note)
        VALUES(:batch_code, :profile, :quantity, :price, :currency, :status, :created_by, :created_at, :note)
      ');
      $batch->execute(array(
        ':batch_code' => $batchCode,
        ':profile' => $profile,
        ':quantity' => count($tickets),
        ':price' => (float) $price,
        ':currency' => $currency,
        ':status' => $status,
        ':created_by' => $sourceSession,
        ':created_at' => $now,
        ':note' => isset($params['comment']) ? (string) $params['comment'] : '',
      ));

      $ticketStmt = $pdo->prepare('
        INSERT OR REPLACE INTO tickets(ticket_code, username, password, profile, price, currency, comment, source_session, batch_code, status, created_at, expires_at, sold_at, raw_json)
        VALUES(:ticket_code, :username, :password, :profile, :price, :currency, :comment, :source_session, :batch_code, :status, :created_at, :expires_at, :sold_at, :raw_json)
      ');
      foreach ($tickets as $ticket) {
        $username = isset($ticket['username']) ? (string) $ticket['username'] : '';
        if ($username == '') {
          continue;
        }
        $password = isset($ticket['password']) ? (string) $ticket['password'] : '';
        $ticketStmt->execute(array(
          ':ticket_code' => $username,
          ':username' => $username,
          ':password' => $password,
          ':profile' => $profile,
          ':price' => (float) $price,
          ':currency' => $currency,
          ':comment' => isset($params['comment']) ? (string) $params['comment'] : '',
          ':source_session' => $sourceSession,
          ':batch_code' => $batchCode,
          ':status' => $status,
          ':created_at' => $now,
          ':expires_at' => null,
          ':sold_at' => null,
          ':raw_json' => json_encode(array('ticket' => $ticket, 'params' => $params, 'meta' => $meta)),
        ));
        $result['tickets']++;
      }

      if (is_array($syncRows)) {
        $routeStmt = $pdo->prepare('
          INSERT OR REPLACE INTO roaming_batch_routes(batch_code, session, sync_status, synced_at, error_message)
          VALUES(:batch_code, :session, :sync_status, :synced_at, :error_message)
        ');
        foreach ($syncRows as $row) {
          $target = isset($row['session']) ? (string) $row['session'] : '';
          if ($target == '') {
            continue;
          }
          $routerStub->execute(array(
            ':session' => $target,
            ':display_name' => $target,
            ':hotspot_name' => $target,
            ':currency' => $currency,
            ':source_hash' => sha1('stub|' . $target),
            ':created_at' => $now,
            ':updated_at' => $now,
          ));
          $rowOk = isset($row['ok']) && $row['ok'];
          $routeStmt->execute(array(
            ':batch_code' => $batchCode,
            ':session' => $target,
            ':sync_status' => $rowOk ? 'synced' : 'error',
            ':synced_at' => $rowOk ? $now : null,
            ':error_message' => isset($row['message']) ? (string) $row['message'] : '',
          ));
          $result['routes']++;
        }
      }

      $pdo->commit();
      tikras_storage_audit('tickets.batch_recorded', 'ticket_batch', $batchCode, $sourceSession, 'Lot de tickets enregistre.', $result);
      $result['ok'] = true;
    } catch (Exception $e) {
      if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $result['error'] = $e->getMessage();
      tikras_log('storage.record_ticket_batch.failed', array('batch' => $batchCode, 'error' => $result['error']));
    }

    return $result;
  }
}

if (!function_exists('tikras_storage_sale_datetime')) {
  function tikras_storage_sale_datetime($date, $time)
  {
    $date = trim((string) $date);
    $time = trim((string) $time);
    if ($date == '') {
      $date = date('Y-m-d');
    }
    if ($time == '') {
      $time = '00:00:00';
    }
    return $date . ' ' . $time;
  }
}

if (!function_exists('tikras_storage_replace_sales_cache')) {
  function tikras_storage_replace_sales_cache($session, $reportKey, $rows, $currency = '')
  {
    $result = array('ok' => false, 'rows' => 0, 'error' => '');
    $session = (string) $session;
    $reportKey = (string) $reportKey;
    if (!tikras_storage_available() || $session == '' || $reportKey == '' || !is_array($rows)) {
      return $result;
    }

    try {
      $pdo = tikras_storage_pdo();
      $pdo->beginTransaction();
      $delete = $pdo->prepare('DELETE FROM sales_cache WHERE session = :session AND report_month = :report_month');
      $delete->execute(array(':session' => $session, ':report_month' => $reportKey));

      $insert = $pdo->prepare('
        INSERT INTO sales_cache(session, ticket_code, username, profile, price, currency, sold_at, comment, report_month, source_hash, created_at)
        VALUES(:session, :ticket_code, :username, :profile, :price, :currency, :sold_at, :comment, :report_month, :source_hash, :created_at)
      ');
      $now = tikras_storage_now();
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $username = isset($row['username']) ? (string) $row['username'] : '';
        $soldAt = tikras_storage_sale_datetime(isset($row['date']) ? $row['date'] : '', isset($row['time']) ? $row['time'] : '');
        $hash = sha1($session . '|' . $reportKey . '|' . json_encode($row));
        $insert->execute(array(
          ':session' => $session,
          ':ticket_code' => $username,
          ':username' => $username,
          ':profile' => isset($row['profile']) ? (string) $row['profile'] : '',
          ':price' => isset($row['amount']) ? (float) $row['amount'] : 0,
          ':currency' => (string) $currency,
          ':sold_at' => $soldAt,
          ':comment' => isset($row['comment']) ? (string) $row['comment'] : '',
          ':report_month' => $reportKey,
          ':source_hash' => $hash,
          ':created_at' => $now,
        ));
        $result['rows']++;
      }
      $pdo->commit();
      $result['ok'] = true;
    } catch (Exception $e) {
      if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $result['error'] = $e->getMessage();
      tikras_log('storage.sales_cache.failed', array('session' => $session, 'report' => $reportKey, 'error' => $result['error']));
    }

    return $result;
  }
}

if (!function_exists('tikras_storage_ticket_filters')) {
  function tikras_storage_ticket_filters($filters, $tableAlias, &$params)
  {
    $where = array();
    $prefix = $tableAlias != '' ? $tableAlias . '.' : '';

    if (isset($filters['q']) && trim((string) $filters['q']) != '') {
      $params[':q'] = '%' . trim((string) $filters['q']) . '%';
      $where[] = '(' . $prefix . 'ticket_code LIKE :q OR ' . $prefix . 'username LIKE :q OR ' . $prefix . 'profile LIKE :q OR ' . $prefix . 'comment LIKE :q OR ' . $prefix . 'batch_code LIKE :q)';
    }
    if (isset($filters['status']) && trim((string) $filters['status']) != '') {
      $params[':ticket_status'] = trim((string) $filters['status']);
      $where[] = $prefix . 'status = :ticket_status';
    }
    if (isset($filters['session']) && trim((string) $filters['session']) != '') {
      $params[':ticket_session'] = trim((string) $filters['session']);
      $where[] = $prefix . 'source_session = :ticket_session';
    }
    if (isset($filters['batch']) && trim((string) $filters['batch']) != '') {
      $params[':ticket_batch'] = trim((string) $filters['batch']);
      $where[] = $prefix . 'batch_code = :ticket_batch';
    }

    return $where;
  }
}

if (!function_exists('tikras_storage_list_tickets')) {
  function tikras_storage_list_tickets($filters = array(), $limit = 100)
  {
    $limit = max(1, min(10000, (int) $limit));
    if (!tikras_storage_available()) {
      return array();
    }

    try {
      $pdo = tikras_storage_pdo();
      $params = array();
      $where = tikras_storage_ticket_filters($filters, 't', $params);
      $sql = '
        SELECT t.*, b.status AS batch_status, b.quantity AS batch_quantity
        FROM tickets t
        LEFT JOIN roaming_batches b ON b.batch_code = t.batch_code
      ';
      if (count($where) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
      }
      $sql .= ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . $limit;
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt->fetchAll();
    } catch (Exception $e) {
      tikras_log('storage.list_tickets.failed', array('error' => $e->getMessage()));
      return array();
    }
  }
}

if (!function_exists('tikras_storage_batch_filters')) {
  function tikras_storage_batch_filters($filters, &$params)
  {
    $where = array();
    if (isset($filters['q']) && trim((string) $filters['q']) != '') {
      $params[':batch_q'] = '%' . trim((string) $filters['q']) . '%';
      $where[] = '(b.batch_code LIKE :batch_q OR b.profile LIKE :batch_q OR b.note LIKE :batch_q OR b.created_by LIKE :batch_q)';
    }
    if (isset($filters['status']) && trim((string) $filters['status']) != '') {
      $params[':batch_status'] = trim((string) $filters['status']);
      $where[] = 'b.status = :batch_status';
    }
    if (isset($filters['session']) && trim((string) $filters['session']) != '') {
      $params[':batch_session'] = trim((string) $filters['session']);
      $where[] = '(b.created_by = :batch_session OR EXISTS (SELECT 1 FROM roaming_batch_routes rr WHERE rr.batch_code = b.batch_code AND rr.session = :batch_session))';
    }
    if (isset($filters['batch']) && trim((string) $filters['batch']) != '') {
      $params[':batch_code'] = trim((string) $filters['batch']);
      $where[] = 'b.batch_code = :batch_code';
    }
    return $where;
  }
}

if (!function_exists('tikras_storage_list_ticket_batches')) {
  function tikras_storage_list_ticket_batches($filters = array(), $limit = 80)
  {
    $limit = max(1, min(1000, (int) $limit));
    if (!tikras_storage_available()) {
      return array();
    }

    try {
      $pdo = tikras_storage_pdo();
      $params = array();
      $where = tikras_storage_batch_filters($filters, $params);
      $sql = '
        SELECT b.*,
          COUNT(DISTINCT t.id) AS ticket_count,
          COUNT(DISTINCT r.session) AS route_count,
          SUM(CASE WHEN r.sync_status = "synced" THEN 1 ELSE 0 END) AS route_synced,
          SUM(CASE WHEN r.sync_status != "synced" THEN 1 ELSE 0 END) AS route_error
        FROM roaming_batches b
        LEFT JOIN tickets t ON t.batch_code = b.batch_code
        LEFT JOIN roaming_batch_routes r ON r.batch_code = b.batch_code
      ';
      if (count($where) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
      }
      $sql .= ' GROUP BY b.batch_code ORDER BY b.created_at DESC LIMIT ' . $limit;
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt->fetchAll();
    } catch (Exception $e) {
      tikras_log('storage.list_ticket_batches.failed', array('error' => $e->getMessage()));
      return array();
    }
  }
}

if (!function_exists('tikras_storage_routes_by_batch')) {
  function tikras_storage_routes_by_batch($batchCodes)
  {
    $routes = array();
    if (!tikras_storage_available() || !is_array($batchCodes) || count($batchCodes) < 1) {
      return $routes;
    }

    $clean = array();
    foreach ($batchCodes as $code) {
      $code = trim((string) $code);
      if ($code != '') {
        $clean[$code] = $code;
      }
    }
    if (count($clean) < 1) {
      return $routes;
    }

    try {
      $pdo = tikras_storage_pdo();
      $marks = array();
      $params = array();
      $i = 0;
      foreach ($clean as $code) {
        $key = ':b' . $i;
        $marks[] = $key;
        $params[$key] = $code;
        $i++;
      }
      $stmt = $pdo->prepare('
        SELECT r.*, ro.display_name, ro.hotspot_name
        FROM roaming_batch_routes r
        LEFT JOIN routers ro ON ro.session = r.session
        WHERE r.batch_code IN (' . implode(',', $marks) . ')
        ORDER BY r.batch_code, r.session
      ');
      $stmt->execute($params);
      while ($row = $stmt->fetch()) {
        $batch = $row['batch_code'];
        if (!isset($routes[$batch])) {
          $routes[$batch] = array();
        }
        $routes[$batch][] = $row;
      }
    } catch (Exception $e) {
      tikras_log('storage.routes_by_batch.failed', array('error' => $e->getMessage()));
    }

    return $routes;
  }
}

if (!function_exists('tikras_storage_ticket_stats')) {
  function tikras_storage_ticket_stats()
  {
    $stats = array(
      'tickets' => 0,
      'batches' => 0,
      'synced_batches' => 0,
      'error_batches' => 0,
      'routes' => 0,
      'sales_cache' => 0,
    );
    if (!tikras_storage_available()) {
      return $stats;
    }
    try {
      $pdo = tikras_storage_pdo();
      $stats['tickets'] = (int) $pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
      $stats['batches'] = (int) $pdo->query('SELECT COUNT(*) FROM roaming_batches')->fetchColumn();
      $stats['synced_batches'] = (int) $pdo->query('SELECT COUNT(*) FROM roaming_batches WHERE status = "synced"')->fetchColumn();
      $stats['error_batches'] = (int) $pdo->query('SELECT COUNT(*) FROM roaming_batches WHERE status IN ("error", "partial")')->fetchColumn();
      $stats['routes'] = (int) $pdo->query('SELECT COUNT(*) FROM roaming_batch_routes')->fetchColumn();
      $stats['sales_cache'] = (int) $pdo->query('SELECT COUNT(*) FROM sales_cache')->fetchColumn();
    } catch (Exception $e) {
      tikras_log('storage.ticket_stats.failed', array('error' => $e->getMessage()));
    }
    return $stats;
  }
}

if (!function_exists('tikras_storage_log_severity')) {
  function tikras_storage_log_severity($topics)
  {
    $topics = strtolower((string) $topics);
    if (strpos($topics, 'critical') !== false || strpos($topics, 'error') !== false) {
      return 'error';
    }
    if (strpos($topics, 'warning') !== false) {
      return 'warning';
    }
    if (strpos($topics, 'debug') !== false) {
      return 'debug';
    }
    return 'info';
  }
}

if (!function_exists('tikras_storage_record_routeros_logs')) {
  function tikras_storage_record_routeros_logs($session, $rows)
  {
    $result = array('ok' => false, 'inserted' => 0, 'error' => '');
    $session = (string) $session;
    if (!tikras_storage_available() || $session == '' || !is_array($rows) || count($rows) < 1) {
      return $result;
    }

    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare('
        INSERT OR IGNORE INTO routeros_logs(session, topic, severity, message, router_time, captured_at, raw_json)
        VALUES(:session, :topic, :severity, :message, :router_time, :captured_at, :raw_json)
      ');
      $capturedAt = tikras_storage_now();
      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }
        $topic = isset($row['topics']) ? (string) $row['topics'] : '';
        $message = isset($row['message']) ? (string) $row['message'] : '';
        $routerTime = isset($row['time']) ? (string) $row['time'] : '';
        if ($topic == '' && $message == '') {
          continue;
        }
        $stmt->execute(array(
          ':session' => $session,
          ':topic' => $topic,
          ':severity' => tikras_storage_log_severity($topic),
          ':message' => $message,
          ':router_time' => $routerTime,
          ':captured_at' => $capturedAt,
          ':raw_json' => json_encode($row),
        ));
        $result['inserted'] += $stmt->rowCount();
      }
      $result['ok'] = true;
    } catch (Exception $e) {
      $result['error'] = $e->getMessage();
      tikras_log('storage.record_routeros_logs.failed', array('session' => $session, 'error' => $result['error']));
    }
    return $result;
  }
}

if (!function_exists('tikras_storage_list_routeros_logs')) {
  function tikras_storage_list_routeros_logs($session, $topic = '', $limit = 300)
  {
    $session = (string) $session;
    $limit = max(1, min(2000, (int) $limit));
    if (!tikras_storage_available() || $session == '') {
      return array();
    }

    try {
      $pdo = tikras_storage_pdo();
      $params = array(':session' => $session);
      $where = 'WHERE session = :session';
      if (trim((string) $topic) != '') {
        $params[':topic'] = '%' . trim((string) $topic) . '%';
        $where .= ' AND topic LIKE :topic';
      }
      $stmt = $pdo->prepare('
        SELECT router_time AS time, topic AS topics, message, captured_at, severity
        FROM routeros_logs
        ' . $where . '
        ORDER BY id DESC
        LIMIT ' . $limit
      );
      $stmt->execute($params);
      return $stmt->fetchAll();
    } catch (Exception $e) {
      tikras_log('storage.list_routeros_logs.failed', array('session' => $session, 'error' => $e->getMessage()));
      return array();
    }
  }
}

if (!function_exists('tikras_storage_health')) {
  function tikras_storage_health()
  {
    $path = tikras_storage_path();
    $health = array(
      'available' => tikras_storage_available(),
      'path' => $path,
      'exists' => is_file($path),
      'size' => is_file($path) ? filesize($path) : 0,
      'version' => 0,
      'tables' => array(),
      'error' => '',
    );

    if (!$health['available']) {
      $health['error'] = 'Extension pdo_sqlite indisponible.';
      return $health;
    }

    try {
      $pdo = tikras_storage_pdo();
      $health['exists'] = is_file($path);
      $health['size'] = is_file($path) ? filesize($path) : 0;
      $health['version'] = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
      $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
      while ($row = $stmt->fetch()) {
        $health['tables'][] = $row['name'];
      }
    } catch (Exception $e) {
      $health['error'] = $e->getMessage();
    }

    return $health;
  }
}

if (!function_exists('tikras_storage_audit')) {
  function tikras_storage_audit($action, $entityType = '', $entityId = '', $session = '', $message = '', $context = array())
  {
    if (!tikras_storage_available()) {
      return false;
    }
    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare('
        INSERT INTO audit_logs(actor, action, entity_type, entity_id, session, ip, message, context_json, created_at)
        VALUES(:actor, :action, :entity_type, :entity_id, :session, :ip, :message, :context_json, :created_at)
      ');
      $actor = isset($_SESSION['mikhmon']) ? $_SESSION['mikhmon'] : '';
      $stmt->execute(array(
        ':actor' => (string) $actor,
        ':action' => (string) $action,
        ':entity_type' => (string) $entityType,
        ':entity_id' => (string) $entityId,
        ':session' => (string) $session,
        ':ip' => tikras_server('REMOTE_ADDR', ''),
        ':message' => (string) $message,
        ':context_json' => json_encode($context),
        ':created_at' => tikras_storage_now(),
      ));
      return true;
    } catch (Exception $e) {
      tikras_log('storage.audit.failed', array('error' => $e->getMessage(), 'action' => $action));
      return false;
    }
  }
}

if (!function_exists('tikras_storage_list_audit_logs')) {
  function tikras_storage_list_audit_logs($filters = array(), $limit = 200)
  {
    if (!tikras_storage_available()) {
      return array();
    }

    $limit = max(20, min(500, (int) $limit));
    $where = array();
    $params = array();

    $action = isset($filters['action']) ? trim((string) $filters['action']) : '';
    if ($action != '') {
      $where[] = 'action LIKE :action';
      $params[':action'] = '%' . $action . '%';
    }

    $session = isset($filters['session']) ? trim((string) $filters['session']) : '';
    if ($session != '') {
      $where[] = 'session = :session';
      $params[':session'] = $session;
    }

    $entityType = isset($filters['entity_type']) ? trim((string) $filters['entity_type']) : '';
    if ($entityType != '') {
      $where[] = 'entity_type = :entity_type';
      $params[':entity_type'] = $entityType;
    }

    $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
    if ($q != '') {
      $where[] = '(entity_id LIKE :q OR message LIKE :q OR context_json LIKE :q)';
      $params[':q'] = '%' . $q . '%';
    }

    $sql = 'SELECT * FROM audit_logs';
    if (count($where) > 0) {
      $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;

    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt->fetchAll();
    } catch (Exception $e) {
      tikras_log('storage.list_audit_logs.failed', array('error' => $e->getMessage()));
      return array();
    }
  }
}

if (!function_exists('tikras_storage_audit_stats')) {
  function tikras_storage_audit_stats()
  {
    $stats = array('total' => 0, 'today' => 0, 'backup' => 0, 'router' => 0);
    if (!tikras_storage_available()) {
      return $stats;
    }
    try {
      $pdo = tikras_storage_pdo();
      $stats['total'] = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
      $stats['today'] = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at >= datetime('now', '-1 day')")->fetchColumn();
      $stats['backup'] = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'backup.%'")->fetchColumn();
      $stats['router'] = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entity_type = 'router'")->fetchColumn();
    } catch (Exception $e) {
      tikras_log('storage.audit_stats.failed', array('error' => $e->getMessage()));
    }
    return $stats;
  }
}
?>
