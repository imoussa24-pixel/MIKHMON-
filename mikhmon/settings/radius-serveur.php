<?php
/*
 * Serveur RADIUS TIKRAS: routeurs autorises et tickets partages.
 * La selection des routeurs est explicite: seuls ceux qui doivent partager
 * les memes tickets sont raccordes, pas l'ensemble du parc.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
include_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
include_once(dirname(__DIR__) . '/lib/tikras_storage.php');
include_once(dirname(__DIR__) . '/lib/tikras_radius_server.php');

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  return;
}

$radFlash = '';
$radFlashType = 'success';
$radScript = '';
$radScriptSession = '';

if (tikras_has_post('autoriser')) {
  $cible = (string) tikras_post('session');
  $adresse = trim((string) tikras_post('adresse'));
  if (!isset($data[$cible])) {
    $radFlash = 'Routeur inconnu.';
    $radFlashType = 'danger';
  } else {
    if ($adresse == '') {
      $adresse = tikras_cfg_value($data, $cible, 1, '!', '');
    }
    $libelle = tikras_cfg_value($data, $cible, 4, '%', $cible);
    $resultat = tikras_rad_autoriser_routeur($cible, $adresse, $libelle);
    $radFlash = $resultat['message'];
    $radFlashType = $resultat['ok'] ? 'success' : 'danger';
    if ($resultat['ok']) {
      $radScriptSession = $cible;

      /*
       * Adresse du serveur et profils vises sont deduits du routeur lui-meme:
       * une adresse prise sur un autre reseau, ou un profil qu'aucun serveur
       * Hotspot n'utilise, donnent une configuration sans effet et sans
       * message d'erreur.
       */
      $adresseServeur = trim((string) tikras_post('serveur_adresse', ''));
      if ($adresseServeur == '') {
        $adresseServeur = tikras_rad_adresse_pour_routeur($adresse);
      }
      if ($adresseServeur == '') {
        $adresseServeur = '10.200.0.1';
        $radFlash .= " Adresse du serveur non deduite : verifiez-la avant de coller le script.";
        $radFlashType = 'warning';
      }

      $profilsVises = array();
      $profilSaisi = trim((string) tikras_post('profil_hotspot', ''));
      if ($profilSaisi != '') {
        $profilsVises = array($profilSaisi);
      } else {
        include_once(dirname(__DIR__) . '/lib/tikras_routeros.php');
        $apiRouteur = tikras_routeros_create();
        $apiRouteur->attempts = 1;
        $apiRouteur->timeout = 8;
        $utilisateurRouteur = tikras_cfg_value($data, $cible, 2, '@|@', '');
        $passRouteur = decrypt(tikras_cfg_value($data, $cible, 3, '#|#', ''));
        if (tikras_routeros_connect($apiRouteur, $adresse, $utilisateurRouteur, $passRouteur, $cible, array('timeout' => 8, 'force' => true))) {
          $profilsVises = tikras_rad_profils_actifs($apiRouteur);
          tikras_routeros_disconnect($apiRouteur);
        }
      }

      $radScript = tikras_rad_script_routeur(
        $adresseServeur,
        $resultat['secret'],
        $profilsVises,
        tikras_post('avec_ppp') != ''
      );
      if (count($profilsVises) > 0) {
        $radFlash .= ' Profil(s) visé(s) : ' . implode(', ', $profilsVises) . '.';
      }
    }
  }
}

if (tikras_has_post('retirer')) {
  $cible = (string) tikras_post('session');
  tikras_rad_retirer_routeur($cible);
  $radFlash = 'Routeur retiré du serveur RADIUS. Pensez à retirer sa configuration sur le routeur lui-même.';
}

if (tikras_has_post('revoir')) {
  $cible = (string) tikras_post('session');
  $connus = tikras_rad_routeurs();
  if (isset($connus[$cible])) {
    $radScriptSession = $cible;
    $radScript = tikras_rad_script_routeur(
      (string) tikras_post('serveur_adresse', '10.200.0.1'),
      (string) $connus[$cible]['secret'],
      (string) tikras_post('profil_hotspot', ''),
      false
    );
  }
}

if (tikras_has_post('supprimer_ticket')) {
  tikras_rad_supprimer_ticket((string) tikras_post('identifiant'));
  $radFlash = 'Ticket supprimé du serveur RADIUS.';
}

$radEtat = tikras_rad_etat();
$radRouteurs = tikras_rad_routeurs();
$radTickets = $radEtat['disponible'] ? tikras_rad_lister_tickets(30) : array();
$statuts = tikras_storage_all_router_statuses();
?>

<?= tikras_ui_page_header('key', 'Serveur RADIUS',
  $radEtat['disponible']
    ? count($radRouteurs) . ' routeur(s) raccordé(s) · ' . $radEtat['tickets'] . ' ticket(s) partagé(s)'
    : 'Serveur non installé',
  tikras_ui_button('./admin.php?id=radius-serveur', 'refresh', 'Actualiser', 'muted')); ?>

<?php
if ($radFlash != '') {
  echo tikras_ui_alert($radFlashType, $radFlash);
}
if (!$radEtat['disponible']) {
  echo tikras_ui_alert('danger', $radEtat['erreur']);
}
?>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-info-circle"></i> À quoi sert ce serveur</h3>
  </div>
  <div class="tikras-panel-body">
    <p class="tikras-wg-intro">
      Un ticket enregistré ici est valable sur <strong>tous les routeurs raccordés</strong>,
      sans être recopié sur chacun. Le client peut se connecter sur l'un
      quelconque d'entre eux, et sa consommation est comptée une seule fois.
    </p>
    <p class="tikras-wg-intro">
      <strong>Ne raccordez que les routeurs concernés.</strong> Un routeur qui vend ses
      propres tickets n'a rien à faire ici : il continue de fonctionner seul,
      sans dépendre du serveur. Le raccordement se justifie pour des sites entre
      lesquels vos clients circulent.
    </p>
    <?php if ($radEtat['disponible']) { ?>
    <div class="tikras-wg-hub">
      <span><i class="fa fa-database"></i> Tickets : <strong><?= (int) $radEtat['tickets']; ?></strong></span>
      <span><i class="fa fa-server"></i> Routeurs : <strong><?= (int) $radEtat['routeurs']; ?></strong></span>
      <span><i class="fa fa-signal"></i> Sessions en cours : <strong><?= (int) $radEtat['sessions_actives']; ?></strong></span>
      <span><i class="fa fa-history"></i> Sessions enregistrées : <strong><?= (int) $radEtat['sessions_total']; ?></strong></span>
    </div>
    <?php } ?>
  </div>
</div>

<?php if ($radScript != '') { ?>
<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-terminal"></i> À coller dans <?= tikras_h($radScriptSession); ?></h3>
  </div>
  <div class="tikras-panel-body">
    <p class="tikras-wg-intro">
      Ces commandes ajoutent le serveur RADIUS au routeur et activent
      l'authentification à distance sur le profil indiqué. Elles ne suppriment
      ni les tickets déjà présents sur le routeur, ni ses autres réglages.
    </p>
    <div class="tikras-wg-script">
      <textarea id="radScriptTexte" class="form-control" rows="6" readonly><?= tikras_h($radScript); ?></textarea>
      <div class="tikras-form-actions">
        <button class="tikras-btn tikras-btn-primary" type="button" onclick="copierRadius()">
          <i class="fa fa-copy"></i><span>Copier</span>
        </button>
        <span class="tikras-version-note" id="radCopie"></span>
      </div>
    </div>
  </div>
</div>
<?php } ?>

<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-server"></i> Routeurs du serveur RADIUS</h3>
    <span class="tikras-version-note"><?= count($radRouteurs); ?> raccordé(s)</span>
  </div>
  <div class="tikras-panel-body">
    <div class="tikras-router-tools">
      <input id="radSearch" class="form-control" type="search" placeholder="Rechercher un routeur">
    </div>
    <form method="post" action="" class="tikras-rad-options">
      <div class="tikras-plan-grille">
        <div class="tikras-field">
          <label for="serveur_adresse">Adresse du serveur vue par les routeurs</label>
          <input class="form-control" id="serveur_adresse" name="serveur_adresse" type="text"
                 value="<?= tikras_h(tikras_post('serveur_adresse', '10.200.0.1')); ?>">
          <small class="tikras-field-hint">10.200.0.1 par le tunnel WireGuard ; sinon l'adresse par laquelle le routeur joint ce serveur.</small>
        </div>
        <div class="tikras-field">
          <label for="profil_hotspot">Profil hotspot à basculer en RADIUS</label>
          <input class="form-control" id="profil_hotspot" name="profil_hotspot" type="text"
                 value="<?= tikras_h(tikras_post('profil_hotspot', '')); ?>" placeholder="laisser vide pour ne pas y toucher">
        </div>
        <div class="tikras-field">
          <label for="avec_ppp">PPP</label>
          <select class="form-control" id="avec_ppp" name="avec_ppp">
            <option value="">Hotspot seulement</option>
            <option value="1"<?= tikras_post('avec_ppp') != '' ? ' selected' : ''; ?>>Hotspot et PPP</option>
          </select>
        </div>
      </div>

      <div class="tikras-table-wrap">
        <table class="table" id="radTable">
          <thead>
            <tr>
              <th>Routeur</th>
              <th>Adresse</th>
              <th>État</th>
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
            $raccorde = isset($radRouteurs[$nom]);
            $enLigne = isset($statuts[$nom]) && isset($statuts[$nom]['last_state']) && $statuts[$nom]['last_state'] == 'online';
            ?>
            <tr class="tikras-rad-row" data-recherche="<?= tikras_h(strtolower($nom . ' ' . $libelle . ' ' . $hote)); ?>">
              <td>
                <strong><?= tikras_h($libelle != '' ? $libelle : $nom); ?></strong>
                <div class="tikras-wg-session"><?= tikras_h($nom); ?></div>
              </td>
              <td>
                <?= tikras_h($raccorde ? $radRouteurs[$nom]['nasname'] : $hote); ?>
                <?php if (!$enLigne) { ?><div class="tikras-wg-session">hors ligne</div><?php } ?>
              </td>
              <td>
                <?php if ($raccorde) {
                  echo '<span class="tikras-status tikras-status-online"><i class="fa fa-check-circle"></i> Raccordé</span>';
                } else {
                  echo '<span class="tikras-status tikras-status-unknown"><i class="fa fa-minus-circle"></i> Autonome</span>';
                } ?>
              </td>
              <td class="text-right">
                <div class="tikras-plan-actions">
                  <?php if ($radEtat['disponible']) { ?>
                    <?php if ($raccorde) { ?>
                      <button class="tikras-btn tikras-btn-muted" type="submit" name="revoir" value="1"
                              onclick="document.getElementById('radSession').value='<?= tikras_h($nom); ?>'">
                        <i class="fa fa-terminal"></i><span>Revoir le script</span>
                      </button>
                      <button class="tikras-btn tikras-btn-danger" type="submit" name="retirer" value="1"
                              onclick="document.getElementById('radSession').value='<?= tikras_h($nom); ?>'; return confirm('Retirer ce routeur du serveur RADIUS ?');">
                        <i class="fa fa-times"></i><span>Retirer</span>
                      </button>
                    <?php } else { ?>
                      <button class="tikras-btn tikras-btn-primary" type="submit" name="autoriser" value="1"
                              onclick="document.getElementById('radSession').value='<?= tikras_h($nom); ?>'">
                        <i class="fa fa-plus"></i><span>Raccorder</span>
                      </button>
                    <?php } ?>
                  <?php } else { ?>
                    <button class="tikras-btn tikras-btn-muted" type="button" disabled>Serveur absent</button>
                  <?php } ?>
                </div>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <input type="hidden" name="session" id="radSession" value="">
      <input type="hidden" name="adresse" value="">
    </form>
  </div>
</div>

<?php if ($radEtat['disponible']) { ?>
<div class="tikras-panel">
  <div class="tikras-panel-header">
    <h3><i class="fa fa-ticket"></i> Tickets partagés</h3>
    <span class="tikras-version-note"><?= count($radTickets); ?> affiché(s) sur <?= (int) $radEtat['tickets']; ?></span>
  </div>
  <div class="tikras-panel-body">
    <?php if (count($radTickets) < 1) { ?>
      <p class="tikras-wg-intro">
        Aucun ticket pour le moment. Depuis <em>Tickets automatiques</em> ou la génération
        manuelle, choisissez le partage RADIUS pour qu'ils soient valables sur tous les
        routeurs raccordés.
      </p>
    <?php } else { ?>
    <div class="tikras-table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Profil</th>
            <th>Durée allouée</th>
            <th>Sessions</th>
            <th>Dernière connexion</th>
            <th class="text-right">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($radTickets as $ticket) { ?>
          <tr>
            <td><strong><?= tikras_h($ticket['username']); ?></strong></td>
            <td><?= tikras_h($ticket['profil'] != '' ? $ticket['profil'] : '—'); ?></td>
            <td><?= $ticket['duree'] != '' ? tikras_h(round(((int) $ticket['duree']) / 3600, 1)) . ' h' : 'illimitée'; ?></td>
            <td><?= (int) $ticket['sessions']; ?></td>
            <td><?= $ticket['derniere'] != '' ? tikras_h($ticket['derniere']) : '<span class="tikras-wg-vide">jamais</span>'; ?></td>
            <td class="text-right">
              <form method="post" action="" onsubmit="return confirm('Supprimer ce ticket ?');">
                <input type="hidden" name="identifiant" value="<?= tikras_h($ticket['username']); ?>">
                <button class="tikras-btn tikras-btn-danger" type="submit" name="supprimer_ticket" value="1">
                  <i class="fa fa-trash"></i><span>Supprimer</span>
                </button>
              </form>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</div>
<?php } ?>

<script>
function copierRadius() {
  var champ = document.getElementById('radScriptTexte');
  var etat = document.getElementById('radCopie');
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
document.getElementById('radSearch').addEventListener('input', function () {
  var terme = this.value.toLowerCase();
  var lignes = document.querySelectorAll('.tikras-rad-row');
  for (var i = 0; i < lignes.length; i++) {
    var texte = lignes[i].getAttribute('data-recherche') || '';
    lignes[i].style.display = texte.indexOf(terme) !== -1 ? '' : 'none';
  }
});
</script>
