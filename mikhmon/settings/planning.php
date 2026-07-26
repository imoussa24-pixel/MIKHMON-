<?php
/*
 * Planification des generations de tickets et de leur envoi automatique.
 * L'execution est portee par le moteur d'automatisation, il n'y a donc rien
 * a programmer en dehors de l'application.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
include_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
include_once(dirname(__DIR__) . '/lib/tikras_storage.php');
include_once(dirname(__DIR__) . '/lib/tikras_ticket_schedule.php');
include_once(dirname(__DIR__) . '/lib/tikras_notify.php');

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  return;
}

$planFlash = '';
$planFlashType = 'success';
$planEdite = null;

if (tikras_has_post('enregistrer')) {
  $resultat = tikras_sched_enregistrer(array(
    'id' => tikras_post('id', '0'),
    'label' => tikras_post('label'),
    'session' => tikras_post('session'),
    'profile' => tikras_post('profile'),
    'server' => tikras_post('server', 'all'),
    'quantity' => tikras_post('quantity', '10'),
    'user_mode' => tikras_post('user_mode', 'vc'),
    'code_length' => tikras_post('code_length', '6'),
    'code_chars' => tikras_post('code_chars', 'mix'),
    'prefix' => tikras_post('prefix'),
    'time_limit' => tikras_post('time_limit'),
    'data_limit' => tikras_post('data_limit', '0'),
    'comment' => tikras_post('comment'),
    'with_qr' => tikras_post('with_qr', '0'),
    'logo_file' => tikras_post('logo_file', ''),
    'channel' => tikras_post('channel', 'email'),
    'target' => tikras_post('target'),
    'frequency' => tikras_post('frequency', 'daily'),
    'hour' => tikras_post('hour', '8'),
    'weekday' => tikras_post('weekday', '1'),
    'monthday' => tikras_post('monthday', '1'),
    'enabled' => tikras_post('enabled', '0'),
  ));
  $planFlash = $resultat['message'];
  $planFlashType = $resultat['ok'] ? 'success' : 'danger';
}

if (tikras_has_post('supprimer')) {
  tikras_sched_supprimer(tikras_post('id', '0'));
  $planFlash = 'Planification supprimee.';
}

if (tikras_has_post('executer')) {
  $id = (int) tikras_post('id', '0');
  $plan = tikras_sched_get($id);
  if ($plan === null) {
    $planFlash = 'Planification introuvable.';
    $planFlashType = 'danger';
  } else {
    @set_time_limit(180);
    $rapport = tikras_sched_executer($plan, $data);
    tikras_sched_marquer($id, $rapport['statut'], $rapport['erreur'], $rapport['crees']);
    if ($rapport['ok']) {
      $planFlash = $rapport['crees'] . ' ticket(s) generes et envoyes.';
      $planFlashType = 'success';
    } else {
      $planFlash = ($rapport['crees'] > 0 ? $rapport['crees'] . ' ticket(s) generes. ' : '') . $rapport['erreur'];
      $planFlashType = $rapport['crees'] > 0 ? 'warning' : 'danger';
    }
    if ($rapport['pdf'] != '') {
      $planFlash .= ' PDF : ' . $rapport['pdf'];
    }
  }
}

if (tikras_has_get('modifier')) {
  $planEdite = tikras_sched_get(tikras_get('modifier'));
}

$planifications = tikras_sched_liste();
$frequences = tikras_sched_frequences();
$canaux = tikras_sched_canaux();
$jours = tikras_sched_jours();

/* Profils du routeur selectionne, pour eviter une saisie a l'aveugle. */
$profilsParRouteur = array();
$sessionEditee = $planEdite !== null ? (string) $planEdite['session'] : '';
if ($sessionEditee != '' && isset($data[$sessionEditee])) {
  include_once(dirname(__DIR__) . '/lib/tikras_routeros.php');
  $api = tikras_routeros_create();
  $api->attempts = 1;
  $api->timeout = 6;
  $hote = tikras_cfg_value($data, $sessionEditee, 1, '!', '');
  $utilisateur = tikras_cfg_value($data, $sessionEditee, 2, '@|@', '');
  $motDePasse = decrypt(tikras_cfg_value($data, $sessionEditee, 3, '#|#', ''));
  if (tikras_routeros_connect($api, $hote, $utilisateur, $motDePasse, $sessionEditee, array('timeout' => 6, 'force' => true))) {
    $lignes = $api->comm('/ip/hotspot/user/profile/print', array('.proplist' => 'name'));
    if (is_array($lignes)) {
      foreach ($lignes as $ligne) {
        if (isset($ligne['name'])) {
          $profilsParRouteur[] = (string) $ligne['name'];
        }
      }
    }
    tikras_routeros_disconnect($api);
  }
}

$valeur = function ($cle, $defaut = '') use ($planEdite) {
  if ($planEdite !== null && isset($planEdite[$cle])) {
    return (string) $planEdite[$cle];
  }
  return $defaut;
};
?>

<?= tikras_ui_page_header('calendar', 'Tickets automatiques',
  count($planifications) . ' planification(s) enregistree(s)',
  tikras_ui_button('./admin.php?id=planning', 'plus', 'Nouvelle planification', 'primary')); ?>

<?php
if ($planFlash != '') {
  echo tikras_ui_alert($planFlashType, $planFlash);
}
?>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-info-circle"></i> Comment ça fonctionne</h3>
  </div>
  <div class="tikras-panel-body">
    <p class="tikras-wg-intro">
      Définissez une fois ce qu'il faut produire — routeur, profil, quantité, format
      des codes — et à quelle fréquence. À l'heure dite, les tickets sont créés sur le
      routeur, le PDF est fabriqué au même format que la génération manuelle, puis
      envoyé au destinataire choisi.
    </p>
    <p class="tikras-wg-intro">
      Aucun outil externe n'est nécessaire : le serveur s'en charge tout seul, même
      lorsque personne n'est connecté au panneau.
    </p>
  </div>
</div>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-<?= $planEdite !== null ? 'edit' : 'plus-circle'; ?>"></i>
      <?= $planEdite !== null ? 'Modifier la planification' : 'Nouvelle planification'; ?></h3>
    <?php if ($planEdite !== null) { ?>
      <a class="tikras-version-note" href="./admin.php?id=planning">Annuler la modification</a>
    <?php } ?>
  </div>
  <div class="tikras-panel-body">
    <form method="post" action="" class="tikras-plan-form">
      <input type="hidden" name="id" value="<?= tikras_h($valeur('id', '0')); ?>">

      <div class="tikras-plan-grille">
        <div class="tikras-field">
          <label for="label">Nom de la planification</label>
          <input class="form-control" id="label" type="text" name="label"
                 value="<?= tikras_h($valeur('label')); ?>" placeholder="ex : Tickets du matin" required>
        </div>

        <div class="tikras-field">
          <label for="session">Routeur</label>
          <select class="form-control" id="session" name="session" required
                  onchange="window.location='./admin.php?id=planning&modifier=<?= tikras_h($valeur('id','0')); ?>&routeur='+encodeURIComponent(this.value)">
            <option value="">— choisir —</option>
            <?php foreach ($data as $nom => $cfg) {
              if ($nom == '' || $nom == 'mikhmon') { continue; }
              $libelle = tikras_cfg_value($data, $nom, 4, '%', '');
              echo '<option value="' . tikras_h($nom) . '"' . ($valeur('session') == $nom ? ' selected' : '') . '>'
                . tikras_h($libelle != '' ? $libelle . ' (' . $nom . ')' : $nom) . '</option>';
            } ?>
          </select>
        </div>

        <div class="tikras-field">
          <label for="profile">Profil hotspot</label>
          <?php if (count($profilsParRouteur) > 0) { ?>
          <select class="form-control" id="profile" name="profile" required>
            <?php foreach ($profilsParRouteur as $profil) {
              echo '<option value="' . tikras_h($profil) . '"' . ($valeur('profile') == $profil ? ' selected' : '') . '>'
                . tikras_h($profil) . '</option>';
            } ?>
          </select>
          <?php } else { ?>
          <input class="form-control" id="profile" type="text" name="profile"
                 value="<?= tikras_h($valeur('profile')); ?>" placeholder="nom exact du profil" required>
          <small class="tikras-field-hint">Enregistrez puis rouvrez pour choisir dans la liste du routeur.</small>
          <?php } ?>
        </div>

        <div class="tikras-field">
          <label for="quantity">Nombre de tickets</label>
          <input class="form-control" id="quantity" type="number" name="quantity" min="1" max="1000"
                 value="<?= tikras_h($valeur('quantity', '10')); ?>" required>
        </div>

        <div class="tikras-field">
          <label for="user_mode">Type de ticket</label>
          <select class="form-control" id="user_mode" name="user_mode">
            <option value="vc"<?= $valeur('user_mode', 'vc') == 'vc' ? ' selected' : ''; ?>>Code unique (voucher)</option>
            <option value="up"<?= $valeur('user_mode') == 'up' ? ' selected' : ''; ?>>Utilisateur + mot de passe</option>
          </select>
        </div>

        <div class="tikras-field">
          <label for="code_chars">Caractères des codes</label>
          <select class="form-control" id="code_chars" name="code_chars">
            <?php
            $jeux = array(
              'lower' => 'Lettres minuscules — abcd',
              'upper' => 'Lettres majuscules — ABCD',
              'upplow' => 'Lettres mélangées — aBcD',
              'mix' => 'Minuscules + chiffres — abcd2345',
              'mix1' => 'Majuscules + chiffres — ABCD2345',
              'mix2' => 'Mélangé + chiffres — aBcD2345',
              'num' => 'Chiffres uniquement — 2345',
            );
            foreach ($jeux as $cle => $libelle) {
              echo '<option value="' . $cle . '"' . ($valeur('code_chars', 'mix') == $cle ? ' selected' : '') . '>'
                . tikras_h($libelle) . '</option>';
            } ?>
          </select>
        </div>

        <div class="tikras-field">
          <label for="code_length">Longueur des codes</label>
          <select class="form-control" id="code_length" name="code_length">
            <?php for ($l = 3; $l <= 8; $l++) {
              echo '<option value="' . $l . '"' . ((int) $valeur('code_length', '6') === $l ? ' selected' : '') . '>' . $l . '</option>';
            } ?>
          </select>
        </div>

        <div class="tikras-field">
          <label for="prefix">Préfixe (facultatif)</label>
          <input class="form-control" id="prefix" type="text" name="prefix" maxlength="6"
                 value="<?= tikras_h($valeur('prefix')); ?>" placeholder="ex : WIFI">
        </div>

        <div class="tikras-field">
          <label for="time_limit">Limite de temps</label>
          <input class="form-control" id="time_limit" type="text" name="time_limit"
                 value="<?= tikras_h($valeur('time_limit')); ?>" placeholder="ex : 1d ou 2h (vide = illimité)">
        </div>

        <div class="tikras-field">
          <label for="data_limit">Limite de données (octets)</label>
          <input class="form-control" id="data_limit" type="text" name="data_limit"
                 value="<?= tikras_h($valeur('data_limit', '0')); ?>" placeholder="0 = illimité">
        </div>
      </div>

      <div class="tikras-plan-section"><i class="fa fa-print"></i> Apparence du ticket imprimé</div>
      <div class="tikras-plan-grille">
        <div class="tikras-field">
          <label for="with_qr">Code QR</label>
          <select class="form-control" id="with_qr" name="with_qr">
            <option value="1"<?= $valeur('with_qr', '1') == '1' ? ' selected' : ''; ?>>Imprimer un QR sur chaque ticket</option>
            <option value="0"<?= $valeur('with_qr', '1') == '0' ? ' selected' : ''; ?>>Sans QR</option>
          </select>
          <small class="tikras-field-hint">Le client scanne et se connecte sans recopier le code.</small>
        </div>
        <div class="tikras-field">
          <label for="logo_file">Logo (facultatif)</label>
          <select class="form-control" id="logo_file" name="logo_file">
            <option value="">Aucun logo</option>
            <?php foreach (tikras_sched_logos() as $fichier) {
              echo '<option value="' . tikras_h($fichier) . '"' . ($valeur('logo_file') == $fichier ? ' selected' : '') . '>'
                . tikras_h($fichier) . '</option>';
            } ?>
          </select>
          <small class="tikras-field-hint">Déposez vos images via « Téléverser un logo » pour les retrouver ici.</small>
        </div>
      </div>

      <div class="tikras-plan-section"><i class="fa fa-clock-o"></i> Périodicité</div>
      <div class="tikras-plan-grille">
        <div class="tikras-field">
          <label for="frequency">Fréquence</label>
          <select class="form-control" id="frequency" name="frequency" onchange="majPeriodicite()">
            <?php foreach ($frequences as $cle => $libelle) {
              echo '<option value="' . $cle . '"' . ($valeur('frequency', 'daily') == $cle ? ' selected' : '') . '>'
                . tikras_h($libelle) . '</option>';
            } ?>
          </select>
        </div>
        <div class="tikras-field" id="champHeure">
          <label for="hour">Heure</label>
          <select class="form-control" id="hour" name="hour">
            <?php for ($h = 0; $h <= 23; $h++) {
              echo '<option value="' . $h . '"' . ((int) $valeur('hour', '8') === $h ? ' selected' : '') . '>'
                . sprintf('%02d h', $h) . '</option>';
            } ?>
          </select>
        </div>
        <div class="tikras-field" id="champJour">
          <label for="weekday">Jour de la semaine</label>
          <select class="form-control" id="weekday" name="weekday">
            <?php foreach ($jours as $num => $libelle) {
              echo '<option value="' . $num . '"' . ((int) $valeur('weekday', '1') === $num ? ' selected' : '') . '>'
                . $libelle . '</option>';
            } ?>
          </select>
        </div>
        <div class="tikras-field" id="champDate">
          <label for="monthday">Jour du mois</label>
          <select class="form-control" id="monthday" name="monthday">
            <?php for ($j = 1; $j <= 28; $j++) {
              echo '<option value="' . $j . '"' . ((int) $valeur('monthday', '1') === $j ? ' selected' : '') . '>'
                . $j . '</option>';
            } ?>
          </select>
        </div>
      </div>

      <div class="tikras-plan-section"><i class="fa fa-paper-plane"></i> Envoi du PDF</div>
      <div class="tikras-plan-grille">
        <div class="tikras-field">
          <label for="channel">Canal</label>
          <select class="form-control" id="channel" name="channel" onchange="majCanal()">
            <?php foreach ($canaux as $cle => $libelle) {
              echo '<option value="' . $cle . '"' . ($valeur('channel', 'email') == $cle ? ' selected' : '') . '>'
                . tikras_h($libelle) . '</option>';
            } ?>
          </select>
        </div>
        <div class="tikras-field" id="champCible">
          <label for="target">Destinataire</label>
          <input class="form-control" id="target" type="text" name="target"
                 value="<?= tikras_h($valeur('target')); ?>">
          <small class="tikras-field-hint" id="aideCible"></small>
        </div>
        <div class="tikras-field">
          <label for="comment">Commentaire sur les tickets</label>
          <input class="form-control" id="comment" type="text" name="comment"
                 value="<?= tikras_h($valeur('comment')); ?>" placeholder="laisser vide pour un commentaire automatique">
        </div>
        <div class="tikras-field">
          <label for="enabled">État</label>
          <select class="form-control" id="enabled" name="enabled">
            <option value="1"<?= $valeur('enabled', '1') == '1' ? ' selected' : ''; ?>>Active</option>
            <option value="0"<?= $valeur('enabled', '1') == '0' ? ' selected' : ''; ?>>En pause</option>
          </select>
        </div>
      </div>

      <div class="tikras-form-actions">
        <button class="tikras-btn tikras-btn-primary" type="submit" name="enregistrer" value="1">
          <i class="fa fa-save"></i><span><?= $planEdite !== null ? 'Enregistrer les modifications' : 'Créer la planification'; ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-list"></i> Planifications</h3>
    <span class="tikras-version-note"><?= count($planifications); ?> enregistrée(s)</span>
  </div>
  <div class="tikras-panel-body">
    <?php if (count($planifications) < 1) { ?>
      <p class="tikras-wg-intro">Aucune planification pour le moment.</p>
    <?php } else { ?>
    <div class="tikras-table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Planification</th>
            <th>Production</th>
            <th>Périodicité</th>
            <th>Envoi</th>
            <th>Dernière exécution</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($planifications as $plan) {
          $freq = isset($frequences[$plan['frequency']]) ? $frequences[$plan['frequency']] : $plan['frequency'];
          $detail = $freq;
          if ($plan['frequency'] == 'weekly') {
            $detail .= ' — ' . (isset($jours[$plan['weekday']]) ? $jours[$plan['weekday']] : '');
          } elseif ($plan['frequency'] == 'monthly') {
            $detail .= ' — le ' . (int) $plan['monthday'];
          }
          if ($plan['frequency'] != 'hourly') {
            $detail .= ' à ' . sprintf('%02d h', (int) $plan['hour']);
          }
          $succes = in_array($plan['last_status'], array('courriel_envoye', 'whatsapp_envoye', 'telegram_envoye', 'pdf_conserve'));
          ?>
          <tr>
            <td>
              <strong><?= tikras_h($plan['label'] != '' ? $plan['label'] : 'Sans nom'); ?></strong>
              <div class="tikras-wg-session"><?= tikras_h($plan['session']); ?></div>
              <?php if ((int) $plan['enabled'] !== 1) {
                echo '<span class="tikras-status tikras-status-unknown">En pause</span>';
              } ?>
            </td>
            <td>
              <?= (int) $plan['quantity']; ?> × <?= tikras_h($plan['profile']); ?>
              <div class="tikras-wg-session"><?= (int) $plan['code_length']; ?> car. · <?= tikras_h($plan['code_chars']); ?></div>
              <div class="tikras-wg-session">
                <?= (int) tikras_array_get($plan, 'with_qr', 1) === 1 ? 'avec QR' : 'sans QR'; ?>
                <?= tikras_array_get($plan, 'logo_file', '') != '' ? ' · logo' : ''; ?>
              </div>
            </td>
            <td><?= tikras_h($detail); ?></td>
            <td>
              <?= tikras_h(isset($canaux[$plan['channel']]) ? $canaux[$plan['channel']] : $plan['channel']); ?>
              <?php if ($plan['target'] != '') { ?>
                <div class="tikras-wg-session"><?= tikras_h($plan['target']); ?></div>
              <?php } ?>
            </td>
            <td>
              <?php if ($plan['last_run_at'] == '') { ?>
                <span class="tikras-wg-vide">jamais</span>
              <?php } else { ?>
                <?= tikras_h(date('d/m H:i', strtotime($plan['last_run_at']))); ?>
                <div>
                  <span class="tikras-status <?= $succes ? 'tikras-status-online' : 'tikras-status-offline'; ?>">
                    <?= tikras_h(str_replace('_', ' ', $plan['last_status'])); ?>
                  </span>
                </div>
                <?php if ($plan['last_error'] != '') { ?>
                  <div class="tikras-wg-erreur"><?= tikras_h($plan['last_error']); ?></div>
                <?php } ?>
              <?php } ?>
              <div class="tikras-wg-session"><?= (int) $plan['total_tickets']; ?> ticket(s) au total</div>
            </td>
            <td class="text-right">
              <div class="tikras-plan-actions">
                <a class="tikras-btn tikras-btn-muted" href="./admin.php?id=planning&modifier=<?= (int) $plan['id']; ?>">
                  <i class="fa fa-edit"></i><span>Modifier</span>
                </a>
                <form method="post" action="" onsubmit="return confirm('Générer et envoyer maintenant ?');">
                  <input type="hidden" name="id" value="<?= (int) $plan['id']; ?>">
                  <button class="tikras-btn tikras-btn-primary" type="submit" name="executer" value="1">
                    <i class="fa fa-play"></i><span>Exécuter</span>
                  </button>
                </form>
                <form method="post" action="" onsubmit="return confirm('Supprimer cette planification ?');">
                  <input type="hidden" name="id" value="<?= (int) $plan['id']; ?>">
                  <button class="tikras-btn tikras-btn-danger" type="submit" name="supprimer" value="1">
                    <i class="fa fa-trash"></i><span>Supprimer</span>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</div>

<script>
function majPeriodicite() {
  var f = document.getElementById('frequency').value;
  document.getElementById('champHeure').style.display = (f === 'hourly') ? 'none' : '';
  document.getElementById('champJour').style.display = (f === 'weekly') ? '' : 'none';
  document.getElementById('champDate').style.display = (f === 'monthly') ? '' : 'none';
}
function majCanal() {
  var c = document.getElementById('channel').value;
  var ligne = document.getElementById('champCible');
  var cible = document.getElementById('target');
  var aide = document.getElementById('aideCible');
  if (c === 'none') {
    ligne.style.display = 'none';
    return;
  }
  ligne.style.display = '';
  if (c === 'email') {
    cible.type = 'email';
    cible.placeholder = 'destinataire@exemple.com';
    aide.textContent = 'Le PDF est joint au message.';
  } else if (c === 'whatsapp') {
    cible.type = 'text';
    cible.placeholder = '22790000000';
    aide.textContent = 'Chiffres uniquement, avec l\'indicatif du pays.';
  } else {
    cible.type = 'text';
    cible.placeholder = 'identifiant de discussion';
    aide.textContent = 'Laisser vide pour utiliser la discussion configurée.';
  }
}
majPeriodicite();
majCanal();
</script>
