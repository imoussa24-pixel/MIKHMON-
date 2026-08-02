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
include_once(dirname(__DIR__) . '/lib/tikras_wg_appareils.php');

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  return;
}

/*
 * Le telechargement du fichier de configuration est traite en amont, dans
 * admin.php, avant que le menu n'ecrive la page: place ici, le fichier
 * partirait precede de tout le HTML de l'interface.
 */

$wgConfig = tikras_wg_config();
$wgPeers = tikras_wg_liste();
$wgFlash = '';
$wgFlashType = 'success';
$wgScript = '';
$wgScriptSession = '';
$wgScriptIp = '';
$wgConfigAffichee = '';
$wgConfigNom = '';

if (tikras_has_post('ajouter_appareil')) {
  $rapport = tikras_wga_creer(tikras_post('appareil_nom'));
  $wgFlash = $rapport['message'];
  $wgFlashType = $rapport['ok'] ? 'success' : 'danger';
  if ($rapport['ok']) {
    $wgConfigAffichee = tikras_wga_configuration($rapport['identifiant']);
    $wgConfigNom = (string) $rapport['identifiant'];
  }
}

if (tikras_has_post('retirer_appareil')) {
  $rapport = tikras_wga_supprimer(tikras_post('appareil'));
  $wgFlash = $rapport['message'];
  $wgFlashType = $rapport['ok'] ? 'success' : 'danger';
}

if (tikras_has_post('voir_config')) {
  $cible = (string) tikras_post('appareil');
  $wgConfigAffichee = tikras_wga_configuration($cible);
  $wgConfigNom = $cible;
  if ($wgConfigAffichee === '') {
    $wgFlash = 'Configuration introuvable pour cet appareil.';
    $wgFlashType = 'danger';
  }
}

if (tikras_has_post('definir_lan')) {
  $rapport = tikras_wga_definir_lan(tikras_post('session'), tikras_post('lan_subnet'));
  $wgFlash = $rapport['message'];
  $wgFlashType = $rapport['ok'] ? 'success' : 'danger';
  $wgPeers = tikras_wg_liste();
}

$wgAppareils = tikras_wga_liste();

/*
 * Un routeur neuf est d'abord configure sur le reseau local: le serveur ne
 * peut donc pas le joindre. On reserve son adresse et on fournit le script a
 * coller sur place; il rejoint ensuite le tunnel et devient administrable.
 */
if (tikras_has_post('preparer')) {
  $nouveau = trim((string) tikras_post('nouveau_nom'));
  $rapport = tikras_wg_preparer($nouveau);
  $wgFlash = $rapport['message'];
  $wgFlashType = $rapport['ok'] ? 'success' : 'danger';
  if ($rapport['ok']) {
    $wgScript = $rapport['script'];
    $wgScriptSession = $nouveau;
    $wgScriptIp = $rapport['ip'];
  }
  $wgPeers = tikras_wg_liste();
}

if (tikras_has_post('revoir_script')) {
  $cible = (string) tikras_post('session');
  $connus = tikras_wg_liste();
  if (isset($connus[$cible]) && $connus[$cible]['private_key'] != '') {
    $wgScript = tikras_wg_script_routeur($connus[$cible]['private_key'], $connus[$cible]['tunnel_ip'], $wgConfig);
    $wgScriptSession = $cible;
    $wgScriptIp = (string) $connus[$cible]['tunnel_ip'];
  }
}

if (tikras_has_post('ajouter_mikhmon')) {
  $cible = (string) tikras_post('session');
  $connus = tikras_wg_liste();
  if (!isset($connus[$cible])) {
    $wgFlash = 'Routeur inconnu.';
    $wgFlashType = 'danger';
  } else {
    $resultat = tikras_wg_ajouter_routeur_mikhmon(
      $data,
      $cible,
      (string) $connus[$cible]['tunnel_ip'],
      (string) tikras_post('routeur_user', 'admin'),
      (string) tikras_post('routeur_pass'),
      (string) tikras_post('routeur_hotspot'),
      (string) tikras_post('routeur_dns'),
      (string) tikras_post('routeur_devise')
    );
    $wgFlash = $resultat['message'];
    $wgFlashType = $resultat['ok'] ? 'success' : 'danger';
    if ($resultat['ok']) {
      tikras_wg_enregistrer($cible, (string) $connus[$cible]['tunnel_ip'], array(
        'privee' => (string) $connus[$cible]['private_key'],
        'publique' => (string) $connus[$cible]['public_key'],
      ), 'actif', '', true);
      $wgPeers = tikras_wg_liste();
    }
  }
}

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

<?php
// Routeurs prepares mais pas encore enregistres dans TIKRAS IT.
$wgEnAttente = array();
foreach ($wgPeers as $nom => $pair) {
  if (!isset($data[$nom])) {
    $etat = tikras_wg_etat_pair($nom);
    $handshake = isset($etat['handshake']) ? (int) $etat['handshake'] : 0;
    $wgEnAttente[$nom] = array(
      'pair' => $pair,
      'vivant' => $handshake > 0 && (time() - $handshake) < 300,
    );
  }
}
?>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-plus-circle"></i> Nouveau routeur configuré en local</h3>
  </div>
  <div class="tikras-panel-body">
    <p class="tikras-wg-intro">
      Vous venez de configurer un routeur sur votre réseau local ? Il n'est pas
      encore joignable depuis ce serveur. Réservez son adresse ici, collez le
      script obtenu dans son terminal, et il rejoindra le tunnel tout seul.
      Vous pourrez ensuite l'ajouter à TIKRAS IT en un clic.
    </p>
    <form method="post" action="" class="tikras-wg-nouveau">
      <div class="tikras-field">
        <label for="nouveau_nom">Nom du routeur</label>
        <input class="form-control" id="nouveau_nom" type="text" name="nouveau_nom"
               placeholder="ex : BOUTIQUE-CENTRE" pattern="[A-Za-z0-9_.-]+" required>
      </div>
      <button class="tikras-btn tikras-btn-primary" type="submit" name="preparer" value="1">
        <i class="fa fa-magic"></i><span>Réserver une adresse</span>
      </button>
    </form>

    <?php if ($wgScript != '') { ?>
    <div class="tikras-wg-script">
      <h4><i class="fa fa-terminal"></i> À coller dans le terminal de <?= tikras_h($wgScriptSession); ?></h4>
      <p class="tikras-wg-intro">
        Ouvrez le routeur avec Winbox ou WebFig (sur votre réseau local), allez dans
        <strong>New Terminal</strong>, puis collez ces quatre lignes. Adresse attribuée :
        <strong><?= tikras_h($wgScriptIp); ?></strong>
      </p>
      <textarea id="wgScriptTexte" class="form-control" rows="7" readonly><?= tikras_h($wgScript); ?></textarea>
      <div class="tikras-form-actions">
        <button class="tikras-btn tikras-btn-primary" type="button" onclick="copierScriptWg()">
          <i class="fa fa-copy"></i><span>Copier le script</span>
        </button>
        <span class="tikras-version-note" id="wgCopieEtat"></span>
      </div>
    </div>
    <?php } ?>
  </div>
</div>

<?php if (count($wgEnAttente) > 0) { ?>
<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-hourglass-half"></i> Routeurs préparés, pas encore dans TIKRAS IT</h3>
    <span class="tikras-version-note"><?= count($wgEnAttente); ?> en attente</span>
  </div>
  <div class="tikras-panel-body">
    <?php foreach ($wgEnAttente as $nom => $info) { ?>
    <div class="tikras-wg-attente">
      <div class="tikras-wg-attente-tete">
        <strong><?= tikras_h($nom); ?></strong>
        <span class="tikras-wg-session"><?= tikras_h($info['pair']['tunnel_ip']); ?></span>
        <?php if ($info['vivant']) {
          echo '<span class="tikras-status tikras-status-online"><i class="fa fa-check-circle"></i> Tunnel actif</span>';
        } else {
          echo '<span class="tikras-status tikras-status-offline"><i class="fa fa-clock-o"></i> En attente du routeur</span>';
        } ?>
      </div>
      <?php if ($info['vivant']) { ?>
      <form method="post" action="" class="tikras-wg-ajout">
        <input type="hidden" name="session" value="<?= tikras_h($nom); ?>">
        <div class="tikras-wg-ajout-champs">
          <input class="form-control" type="text" name="routeur_user" value="admin" placeholder="Identifiant" required>
          <input class="form-control" type="password" name="routeur_pass" placeholder="Mot de passe du routeur">
          <input class="form-control" type="text" name="routeur_hotspot" placeholder="Nom du hotspot">
          <input class="form-control" type="text" name="routeur_dns" placeholder="DNS (ex : wifi.zone)">
          <input class="form-control" type="text" name="routeur_devise" value="CFA" placeholder="Devise">
        </div>
        <button class="tikras-btn tikras-btn-primary" type="submit" name="ajouter_mikhmon" value="1">
          <i class="fa fa-plus"></i><span>Ajouter à TIKRAS IT</span>
        </button>
      </form>
      <?php } else { ?>
      <form method="post" action="" class="tikras-wg-ajout">
        <input type="hidden" name="session" value="<?= tikras_h($nom); ?>">
        <p class="tikras-wg-intro">
          Le routeur n'a pas encore contacté le serveur. Vérifiez que le script a bien
          été collé, puis actualisez cette page.
        </p>
        <button class="tikras-btn tikras-btn-muted" type="submit" name="revoir_script" value="1">
          <i class="fa fa-terminal"></i><span>Revoir le script</span>
        </button>
      </form>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
</div>
<?php } ?>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-laptop"></i> Mes appareils</h3>
    <span class="tikras-version-note"><?= count($wgAppareils); ?> raccordé(s)</span>
  </div>
  <div class="tikras-panel-body">
    <p class="tikras-wg-intro">
      Un ordinateur ou un téléphone raccordé ici atteint <strong>tous vos routeurs</strong>
      et les équipements de leurs sites, où que vous soyez. C'est ce qui permet
      d'ouvrir Winbox sur une antenne sans être présent sur place.
    </p>
    <?php if (!$wgConfig['disponible']) { ?>
      <?= tikras_ui_alert('warning', "Le concentrateur n'est pas encore actif sur le serveur : lancez deploy/wireguard-hub.sh."); ?>
    <?php } else { ?>
    <form method="post" action="" class="tikras-wg-appareil-forme">
      <input class="form-control" type="text" name="appareil_nom" maxlength="40" required
        placeholder="Nom de l'appareil (ex : Portable Ibrahim)" aria-label="Nom du nouvel appareil">
      <button class="tikras-btn tikras-btn-primary" type="submit" name="ajouter_appareil" value="1">
        <i class="fa fa-plus"></i><span>Ajouter cet appareil</span>
      </button>
    </form>

    <?php if (count($wgAppareils) > 0) { ?>
    <div class="tikras-table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Appareil</th>
            <th>Adresse dans le tunnel</th>
            <th>État</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($wgAppareils as $appareil) {
          $etatApp = tikras_wg_etat_pair((string) $appareil['session']);
          $hsApp = isset($etatApp['handshake']) ? (int) $etatApp['handshake'] : 0;
          $actifApp = $hsApp > 0 && (time() - $hsApp) < 300;
        ?>
          <tr>
            <td>
              <strong><?= tikras_h($appareil['label'] != '' ? $appareil['label'] : $appareil['session']); ?></strong>
              <div class="tikras-wg-session"><?= tikras_h($appareil['session']); ?></div>
            </td>
            <td><?= tikras_h($appareil['tunnel_ip']); ?></td>
            <td>
              <?php if ($actifApp) {
                echo '<span class="tikras-status tikras-status-online"><i class="fa fa-check-circle"></i> Connecté</span>';
              } elseif ($hsApp > 0) {
                echo '<span class="tikras-status tikras-status-offline"><i class="fa fa-clock-o"></i> Vu ' . tikras_h(date('d/m H:i', $hsApp)) . '</span>';
              } else {
                echo '<span class="tikras-status tikras-status-unknown"><i class="fa fa-hourglass-half"></i> Jamais connecté</span>';
              } ?>
            </td>
            <td class="text-right">
              <form method="post" action="" class="tikras-wg-form">
                <input type="hidden" name="appareil" value="<?= tikras_h($appareil['session']); ?>">
                <button class="tikras-btn tikras-btn-muted" type="submit" name="voir_config" value="1"
                  title="Afficher la configuration à installer">
                  <i class="fa fa-eye"></i><span>Configuration</span>
                </button>
                <a class="tikras-btn tikras-btn-muted tikras-btn-icon" title="Télécharger le fichier"
                  href="./admin.php?id=wireguard&config_appareil=<?= rawurlencode((string) $appareil['session']); ?>">
                  <i class="fa fa-download"></i><span>Télécharger</span>
                </a>
                <button class="tikras-btn tikras-btn-danger tikras-btn-icon" type="submit" name="retirer_appareil" value="1"
                  title="Retirer cet appareil"
                  onclick="return confirm('Retirer cet appareil du concentrateur ? Il perdra l\'accès à vos routeurs.');">
                  <i class="fa fa-trash"></i><span>Retirer</span>
                </button>
              </form>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
    <?php } ?>

    <?php if ($wgConfigAffichee != '') { ?>
    <div class="tikras-wg-config">
      <h4>Configuration de <?= tikras_h($wgConfigNom); ?></h4>
      <p class="tikras-wg-intro">
        Installez WireGuard sur l'appareil, puis importez ce texte (ou le fichier
        téléchargé). Sur téléphone, l'application WireGuard permet d'importer un fichier.
        <strong>Ce texte contient une clé privée : ne le transmettez à personne.</strong>
      </p>
      <textarea id="wgConfigTexte" class="form-control" rows="12" readonly><?= tikras_h($wgConfigAffichee); ?></textarea>
      <div class="tikras-form-actions">
        <button class="tikras-btn tikras-btn-primary" type="button" onclick="copierConfigAppareil()">
          <i class="fa fa-copy"></i><span>Copier</span>
        </button>
        <span class="tikras-version-note" id="wgConfigCopie"></span>
      </div>
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
            <th title="Reseau des antennes et points d'acces du site">Réseau du site</th>
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
            <td>
              <?php if ($raccorde) {
                $lanActuel = isset($pair['lan_subnet']) ? (string) $pair['lan_subnet'] : '';
              ?>
              <form method="post" action="" class="tikras-wg-lan-forme">
                <input type="hidden" name="session" value="<?= tikras_h($nom); ?>">
                <input class="form-control tikras-wg-lan" type="text" name="lan_subnet"
                  value="<?= tikras_h($lanActuel); ?>" placeholder="ex : 192.168.88.0/24"
                  title="Reseau local de ce site : ses antennes deviennent joignables depuis vos appareils"
                  aria-label="Reseau local derriere <?= tikras_h($nom); ?>">
                <button class="tikras-btn tikras-btn-muted tikras-btn-icon" type="submit" name="definir_lan" value="1"
                  title="Enregistrer le réseau du site">
                  <i class="fa fa-check"></i><span>Enregistrer</span>
                </button>
              </form>
              <?php } else { ?>
                <span class="tikras-wg-vide">—</span>
              <?php } ?>
            </td>
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
function copierScriptWg() {
  var champ = document.getElementById('wgScriptTexte');
  var etat = document.getElementById('wgCopieEtat');
  if (!champ) { return; }
  champ.select();
  champ.setSelectionRange(0, 99999);
  try {
    document.execCommand('copy');
    if (etat) { etat.textContent = 'Script copié.'; }
  } catch (e) {
    if (etat) { etat.textContent = 'Sélectionnez le texte puis copiez-le.'; }
  }
}

function copierConfigAppareil() {
  var champ = document.getElementById('wgConfigTexte');
  var etat = document.getElementById('wgConfigCopie');
  if (!champ) { return; }
  champ.select();
  champ.setSelectionRange(0, 99999);
  try {
    document.execCommand('copy');
    if (etat) { etat.textContent = 'Configuration copiée.'; }
  } catch (e) {
    if (etat) { etat.textContent = 'Sélectionnez le texte puis copiez-le.'; }
  }
}
document.getElementById('wgSearch').addEventListener('input', function () {
  var terme = this.value.toLowerCase();
  var lignes = document.querySelectorAll('.tikras-wg-row');
  for (var i = 0; i < lignes.length; i++) {
    var texte = lignes[i].getAttribute('data-recherche') || '';
    lignes[i].style.display = texte.indexOf(terme) !== -1 ? '' : 'none';
  }
});
</script>
