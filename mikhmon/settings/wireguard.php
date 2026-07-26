<?php
/*
 * Page de raccordement des routeurs au tunnel WireGuard.
 * Un bouton par routeur: cles, declaration cote serveur et configuration du
 * routeur sont enchainees sans intervention en ligne de commande.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
include_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
include_once(dirname(__DIR__) . '/lib/tikras_storage.php');
include_once(dirname(__DIR__) . '/lib/tikras_routeros.php');
include_once(dirname(__DIR__) . '/lib/tikras_wireguard.php');

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  return;
}

$wgConfig = tikras_wg_config();
$wgPeers = tikras_wg_liste();
$wgFlash = '';
$wgFlashType = 'success';

if (tikras_has_post('raccorder')) {
  $cible = (string) tikras_post('session');
  if (!isset($data[$cible])) {
    $wgFlash = 'Routeur inconnu.';
    $wgFlashType = 'danger';
  } else {
    $ipRouteur = tikras_cfg_value($data, $cible, 1, '!', '');
    $userRouteur = tikras_cfg_value($data, $cible, 2, '@|@', '');
    $passRouteur = decrypt(tikras_cfg_value($data, $cible, 3, '#|#', ''));

    $api = tikras_routeros_create();
    $api->attempts = 1;
    $api->timeout = 8;
    if (!tikras_routeros_connect($api, $ipRouteur, $userRouteur, $passRouteur, $cible, array('timeout' => 8, 'force' => true))) {
      $wgFlash = "Routeur injoignable ($ipRouteur) : il doit repondre pour etre raccorde.";
      $wgFlashType = 'danger';
    } else {
      $rapport = tikras_wg_raccorder($cible, $api);
      tikras_routeros_disconnect($api);
      $wgFlash = $rapport['message'];
      $wgFlashType = $rapport['ok'] ? 'success' : 'danger';
      if ($rapport['ok']) {
        $wgFlash .= ' — le tunnel s\'etablit sous une minute.';
      }
      $wgPeers = tikras_wg_liste();
    }
  }
}

$wgTotalRouteurs = 0;
$wgRaccordes = 0;
foreach ($data as $nom => $cfg) {
  if ($nom == '' || $nom == 'mikhmon') {
    continue;
  }
  $wgTotalRouteurs++;
  if (isset($wgPeers[$nom]) && $wgPeers[$nom]['state'] == 'actif') {
    $wgRaccordes++;
  }
}
$statuts = tikras_storage_all_router_statuses();
?>

<?= tikras_ui_page_header('shield', 'Acces de secours WireGuard',
  $wgRaccordes . ' routeur(s) raccorde(s) sur ' . $wgTotalRouteurs,
  tikras_ui_button('./admin.php?id=wireguard', 'refresh', 'Actualiser', 'muted')); ?>

<?php
if ($wgFlash != '') {
  echo tikras_ui_alert($wgFlashType, $wgFlash);
}
if (!$wgConfig['disponible']) {
  echo tikras_ui_alert('danger', "Le concentrateur n'est pas encore actif sur le serveur. Lancez deploy/wireguard-hub.sh puis deploy/wireguard-agent-installer.sh.");
}
?>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-info-circle"></i> A quoi sert cette page</h3>
  </div>
  <div class="tikras-panel-body">
    <p class="tikras-wg-intro">
      Vos routeurs sont joints par ZeroTier. Ce tunnel ajoute un <strong>second chemin
      independant</strong> vers votre propre serveur : si ZeroTier devient indisponible,
      les routeurs raccordes ici restent accessibles.
    </p>
    <p class="tikras-wg-intro">
      Le raccordement est automatique : le routeur doit simplement etre en ligne au
      moment ou vous cliquez. Rien n'est supprime ni modifie sur le routeur, la
      configuration existante reste intacte.
    </p>
    <?php if ($wgConfig['disponible']) { ?>
    <div class="tikras-wg-hub">
      <span><i class="fa fa-server"></i> Serveur : <strong><?= tikras_h($wgConfig['endpoint'] . ':' . $wgConfig['port']); ?></strong></span>
      <span><i class="fa fa-sitemap"></i> Plage du tunnel : <strong><?= tikras_h($wgConfig['reseau']); ?></strong></span>
    </div>
    <?php } ?>
  </div>
</div>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-shield"></i> Routeurs</h3>
    <span class="tikras-version-note"><?= $wgRaccordes; ?> / <?= $wgTotalRouteurs; ?> raccorde(s)</span>
  </div>
  <div class="tikras-panel-body">
    <div class="tikras-router-tools">
      <input id="wgSearch" class="form-control" type="search" placeholder="Rechercher un routeur">
    </div>
    <div class="tikras-table-wrap">
      <table class="table" id="wgTable">
        <thead>
          <tr>
            <th>Routeur</th>
            <th>Adresse actuelle</th>
            <th>Etat du tunnel</th>
            <th>Adresse de secours</th>
            <th class="text-right">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php
        foreach ($data as $nom => $cfg) {
          if ($nom == '' || $nom == 'mikhmon') {
            continue;
          }
          $hote = tikras_cfg_value($data, $nom, 1, '!', '');
          $libelle = tikras_cfg_value($data, $nom, 4, '%', '');
          $enLigne = isset($statuts[$nom]) && isset($statuts[$nom]['last_state']) && $statuts[$nom]['last_state'] == 'online';
          $pair = isset($wgPeers[$nom]) ? $wgPeers[$nom] : null;
          $raccorde = $pair !== null && $pair['state'] == 'actif';
          $etatPair = $raccorde ? tikras_wg_etat_pair($nom) : array();
          $handshake = isset($etatPair['handshake']) ? (int) $etatPair['handshake'] : 0;
          $vivant = $handshake > 0 && (time() - $handshake) < 300;
          ?>
          <tr class="tikras-wg-row" data-recherche="<?= tikras_h(strtolower($nom . ' ' . $libelle . ' ' . $hote)); ?>">
            <td>
              <strong><?= tikras_h($libelle != '' ? $libelle : $nom); ?></strong>
              <div class="tikras-wg-session"><?= tikras_h($nom); ?></div>
            </td>
            <td><?= tikras_h($hote); ?></td>
            <td>
              <?php
              if (!$raccorde) {
                echo '<span class="tikras-status tikras-status-unknown"><i class="fa fa-minus-circle"></i> Non raccorde</span>';
              } elseif ($vivant) {
                echo '<span class="tikras-status tikras-status-online"><i class="fa fa-check-circle"></i> Tunnel actif</span>';
              } else {
                echo '<span class="tikras-status tikras-status-offline"><i class="fa fa-clock-o"></i> En attente de contact</span>';
              }
              if ($pair !== null && $pair['last_error'] != '') {
                echo '<div class="tikras-wg-erreur">' . tikras_h($pair['last_error']) . '</div>';
              }
              ?>
            </td>
            <td><?= $pair !== null ? tikras_h($pair['tunnel_ip']) : '<span class="tikras-wg-vide">—</span>'; ?></td>
            <td class="text-right">
              <form method="post" action="" class="tikras-wg-form">
                <input type="hidden" name="session" value="<?= tikras_h($nom); ?>">
                <?php if (!$wgConfig['disponible']) { ?>
                  <button class="tikras-btn tikras-btn-muted" type="button" disabled>Serveur non pret</button>
                <?php } elseif (!$enLigne) { ?>
                  <button class="tikras-btn tikras-btn-muted" type="button" disabled title="Le routeur doit etre en ligne">Hors ligne</button>
                <?php } else { ?>
                  <button class="tikras-btn <?= $raccorde ? 'tikras-btn-muted' : 'tikras-btn-primary'; ?>" type="submit" name="raccorder" value="1">
                    <i class="fa fa-<?= $raccorde ? 'refresh' : 'plug'; ?>"></i>
                    <span><?= $raccorde ? 'Reconfigurer' : 'Raccorder'; ?></span>
                  </button>
                <?php } ?>
              </form>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.getElementById('wgSearch').addEventListener('input', function () {
  var terme = this.value.toLowerCase();
  var lignes = document.querySelectorAll('.tikras-wg-row');
  for (var i = 0; i < lignes.length; i++) {
    var texte = lignes[i].getAttribute('data-recherche') || '';
    lignes[i].style.display = texte.indexOf(terme) !== -1 ? '' : 'none';
  }
});
</script>
