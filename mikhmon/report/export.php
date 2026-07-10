<?php
/*
 * Compatibility route for report export.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}
$safeSession = htmlspecialchars((string) $session, ENT_QUOTES, "UTF-8");
?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-download"></i> Export</h3>
      </div>
      <div class="card-body">
        <p>Utilisez le bouton CSV du rapport de vente pour exporter les données.</p>
        <a class="btn bg-primary" href="./?report=selling&idbl=<?= date("m") . date("Y"); ?>&session=<?= $safeSession; ?>"><i class="fa fa-table"></i> Ouvrir le rapport</a>
      </div>
    </div>
  </div>
</div>
