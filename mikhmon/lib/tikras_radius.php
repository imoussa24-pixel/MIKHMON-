<?php
/*
 * Central RADIUS/User Manager integration for roaming tickets.
 */
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -17) == "tikras_radius.php") {
  header("Location:../");
  exit;
}

include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_config_store.php');
include_once(dirname(__FILE__) . '/tikras_routeros.php');

if (!function_exists('tikras_radius_h')) {
  function tikras_radius_h($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('tikras_radius_config_file')) {
  function tikras_radius_config_file()
  {
    return tikras_config_local_dir() . DIRECTORY_SEPARATOR . 'radius.local.php';
  }
}

if (!function_exists('tikras_radius_defaults')) {
  function tikras_radius_defaults()
  {
    return array(
      'enabled' => 'no',
      'manager_session' => '',
      'shared_secret' => 'ChangeMeRadiusSecret',
      'server_address' => '',
      'auth_port' => '1812',
      'acct_port' => '1813',
      'timeout' => '3s',
      'default_engine' => 'radius',
      'auto_create_nas' => 'yes',
      'nas_scope' => 'all',
      'nas_sessions' => array(),
      'nas_address_mode' => 'configured_ip',
      'use_profiles' => 'yes',
      'hotspot_profile_attribute' => 'yes',
      'accounting_required' => 'yes',
    );
  }
}

if (!function_exists('tikras_radius_config')) {
  function tikras_radius_config()
  {
    $defaults = tikras_radius_defaults();
    $file = tikras_radius_config_file();
    if (!is_file($file)) {
      return $defaults;
    }

    $data = include($file);
    if (!is_array($data)) {
      return $defaults;
    }
    return array_merge($defaults, $data);
  }
}

if (!function_exists('tikras_radius_write_config')) {
  function tikras_radius_write_config($config)
  {
    if (!is_array($config)) {
      return false;
    }
    $dir = tikras_config_local_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
      return false;
    }
    $config = array_merge(tikras_radius_defaults(), $config);
    $file = tikras_radius_config_file();
    $content = "<?php\nreturn " . var_export($config, true) . ";\n";
    $tmp = $file . '.tmp-' . str_replace('.', '-', uniqid('', true));
    if (@file_put_contents($tmp, $content) === false) {
      return false;
    }
    if (@rename($tmp, $file)) {
      return true;
    }
    @unlink($file);
    if (@rename($tmp, $file)) {
      return true;
    }
    $ok = @file_put_contents($file, $content) !== false;
    @unlink($tmp);
    return $ok;
  }
}

if (!function_exists('tikras_radius_enabled')) {
  function tikras_radius_enabled($config = null)
  {
    if (!is_array($config)) {
      $config = tikras_radius_config();
    }
    return isset($config['enabled']) && $config['enabled'] == 'yes';
  }
}

if (!function_exists('tikras_radius_clean_engine')) {
  function tikras_radius_clean_engine($engine)
  {
    return $engine == 'radius' ? 'radius' : 'api';
  }
}

if (!function_exists('tikras_radius_yesno')) {
  function tikras_radius_yesno($value)
  {
    return $value == 'yes' ? 'yes' : 'no';
  }
}

if (!function_exists('tikras_radius_clean_number')) {
  function tikras_radius_clean_number($value, $default, $min, $max)
  {
    $value = (int) $value;
    if ($value < $min || $value > $max) {
      return (string) $default;
    }
    return (string) $value;
  }
}

if (!function_exists('tikras_radius_clean_interval')) {
  function tikras_radius_clean_interval($value, $default)
  {
    $value = trim((string) $value);
    if ($value == '' || !preg_match('/^[0-9]+[smhdw]?$/i', $value)) {
      return $default;
    }
    return $value;
  }
}

if (!function_exists('tikras_radius_clean_label')) {
  function tikras_radius_clean_label($value, $default)
  {
    $value = trim((string) $value);
    if ($value == '') {
      return $default;
    }
    return substr($value, 0, 80);
  }
}

if (!function_exists('tikras_radius_secret')) {
  function tikras_radius_secret($value)
  {
    $value = trim((string) $value);
    if ($value == '') {
      $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
      $value = '';
      for ($i = 0; $i < 24; $i++) {
        $value .= $chars[rand(0, strlen($chars) - 1)];
      }
    }
    return substr($value, 0, 80);
  }
}

if (!function_exists('tikras_radius_config_part')) {
  function tikras_radius_config_part($data, $session, $index, $separator)
  {
    if (!is_array($data) || !isset($data[$session]) || !isset($data[$session][$index])) {
      return '';
    }
    $parts = explode($separator, (string) $data[$session][$index], 2);
    return isset($parts[1]) ? $parts[1] : '';
  }
}

if (!function_exists('tikras_radius_session_config')) {
  function tikras_radius_session_config($data, $session)
  {
    return array(
      'session' => (string) $session,
      'ip' => tikras_radius_config_part($data, $session, 1, '!'),
      'user' => tikras_radius_config_part($data, $session, 2, '@|@'),
      'password' => tikras_radius_config_part($data, $session, 3, '#|#'),
      'hotspot' => tikras_radius_config_part($data, $session, 4, '%'),
      'dns' => tikras_radius_config_part($data, $session, 5, '^'),
    );
  }
}

if (!function_exists('tikras_radius_sessions')) {
  function tikras_radius_sessions($data)
  {
    $sessions = array();
    if (!is_array($data)) {
      return $sessions;
    }
    foreach ($data as $name => $row) {
      if ($name == 'mikhmon' || !is_array($row)) {
        continue;
      }
      $sessions[] = (string) $name;
    }
    sort($sessions);
    return $sessions;
  }
}

if (!function_exists('tikras_radius_clean_nas_scope')) {
  function tikras_radius_clean_nas_scope($scope)
  {
    return $scope == 'selected' ? 'selected' : 'all';
  }
}

if (!function_exists('tikras_radius_clean_nas_sessions')) {
  function tikras_radius_clean_nas_sessions($selected, $available)
  {
    $clean = array();
    if (!is_array($selected) || !is_array($available)) {
      return $clean;
    }
    foreach ($selected as $session) {
      $session = trim((string) $session);
      if ($session != '' && in_array($session, $available) && !in_array($session, $clean)) {
        $clean[] = $session;
      }
    }
    sort($clean);
    return $clean;
  }
}

if (!function_exists('tikras_radius_target_nas_sessions')) {
  function tikras_radius_target_nas_sessions($data, $config = null)
  {
    if (!is_array($config)) {
      $config = tikras_radius_config();
    }
    $available = tikras_radius_sessions($data);
    $scope = isset($config['nas_scope']) ? tikras_radius_clean_nas_scope($config['nas_scope']) : 'all';
    if ($scope != 'selected') {
      return $available;
    }
    $selected = isset($config['nas_sessions']) && is_array($config['nas_sessions']) ? $config['nas_sessions'] : array();
    return tikras_radius_clean_nas_sessions($selected, $available);
  }
}

if (!function_exists('tikras_radius_has_trap')) {
  function tikras_radius_has_trap($response)
  {
    return is_array($response) && isset($response['!trap']);
  }
}

if (!function_exists('tikras_radius_manager_session')) {
  function tikras_radius_manager_session($config, $fallbackSession)
  {
    $manager = isset($config['manager_session']) ? trim((string) $config['manager_session']) : '';
    return $manager != '' ? $manager : (string) $fallbackSession;
  }
}

if (!function_exists('tikras_radius_connect_manager')) {
  function tikras_radius_connect_manager($data, $currentSession, $currentApi, $options = array())
  {
    $config = tikras_radius_config();
    $manager = tikras_radius_manager_session($config, $currentSession);
    $result = array(
      'ok' => false,
      'api' => null,
      'session' => $manager,
      'own' => false,
      'config' => $config,
      'message' => '',
    );

    if ($manager == '') {
      $result['message'] = 'Session User Manager non configuree';
      return $result;
    }

    if ($manager == $currentSession && is_object($currentApi) && isset($currentApi->connected) && $currentApi->connected) {
      $result['ok'] = true;
      $result['api'] = $currentApi;
      $result['own'] = false;
      return $result;
    }

    $cfg = tikras_radius_session_config($data, $manager);
    if ($cfg['ip'] == '' || $cfg['user'] == '') {
      $result['message'] = 'Configuration routeur centrale incomplete';
      return $result;
    }

    $api = tikras_routeros_create();
    $api->attempts = 1;
    $api->delay = 0;
    $api->timeout = isset($options['timeout']) ? (int) $options['timeout'] : 2;
    if (!tikras_routeros_connect($api, $cfg['ip'], $cfg['user'], decrypt($cfg['password']), $manager, array('timeout' => $api->timeout, 'cooldown' => 15))) {
      $result['message'] = 'Connexion User Manager impossible';
      return $result;
    }

    $result['ok'] = true;
    $result['api'] = $api;
    $result['own'] = true;
    return $result;
  }
}

if (!function_exists('tikras_radius_disconnect_manager')) {
  function tikras_radius_disconnect_manager($manager)
  {
    if (is_array($manager) && isset($manager['own']) && $manager['own'] && isset($manager['api'])) {
      tikras_routeros_disconnect($manager['api']);
    }
  }
}

if (!function_exists('tikras_radius_slug')) {
  function tikras_radius_slug($value, $fallback)
  {
    $value = trim((string) $value);
    $value = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $value);
    $value = trim($value, '-_.');
    if ($value == '') {
      $value = $fallback;
    }
    return substr($value, 0, 48);
  }
}

if (!function_exists('tikras_radius_um_profile_name')) {
  function tikras_radius_um_profile_name($hotspotProfile)
  {
    return 'mkh-' . tikras_radius_slug($hotspotProfile, 'profile');
  }
}

if (!function_exists('tikras_radius_limitation_name')) {
  function tikras_radius_limitation_name($params)
  {
    $profile = isset($params['profile']) ? $params['profile'] : 'profile';
    $time = isset($params['timelimit']) ? $params['timelimit'] : '0';
    $data = isset($params['datalimit']) ? $params['datalimit'] : '0';
    return 'lim-' . tikras_radius_slug($profile . '-' . $time . '-' . $data, substr(sha1($profile . $time . $data), 0, 10));
  }
}

if (!function_exists('tikras_radius_price')) {
  function tikras_radius_price($value)
  {
    $value = preg_replace('/[^0-9.]/', '', (string) $value);
    return $value == '' ? '0' : $value;
  }
}

if (!function_exists('tikras_radius_validity')) {
  function tikras_radius_validity($value)
  {
    $value = trim((string) $value);
    if ($value == '' || $value == '-' || $value == '0') {
      return 'unlimited';
    }
    return $value;
  }
}

if (!function_exists('tikras_radius_uptime_limit')) {
  function tikras_radius_uptime_limit($value)
  {
    $value = trim((string) $value);
    if ($value == '' || $value == '-' || $value == '0') {
      return '00:00:00';
    }
    return $value;
  }
}

if (!function_exists('tikras_radius_data_limit')) {
  function tikras_radius_data_limit($value)
  {
    $value = (string) $value;
    if ($value == '' || $value == '-' || !is_numeric($value)) {
      return '0';
    }
    return (string) max(0, (int) $value);
  }
}

if (!function_exists('tikras_radius_profile_payload_rate')) {
  function tikras_radius_profile_payload_rate($params)
  {
    if (!isset($params['profile_payload']) || !is_array($params['profile_payload'])) {
      return '';
    }
    return isset($params['profile_payload']['rate-limit']) ? trim((string) $params['profile_payload']['rate-limit']) : '';
  }
}

if (!function_exists('tikras_radius_user_attributes')) {
  function tikras_radius_user_attributes($params, $config)
  {
    $attributes = array();
    $profile = isset($params['profile']) ? trim((string) $params['profile']) : '';
    if (isset($config['hotspot_profile_attribute']) && $config['hotspot_profile_attribute'] == 'yes' && $profile != '') {
      $attributes[] = 'Mikrotik-Group:' . $profile;
    }
    $rate = tikras_radius_profile_payload_rate($params);
    if ($rate != '') {
      $attributes[] = 'Mikrotik-Rate-Limit:' . $rate;
    }
    return implode(',', $attributes);
  }
}

if (!function_exists('tikras_radius_ensure_attribute')) {
  function tikras_radius_ensure_attribute($api, $name, $vendorId, $typeId, $valueType)
  {
    $rows = tikras_routeros_comm($api, '/user-manager/attribute/print', array('?name' => $name), array());
    if (is_array($rows) && isset($rows[0]['name'])) {
      return true;
    }
    $response = $api->comm('/user-manager/attribute/add', array(
      'name' => $name,
      'vendor-id' => (string) $vendorId,
      'type-id' => (string) $typeId,
      'value-type' => $valueType,
      'packet-types' => 'access-accept',
    ));
    return !tikras_radius_has_trap($response);
  }
}

if (!function_exists('tikras_radius_ensure_base_attributes')) {
  function tikras_radius_ensure_base_attributes($api)
  {
    tikras_radius_ensure_attribute($api, 'Mikrotik-Group', 14988, 3, 'string');
    tikras_radius_ensure_attribute($api, 'Mikrotik-Rate-Limit', 14988, 8, 'string');
    return true;
  }
}

if (!function_exists('tikras_radius_ensure_profile')) {
  function tikras_radius_ensure_profile($api, $params)
  {
    $hotspotProfile = isset($params['profile']) ? (string) $params['profile'] : 'default';
    $profile = tikras_radius_um_profile_name($hotspotProfile);
    $price = isset($params['selling_price']) && $params['selling_price'] !== '' ? $params['selling_price'] : (isset($params['price']) ? $params['price'] : '0');
    $payload = array(
      'name' => $profile,
      'name-for-users' => $hotspotProfile,
      'starts-when' => 'first-auth',
      'validity' => tikras_radius_validity(isset($params['validity']) ? $params['validity'] : ''),
      'price' => tikras_radius_price($price),
      'comment' => 'TIKRAS IT - ticket roaming',
    );
    $rows = tikras_routeros_comm($api, '/user-manager/profile/print', array('?name' => $profile), array());
    if (is_array($rows) && isset($rows[0]['.id'])) {
      $payload['.id'] = $rows[0]['.id'];
      $response = $api->comm('/user-manager/profile/set', $payload);
    } else {
      $response = $api->comm('/user-manager/profile/add', $payload);
    }
    if (tikras_radius_has_trap($response)) {
      return array('ok' => false, 'profile' => $profile, 'limitation' => '');
    }

    $uptime = tikras_radius_uptime_limit(isset($params['timelimit']) ? $params['timelimit'] : '0');
    $transfer = tikras_radius_data_limit(isset($params['datalimit']) ? $params['datalimit'] : '0');
    $limitation = '';
    if ($uptime != '00:00:00' || $transfer != '0') {
      $limitation = tikras_radius_limitation_name($params);
      $limitPayload = array(
        'name' => $limitation,
        'uptime-limit' => $uptime,
        'transfer-limit' => $transfer,
        'comment' => 'TIKRAS IT - limites ticket',
      );
      $limitRows = tikras_routeros_comm($api, '/user-manager/limitation/print', array('?name' => $limitation), array());
      if (is_array($limitRows) && isset($limitRows[0]['.id'])) {
        $limitPayload['.id'] = $limitRows[0]['.id'];
        $api->comm('/user-manager/limitation/set', $limitPayload);
      } else {
        $api->comm('/user-manager/limitation/add', $limitPayload);
      }

      $linkRows = tikras_routeros_comm($api, '/user-manager/profile-limitation/print', array('?profile' => $profile, '?limitation' => $limitation), array());
      if (!is_array($linkRows) || !isset($linkRows[0]['.id'])) {
        $api->comm('/user-manager/profile-limitation/add', array(
          'profile' => $profile,
          'limitation' => $limitation,
          'comment' => 'TIKRAS IT - lien profil limite',
        ));
      }
    }

    return array('ok' => true, 'profile' => $profile, 'limitation' => $limitation);
  }
}

if (!function_exists('tikras_radius_sync_tickets')) {
  function tikras_radius_sync_tickets($data, $currentSession, $currentApi, $tickets, $params)
  {
    $config = tikras_radius_config();
    $managerSession = tikras_radius_manager_session($config, $currentSession);
    $status = array(
      'session' => $managerSession,
      'hotspot' => 'User Manager RADIUS',
      'ok' => false,
      'created' => 0,
      'updated' => 0,
      'failed' => 0,
      'queued' => 0,
      'message' => '',
      'api' => 'unchecked',
      'profile' => isset($params['profile']) ? $params['profile'] : '',
      'server' => 'radius',
    );

    if (!tikras_radius_enabled($config)) {
      $status['failed'] = is_array($tickets) ? count($tickets) : 0;
      $status['message'] = 'Module RADIUS non active';
      return $status;
    }
    if (!is_array($tickets) || count($tickets) < 1) {
      $status['message'] = 'Aucun ticket a envoyer';
      return $status;
    }

    $manager = tikras_radius_connect_manager($data, $currentSession, $currentApi, array('timeout' => 3));
    if (!$manager['ok']) {
      $status['failed'] = count($tickets);
      $status['message'] = $manager['message'];
      return $status;
    }

    $api = $manager['api'];
    $status['api'] = 'online';
    $setup = $api->comm('/user-manager/set', array(
      'enabled' => 'yes',
      'use-profiles' => isset($config['use_profiles']) && $config['use_profiles'] == 'yes' ? 'yes' : 'no',
    ));
    if (tikras_radius_has_trap($setup)) {
      $status['failed'] = count($tickets);
      $status['message'] = 'User Manager indisponible ou paquet absent';
      tikras_radius_disconnect_manager($manager);
      return $status;
    }

    tikras_radius_ensure_base_attributes($api);
    $profileState = tikras_radius_ensure_profile($api, $params);
    if (!isset($profileState['ok']) || !$profileState['ok']) {
      $status['failed'] = count($tickets);
      $status['message'] = 'Profil User Manager non cree';
      tikras_radius_disconnect_manager($manager);
      return $status;
    }
    $umProfile = $profileState['profile'];
    $attributes = tikras_radius_user_attributes($params, $config);
    $sharedUsers = '1';
    if (isset($params['profile_payload']) && is_array($params['profile_payload']) && isset($params['profile_payload']['shared-users'])) {
      $candidate = (string) $params['profile_payload']['shared-users'];
      $sharedUsers = $candidate == 'unlimited' ? 'unlimited' : (string) max(1, (int) $candidate);
    }

    foreach ($tickets as $ticket) {
      $username = isset($ticket['username']) ? (string) $ticket['username'] : '';
      $password = isset($ticket['password']) ? (string) $ticket['password'] : '';
      if ($username == '') {
        $status['failed']++;
        continue;
      }
      $payload = array(
        'name' => $username,
        'password' => $password,
        'shared-users' => $sharedUsers,
        'comment' => isset($params['comment']) ? (string) $params['comment'] : 'TIKRAS IT - ticket roaming',
        'disabled' => 'no',
      );
      if ($attributes != '') {
        $payload['attributes'] = $attributes;
      }

      $rows = tikras_routeros_comm($api, '/user-manager/user/print', array('?name' => $username), array());
      if (is_array($rows) && isset($rows[0]['.id'])) {
        $payload['.id'] = $rows[0]['.id'];
        $response = $api->comm('/user-manager/user/set', $payload);
        if (tikras_radius_has_trap($response)) {
          $status['failed']++;
        } else {
          $status['updated']++;
        }
      } else {
        $response = $api->comm('/user-manager/user/add', $payload);
        if (tikras_radius_has_trap($response)) {
          $status['failed']++;
        } else {
          $status['created']++;
        }
      }

      if (!tikras_radius_has_trap($response)) {
        $profileRows = tikras_routeros_comm($api, '/user-manager/user-profile/print', array('?user' => $username, '?profile' => $umProfile), array());
        if (!is_array($profileRows) || !isset($profileRows[0]['.id'])) {
          $profileResponse = $api->comm('/user-manager/user-profile/add', array(
            'user' => $username,
            'profile' => $umProfile,
          ));
          if (tikras_radius_has_trap($profileResponse)) {
            $status['failed']++;
          }
        }
      }
    }

    tikras_radius_disconnect_manager($manager);
    $status['ok'] = $status['failed'] == 0;
    $status['message'] = $status['ok'] ? 'Synchronise dans User Manager' : 'User Manager avec erreurs';
    return $status;
  }
}

if (!function_exists('tikras_radius_router_address')) {
  function tikras_radius_router_address($data, $session, $config)
  {
    $cfg = tikras_radius_session_config($data, $session);
    return $cfg['ip'];
  }
}

if (!function_exists('tikras_radius_prepare_manager')) {
  function tikras_radius_prepare_manager($data, $currentSession, $currentApi)
  {
    $config = tikras_radius_config();
    $result = array('ok' => false, 'message' => '', 'routers' => 0, 'failed' => 0);
    $manager = tikras_radius_connect_manager($data, $currentSession, $currentApi, array('timeout' => 3));
    if (!$manager['ok']) {
      $result['message'] = $manager['message'];
      return $result;
    }
    $api = $manager['api'];
    $setup = $api->comm('/user-manager/set', array('enabled' => 'yes', 'use-profiles' => 'yes'));
    if (tikras_radius_has_trap($setup)) {
      $result['message'] = 'User Manager indisponible ou paquet absent';
      tikras_radius_disconnect_manager($manager);
      return $result;
    }
    tikras_radius_ensure_base_attributes($api);

    if (isset($config['auto_create_nas']) && $config['auto_create_nas'] == 'yes') {
      $sessions = tikras_radius_target_nas_sessions($data, $config);
      if (count($sessions) < 1) {
        tikras_radius_disconnect_manager($manager);
        $result['message'] = 'Aucun routeur NAS selectionne';
        return $result;
      }
      foreach ($sessions as $routerSession) {
        $address = tikras_radius_router_address($data, $routerSession, $config);
        if ($address == '') {
          continue;
        }
        $payload = array(
          'name' => $routerSession,
          'address' => $address,
          'shared-secret' => $config['shared_secret'],
          'comment' => 'TIKRAS IT - NAS roaming',
          'disabled' => 'no',
        );
        $rows = tikras_routeros_comm($api, '/user-manager/router/print', array('?name' => $routerSession), array());
        if (is_array($rows) && isset($rows[0]['.id'])) {
          $payload['.id'] = $rows[0]['.id'];
          $response = $api->comm('/user-manager/router/set', $payload);
        } else {
          $response = $api->comm('/user-manager/router/add', $payload);
        }
        if (tikras_radius_has_trap($response)) {
          $result['failed']++;
        } else {
          $result['routers']++;
        }
      }
    }

    tikras_radius_disconnect_manager($manager);
    $result['ok'] = $result['failed'] == 0;
    $result['message'] = $result['ok'] ? 'Serveur User Manager prepare' : 'Preparation partielle';
    return $result;
  }
}

if (!function_exists('tikras_radius_manager_status')) {
  function tikras_radius_manager_status($data, $currentSession, $currentApi)
  {
    $config = tikras_radius_config();
    $managerSession = tikras_radius_manager_session($config, $currentSession);
    $status = array(
      'session' => $managerSession,
      'hotspot' => 'User Manager RADIUS',
      'ok' => false,
      'warning' => false,
      'api' => 'offline',
      'profile' => '-',
      'server' => isset($config['server_address']) && $config['server_address'] != '' ? $config['server_address'] : $managerSession,
      'message' => '',
      'users' => 0,
      'routers' => 0,
    );
    if (!tikras_radius_enabled($config)) {
      $status['message'] = 'Module RADIUS desactive';
      return $status;
    }
    $manager = tikras_radius_connect_manager($data, $currentSession, $currentApi, array('timeout' => 3));
    if (!$manager['ok']) {
      $status['message'] = $manager['message'];
      return $status;
    }
    $api = $manager['api'];
    $status['api'] = 'online';
    $rows = tikras_routeros_comm($api, '/user-manager/print', array(), array());
    if (tikras_radius_has_trap($rows)) {
      $status['message'] = 'User Manager absent';
      tikras_radius_disconnect_manager($manager);
      return $status;
    }
    $users = tikras_routeros_comm($api, '/user-manager/user/print', array('.proplist' => 'name'), array());
    $routers = tikras_routeros_comm($api, '/user-manager/router/print', array('.proplist' => 'name'), array());
    $status['users'] = is_array($users) ? count($users) : 0;
    $status['routers'] = is_array($routers) ? count($routers) : 0;
    $status['ok'] = true;
    $status['message'] = 'User Manager joignable';
    tikras_radius_disconnect_manager($manager);
    return $status;
  }
}
?>
