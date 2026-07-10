<?php
/*
 * SQLite ticket and roaming history.
 */
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
  exit;
}

include_once(dirname(__DIR__) . '/lib/tikras_storage.php');

if (!function_exists('tikras_tickets_h')) {
  function tikras_tickets_h($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('tikras_tickets_money')) {
  function tikras_tickets_money($amount, $currency)
  {
    $currency = trim((string) $currency);
    if ($currency == '') {
      $currency = 'CFA';
    }
    return $currency . ' ' . number_format((float) $amount, 0, ',', ' ');
  }
}

if (!function_exists('tikras_tickets_status_label')) {
  function tikras_tickets_status_label($status)
  {
    $labels = array(
      'created' => 'Cree',
      'synced' => 'Synchronise',
      'partial' => 'Partiel',
      'error' => 'Erreur',
      'sold' => 'Vendu',
      'expired' => 'Expire',
    );
    return isset($labels[$status]) ? $labels[$status] : ucfirst((string) $status);
  }
}

$q = trim(tikras_get('q'));
$status = trim(tikras_get('status'));
$filterSession = trim(tikras_get('router'));
$batch = trim(tikras_get('batch'));
$filters = array(
  'q' => $q,
  'status' => $status,
  'session' => $filterSession,
  'batch' => $batch,
);

$storageHealth = tikras_storage_health();
if ($storageHealth['available']) {
  tikras_storage_sync_routers($data);
}
$stats = tikras_storage_ticket_stats();
$batches = tikras_storage_list_ticket_batches($filters, 80);
$tickets = tikras_storage_list_tickets($filters, 120);
$batchCodes = array();
foreach ($batches as $row) {
  $batchCodes[] = $row['batch_code'];
}
$routesByBatch = tikras_storage_routes_by_batch($batchCodes);

$routerOptions = array();
if (isset($data) && is_array($data)) {
  foreach ($data as $name => $row) {
    if ($name == 'mikhmon' || !is_array($row)) {
      continue;
    }
    $routerOptions[] = $name;
  }
  sort($routerOptions);
}

$query = array();
if ($q != '') {
  $query['q'] = $q;
}
if ($status != '') {
  $query['status'] = $status;
}
if ($filterSession != '') {
  $query['router'] = $filterSession;
}
if ($batch != '') {
  $query['batch'] = $batch;
}
$exportUrl = './process/tickets-export.php';
if (count($query) > 0) {
  $exportUrl .= '?' . http_build_query($query);
}
?>

<div class="tikras-tickets-page">
  <div class="tikras-tickets-hero">
    <div>
      <span class="tikras-storage-eyebrow">Tickets et roaming</span>
      <h2>Historique tickets roaming</h2>
      <p>Suivi local des lots generes, routes de synchronisation, tickets et cache des ventes.</p>
    </div>
    <div class="tikras-tickets-actions">
      <a class="btn bg-primary" href="./admin.php?id=storage"><i class="fa fa-database"></i> Base locale</a>
      <a class="btn bg-secondary" href="<?= tikras_tickets_h($exportUrl); ?>"><i class="fa fa-download"></i> CSV</a>
    </div>
  </div>

  <?php if ($storageHealth['error'] != '') { ?>
    <div class="tikras-storage-alert">
      <i class="fa fa-warning"></i>
      <span><?= tikras_tickets_h($storageHealth['error']); ?></span>
    </div>
  <?php } ?>

  <div class="tikras-tickets-stats">
    <div><span>Tickets</span><strong><?= (int) $stats['tickets']; ?></strong></div>
    <div><span>Lots</span><strong><?= (int) $stats['batches']; ?></strong></div>
    <div><span>Lots OK</span><strong><?= (int) $stats['synced_batches']; ?></strong></div>
    <div><span>A verifier</span><strong><?= (int) $stats['error_batches']; ?></strong></div>
    <div><span>Routes</span><strong><?= (int) $stats['routes']; ?></strong></div>
    <div><span>Ventes cache</span><strong><?= (int) $stats['sales_cache']; ?></strong></div>
  </div>

  <form class="tikras-tickets-filters" method="get" action="./admin.php">
    <input type="hidden" name="id" value="tickets">
    <label>
      <span>Recherche</span>
      <input class="form-control" type="search" name="q" value="<?= tikras_tickets_h($q); ?>" placeholder="Ticket, profil, commentaire, lot">
    </label>
    <label>
      <span>Routeur</span>
      <select class="form-control" name="router">
        <option value="">Tous</option>
        <?php foreach ($routerOptions as $routerName) { ?>
          <option value="<?= tikras_tickets_h($routerName); ?>" <?= $filterSession == $routerName ? 'selected' : ''; ?>><?= tikras_tickets_h($routerName); ?></option>
        <?php } ?>
      </select>
    </label>
    <label>
      <span>Statut</span>
      <select class="form-control" name="status">
        <option value="">Tous</option>
        <?php foreach (array('created', 'synced', 'partial', 'error', 'sold', 'expired') as $statusOption) { ?>
          <option value="<?= $statusOption; ?>" <?= $status == $statusOption ? 'selected' : ''; ?>><?= tikras_tickets_status_label($statusOption); ?></option>
        <?php } ?>
      </select>
    </label>
    <label>
      <span>Lot</span>
      <input class="form-control" type="text" name="batch" value="<?= tikras_tickets_h($batch); ?>" placeholder="Code lot">
    </label>
    <button class="btn bg-primary" type="submit"><i class="fa fa-search"></i> Filtrer</button>
    <a class="btn bg-warning" href="./admin.php?id=tickets"><i class="fa fa-refresh"></i> Reset</a>
  </form>

  <div class="tikras-tickets-grid">
    <section class="tikras-tickets-panel tikras-tickets-batches">
      <div class="tikras-tickets-panel-title">
        <h3><i class="fa fa-object-group"></i> Lots recents</h3>
        <span><?= count($batches); ?> lot(s)</span>
      </div>
      <?php if (count($batches) < 1) { ?>
        <div class="tikras-empty-state">
          <strong>Aucun lot trouve</strong>
          <span>Les prochains tickets generes seront visibles ici.</span>
        </div>
      <?php } else { ?>
        <?php foreach ($batches as $row) {
          $rowRoutes = isset($routesByBatch[$row['batch_code']]) ? $routesByBatch[$row['batch_code']] : array();
        ?>
          <article class="tikras-batch-card">
            <div class="tikras-batch-main">
              <div>
                <strong><?= tikras_tickets_h($row['batch_code']); ?></strong>
                <span><?= tikras_tickets_h($row['profile']); ?> | <?= (int) $row['ticket_count']; ?> ticket(s)</span>
              </div>
              <em class="tikras-status tikras-status-<?= tikras_tickets_h($row['status']); ?>"><?= tikras_tickets_status_label($row['status']); ?></em>
            </div>
            <div class="tikras-batch-meta">
              <span><i class="fa fa-server"></i> <?= tikras_tickets_h($row['created_by']); ?></span>
              <span><i class="fa fa-money"></i> <?= tikras_tickets_money($row['price'], $row['currency']); ?></span>
              <span><i class="fa fa-clock-o"></i> <?= tikras_tickets_h($row['created_at']); ?></span>
            </div>
            <?php if (count($rowRoutes) > 0) { ?>
              <div class="tikras-route-tags">
                <?php foreach ($rowRoutes as $route) { ?>
                  <span class="<?= $route['sync_status'] == 'synced' ? 'is-ok' : 'is-error'; ?>">
                    <?= tikras_tickets_h($route['session']); ?>: <?= tikras_tickets_h($route['sync_status']); ?>
                  </span>
                <?php } ?>
              </div>
            <?php } ?>
          </article>
        <?php } ?>
      <?php } ?>
    </section>

    <section class="tikras-tickets-panel">
      <div class="tikras-tickets-panel-title">
        <h3><i class="fa fa-ticket"></i> Tickets</h3>
        <span><?= count($tickets); ?> ligne(s)</span>
      </div>
      <div class="tikras-table-wrap">
        <table class="table tikras-tickets-table">
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Profil</th>
              <th>Routeur</th>
              <th>Lot</th>
              <th>Prix</th>
              <th>Statut</th>
              <th>Cree le</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($tickets) < 1) { ?>
              <tr><td colspan="7">Aucun ticket trouve dans la base locale.</td></tr>
            <?php } ?>
            <?php foreach ($tickets as $ticket) { ?>
              <tr>
                <td><strong><?= tikras_tickets_h($ticket['ticket_code']); ?></strong></td>
                <td><?= tikras_tickets_h($ticket['profile']); ?></td>
                <td><?= tikras_tickets_h($ticket['source_session']); ?></td>
                <td><?= tikras_tickets_h($ticket['batch_code']); ?></td>
                <td><?= tikras_tickets_money($ticket['price'], $ticket['currency']); ?></td>
                <td><span class="tikras-status tikras-status-<?= tikras_tickets_h($ticket['status']); ?>"><?= tikras_tickets_status_label($ticket['status']); ?></span></td>
                <td><?= tikras_tickets_h($ticket['created_at']); ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
