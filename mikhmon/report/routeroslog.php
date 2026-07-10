<?php
/*
 * RouterOS log page for TIKRAS Mikhmon.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
include_once(dirname(__DIR__) . '/lib/tikras_storage.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  $topicFilter = trim(tikras_get('topic'));
  $limit = (int) tikras_get('limit', 300);
  if ($limit < 50) {
    $limit = 50;
  } elseif ($limit > 1000) {
    $limit = 1000;
  }

  $logSource = "RouterOS";
  $rawLogs = $API->comm("/log/print");
  if (is_array($rawLogs) && count($rawLogs) > 0) {
    tikras_storage_record_routeros_logs($session, $rawLogs);
    $rawLogs = array_reverse($rawLogs);
  } else {
    $rawLogs = tikras_storage_list_routeros_logs($session, $topicFilter, $limit);
    $logSource = "Base locale";
  }

  $topics = array();
  $logs = array();
  for ($i = 0; $i < count($rawLogs); $i++) {
    $row = $rawLogs[$i];
    $rowTopics = tikras_array_get($row, 'topics', '');
    if ($rowTopics != '') {
      $parts = explode(',', $rowTopics);
      for ($p = 0; $p < count($parts); $p++) {
        $topicName = trim($parts[$p]);
        if ($topicName != '') {
          $topics[$topicName] = true;
        }
      }
    }

    if ($topicFilter != '' && stripos($rowTopics, $topicFilter) === false) {
      continue;
    }

    $logs[] = $row;
    if (count($logs) >= $limit) {
      break;
    }
  }
  ksort($topics);

  $title = "Journal RouterOS";
  $subtitle = count($logs) . " entree(s) affichee(s) | Source " . $logSource;
  if ($topicFilter != '') {
    $subtitle .= " | Topic " . $topicFilter;
  }
  $actions = tikras_ui_button('./?report=routeros-log&session=' . rawurlencode($session), 'list', $_all, 'muted')
    . tikras_ui_button('./?report=routeros-log&session=' . rawurlencode($session), 'refresh', 'Actualiser', 'primary');
  echo tikras_ui_page_header('terminal', $title, $subtitle, $actions);
}
?>

<div class="row tikras-data-page">
  <div class="col-12">
    <div class="card tikras-data-card">
      <div class="card-header">
        <h3><i class="fa fa-terminal"></i> Journal RouterOS <span class="tikras-data-count" id="routerosLogVisible"><?= count($logs); ?></span></h3>
      </div>
      <div class="card-body">
        <div class="tikras-data-toolbar">
          <div class="tikras-data-filters">
            <input id="routerosLogSearch" type="search" class="group-item tikras-data-search" data-table="#routerosLogTable" data-counter="#routerosLogVisible" placeholder="<?= $_search ?> message, topic, heure">
            <select class="group-item" onchange="if(this.value !== ''){location = this.value;}">
              <option value=""><?= $topicFilter == '' ? 'Topics' : tikras_h($topicFilter); ?></option>
              <option value="./?report=routeros-log&session=<?= rawurlencode($session); ?>"><?= $_show_all ?></option>
              <?php foreach ($topics as $topicName => $enabled) { ?>
                <option value="./?report=routeros-log&topic=<?= rawurlencode($topicName); ?>&session=<?= rawurlencode($session); ?>"><?= tikras_h($topicName); ?></option>
              <?php } ?>
            </select>
            <select class="group-item" onchange="location='./?report=routeros-log&limit='+this.value+'&session=<?= rawurlencode($session); ?>';">
              <option value="<?= $limit; ?>"><?= $limit; ?> lignes</option>
              <option value="100">100 lignes</option>
              <option value="300">300 lignes</option>
              <option value="500">500 lignes</option>
              <option value="1000">1000 lignes</option>
            </select>
          </div>
          <div class="tikras-data-actions">
            <button class="btn bg-primary" type="button" onclick="location.reload();"><i class="fa fa-refresh"></i> Actualiser</button>
          </div>
        </div>

        <div class="overflow tikras-data-table-wrap">
          <table id="routerosLogTable" class="table table-bordered table-hover text-nowrap">
            <thead>
              <tr>
                <th><?= $_time ?></th>
                <th>Topics</th>
                <th><?= $_messages ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($logs) == 0) { ?>
                <tr><td colspan="3">Aucune entree RouterOS trouvee.</td></tr>
              <?php } ?>
              <?php for ($i = 0; $i < count($logs); $i++) {
                $logRow = $logs[$i];
                $time = tikras_array_get($logRow, 'time', '');
                $rowTopics = tikras_array_get($logRow, 'topics', '');
                $message = tikras_array_get($logRow, 'message', '');
              ?>
                <tr>
                  <td><?= tikras_h($time); ?></td>
                  <td><span class="tikras-log-topic"><?= tikras_h($rowTopics); ?></span></td>
                  <td><?= tikras_h($message); ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
