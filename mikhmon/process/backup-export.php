<?php
require_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

require_once(dirname(__DIR__) . '/lib/tikras_backup.php');

$backup = tikras_backup_create();
if (!$backup['ok']) {
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'Erreur sauvegarde: ' . $backup['error'];
  exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($backup['path']) . '"');
header('Content-Length: ' . filesize($backup['path']));
header('Pragma: no-cache');
header('Expires: 0');
readfile($backup['path']);
exit;
?>
