<?php
/*
 * Tableau de bord du parc.
 *
 * L'application ouvrait jusqu'ici sur la liste des cent six routeurs, tous
 * de meme importance visuelle. Pour savoir ce que la journee avait rapporte,
 * il fallait entrer dans chaque routeur et ouvrir sa page Rapport - autant
 * dire que le chiffre n'etait jamais consulte.
 *
 * Cet ecran repond d'abord aux trois questions qu'on se pose en arrivant:
 * combien ai-je encaisse, mon parc repond-il, et qu'est-ce qui demande mon
 * attention. Il ne lit que la base: aucune requete vers les routeurs, donc
 * un affichage immediat meme quand la moitie du parc est hors ligne.
 */

include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
include_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
include_once(dirname(__DIR__) . '/lib/tikras_storage.php');
include_once(dirname(__DIR__) . '/lib/tikras_automation.php');
include_once(dirname(__DIR__) . '/lib/tikras_sales.php');

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  return;
}

$_SESSION["connect"] = "";

$bordSynthese = tikras_sales_synthese();
$bordCouverture = tikras_sales_couverture($data);
$bordEtats = function_exists('tikras_storage_all_router_statuses') ? tikras_storage_all_router_statuses() : array();

$bordDevise = $bordSynthese['devise'] != '' ? $bordSynthese['devise'] : '';
$bordEnLigne = 0;
$bordHorsLigne = 0;
$bordInconnus = 0;
$bordPannes = array();
foreach ($data as $bordSession => $bordConfig) {
  if ($bordSession == '' || $bordSession == 'mikhmon' || !is_array($bordConfig)) {
    continue;
  }
  $bordEtat = isset($bordEtats[$bordSession]['last_state']) ? $bordEtats[$bordSession]['last_state'] : 'unknown';
  if ($bordEtat == 'online') {
    $bordEnLigne++;
  } elseif ($bordEtat == 'offline') {
    $bordHorsLigne++;
    $bordPannes[] = array(
      'session' => $bordSession,
      'nom' => tikras_cfg_value($data, $bordSession, 4, '%', $bordSession),
      'depuis' => isset($bordEtats[$bordSession]['updated_at']) ? (string) $bordEtats[$bordSession]['updated_at'] : '',
    );
  } else {
    $bordInconnus++;
  }
}
$bordParc = $bordEnLigne + $bordHorsLigne + $bordInconnus;

/* Evolution par rapport a la veille, a heure egale dans la journee. */
$bordEvolution = null;
if ($bordSynthese['hier']['total'] > 0) {
  $bordEvolution = (($bordSynthese['jour']['total'] - $bordSynthese['hier']['total']) / $bordSynthese['hier']['total']) * 100;
}

/*
 * Le marqueur du dernier passage est un horodatage Unix: affiche tel quel il
 * ne veut rien dire pour qui lit la page.
 */
$bordDernierReleve = '';
$bordMarqueur = tikras_automation_meta_get('auto.last.sales_sync', '');
if ($bordMarqueur != '' && ctype_digit((string) $bordMarqueur)) {
  $bordEcoule = time() - (int) $bordMarqueur;
  if ($bordEcoule < 90) {
    $bordDernierReleve = "à l'instant";
  } elseif ($bordEcoule < 5400) {
    $bordDernierReleve = 'il y a ' . max(1, (int) round($bordEcoule / 60)) . ' min';
  } elseif ($bordEcoule < 172800) {
    $bordDernierReleve = 'il y a ' . (int) round($bordEcoule / 3600) . ' h';
  } else {
    $bordDernierReleve = 'le ' . date('d/m/Y', (int) $bordMarqueur);
  }
}

function tikras_bord_montant($valeur, $devise)
{
  $texte = number_format((float) $valeur, 0, ',', ' ');
  return $devise != '' ? $texte . ' ' . $devise : $texte;
}

/* Echelle du graphique: la plus forte journee donne la hauteur maximale. */
$bordMaxJour = 0;
foreach ($bordSynthese['jours'] as $bordJour) {
  if ($bordJour['total'] > $bordMaxJour) {
    $bordMaxJour = $bordJour['total'];
  }
}
?>

<div class="tikras-page-head">
  <div class="tikras-page-title">
    <span class="tikras-page-icon"><i class="fa fa-line-chart"></i></span>
    <div>
      <h2>Tableau de bord</h2>
      <p>
        <?= (int) $bordParc; ?> routeur(s) ·
        <?= (int) $bordEnLigne; ?> en ligne ·
        <?= (int) $bordHorsLigne; ?> hors ligne
      </p>
    </div>
  </div>
  <div class="tikras-page-actions">
    <a class="tikras-btn tikras-btn-primary" href="./admin.php?id=sessions">
      <i class="fa fa-server"></i><span>Choisir un routeur</span>
    </a>
    <a class="tikras-btn tikras-btn-muted" href="./admin.php?id=planning">
      <i class="fa fa-clock-o"></i><span>Tickets automatiques</span>
    </a>
  </div>
</div>

<?php if (!$bordSynthese['disponible']) { ?>
<?= tikras_ui_alert('warning', "La base locale n'est pas disponible : les recettes ne peuvent pas être affichées."); ?>
<?php } ?>

<div class="tikras-bord-chiffres">
  <div class="tikras-bord-carte tikras-bord-carte-forte">
    <span class="tikras-bord-libelle">Recette du jour</span>
    <strong class="tikras-bord-valeur"><?= tikras_h(tikras_bord_montant($bordSynthese['jour']['total'], $bordDevise)); ?></strong>
    <span class="tikras-bord-detail">
      <?= (int) $bordSynthese['jour']['nombre']; ?> ticket(s) vendu(s)
      <?php if ($bordEvolution !== null) { ?>
        · <span class="<?= $bordEvolution >= 0 ? 'tikras-bord-hausse' : 'tikras-bord-baisse'; ?>">
          <i class="fa fa-<?= $bordEvolution >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
          <?= number_format(abs($bordEvolution), 0); ?> % vs hier
        </span>
      <?php } ?>
    </span>
  </div>

  <div class="tikras-bord-carte">
    <span class="tikras-bord-libelle">Recette du mois</span>
    <strong class="tikras-bord-valeur"><?= tikras_h(tikras_bord_montant($bordSynthese['mois']['total'], $bordDevise)); ?></strong>
    <span class="tikras-bord-detail">
      <?= (int) $bordSynthese['mois']['nombre']; ?> ticket(s) depuis le 1<sup>er</sup>
      <?php
      /*
       * Une partie du parc encaisse dans une autre monnaie. On ne l'ajoute
       * pas au total - la somme n'aurait pas de sens - mais on l'affiche,
       * sans quoi ces recettes sembleraient perdues.
       */
      foreach ($bordSynthese['devises'] as $bordAutre) {
        if ($bordAutre['devise'] === $bordDevise) {
          continue;
        }
      ?>
        <br><span class="tikras-bord-autre-devise">+ <?= tikras_h(tikras_bord_montant($bordAutre['total'], $bordAutre['devise'])); ?></span>
      <?php } ?>
    </span>
  </div>

  <div class="tikras-bord-carte">
    <span class="tikras-bord-libelle">Parc joignable</span>
    <strong class="tikras-bord-valeur"><?= (int) $bordEnLigne; ?><span class="tikras-bord-sur">/<?= (int) $bordParc; ?></span></strong>
    <span class="tikras-bord-detail">
      <?php if ($bordHorsLigne > 0) { ?>
        <span class="tikras-bord-baisse"><?= (int) $bordHorsLigne; ?> hors ligne</span>
      <?php } else { ?>
        tout le parc répond
      <?php } ?>
    </span>
  </div>

  <div class="tikras-bord-carte">
    <span class="tikras-bord-libelle">Recettes connues</span>
    <strong class="tikras-bord-valeur"><?= (int) $bordCouverture['connus']; ?><span class="tikras-bord-sur">/<?= (int) $bordCouverture['total']; ?></span></strong>
    <span class="tikras-bord-detail">
      <?php if (count($bordCouverture['jamais_releves']) > 0) { ?>
        <?= count($bordCouverture['jamais_releves']); ?> routeur(s) pas encore relevé(s)
      <?php } else { ?>
        tout le parc est relevé
      <?php } ?>
    </span>
  </div>
</div>

<?php
/*
 * Un total qui ne couvrirait qu'une partie du parc tromperait plus qu'une
 * absence de chiffre: tant que le releve n'a pas fait le tour, on le dit.
 */
if ($bordCouverture['total'] > 0 && $bordCouverture['connus'] < $bordCouverture['total']) { ?>
<div class="tikras-bord-note">
  <i class="fa fa-info-circle"></i>
  <span>
    Les montants ci-dessus portent sur <strong><?= (int) $bordCouverture['connus']; ?></strong>
    routeur(s) sur <?= (int) $bordCouverture['total']; ?>.
    Le relevé se poursuit tout seul, par petits groupes, et se complète en quelques heures.
    <?php if ($bordDernierReleve != '') { ?>
      Dernier relevé <?= tikras_h($bordDernierReleve); ?>.
    <?php } ?>
  </span>
</div>
<?php } ?>

<div class="tikras-bord-grille">
  <div class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-bar-chart"></i> Ces quatorze jours</h3>
    </div>
    <div class="tikras-panel-body">
      <?php if ($bordMaxJour <= 0) { ?>
        <p class="tikras-wg-intro">Aucune vente enregistrée sur la période.</p>
      <?php } else { ?>
      <div class="tikras-bord-graphe">
        <?php foreach ($bordSynthese['jours'] as $bordJour) {
          $hauteur = $bordMaxJour > 0 ? max(2, round(($bordJour['total'] / $bordMaxJour) * 100)) : 2;
          $jourCourt = date('d/m', strtotime($bordJour['jour']));
        ?>
        <div class="tikras-bord-barre" title="<?= tikras_h($jourCourt . ' : ' . tikras_bord_montant($bordJour['total'], $bordDevise) . ' (' . $bordJour['nombre'] . ' ticket(s))'); ?>">
          <span class="tikras-bord-barre-remplissage" style="height: <?= (int) $hauteur; ?>%"></span>
          <span class="tikras-bord-barre-jour"><?= tikras_h($jourCourt); ?></span>
        </div>
        <?php } ?>
      </div>
      <?php } ?>
    </div>
  </div>

  <div class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-exclamation-triangle"></i> Demande attention</h3>
      <span class="tikras-version-note"><?= count($bordPannes); ?> hors ligne</span>
    </div>
    <div class="tikras-panel-body">
      <?php if (count($bordPannes) < 1) { ?>
        <p class="tikras-wg-intro">Tous les routeurs connus répondent.</p>
      <?php } else { ?>
      <ul class="tikras-bord-pannes">
        <?php foreach (array_slice($bordPannes, 0, 8) as $bordPanne) { ?>
        <li>
          <a href="./admin.php?id=connect&session=<?= rawurlencode($bordPanne['session']); ?>">
            <strong><?= tikras_h($bordPanne['nom']); ?></strong>
            <span><?= tikras_h($bordPanne['session']); ?></span>
          </a>
          <?php if ($bordPanne['depuis'] != '') { ?>
          <em>vu <?= tikras_h(substr($bordPanne['depuis'], 0, 16)); ?></em>
          <?php } ?>
        </li>
        <?php } ?>
      </ul>
      <?php if (count($bordPannes) > 8) { ?>
      <p class="tikras-version-note">et <?= count($bordPannes) - 8; ?> autre(s).</p>
      <?php } ?>
      <?php } ?>
    </div>
  </div>
</div>

<div class="tikras-bord-grille">
  <div class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-server"></i> Meilleurs routeurs ce mois</h3>
    </div>
    <div class="tikras-panel-body">
      <?php if (count($bordSynthese['par_routeur']) < 1) { ?>
        <p class="tikras-wg-intro">Aucune recette relevée pour le moment.</p>
      <?php } else { ?>
      <table class="table">
        <tbody>
        <?php foreach ($bordSynthese['par_routeur'] as $bordLigne) {
          $nomRouteur = isset($data[$bordLigne['session']])
            ? tikras_cfg_value($data, $bordLigne['session'], 4, '%', $bordLigne['session'])
            : $bordLigne['session'];
        ?>
          <tr>
            <td>
              <a href="./admin.php?id=connect&session=<?= rawurlencode($bordLigne['session']); ?>"><?= tikras_h($nomRouteur); ?></a>
              <div class="tikras-wg-session"><?= (int) $bordLigne['n']; ?> ticket(s)</div>
            </td>
            <td class="tikras-bord-montant"><?= tikras_h(tikras_bord_montant($bordLigne['t'], $bordDevise)); ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
      <?php } ?>
    </div>
  </div>

  <div class="tikras-panel">
    <div class="tikras-panel-header">
      <h3><i class="fa fa-ticket"></i> Forfaits les plus vendus</h3>
    </div>
    <div class="tikras-panel-body">
      <?php if (count($bordSynthese['par_profil']) < 1) { ?>
        <p class="tikras-wg-intro">Aucune recette relevée pour le moment.</p>
      <?php } else { ?>
      <table class="table">
        <tbody>
        <?php foreach ($bordSynthese['par_profil'] as $bordLigne) { ?>
          <tr>
            <td>
              <strong><?= tikras_h($bordLigne['profile'] != '' ? $bordLigne['profile'] : '—'); ?></strong>
              <div class="tikras-wg-session"><?= (int) $bordLigne['n']; ?> ticket(s)</div>
            </td>
            <td class="tikras-bord-montant"><?= tikras_h(tikras_bord_montant($bordLigne['t'], $bordDevise)); ?></td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
      <?php } ?>
    </div>
  </div>
</div>
