<?php
/*
 * Backup and restore helpers for local TIKRAS/Mikhmon data.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_config_store.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');

if (!function_exists('tikras_backup_dir')) {
  function tikras_backup_dir()
  {
    $dir = tikras_storage_dir() . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($dir)) {
      @mkdir($dir, 0775, true);
    }
    return $dir;
  }
}

if (!function_exists('tikras_backup_file_name')) {
  function tikras_backup_file_name($prefix = 'mikhmon-backup')
  {
    return $prefix . '-' . date('Ymd-His') . '.zip';
  }
}

if (!function_exists('tikras_backup_add_file')) {
  function tikras_backup_add_file($zip, $path, $localName)
  {
    if (is_file($path)) {
      return $zip->addFile($path, $localName);
    }
    return false;
  }
}

if (!function_exists('tikras_backup_sqlite_snapshot')) {
  function tikras_backup_sqlite_snapshot()
  {
    $source = tikras_storage_path();
    if (!is_file($source)) {
      return '';
    }

    $snapshot = tikras_backup_dir() . DIRECTORY_SEPARATOR . 'sqlite-snapshot-' . str_replace('.', '-', uniqid('', true)) . '.sqlite';

    // La base tourne en mode WAL: une simple copie du fichier principal laisse
    // de cote tout ce qui est encore dans le journal. VACUUM INTO ecrit une
    // copie complete et coherente, meme pendant des ecritures concurrentes.
    if (tikras_storage_available()) {
      try {
        $pdo = tikras_storage_pdo();
        $stmt = $pdo->prepare('VACUUM INTO ?');
        $stmt->execute(array($snapshot));
        if (is_file($snapshot) && filesize($snapshot) > 0) {
          return $snapshot;
        }
      } catch (Exception $e) {
        @unlink($snapshot);
        tikras_log('backup vacuum into failed, fallback checkpoint', array('error' => $e->getMessage()));
      }
      // Repli (SQLite < 3.27): forcer le WAL dans le fichier avant de copier.
      try {
        tikras_storage_pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
      } catch (Exception $e) {
        tikras_log('backup wal checkpoint failed', array('error' => $e->getMessage()));
      }
    }

    if (@copy($source, $snapshot)) {
      return $snapshot;
    }
    return '';
  }
}

if (!function_exists('tikras_backup_create')) {
  function tikras_backup_create($targetPath = '')
  {
    $result = array('ok' => false, 'path' => '', 'error' => '', 'files' => 0);
    if (!class_exists('ZipArchive')) {
      $result['error'] = 'Extension ZIP indisponible dans PHP.';
      return $result;
    }

    if ($targetPath == '') {
      $targetPath = tikras_backup_dir() . DIRECTORY_SEPARATOR . tikras_backup_file_name();
    }
    $dir = dirname($targetPath);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
      $result['error'] = 'Impossible de creer le dossier sauvegarde.';
      return $result;
    }

    $zip = new ZipArchive();
    if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
      $result['error'] = 'Impossible de creer le fichier ZIP.';
      return $result;
    }

    $manifest = array(
      'app' => 'TIKRAS Mikhmon Pro Admin',
      'created_at' => date('c'),
      'schema' => array(
        'available' => tikras_storage_available(),
        'path' => tikras_storage_path(),
        'exists' => is_file(tikras_storage_path()),
        'size' => is_file(tikras_storage_path()) ? filesize(tikras_storage_path()) : 0,
      ),
    );
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    $result['files']++;

    if (tikras_backup_add_file($zip, tikras_config_local_path(), 'config/config.local.php')) {
      $result['files']++;
    }
    if (tikras_backup_add_file($zip, tikras_quickbt_local_path(), 'config/quickbt.local.php')) {
      $result['files']++;
    }

    $snapshot = tikras_backup_sqlite_snapshot();
    if ($snapshot != '' && tikras_backup_add_file($zip, $snapshot, 'storage/mikhmon-pro-admin.sqlite')) {
      $result['files']++;
    }

    $zip->close();
    if ($snapshot != '') {
      @unlink($snapshot);
    }

    $result['ok'] = is_file($targetPath);
    $result['path'] = $targetPath;
    if (!$result['ok']) {
      $result['error'] = 'Sauvegarde non creee.';
    }
    return $result;
  }
}

if (!function_exists('tikras_backup_write_zip_entry')) {
  function tikras_backup_write_zip_entry($zip, $entry, $target)
  {
    $content = $zip->getFromName($entry);
    if ($content === false) {
      return false;
    }
    $dir = dirname($target);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
      return false;
    }
    $tmp = $target . '.restore-' . str_replace('.', '-', uniqid('', true));
    if (file_put_contents($tmp, $content) === false) {
      return false;
    }
    if (is_file($target)) {
      @unlink($target . '.before-restore');
      @rename($target, $target . '.before-restore');
    }
    return @rename($tmp, $target);
  }
}

if (!function_exists('tikras_backup_restore')) {
  function tikras_backup_restore($zipPath)
  {
    $result = array('ok' => false, 'error' => '', 'restored' => array(), 'safety_backup' => '');
    if (!class_exists('ZipArchive')) {
      $result['error'] = 'Extension ZIP indisponible dans PHP.';
      return $result;
    }
    if (!is_file($zipPath)) {
      $result['error'] = 'Fichier sauvegarde introuvable.';
      return $result;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
      $result['error'] = 'ZIP invalide ou illisible.';
      return $result;
    }
    if ($zip->getFromName('manifest.json') === false) {
      $zip->close();
      $result['error'] = 'Manifest absent. Sauvegarde refusee.';
      return $result;
    }

    $safety = tikras_backup_create(tikras_backup_dir() . DIRECTORY_SEPARATOR . tikras_backup_file_name('before-restore'));
    if ($safety['ok']) {
      $result['safety_backup'] = $safety['path'];
    }

    if (tikras_backup_write_zip_entry($zip, 'config/config.local.php', tikras_config_local_path())) {
      $result['restored'][] = 'config';
    }
    if (tikras_backup_write_zip_entry($zip, 'config/quickbt.local.php', tikras_quickbt_local_path())) {
      $result['restored'][] = 'quickbt';
    }
    if (tikras_backup_write_zip_entry($zip, 'storage/mikhmon-pro-admin.sqlite', tikras_storage_path())) {
      // Les journaux WAL de l'ancienne base ne correspondent plus au fichier
      // restaure: les laisser en place corromprait la base.
      @unlink(tikras_storage_path() . '-wal');
      @unlink(tikras_storage_path() . '-shm');
      $result['restored'][] = 'sqlite';
    }

    $zip->close();
    if (count($result['restored']) < 1) {
      $result['error'] = 'Aucun fichier restaurable trouve dans le ZIP.';
      return $result;
    }
    $result['ok'] = true;
    tikras_storage_audit('backup.restore', 'backup', basename($zipPath), '', 'Restauration effectuee.', $result);
    return $result;
  }
}
?>
