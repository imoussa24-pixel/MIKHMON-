<?php
/*
 * Backup and restore page.
 */
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

include_once(dirname(__DIR__) . '/lib/tikras_backup.php');

$health = tikras_storage_health();
$restoreStatus = tikras_get('restore');
$restoreMsg = tikras_get('msg');
$backupFiles = glob(tikras_backup_dir() . DIRECTORY_SEPARATOR . '*.zip');
if (!is_array($backupFiles)) {
  $backupFiles = array();
}
rsort($backupFiles);
$backupFiles = array_slice($backupFiles, 0, 8);
?>

<div class="tikras-backup-page">
  <div class="tikras-tickets-hero">
    <div>
      <span class="tikras-storage-eyebrow">Protection donnees</span>
      <h2>Sauvegarde et restauration</h2>
      <p>Exportez la configuration locale, la base SQLite, les tickets, rapports cache et journaux RouterOS.</p>
    </div>
    <div class="tikras-tickets-actions">
      <a class="btn bg-primary" href="./process/backup-export.php"><i class="fa fa-download"></i> Telecharger sauvegarde</a>
      <a class="btn bg-secondary" href="./admin.php?id=storage"><i class="fa fa-database"></i> Base locale</a>
    </div>
  </div>

  <?php if ($restoreStatus != '') { ?>
    <div class="tikras-storage-alert <?= $restoreStatus == 'ok' ? 'is-ok' : ''; ?>">
      <i class="fa <?= $restoreStatus == 'ok' ? 'fa-check-circle' : 'fa-warning'; ?>"></i>
      <span><?= tikras_h($restoreMsg); ?></span>
    </div>
  <?php } ?>

  <div class="tikras-backup-grid">
    <section class="tikras-tickets-panel">
      <div class="tikras-tickets-panel-title">
        <h3><i class="fa fa-save"></i> Etat sauvegarde</h3>
        <span><?= $health['available'] ? 'SQLite active' : 'SQLite inactive'; ?></span>
      </div>
      <div class="tikras-backup-facts">
        <div><span>Base</span><strong><?= tikras_h($health['path']); ?></strong></div>
        <div><span>Taille</span><strong><?= number_format((float) $health['size'] / 1024, 2, ',', ' '); ?> Ko</strong></div>
        <div><span>Schema</span><strong>v<?= (int) $health['version']; ?></strong></div>
      </div>
    </section>

    <section class="tikras-tickets-panel">
      <div class="tikras-tickets-panel-title">
        <h3><i class="fa fa-upload"></i> Restaurer un ZIP</h3>
        <span>Avec sauvegarde de secours</span>
      </div>
      <form class="tikras-backup-form" method="post" action="./process/backup-restore.php" enctype="multipart/form-data">
        <input class="form-control" type="file" name="backup_zip" accept=".zip" required>
        <button class="btn bg-danger" type="submit" onclick="return confirm('Restaurer cette sauvegarde ? Une sauvegarde de secours sera creee avant remplacement.');"><i class="fa fa-upload"></i> Restaurer</button>
      </form>
    </section>
  </div>

  <section class="tikras-tickets-panel">
    <div class="tikras-tickets-panel-title">
      <h3><i class="fa fa-history"></i> Dernieres sauvegardes locales</h3>
      <span><?= count($backupFiles); ?> fichier(s)</span>
    </div>
    <div class="tikras-table-wrap">
      <table class="table tikras-tickets-table">
        <thead>
          <tr><th>Fichier</th><th>Taille</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php if (count($backupFiles) < 1) { ?>
            <tr><td colspan="3">Aucune sauvegarde locale creee pour le moment.</td></tr>
          <?php } ?>
          <?php foreach ($backupFiles as $file) { ?>
            <tr>
              <td><?= tikras_h(basename($file)); ?></td>
              <td><?= number_format((float) filesize($file) / 1024, 2, ',', ' '); ?> Ko</td>
              <td><?= date('Y-m-d H:i:s', filemtime($file)); ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
