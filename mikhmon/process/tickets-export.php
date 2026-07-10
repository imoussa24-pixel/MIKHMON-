<?php
/*
 * CSV export for local ticket history.
 */
require_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

require_once(dirname(__DIR__) . '/lib/tikras_storage.php');

$filters = array(
  'q' => trim(tikras_get('q')),
  'status' => trim(tikras_get('status')),
  'session' => trim(tikras_get('router')),
  'batch' => trim(tikras_get('batch')),
);
$rows = tikras_storage_list_tickets($filters, 10000);

$filename = 'tickets-roaming-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fputcsv($out, array('ticket', 'utilisateur', 'profil', 'prix', 'devise', 'commentaire', 'routeur_source', 'lot', 'statut', 'cree_le', 'vendu_le', 'expire_le'));
foreach ($rows as $row) {
  fputcsv($out, array(
    $row['ticket_code'],
    $row['username'],
    $row['profile'],
    $row['price'],
    $row['currency'],
    $row['comment'],
    $row['source_session'],
    $row['batch_code'],
    $row['status'],
    $row['created_at'],
    $row['sold_at'],
    $row['expires_at'],
  ));
}
fclose($out);
exit;
?>
