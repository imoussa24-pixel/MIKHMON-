<?php
/*
 * Local storage diagnostics and bootstrap page.
 */
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

include_once(dirname(__DIR__) . '/lib/tikras_storage.php');

$sync = array('ok' => false, 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'total' => 0, 'error' => '');
if (tikras_storage_available()) {
  $sync = tikras_storage_sync_routers($data);
}
$health = tikras_storage_health();
$counts = array(
  'Routeurs' => tikras_storage_count('routers'),
  'Tickets' => tikras_storage_count('tickets'),
  'Lots roaming' => tikras_storage_count('roaming_batches'),
  'Journaux RouterOS' => tikras_storage_count('routeros_logs'),
  'Rapports cache' => tikras_storage_count('sales_cache'),
  'Audit' => tikras_storage_count('audit_logs'),
);

function tikras_storage_page_size($bytes)
{
  $bytes = (float) $bytes;
  if ($bytes >= 1048576) {
    return number_format($bytes / 1048576, 2, ',', ' ') . ' Mo';
  }
  if ($bytes >= 1024) {
    return number_format($bytes / 1024, 2, ',', ' ') . ' Ko';
  }
  return number_format($bytes, 0, ',', ' ') . ' o';
}
?>

<div class="tikras-storage-page">
  <div class="tikras-storage-hero">
    <div>
      <span class="tikras-storage-eyebrow">Fondation evolutive</span>
      <h2>Base locale Mikhmon</h2>
      <p>Cette base prepare les tickets roaming, les rapports rapides, les journaux RouterOS et les actions multi-routeurs.</p>
    </div>
    <div class="tikras-storage-status <?= $health['available'] && $health['error'] == '' ? 'is-ok' : 'is-error'; ?>">
      <i class="fa <?= $health['available'] && $health['error'] == '' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
      <strong><?= $health['available'] && $health['error'] == '' ? 'Operationnelle' : 'A verifier'; ?></strong>
      <span><?= $health['available'] ? 'SQLite active' : 'SQLite inactive'; ?></span>
    </div>
  </div>

  <?php if ($health['error'] != '') { ?>
    <div class="tikras-storage-alert">
      <i class="fa fa-warning"></i>
      <span><?= tikras_h($health['error']); ?></span>
    </div>
  <?php } ?>

  <div class="tikras-storage-grid">
    <div class="tikras-storage-card">
      <span>Chemin</span>
      <strong class="tikras-storage-path"><?= tikras_h($health['path']); ?></strong>
      <small>Stockage hors dossier programme, dans AppData.</small>
    </div>
    <div class="tikras-storage-card">
      <span>Schema</span>
      <strong>v<?= (int) $health['version']; ?></strong>
      <small><?= count($health['tables']); ?> table(s) creee(s)</small>
    </div>
    <div class="tikras-storage-card">
      <span>Taille</span>
      <strong><?= tikras_storage_page_size($health['size']); ?></strong>
      <small><?= $health['exists'] ? 'Fichier cree' : 'Fichier pas encore cree'; ?></small>
    </div>
    <div class="tikras-storage-card">
      <span>Synchronisation</span>
      <strong><?= (int) $sync['total']; ?> routeur(s)</strong>
      <small><?= (int) $sync['inserted']; ?> nouveau(x), <?= (int) $sync['updated']; ?> mis a jour, <?= (int) $sync['unchanged']; ?> stable(s)</small>
    </div>
  </div>

  <div class="tikras-storage-grid tikras-storage-counts">
    <?php foreach ($counts as $label => $count) { ?>
      <div class="tikras-storage-mini">
        <span><?= tikras_h($label); ?></span>
        <strong><?= (int) $count; ?></strong>
      </div>
    <?php } ?>
  </div>

  <div class="tikras-storage-columns">
    <div class="tikras-storage-panel">
      <h3><i class="fa fa-database"></i> Tables installees</h3>
      <div class="tikras-storage-tags">
        <?php foreach ($health['tables'] as $table) { ?>
          <span><?= tikras_h($table); ?></span>
        <?php } ?>
      </div>
    </div>
    <div class="tikras-storage-panel">
      <h3><i class="fa fa-road"></i> Prochain branchement</h3>
      <ul>
        <li>Enregistrer les tickets generes dans `tickets`.</li>
        <li>Creer les lots dans `roaming_batches`.</li>
        <li>Indexer les ventes dans `sales_cache` pour accelerer les rapports.</li>
        <li>Copier les journaux MikroTik importants dans `routeros_logs`.</li>
      </ul>
    </div>
  </div>
</div>
