<?php
/*
 * TIKRAS IT roaming ticket helpers.
 * Synchronizes the same Hotspot ticket batch to multiple configured MikroTik sessions.
 */
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -18) == "tikras_roaming.php") {
  header("Location:../");
  exit;
}
include_once(dirname(__FILE__) . '/tikras_routeros.php');

if (!function_exists('tikras_roaming_h')) {
  function tikras_roaming_h($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('tikras_roaming_config_part')) {
  function tikras_roaming_config_part($data, $session, $index, $separator)
  {
    if (!isset($data[$session]) || !isset($data[$session][$index])) {
      return "";
    }
    $parts = explode($separator, $data[$session][$index], 2);
    return isset($parts[1]) ? $parts[1] : "";
  }
}

if (!function_exists('tikras_roaming_sessions')) {
  function tikras_roaming_sessions($data)
  {
    $sessions = array();
    if (!is_array($data)) {
      return $sessions;
    }
    foreach ($data as $name => $row) {
      if ($name == "" || $name == "mikhmon" || !is_array($row)) {
        continue;
      }
      $sessions[] = $name;
    }
    sort($sessions);
    return $sessions;
  }
}

if (!function_exists('tikras_roaming_clean_mode')) {
  function tikras_roaming_clean_mode($mode)
  {
    $mode = trim((string) $mode);
    if ($mode == "all" || $mode == "selected") {
      return $mode;
    }
    return "local";
  }
}

if (!function_exists('tikras_roaming_target_sessions')) {
  function tikras_roaming_target_sessions($data, $currentSession, $mode, $selected)
  {
    $all = tikras_roaming_sessions($data);
    $targets = array();
    $seen = array();

    if ($currentSession != "" && isset($data[$currentSession])) {
      $targets[] = $currentSession;
      $seen[$currentSession] = true;
    }

    if ($mode == "all") {
      $selected = $all;
    } elseif ($mode != "selected") {
      $selected = array();
    }

    if (!is_array($selected)) {
      $selected = array($selected);
    }

    foreach ($selected as $sessionName) {
      $sessionName = trim((string) $sessionName);
      if ($sessionName == "" || $sessionName == "mikhmon" || !isset($data[$sessionName]) || isset($seen[$sessionName])) {
        continue;
      }
      $targets[] = $sessionName;
      $seen[$sessionName] = true;
    }

    return $targets;
  }
}

if (!function_exists('tikras_roaming_session_config')) {
  function tikras_roaming_session_config($data, $session)
  {
    return array(
      "session" => $session,
      "ip" => tikras_roaming_config_part($data, $session, 1, "!"),
      "user" => tikras_roaming_config_part($data, $session, 2, "@|@"),
      "password" => tikras_roaming_config_part($data, $session, 3, "#|#"),
      "hotspot" => tikras_roaming_config_part($data, $session, 4, "%"),
    );
  }
}

if (!function_exists('tikras_roaming_has_trap')) {
  function tikras_roaming_has_trap($response)
  {
    return is_array($response) && isset($response['!trap']);
  }
}

if (!function_exists('tikras_roaming_raw_has_trap')) {
  /*
   * Meme controle, sur une reponse non analysee. commBatch rend les mots
   * bruts du routeur: "!trap" y est une valeur de la liste, pas une cle,
   * et tikras_roaming_has_trap ne le verrait pas.
   */
  function tikras_roaming_raw_has_trap($mots)
  {
    return is_array($mots) && in_array('!trap', $mots, true);
  }
}

if (!function_exists('tikras_roaming_profile_payload')) {
  function tikras_roaming_profile_payload($profileRow)
  {
    $payload = array();
    if (!is_array($profileRow)) {
      return $payload;
    }

    $allowed = array(
      "address-pool",
      "idle-timeout",
      "keepalive-timeout",
      "status-autorefresh",
      "shared-users",
      "add-mac-cookie",
      "mac-cookie-timeout",
      "rate-limit",
      "parent-queue",
      "queue-type",
      "insert-queue-before",
      "incoming-packet-mark",
      "outgoing-packet-mark",
      "transparent-proxy",
      "open-status-page",
      "advertise",
      "advertise-url",
      "advertise-interval",
      "advertise-timeout",
      "on-login",
      "on-logout",
    );

    foreach ($allowed as $key) {
      if (isset($profileRow[$key]) && $profileRow[$key] !== "") {
        $payload[$key] = $profileRow[$key];
      }
    }

    return $payload;
  }
}

if (!function_exists('tikras_roaming_ensure_profile')) {
  function tikras_roaming_ensure_profile($api, $profile, $payload)
  {
    $profile = trim((string) $profile);
    if ($profile == "") {
      return false;
    }

    $existing = $api->comm("/ip/hotspot/user/profile/print", array("?name" => $profile));
    if (is_array($existing) && isset($existing[0]['name'])) {
      return true;
    }

    $create = is_array($payload) ? $payload : array();
    $create['name'] = $profile;
    $response = $api->comm("/ip/hotspot/user/profile/add", $create);
    if (!tikras_roaming_has_trap($response)) {
      return true;
    }

    $response = $api->comm("/ip/hotspot/user/profile/add", array("name" => $profile));
    return !tikras_roaming_has_trap($response);
  }
}

if (!function_exists('tikras_roaming_server_for_router')) {
  function tikras_roaming_server_for_router($api, $server)
  {
    $server = trim((string) $server);
    if ($server == "" || strtolower($server) == "all") {
      return "all";
    }
    $found = $api->comm("/ip/hotspot/print", array("?name" => $server));
    if (is_array($found) && isset($found[0]['name'])) {
      return $server;
    }
    return "all";
  }
}

if (!function_exists('tikras_roaming_check_one_router')) {
  function tikras_roaming_check_one_router($api, $sessionName, $hotspotLabel, $params)
  {
    $status = array(
      "session" => $sessionName,
      "hotspot" => $hotspotLabel,
      "ok" => false,
      "warning" => false,
      "api" => "OK",
      "profile" => "",
      "server" => "",
      "message" => "",
    );

    $profile = isset($params['profile']) ? trim((string) $params['profile']) : "";
    $server = isset($params['server']) ? trim((string) $params['server']) : "all";
    $syncProfile = isset($params['sync_profile']) ? $params['sync_profile'] : "yes";
    $profilePayload = isset($params['profile_payload']) ? $params['profile_payload'] : array();

    if ($profile == "") {
      $status['profile'] = "Profil vide";
      $status['message'] = "Choisir un profil Hotspot";
      return $status;
    }

    $profileRow = $api->comm("/ip/hotspot/user/profile/print", array("?name" => $profile));
    $profileReady = is_array($profileRow) && isset($profileRow[0]['name']);
    if ($profileReady) {
      $status['profile'] = "OK";
    } elseif ($syncProfile == "yes") {
      $status['profile'] = count($profilePayload) > 0 ? "A creer" : "A creer minimal";
      $status['warning'] = true;
    } else {
      $status['profile'] = "Absent";
      $status['message'] = "Profil absent et creation automatique inactive";
      return $status;
    }

    $hotspots = $api->comm("/ip/hotspot/print");
    if (!is_array($hotspots) || count($hotspots) < 1) {
      $status['server'] = "Aucun Hotspot";
      $status['message'] = "Aucun serveur Hotspot detecte";
      return $status;
    }

    if ($server == "" || strtolower($server) == "all") {
      $status['server'] = "all";
    } else {
      $found = $api->comm("/ip/hotspot/print", array("?name" => $server));
      if (is_array($found) && isset($found[0]['name'])) {
        $status['server'] = "OK";
      } else {
        $status['server'] = "Introuvable, utilisera all";
        $status['warning'] = true;
      }
    }

    $status['ok'] = true;
    if ($status['warning']) {
      $status['message'] = "Pret avec avertissement";
    } else {
      $status['message'] = "Pret";
    }
    return $status;
  }
}

if (!function_exists('tikras_roaming_check_targets')) {
  function tikras_roaming_check_targets($data, $targetSessions, $currentSession, $currentApi, $params)
  {
    $results = array();
    if (!is_array($targetSessions) || count($targetSessions) < 1) {
      return $results;
    }

    foreach ($targetSessions as $targetSession) {
      $cfg = tikras_roaming_session_config($data, $targetSession);
      $hotspotLabel = $cfg['hotspot'] != "" ? $cfg['hotspot'] : $targetSession;

      if ($targetSession == $currentSession) {
        $results[] = tikras_roaming_check_one_router($currentApi, $targetSession, $hotspotLabel, $params);
        continue;
      }

      $status = array(
        "session" => $targetSession,
        "hotspot" => $hotspotLabel,
        "ok" => false,
        "warning" => false,
        "api" => "Erreur",
        "profile" => "-",
        "server" => "-",
        "message" => "Connexion impossible",
      );

      if ($cfg['ip'] == "" || $cfg['user'] == "") {
        $status['message'] = "Configuration incomplete";
        $results[] = $status;
        continue;
      }

      $api = tikras_routeros_create();
      $api->attempts = 1;
      $api->delay = 0;
      $api->timeout = 2;
      if (!tikras_routeros_connect($api, $cfg['ip'], $cfg['user'], decrypt($cfg['password']), $targetSession, array('timeout' => 2, 'cooldown' => 15))) {
        $results[] = $status;
        continue;
      }

      $status = tikras_roaming_check_one_router($api, $targetSession, $hotspotLabel, $params);
      tikras_routeros_disconnect($api);
      $results[] = $status;
    }

    return $results;
  }
}

if (!function_exists('tikras_roaming_sync_one_router')) {
  function tikras_roaming_sync_one_router($api, $sessionName, $hotspotLabel, $tickets, $params)
  {
    $status = array(
      "session" => $sessionName,
      "hotspot" => $hotspotLabel,
      "ok" => false,
      "created" => 0,
      "updated" => 0,
      "failed" => 0,
      "queued" => 0,
      "message" => "",
    );

    $profile = isset($params['profile']) ? $params['profile'] : "";
    $profileRow = $api->comm("/ip/hotspot/user/profile/print", array("?name" => $profile));
    if (!is_array($profileRow) || !isset($profileRow[0]['name'])) {
      $syncProfile = isset($params['sync_profile']) ? $params['sync_profile'] : "yes";
      if ($syncProfile == "yes") {
        $profilePayload = isset($params['profile_payload']) ? $params['profile_payload'] : array();
        tikras_roaming_ensure_profile($api, $profile, $profilePayload);
        $profileRow = $api->comm("/ip/hotspot/user/profile/print", array("?name" => $profile));
      }
      if (!is_array($profileRow) || !isset($profileRow[0]['name'])) {
        $status['message'] = "Profil absent: " . $profile;
        $status['failed'] = is_array($tickets) ? count($tickets) : 0;
        return $status;
      }
    }

    $server = tikras_roaming_server_for_router($api, isset($params['server']) ? $params['server'] : "all");
    $timelimit = isset($params['timelimit']) ? $params['timelimit'] : "0";
    $datalimit = isset($params['datalimit']) ? $params['datalimit'] : "0";
    $comment = isset($params['comment']) ? $params['comment'] : "";
    $sourceSession = isset($params['source_session']) ? (string) $params['source_session'] : "";
    $ticketsAreNewOnSource = isset($params['tickets_are_new_on_source']) && $params['tickets_are_new_on_source'] == "yes";
    $skipExistingLookup = $ticketsAreNewOnSource && $sourceSession != "" && $sourceSession == $sessionName;
    $existingUsers = array();
    if (!$skipExistingLookup) {
      $existingRows = tikras_routeros_comm($api, "/ip/hotspot/user/print", array(".proplist" => ".id,name"), array());
      if (is_array($existingRows)) {
        foreach ($existingRows as $existingRow) {
          if (isset($existingRow['name']) && $existingRow['name'] != "") {
            $existingUsers[(string) $existingRow['name']] = isset($existingRow['.id']) ? $existingRow['.id'] : "";
          }
        }
      }
    }

    /*
     * Les tickets partent par tranches, toutes les commandes d'une tranche
     * etant envoyees avant qu'on lise la moindre reponse.
     *
     * Un comm() par ticket payait un aller-retour complet chacun. Sur ce parc
     * un aller-retour vaut environ 300 ms de latence reseau: 100 tickets
     * prenaient 30 s, 300 tickets 95 s, soit le seuil ou le bord Cloudflare
     * coupe et rend sa propre page d'erreur. Groupees, les memes commandes ne
     * paient la latence qu'une fois par tranche - mesure a x29 sur 30
     * commandes.
     *
     * La tranche reste petite a dessein. Envoyer des milliers de commandes
     * sans jamais lire remplirait les tampons des deux cotes: le routeur
     * bloquerait en ecrivant ses reponses pendant que nous bloquerions en
     * ecrivant nos commandes, et personne ne lirait. Cent commandes tiennent
     * largement dans les tampons.
     */
    $aEnvoyer = array();
    for ($i = 0; $i < count($tickets); $i++) {
      $username = isset($tickets[$i]['username']) ? $tickets[$i]['username'] : "";
      $password = isset($tickets[$i]['password']) ? $tickets[$i]['password'] : "";
      if ($username == "") {
        $status['failed']++;
        continue;
      }

      if (isset($existingUsers[$username]) && $existingUsers[$username] != "") {
        $aEnvoyer[] = array(
          'type' => 'set',
          'username' => $username,
          'cmd' => "/ip/hotspot/user/set",
          'args' => array(
            ".id" => $existingUsers[$username],
            "server" => $server,
            "password" => $password,
            "profile" => $profile,
            "limit-uptime" => $timelimit,
            "limit-bytes-total" => $datalimit,
            "comment" => $comment,
            "disabled" => "no",
          ),
        );
      } else {
        $aEnvoyer[] = array(
          'type' => 'add',
          'username' => $username,
          'cmd' => "/ip/hotspot/user/add",
          'args' => array(
            "server" => $server,
            "name" => $username,
            "password" => $password,
            "profile" => $profile,
            "limit-uptime" => $timelimit,
            "limit-bytes-total" => $datalimit,
            "comment" => $comment,
          ),
        );
        // Marque tout de suite: deux tickets homonymes dans le meme lot ne
        // doivent pas produire deux ajouts.
        $existingUsers[$username] = true;
      }
    }

    $tranches = array_chunk($aEnvoyer, 100);
    foreach ($tranches as $tranche) {
      $reponses = $api->commBatch($tranche);
      for ($j = 0; $j < count($tranche); $j++) {
        $reponse = isset($reponses[$j]) ? $reponses[$j] : array();
        if (tikras_roaming_raw_has_trap($reponse)) {
          $status['failed']++;
          if ($tranche[$j]['type'] == 'add') {
            unset($existingUsers[$tranche[$j]['username']]);
          }
        } elseif ($tranche[$j]['type'] == 'set') {
          $status['updated']++;
        } else {
          $status['created']++;
        }
      }
    }

    $status['ok'] = $status['failed'] == 0;
    $status['message'] = $status['ok'] ? "Synchronise" : "Synchronise avec erreurs";
    return $status;
  }
}

if (!function_exists('tikras_roaming_sync_tickets')) {
  function tikras_roaming_sync_tickets($data, $targetSessions, $currentSession, $currentApi, $tickets, $params)
  {
    $results = array();
    if (!is_array($targetSessions) || count($targetSessions) < 1 || !is_array($tickets) || count($tickets) < 1) {
      return $results;
    }

    foreach ($targetSessions as $targetSession) {
      $cfg = tikras_roaming_session_config($data, $targetSession);
      $hotspotLabel = $cfg['hotspot'] != "" ? $cfg['hotspot'] : $targetSession;

      if ($targetSession == $currentSession) {
        $results[] = tikras_roaming_sync_one_router($currentApi, $targetSession, $hotspotLabel, $tickets, $params);
        continue;
      }

      $status = array(
        "session" => $targetSession,
        "hotspot" => $hotspotLabel,
        "ok" => false,
        "created" => 0,
        "updated" => 0,
        "queued" => 0,
        "failed" => count($tickets),
        "message" => "Connexion impossible",
      );

      if ($cfg['ip'] == "" || $cfg['user'] == "") {
        $status['message'] = "Configuration incomplete";
        $results[] = $status;
        continue;
      }

      $api = tikras_routeros_create();
      $api->attempts = 1;
      $api->delay = 0;
      $api->timeout = 2;
      if (!tikras_routeros_connect($api, $cfg['ip'], $cfg['user'], decrypt($cfg['password']), $targetSession, array('timeout' => 2, 'cooldown' => 15))) {
        $results[] = $status;
        continue;
      }

      $status = tikras_roaming_sync_one_router($api, $targetSession, $hotspotLabel, $tickets, $params);
      tikras_routeros_disconnect($api);
      $results[] = $status;
    }

    return $results;
  }
}

if (!function_exists('tikras_roaming_queue_file')) {
  function tikras_roaming_queue_file()
  {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . "voucher" . DIRECTORY_SEPARATOR . "roaming_queue.php";
  }
}

if (!function_exists('tikras_roaming_encode_data')) {
  function tikras_roaming_encode_data($value)
  {
    $serialized = serialize($value);
    if (function_exists('encrypt')) {
      return encrypt($serialized);
    }
    return base64_encode($serialized);
  }
}

if (!function_exists('tikras_roaming_decode_data')) {
  function tikras_roaming_decode_data($value)
  {
    $value = (string) $value;
    if ($value == "") {
      return array();
    }
    $decoded = function_exists('decrypt') ? @decrypt($value) : @base64_decode($value);
    $data = @unserialize($decoded);
    return is_array($data) ? $data : array();
  }
}

if (!function_exists('tikras_roaming_queue_read')) {
  function tikras_roaming_queue_read()
  {
    $file = tikras_roaming_queue_file();
    $queue = array("items" => array());
    if (!file_exists($file)) {
      return $queue;
    }

    $tikras_roaming_queue = "";
    include($file);
    if (isset($tikras_roaming_queue) && $tikras_roaming_queue != "") {
      $decoded = tikras_roaming_decode_data($tikras_roaming_queue);
      if (isset($decoded['items']) && is_array($decoded['items'])) {
        return $decoded;
      }
    }

    return $queue;
  }
}

if (!function_exists('tikras_roaming_queue_write')) {
  function tikras_roaming_queue_write($queue)
  {
    if (!isset($queue['items']) || !is_array($queue['items'])) {
      $queue = array("items" => array());
    }
    $file = tikras_roaming_queue_file();
    $encoded = tikras_roaming_encode_data($queue);
    $content = '<?php $tikras_roaming_queue="' . addslashes($encoded) . '";?>';
    return file_put_contents($file, $content, LOCK_EX) !== false;
  }
}

if (!function_exists('tikras_roaming_queue_summary')) {
  function tikras_roaming_queue_summary()
  {
    $queue = tikras_roaming_queue_read();
    $items = isset($queue['items']) && is_array($queue['items']) ? $queue['items'] : array();
    $tickets = 0;
    $targets = 0;
    foreach ($items as $item) {
      if (isset($item['tickets']) && is_array($item['tickets'])) {
        $tickets += count($item['tickets']);
      }
      if (isset($item['targets']) && is_array($item['targets'])) {
        $targets += count($item['targets']);
      }
    }
    return array("items" => count($items), "tickets" => $tickets, "targets" => $targets);
  }
}

if (!function_exists('tikras_roaming_queue_failed')) {
  function tikras_roaming_queue_failed($sourceSession, $currentSession, $results, $tickets, $params)
  {
    $failedTargets = array();
    if (!is_array($results) || !is_array($tickets) || count($tickets) < 1) {
      return array();
    }

    foreach ($results as $row) {
      $target = isset($row['session']) ? $row['session'] : "";
      $ok = isset($row['ok']) && $row['ok'];
      if ($target == "" || $target == $currentSession || $ok) {
        continue;
      }
      $failedTargets[] = $target;
    }

    if (count($failedTargets) < 1) {
      return array();
    }

    $queue = tikras_roaming_queue_read();
    if (!isset($queue['items']) || !is_array($queue['items'])) {
      $queue['items'] = array();
    }

    $item = array(
      "id" => date("YmdHis") . "-" . mt_rand(1000, 9999),
      "created_at" => date("Y-m-d H:i:s"),
      "source_session" => $sourceSession,
      "targets" => $failedTargets,
      "tickets" => $tickets,
      "params" => $params,
      "attempts" => 0,
      "last_status" => $results,
    );

    $queue['items'][] = $item;
    while (count($queue['items']) > 30) {
      array_shift($queue['items']);
    }

    tikras_roaming_queue_write($queue);
    return array(
      "id" => $item['id'],
      "targets" => $failedTargets,
      "tickets" => count($tickets),
    );
  }
}

if (!function_exists('tikras_roaming_retry_queue')) {
  function tikras_roaming_retry_queue($data, $currentSession, $currentApi, $limit)
  {
    $queue = tikras_roaming_queue_read();
    $items = isset($queue['items']) && is_array($queue['items']) ? $queue['items'] : array();
    $kept = array();
    $results = array();
    $processed = 0;

    foreach ($items as $item) {
      if ($processed >= $limit) {
        $kept[] = $item;
        continue;
      }

      $targets = isset($item['targets']) && is_array($item['targets']) ? $item['targets'] : array();
      $tickets = isset($item['tickets']) && is_array($item['tickets']) ? $item['tickets'] : array();
      $params = isset($item['params']) && is_array($item['params']) ? $item['params'] : array();
      if (count($targets) < 1 || count($tickets) < 1) {
        continue;
      }

      $sync = tikras_roaming_sync_tickets($data, $targets, $currentSession, $currentApi, $tickets, $params);
      $processed++;
      $stillFailed = array();
      foreach ($sync as $row) {
        $target = isset($row['session']) ? $row['session'] : "";
        $ok = isset($row['ok']) && $row['ok'];
        $results[] = $row;
        if ($target != "" && !$ok) {
          $stillFailed[] = $target;
        }
      }

      if (count($stillFailed) > 0) {
        $item['targets'] = $stillFailed;
        $item['attempts'] = isset($item['attempts']) ? ((int) $item['attempts'] + 1) : 1;
        $item['last_attempt_at'] = date("Y-m-d H:i:s");
        $item['last_status'] = $sync;
        $kept[] = $item;
      }
    }

    $queue['items'] = $kept;
    tikras_roaming_queue_write($queue);

    return array(
      "processed" => $processed,
      "results" => $results,
      "remaining" => count($kept),
    );
  }
}
?>
