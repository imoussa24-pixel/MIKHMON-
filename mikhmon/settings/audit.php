<?php
/*
 * Local system audit journal.
 */
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

include_once(dirname(__DIR__) . '/lib/tikras_storage.php');

$auditFilters = array(
  'q' => tikras_get('q'),
  'action' => tikras_get('action'),
  'session' => tikras_get('router_session'),
  'entity_type' => tikras_get('entity_type'),
);
$auditLimit = (int) tikras_get('limit', 200);
if (!in_array($auditLimit, array(50, 100, 200, 500))) {
  $auditLimit = 200;
}
$auditRows = tikras_storage_list_audit_logs($auditFilters, $auditLimit);
$auditStats = tikras_storage_audit_stats();
?>

<div class="tikras-audit-page tikras-tickets-page">
  <div class="tikras-tickets-hero">
    <div>
      <span class="tikras-storage-eyebrow">Journal systeme</span>
      <h2>Activite locale</h2>
      <p>Suivez les sauvegardes, restaurations, synchronisations et actions techniques conservees dans SQLite.</p>
    </div>
    <div class="tikras-tickets-actions">
      <a class="btn bg-primary" href="./admin.php?id=backup"><i class="fa fa-save"></i> Sauvegarde</a>
      <a class="btn bg-secondary" href="./admin.php?id=storage"><i class="fa fa-database"></i> Base locale</a>
    </div>
  </div>

  <div class="tikras-tickets-stats">
    <div><span>Total</span><strong><?= number_format((int) $auditStats['total'], 0, ',', ' '); ?></strong></div>
    <div><span>24 heures</span><strong><?= number_format((int) $auditStats['today'], 0, ',', ' '); ?></strong></div>
    <div><span>Sauvegarde</span><strong><?= number_format((int) $auditStats['backup'], 0, ',', ' '); ?></strong></div>
    <div><span>Routeurs</span><strong><?= number_format((int) $auditStats['router'], 0, ',', ' '); ?></strong></div>
  </div>

  <form class="tikras-tickets-filters" method="get" action="./admin.php">
    <input type="hidden" name="id" value="audit">
    <label>
      <span>Recherche</span>
      <input class="form-control" type="search" name="q" value="<?= tikras_h($auditFilters['q']); ?>" placeholder="message, contexte, identifiant">
    </label>
    <label>
      <span>Action</span>
      <input class="form-control" type="text" name="action" value="<?= tikras_h($auditFilters['action']); ?>" placeholder="backup.restore">
    </label>
    <label>
      <span>Session</span>
      <input class="form-control" type="text" name="router_session" value="<?= tikras_h($auditFilters['session']); ?>" placeholder="simnet">
    </label>
    <label>
      <span>Type</span>
      <input class="form-control" type="text" name="entity_type" value="<?= tikras_h($auditFilters['entity_type']); ?>" placeholder="backup, router">
    </label>
    <label>
      <span>Limite</span>
      <select class="form-control" name="limit">
        <?php foreach (array(50, 100, 200, 500) as $limitOption) { ?>
          <option value="<?= $limitOption; ?>" <?= $auditLimit == $limitOption ? 'selected' : ''; ?>><?= $limitOption; ?></option>
        <?php } ?>
      </select>
    </label>
    <button class="btn bg-primary" type="submit"><i class="fa fa-search"></i> Filtrer</button>
  </form>

  <section class="tikras-tickets-panel">
    <div class="tikras-tickets-panel-title">
      <h3><i class="fa fa-history"></i> Dernieres actions</h3>
      <span><?= count($auditRows); ?> entree(s)</span>
    </div>
    <div class="tikras-table-wrap">
      <table class="table tikras-tickets-table tikras-audit-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Action</th>
            <th>Type</th>
            <th>Session</th>
            <th>Message</th>
            <th>Contexte</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($auditRows) < 1) { ?>
            <tr><td colspan="6">Aucune entree trouvee pour ces filtres.</td></tr>
          <?php } ?>
          <?php foreach ($auditRows as $row) { ?>
            <tr>
              <td><?= tikras_h($row['created_at']); ?></td>
              <td><span class="tikras-status tikras-status-created"><?= tikras_h($row['action']); ?></span></td>
              <td><?= tikras_h($row['entity_type']); ?></td>
              <td><?= tikras_h($row['session']); ?></td>
              <td><?= tikras_h($row['message']); ?></td>
              <td><code class="tikras-audit-context"><?= tikras_h($row['context_json']); ?></code></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
