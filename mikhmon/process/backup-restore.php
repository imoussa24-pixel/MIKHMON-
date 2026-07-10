<?php
require_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

require_once(dirname(__DIR__) . '/lib/tikras_backup.php');

if (!isset($_FILES['backup_zip']) || !is_uploaded_file($_FILES['backup_zip']['tmp_name'])) {
  tikras_redirect('../admin.php?id=backup&restore=error&msg=' . rawurlencode('Aucun fichier envoye.'));
}

$result = tikras_backup_restore($_FILES['backup_zip']['tmp_name']);
if ($result['ok']) {
  tikras_redirect('../admin.php?id=backup&restore=ok&msg=' . rawurlencode('Restauration terminee: ' . implode(', ', $result['restored'])));
}
tikras_redirect('../admin.php?id=backup&restore=error&msg=' . rawurlencode($result['error']));
?>
