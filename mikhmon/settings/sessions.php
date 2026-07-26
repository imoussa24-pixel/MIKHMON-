<?php
/*
 *  Copyright (C) 2026 TIKRAS IT.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
include_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

// array color
  $color = array('1' => 'bg-blue', 'bg-indigo', 'bg-purple', 'bg-pink', 'bg-red', 'bg-yellow', 'bg-green', 'bg-teal', 'bg-cyan', 'bg-grey', 'bg-light-blue');

  if (tikras_has_post('save')) {

    $suseradm = trim((string) tikras_post('useradm'));
    $rawPassadm = (string) tikras_post('passadm');
    $logobt = tikras_post('logobt');
    $qrbt = tikras_post('qrbt', 'disable');
    $adminSaveError = '';

    // Un identifiant ou un mot de passe vide fermerait definitivement l'acces
    // au panneau: on refuse l'enregistrement plutot que de bloquer le compte.
    if ($suseradm === '' || $rawPassadm === '') {
      $adminSaveError = "Nom d'utilisateur et mot de passe sont obligatoires : aucune modification enregistrée.";
    } elseif (strlen($rawPassadm) < 6) {
      $adminSaveError = "Le mot de passe doit contenir au moins 6 caractères : aucune modification enregistrée.";
    }

    if ($adminSaveError === '') {
      $updatedConfig = is_array($data) ? $data : array();
      $updatedConfig['mikhmon'] = array(
        '1' => "mikhmon<|<$suseradm",
        "mikhmon>|>" . encrypt($rawPassadm),
      );
      if (!tikras_config_write_all($updatedConfig)) {
        tikras_log('admin config save failed', array('config' => tikras_config_local_path()));
        $adminSaveError = "Enregistrement impossible : vérifiez les droits d'écriture du stockage.";
      } else {
        tikras_storage_audit('admin.credentials', 'admin', $suseradm, '', 'Compte administrateur mis a jour.');
      }
    }

    if (!tikras_quickbt_write($qrbt)) {
      tikras_log('quick print config save failed', array('config' => tikras_quickbt_local_path()));
    }

    if ($adminSaveError === '') {
      $_SESSION['admin_flash'] = "Compte administrateur mis à jour. Utilisez le nouveau mot de passe à la prochaine connexion.";
      tikras_redirect('./admin.php?id=sessions');
    }
    $_SESSION['admin_flash_error'] = $adminSaveError;
    tikras_redirect('./admin.php?id=sessions');
  }

}
?>
<script>
  function Pass(id){
    var x = document.getElementById(id);
    if (x.type === 'password') {
    x.type = 'text';
    } else {
    x.type = 'password';
    }}
</script>

<?php
include_once(dirname(__DIR__) . '/lib/tikras_storage.php');
include_once(dirname(__DIR__) . '/lib/tikras_automation.php');

$routerStatuses = tikras_storage_all_router_statuses();
$routerTotal = 0;
$routerOnline = 0;
$routerOffline = 0;
$routerCards = '';
foreach ($data as $value => $routerConfig) {
  if ($value == "" || $value == "mikhmon") {
    continue;
  }
  $routerTotal++;
  $routerStatus = isset($routerStatuses[$value]) ? $routerStatuses[$value] : null;
  if (is_array($routerStatus) && isset($routerStatus['last_state'])) {
    if ($routerStatus['last_state'] == 'online') {
      $routerOnline++;
    } elseif ($routerStatus['last_state'] == 'offline') {
      $routerOffline++;
    }
  }
  $routerCards .= tikras_ui_router_card(
    $value,
    tikras_cfg_value($data, $value, 4, '%', ''),
    tikras_cfg_value($data, $value, 5, '^', ''),
    tikras_cfg_value($data, $value, 6, '&', ''),
    $routerStatus
  );
}
$autoStatus = tikras_automation_status();
$autoJobLabels = array(
  'health_check' => 'Surveillance routeurs',
  'sync_routers' => 'Sync base locale',
  'roaming_retry' => 'Reprise roaming',
  'tickets' => 'Tickets planifiés',
  'auto_backup' => 'Sauvegarde auto',
  'prune' => 'Nettoyage journaux',
);
$routerSubtitle = $routerTotal . ' routeur(s) · ' . $routerOnline . ' en ligne · ' . $routerOffline . ' hors ligne';
?>

<?= tikras_ui_page_header('gear', $_admin_settings, $routerSubtitle, tikras_ui_button('./admin.php?id=settings&router=new-' . rand(1111,9999), 'plus', $_add_router, 'primary') . tikras_ui_button('./admin.php?id=sessions', 'refresh', 'Actualiser', 'muted')); ?>

        <?php
        $connectFlash = tikras_session_get('connect_flash', '');
        if ($connectFlash != '') {
          unset($_SESSION['connect_flash']);
          echo tikras_ui_alert('danger', $connectFlash);
        }
        $adminFlash = tikras_session_get('admin_flash', '');
        if ($adminFlash != '') {
          unset($_SESSION['admin_flash']);
          echo tikras_ui_alert('success', $adminFlash);
        }
        $adminFlashError = tikras_session_get('admin_flash_error', '');
        if ($adminFlashError != '') {
          unset($_SESSION['admin_flash_error']);
          echo tikras_ui_alert('danger', $adminFlashError);
        }
        ?>

<div class="tikras-admin-grid">
  <section class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-server"></i> <?= $_router_list ?></h3>
      <span class="tikras-version-note"><?= $routerTotal; ?> session(s)</span>
    </div>
    <div class="tikras-panel-body">
      <div class="tikras-router-tools">
        <input id="adminRouterSearch" class="form-control" type="search" placeholder="<?= $_search ?> routeur, session, DNS">
      </div>
      <div class="tikras-router-list">
        <?= $routerCards; ?>
      </div>
    </div>
  </section>

  <section class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-user-circle"></i> <?= $_admin ?></h3>
    </div>
    <div class="tikras-panel-body">
      <form class="tikras-admin-form" autocomplete="off" method="post" action="">
        <div class="tikras-field">
          <label for="useradm"><?= $_user_name ?></label>
          <input class="form-control" id="useradm" type="text" name="useradm" title="User Admin" value="<?= tikras_h($useradm); ?>" required="1"/>
        </div>
        <div class="tikras-field">
          <label for="passadm"><?= $_password ?></label>
          <div class="input-group">
            <div class="input-group-11 col-box-10">
              <input class="group-item group-item-l" id="passadm" type="password" name="passadm" title="Password Admin" value="<?= tikras_h(decrypt($passadm)); ?>" required="1"/>
            </div>
            <div class="input-group-1 col-box-2">
              <div class="group-item group-item-r pd-2p5 text-center align-middle">
                <input title="Show/Hide Password" type="checkbox" onclick="Pass('passadm')">
              </div>
            </div>
          </div>
        </div>
        <div class="tikras-field">
          <label for="qrbt"><?= $_quick_print ?> QR</label>
          <select class="form-control" id="qrbt" name="qrbt">
            <option><?= $qrbt ?></option>
            <option>enable</option>
            <option>disable</option>
          </select>
        </div>
        <div class="tikras-form-actions">
          <button class="tikras-btn tikras-btn-primary" type="submit" name="save"><i class="fa fa-save"></i><span><?= $_save ?></span></button>
          <button class="tikras-btn tikras-btn-muted" type="button" onclick="location.reload();" title="Recharger les données"><i class="fa fa-refresh"></i><span>Recharger</span></button>
        </div>
      </form>
      <div class="tikras-version-note" id="loadV">v<?= tikras_h($_SESSION['v']); ?> </div>
      <div><b id="newVer" class="text-green"></b></div>
    </div>
  </section>

  <section class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-magic"></i> Automatisations</h3>
      <span class="tikras-version-note"><?= $autoStatus['last_tick'] > 0 ? 'Dernier cycle : ' . tikras_h(date('d/m H:i', $autoStatus['last_tick'])) : 'Jamais exécuté'; ?></span>
    </div>
    <div class="tikras-panel-body">
      <ul class="tikras-auto-list">
        <?php foreach ($autoStatus['jobs'] as $jobKey => $job) {
          $label = isset($autoJobLabels[$jobKey]) ? $autoJobLabels[$jobKey] : $jobKey;
          $state = $job['interval'] < 1 ? '<span class="tikras-status tikras-status-unknown">Désactivé</span>'
            : ($job['last_run'] > 0
              ? '<span class="tikras-status tikras-status-online">' . tikras_h(date('d/m H:i', $job['last_run'])) . '</span>'
              : '<span class="tikras-status tikras-status-unknown">En attente</span>');
          $every = $job['interval'] >= 3600 ? round($job['interval'] / 3600) . ' h' : round($job['interval'] / 60) . ' min';
          echo '<li><span class="tikras-auto-name">' . tikras_h($label) . '</span><span class="tikras-auto-interval">toutes les ' . tikras_h($every) . '</span>' . $state . '</li>';
        } ?>
      </ul>
      <div class="tikras-form-actions">
        <button class="tikras-btn tikras-btn-primary" type="button" id="runAutomations"><i class="fa fa-play"></i><span>Exécuter maintenant</span></button>
      </div>
      <div class="tikras-version-note" id="autoRunResult"></div>
    </div>
  </section>
</div>
<script>
  $("#adminRouterSearch").on("input", function(){
    var term = $(this).val().toLowerCase();
    $(".tikras-router-row").each(function(){
      var router = $(this).data("router");
      $(this).toggle(router.indexOf(term) !== -1);
    });
  });

  document.getElementById("runAutomations").addEventListener("click", function(){
    var btn = this;
    var out = document.getElementById("autoRunResult");
    btn.disabled = true;
    out.textContent = "Exécution en cours...";
    fetch("cron.php?soft=1&force=1", { credentials: "same-origin" })
      .then(function(r){ return r.json(); })
      .then(function(report){
        var ran = report.ran ? Object.keys(report.ran).length : 0;
        out.textContent = report.ok ? ("Terminé : " + ran + " tâche(s) exécutée(s).") : ("Erreur : " + (report.error || "inconnue"));
        window.setTimeout(function(){ window.location.reload(); }, 1500);
      })
      .catch(function(){ out.textContent = "Erreur réseau."; btn.disabled = false; });
  });
</script>









