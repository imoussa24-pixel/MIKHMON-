<?php
/*
 * Central RADIUS/User Manager configuration page.
 */
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

include_once(dirname(__DIR__) . '/lib/tikras_radius.php');

if (!function_exists('tikras_radius_page_clean_text')) {
  function tikras_radius_page_clean_text($value, $max = 120)
  {
    $value = trim((string) $value);
    $value = str_replace(array("\r", "\n", "\t"), '', $value);
    return substr($value, 0, $max);
  }
}

if (!function_exists('tikras_radius_page_clean_session')) {
  function tikras_radius_page_clean_session($value, $sessions)
  {
    $value = tikras_radius_page_clean_text($value, 80);
    if ($value == '') {
      return '';
    }
    return in_array($value, $sessions) ? $value : '';
  }
}

if (!function_exists('tikras_radius_page_form_config')) {
  function tikras_radius_page_form_config($current, $sessions)
  {
    $nasScope = tikras_radius_clean_nas_scope(tikras_post('nas_scope', 'all'));
    return array(
      'enabled' => tikras_post('enabled') == 'yes' ? 'yes' : 'no',
      'manager_session' => tikras_radius_page_clean_session(tikras_post('manager_session'), $sessions),
      'shared_secret' => tikras_radius_secret(tikras_post('shared_secret')),
      'server_address' => tikras_radius_page_clean_text(tikras_post('server_address'), 120),
      'auth_port' => tikras_radius_clean_number(tikras_post('auth_port'), '1812', 1, 65535),
      'acct_port' => tikras_radius_clean_number(tikras_post('acct_port'), '1813', 1, 65535),
      'timeout' => tikras_radius_clean_interval(tikras_post('timeout'), '3s'),
      'default_engine' => tikras_radius_clean_engine(tikras_post('default_engine', 'radius')),
      'auto_create_nas' => tikras_has_post('auto_create_nas') ? 'yes' : 'no',
      'nas_scope' => $nasScope,
      'nas_sessions' => tikras_radius_clean_nas_sessions(tikras_post_array('nas_sessions'), $sessions),
      'nas_address_mode' => 'configured_ip',
      'use_profiles' => tikras_has_post('use_profiles') ? 'yes' : 'no',
      'hotspot_profile_attribute' => tikras_has_post('hotspot_profile_attribute') ? 'yes' : 'no',
      'accounting_required' => tikras_radius_yesno(tikras_post('accounting_required', 'yes')),
    );
  }
}

$radiusSessions = tikras_radius_sessions($data);
$radiusConfig = tikras_radius_config();
$radiusFlash = array('class' => '', 'text' => '');
$radiusLiveStatus = array();
$radiusPrepare = array();

if (tikras_has_post('save_radius') || tikras_has_post('test_radius') || tikras_has_post('prepare_radius')) {
  $radiusConfig = array_merge($radiusConfig, tikras_radius_page_form_config($radiusConfig, $radiusSessions));
  if (tikras_radius_write_config($radiusConfig)) {
    $radiusFlash = array('class' => 'is-ok', 'text' => 'Configuration RADIUS enregistree.');
  } else {
    $radiusFlash = array('class' => '', 'text' => 'Impossible d enregistrer la configuration RADIUS.');
  }

  if ($radiusFlash['class'] == 'is-ok' && tikras_has_post('test_radius')) {
    $radiusLiveStatus = tikras_radius_manager_status($data, '', null);
  }

  if ($radiusFlash['class'] == 'is-ok' && tikras_has_post('prepare_radius')) {
    $radiusPrepare = tikras_radius_prepare_manager($data, '', null);
  }
}

$radiusEnabled = tikras_radius_enabled($radiusConfig);
$managerSession = tikras_radius_manager_session($radiusConfig, '');
$managerCfg = $managerSession != '' ? tikras_radius_session_config($data, $managerSession) : array('ip' => '', 'hotspot' => '');
$serverAddress = isset($radiusConfig['server_address']) && $radiusConfig['server_address'] != '' ? $radiusConfig['server_address'] : (isset($managerCfg['ip']) ? $managerCfg['ip'] : '');
$scriptSession = $managerSession != '' ? $managerSession : (count($radiusSessions) > 0 ? $radiusSessions[0] : '');
$secretLooksDefault = isset($radiusConfig['shared_secret']) && $radiusConfig['shared_secret'] == 'ChangeMeRadiusSecret';
$nasScope = isset($radiusConfig['nas_scope']) ? tikras_radius_clean_nas_scope($radiusConfig['nas_scope']) : 'all';
$targetNasSessions = tikras_radius_target_nas_sessions($data, $radiusConfig);
$selectedNasSessions = isset($radiusConfig['nas_sessions']) && is_array($radiusConfig['nas_sessions']) ? tikras_radius_clean_nas_sessions($radiusConfig['nas_sessions'], $radiusSessions) : array();
?>

<div class="tikras-tickets-page tikras-radius-page">
  <div class="tikras-tickets-hero">
    <div>
      <span class="tikras-storage-eyebrow">Roaming central</span>
      <h2>RADIUS central / User Manager</h2>
      <p>Les tickets roaming peuvent etre crees dans un seul serveur User Manager puis utilises par tous les MikroTik clients.</p>
    </div>
    <div class="tikras-tickets-actions">
      <?php if ($scriptSession != '') { ?>
        <a class="btn bg-primary" href="./?system=script-generator&session=<?= tikras_radius_h($scriptSession); ?>"><i class="fa fa-code"></i> Script RADIUS</a>
      <?php } ?>
      <a class="btn bg-secondary" href="./?hotspot-user=generate-roaming<?= $scriptSession != '' ? '&session=' . tikras_radius_h($scriptSession) : ''; ?>"><i class="fa fa-random"></i> Tickets roaming</a>
      <a class="btn bg-secondary" href="./admin.php?id=tickets"><i class="fa fa-ticket"></i> Historique</a>
    </div>
  </div>

  <?php if ($radiusFlash['text'] != '') { ?>
    <div class="tikras-storage-alert <?= tikras_radius_h($radiusFlash['class']); ?>">
      <i class="fa <?= $radiusFlash['class'] == 'is-ok' ? 'fa-check-circle' : 'fa-warning'; ?>"></i>
      <span><?= tikras_radius_h($radiusFlash['text']); ?></span>
    </div>
  <?php } ?>

  <?php if (count($radiusLiveStatus) > 0) { ?>
    <div class="tikras-storage-alert <?= isset($radiusLiveStatus['ok']) && $radiusLiveStatus['ok'] ? 'is-ok' : ''; ?>">
      <i class="fa <?= isset($radiusLiveStatus['ok']) && $radiusLiveStatus['ok'] ? 'fa-check-circle' : 'fa-warning'; ?>"></i>
      <span><?= tikras_radius_h(isset($radiusLiveStatus['message']) ? $radiusLiveStatus['message'] : ''); ?> - API <?= tikras_radius_h(isset($radiusLiveStatus['api']) ? $radiusLiveStatus['api'] : '-'); ?>, <?= (int) (isset($radiusLiveStatus['users']) ? $radiusLiveStatus['users'] : 0); ?> utilisateur(s), <?= (int) (isset($radiusLiveStatus['routers']) ? $radiusLiveStatus['routers'] : 0); ?> NAS.</span>
    </div>
  <?php } ?>

  <?php if (count($radiusPrepare) > 0) { ?>
    <div class="tikras-storage-alert <?= isset($radiusPrepare['ok']) && $radiusPrepare['ok'] ? 'is-ok' : ''; ?>">
      <i class="fa <?= isset($radiusPrepare['ok']) && $radiusPrepare['ok'] ? 'fa-check-circle' : 'fa-warning'; ?>"></i>
      <span><?= tikras_radius_h(isset($radiusPrepare['message']) ? $radiusPrepare['message'] : ''); ?> - <?= (int) (isset($radiusPrepare['routers']) ? $radiusPrepare['routers'] : 0); ?> routeur(s), <?= (int) (isset($radiusPrepare['failed']) ? $radiusPrepare['failed'] : 0); ?> erreur(s).</span>
    </div>
  <?php } ?>

  <div class="tikras-tickets-stats">
    <div><span>Module</span><strong><?= $radiusEnabled ? 'Actif' : 'Inactif'; ?></strong></div>
    <div><span>Serveur</span><strong><?= tikras_radius_h($managerSession != '' ? $managerSession : 'A choisir'); ?></strong></div>
    <div><span>Adresse</span><strong><?= tikras_radius_h($serverAddress != '' ? $serverAddress : '-'); ?></strong></div>
    <div><span>Secret</span><strong><?= $secretLooksDefault ? 'Defaut' : 'Pret'; ?></strong></div>
    <div><span>NAS cibles</span><strong><?= count($targetNasSessions); ?></strong></div>
    <div><span>Moteur</span><strong><?= isset($radiusConfig['default_engine']) && $radiusConfig['default_engine'] == 'radius' ? 'RADIUS' : 'API'; ?></strong></div>
  </div>

  <div class="tikras-tickets-grid">
    <section class="tikras-tickets-panel">
      <div class="tikras-tickets-panel-title">
        <h3><i class="fa fa-key"></i> Parametres RADIUS</h3>
        <span>User Manager</span>
      </div>
      <form method="post" action="./admin.php?id=radius" class="tikras-radius-form">
        <table class="table tikras-tickets-table">
          <tr>
            <td>Etat du module</td>
            <td>
              <select class="form-control" name="enabled">
                <option value="yes" <?= $radiusConfig['enabled'] == 'yes' ? 'selected' : ''; ?>>Activer RADIUS central</option>
                <option value="no" <?= $radiusConfig['enabled'] != 'yes' ? 'selected' : ''; ?>>Garder inactif</option>
              </select>
            </td>
          </tr>
          <tr>
            <td>Routeur User Manager</td>
            <td>
              <select class="form-control" name="manager_session">
                <option value="">Choisir le routeur central</option>
                <?php foreach ($radiusSessions as $routerName) { ?>
                  <option value="<?= tikras_radius_h($routerName); ?>" <?= $managerSession == $routerName ? 'selected' : ''; ?>><?= tikras_radius_h($routerName); ?></option>
                <?php } ?>
              </select>
            </td>
          </tr>
          <tr>
            <td>Adresse serveur</td>
            <td><input class="form-control" type="text" name="server_address" value="<?= tikras_radius_h($radiusConfig['server_address']); ?>" placeholder="<?= tikras_radius_h($managerCfg['ip'] != '' ? $managerCfg['ip'] : '10.252.0.1'); ?>"></td>
          </tr>
          <tr>
            <td>Secret partage</td>
            <td><input class="form-control" type="text" name="shared_secret" value="<?= tikras_radius_h($radiusConfig['shared_secret']); ?>" autocomplete="off"></td>
          </tr>
          <tr>
            <td>Ports</td>
            <td>
              <input class="form-control" type="number" min="1" max="65535" name="auth_port" value="<?= tikras_radius_h($radiusConfig['auth_port']); ?>" placeholder="1812">
              <input class="form-control mr-t-5" type="number" min="1" max="65535" name="acct_port" value="<?= tikras_radius_h($radiusConfig['acct_port']); ?>" placeholder="1813">
            </td>
          </tr>
          <tr>
            <td>Timeout</td>
            <td><input class="form-control" type="text" name="timeout" value="<?= tikras_radius_h($radiusConfig['timeout']); ?>" placeholder="3s"></td>
          </tr>
          <tr>
            <td>Moteur par defaut</td>
            <td>
              <select class="form-control" name="default_engine">
                <option value="radius" <?= $radiusConfig['default_engine'] == 'radius' ? 'selected' : ''; ?>>RADIUS central</option>
                <option value="api" <?= $radiusConfig['default_engine'] != 'radius' ? 'selected' : ''; ?>>Copie API actuelle</option>
              </select>
            </td>
          </tr>
          <tr>
            <td>Options User Manager</td>
            <td>
              <label><input type="checkbox" name="use_profiles" value="yes" <?= $radiusConfig['use_profiles'] == 'yes' ? 'checked' : ''; ?>> Utiliser profils et limitations</label><br>
              <label><input type="checkbox" name="hotspot_profile_attribute" value="yes" <?= $radiusConfig['hotspot_profile_attribute'] == 'yes' ? 'checked' : ''; ?>> Envoyer le profil Hotspot par attribut RADIUS</label><br>
              <label><input type="checkbox" name="auto_create_nas" value="yes" <?= $radiusConfig['auto_create_nas'] == 'yes' ? 'checked' : ''; ?>> Declarer automatiquement les routeurs NAS</label>
            </td>
          </tr>
          <tr>
            <td>Routeurs NAS concernes</td>
            <td>
              <select class="form-control" id="nasScope" name="nas_scope" onchange="updateNasScope();">
                <option value="all" <?= $nasScope == 'all' ? 'selected' : ''; ?>>Tous les routeurs configures</option>
                <option value="selected" <?= $nasScope == 'selected' ? 'selected' : ''; ?>>Seulement les routeurs selectionnes</option>
              </select>
              <select class="form-control mr-t-5" id="nasSessions" name="nas_sessions[]" multiple size="8">
                <?php foreach ($radiusSessions as $routerName) {
                  $routerCfg = tikras_radius_session_config($data, $routerName);
                  $routerLabel = $routerName;
                  if ($routerCfg['hotspot'] != '') {
                    $routerLabel .= ' - ' . $routerCfg['hotspot'];
                  }
                ?>
                  <option value="<?= tikras_radius_h($routerName); ?>" <?= in_array($routerName, $selectedNasSessions) ? 'selected' : ''; ?>><?= tikras_radius_h($routerLabel); ?></option>
                <?php } ?>
              </select>
              <small class="tikras-script-help">En mode selection, le bouton Preparer NAS ne declarera que ces routeurs dans User Manager.</small>
            </td>
          </tr>
        </table>
        <div class="tikras-tickets-actions mr-t-10">
          <button class="btn bg-primary" type="submit" name="save_radius" value="1"><i class="fa fa-save"></i> Enregistrer</button>
          <button class="btn bg-info" type="submit" name="test_radius" value="1"><i class="fa fa-heartbeat"></i> Tester User Manager</button>
          <button class="btn bg-warning" type="submit" name="prepare_radius" value="1" onclick="return confirm('Declarer les routeurs NAS dans User Manager maintenant ?');"><i class="fa fa-server"></i> Preparer NAS</button>
        </div>
      </form>
    </section>

    <section class="tikras-tickets-panel">
      <div class="tikras-tickets-panel-title">
        <h3><i class="fa fa-sitemap"></i> Routeurs clients</h3>
        <span><?= count($targetNasSessions); ?> cible(s)</span>
      </div>
      <div class="tikras-table-wrap">
        <table class="table tikras-tickets-table">
          <thead>
            <tr>
              <th>Routeur</th>
              <th>Adresse NAS</th>
              <th>Hotspot</th>
              <th>Role</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($radiusSessions) < 1) { ?>
              <tr><td colspan="5">Aucun routeur configure.</td></tr>
            <?php } ?>
            <?php foreach ($radiusSessions as $routerName) {
              $routerCfg = tikras_radius_session_config($data, $routerName);
              $isManager = $routerName == $managerSession;
              $isNasTarget = in_array($routerName, $targetNasSessions);
            ?>
              <tr>
                <td><strong><?= tikras_radius_h($routerName); ?></strong></td>
                <td><?= tikras_radius_h($routerCfg['ip'] != '' ? $routerCfg['ip'] : '-'); ?></td>
                <td><?= tikras_radius_h($routerCfg['hotspot'] != '' ? $routerCfg['hotspot'] : '-'); ?></td>
                <td><span class="tikras-status <?= $isManager ? 'tikras-status-synced' : ($isNasTarget ? 'tikras-status-created' : 'tikras-status-error'); ?>"><?= $isManager ? 'Serveur' : ($isNasTarget ? 'NAS cible' : 'Ignore'); ?></span></td>
                <td><a class="btn bg-secondary" href="./?system=script-generator&session=<?= tikras_radius_h($routerName); ?>"><i class="fa fa-code"></i> Script</a></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
<script>
function updateNasScope(){
  var scope = document.getElementById('nasScope');
  var sessions = document.getElementById('nasSessions');
  if (!scope || !sessions) {
    return;
  }
  sessions.style.display = scope.value === 'selected' ? '' : 'none';
}
updateNasScope();
</script>
