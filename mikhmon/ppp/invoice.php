<?php
/*
 * Printable PPP invoice page.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  include_once(dirname(__FILE__) . '/helpers.php');

  if (!function_exists('ppp_invoice_money')) {
    function ppp_invoice_money($comment, $currency)
    {
      $comment = trim((string) $comment);
      $amount = "";
      if (preg_match('/(?:prix|price|montant|amount)[:=\s-]*([0-9]+(?:[\.,][0-9]+)?)/i', $comment, $match)) {
        $amount = $match[1];
      } elseif (preg_match('/([0-9]+(?:[\.,][0-9]+)?)\s*(CFA|XOF|NGN|FCFA)/i', $comment, $match)) {
        $amount = $match[1];
      }
      if ($amount == "") {
        return "A definir";
      }
      $amount = str_replace(',', '.', $amount);
      return trim($currency . " " . number_format((float) $amount, 0, ",", " "));
    }
  }

  $invoiceSecret = tikras_get('secret');
  $getsecret = array();
  if ($invoiceSecret != "") {
    if (substr($invoiceSecret, 0, 1) == "*") {
      $getsecret = $API->comm("/ppp/secret/print", array("?.id" => "$invoiceSecret"));
    } else {
      $getsecret = $API->comm("/ppp/secret/print", array("?name" => "$invoiceSecret"));
    }
  }

  $secret = isset($getsecret[0]) ? $getsecret[0] : array();
  $name = ppp_v($secret, 'name', '-');
  $service = ppp_v($secret, 'service', 'any');
  $profile = ppp_v($secret, 'profile', '-');
  $local = ppp_v($secret, 'local-address', '-');
  $remote = ppp_v($secret, 'remote-address', '-');
  $comment = ppp_v($secret, 'comment', '');
  $disabled = ppp_v($secret, 'disabled', 'false');
  $limitTotal = ppp_format_bytes_value(ppp_v($secret, 'limit-bytes-total'));
  if ($limitTotal == "") {
    $limitTotal = "Illimite";
  }
  $invoiceNo = "PPP-" . date("Ymd") . "-" . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8));
  $amount = ppp_invoice_money($comment, $currency);
  $status = ($disabled == "true" || $disabled == "yes") ? "Suspendu" : "Actif";
}
?>

<div class="ppp-invoice-page">
  <div class="ppp-invoice-actions text-right mr-b-10">
    <a class="btn bg-secondary" href="./?ppp=secrets&profile=all&session=<?= rawurlencode($session); ?>"><i class="fa fa-arrow-left"></i> Retour</a>
    <button class="btn bg-primary" onclick="window.print();"><i class="fa fa-print"></i> Imprimer</button>
  </div>

  <?php if (!isset($secret['name'])) { ?>
  <div class="card">
    <div class="card-header"><h3><i class="fa fa-file-text-o"></i> Facture PPP</h3></div>
    <div class="card-body">Client PPP introuvable.</div>
  </div>
  <?php } else { ?>
  <div class="ppp-invoice-sheet">
    <div class="ppp-invoice-head">
      <div>
        <div class="ppp-invoice-brand">TIKRAS IT</div>
        <span class="ppp-invoice-label"><?= ppp_h($hotspotname); ?></span>
        <div><?= ppp_h($dnsname); ?></div>
      </div>
      <div>
        <div class="ppp-invoice-title">FACTURE</div>
        <div class="text-right">N: <?= ppp_h($invoiceNo); ?></div>
        <div class="text-right">Date: <?= date("Y-m-d H:i"); ?></div>
      </div>
    </div>

    <div class="ppp-invoice-grid">
      <div class="ppp-invoice-box">
        <span class="ppp-invoice-label">Client</span>
        <strong><?= ppp_h($name); ?></strong><br>
        Service: <?= ppp_h($service); ?><br>
        Statut: <?= ppp_h($status); ?>
      </div>
      <div class="ppp-invoice-box">
        <span class="ppp-invoice-label">Abonnement</span>
        Profil: <?= ppp_h($profile); ?><br>
        Local: <?= ppp_h($local); ?><br>
        Remote: <?= ppp_h($remote); ?>
      </div>
    </div>

    <table class="ppp-invoice-table">
      <thead>
        <tr>
          <th>Description</th>
          <th>Limite</th>
          <th>Commentaire</th>
          <th class="text-right">Montant</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Abonnement PPP - <?= ppp_h($profile); ?></td>
          <td><?= ppp_h($limitTotal); ?></td>
          <td><?= ppp_h($comment == "" ? "-" : $comment); ?></td>
          <td class="text-right"><?= ppp_h($amount); ?></td>
        </tr>
      </tbody>
    </table>

    <div class="ppp-invoice-total">
      <div>
        <span class="ppp-invoice-label">Informations</span>
        Facture generee depuis TIKRAS IT.
      </div>
      <div class="text-right">
        <span class="ppp-invoice-label">Total</span>
        <strong><?= ppp_h($amount); ?></strong>
      </div>
    </div>
  </div>
  <?php } ?>
</div>
