<?php
/*
 *  Copyright (C) 2026 TIKRAS IT.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');
include_once(dirname(__DIR__) . '/lib/tikras_roaming.php');
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {


// get MikroTik system clock
  $getclock = $API->comm("/system/clock/print");
  $clock = $getclock[0];
  $timezone = $getclock[0]['time-zone-name'];
  $_SESSION['timezone'] = $timezone;
  date_default_timezone_set($timezone);

// get system resource MikroTik
  $getresource = $API->comm("/system/resource/print");
  $resource = $getresource[0];

// get routeboard info
  $getrouterboard = $API->comm("/system/routerboard/print");
  $routerboard = $getrouterboard[0];
/*
// move hotspot log to disk *
  $getlogging = $API->comm("/system/logging/print", array("?prefix" => "->", ));
  $logging = $getlogging[0];
  if ($logging['prefix'] == "->") {
  } else {
    $API->comm("/system/logging/add", array("action" => "disk", "prefix" => "->", "topics" => "hotspot,info,debug", ));
  }

// get hotspot log
  $getlog = $API->comm("/log/print", array("?topics" => "hotspot,info,debug", ));
  $log = array_reverse($getlog);
  $THotspotLog = count($getlog);
*/
// get & counting hotspot users
  $countallusers = $API->comm("/ip/hotspot/user/print", array("count-only" => ""));
  if ($countallusers < 2) {
    $uunit = "item";
  } elseif ($countallusers > 1) {
    $uunit = "items";
  }

// get & counting hotspot active
  $counthotspotactive = $API->comm("/ip/hotspot/active/print", array("count-only" => ""));
  if ($counthotspotactive < 2) {
    $hunit = "item";
  } elseif ($counthotspotactive > 1) {
    $hunit = "items";
  }

// get & counting ppp secrets
  $countpppsecrets = $API->comm("/ppp/secret/print", array("count-only" => ""));
  if ($countpppsecrets < 2) {
    $punit = "item";
  } elseif ($countpppsecrets > 1) {
    $punit = "items";
  }

// get & counting ppp active
  $countpppactive = $API->comm("/ppp/active/print", array("count-only" => ""));
  if ($countpppactive < 2) {
    $paunit = "item";
  } elseif ($countpppactive > 1) {
    $paunit = "items";
  }

// get & counting ppp profiles
  $countpppprofiles = $API->comm("/ppp/profile/print", array("count-only" => ""));
  if ($countpppprofiles < 2) {
    $ppunit = "item";
  } elseif ($countpppprofiles > 1) {
    $ppunit = "items";
  }

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
  }

  $totalMemory = (float) $resource['total-memory'];
  $freeMemory = (float) $resource['free-memory'];
  $memoryUsed = $totalMemory > 0 ? max(0, min(100, round((($totalMemory - $freeMemory) / $totalMemory) * 100))) : 0;
  $memoryFree = $totalMemory > 0 ? max(0, min(100, round(($freeMemory / $totalMemory) * 100))) : 0;
  $totalHdd = (float) $resource['total-hdd-space'];
  $freeHdd = (float) $resource['free-hdd-space'];
  $hddFree = $totalHdd > 0 ? max(0, min(100, round(($freeHdd / $totalHdd) * 100))) : 0;
  $clientTotal = (int) $countallusers + (int) $countpppsecrets;
  $clientActive = (int) $counthotspotactive + (int) $countpppactive;
  $activeRatio = $clientTotal > 0 ? max(0, min(100, round(($clientActive / $clientTotal) * 100))) : 0;
  $uptimeLabel = formatDTM($resource['uptime']);
  $routerVisualLabel = isset($routerboard['model']) && $routerboard['model'] != "" ? $routerboard['model'] : $resource['board-name'];

  if (!function_exists('tikras_dashboard_money')) {
    function tikras_dashboard_money($amount, $currency, $cekindo)
    {
      $indoCurrencies = isset($cekindo['indo']) ? $cekindo['indo'] : array();
      if (is_array($indoCurrencies) && in_array($currency, $indoCurrencies)) {
        return $currency . " " . number_format((float) $amount, 0, ",", ".");
      }
      return $currency . " " . number_format((float) $amount, 2, ".", ",");
    }
  }

  $todaySalesCount = 0;
  $monthSalesCount = 0;
  $todaySalesIncome = 0;
  $monthSalesIncome = 0;
  $lastSaleTime = "-";
  $topProfile = "-";
  $topProfileIncome = 0;
  $profileSales = array();
  $todaySalesId = date("Y-m-d");
  $monthSalesId = date("m") . date("Y");
  $getSalesMonth = $API->comm("/system/script/print", array("?owner" => "$monthSalesId"));

  if (is_array($getSalesMonth)) {
    foreach ($getSalesMonth as $saleRow) {
      $saleName = isset($saleRow['name']) ? $saleRow['name'] : "";
      $saleParts = explode("-|-", $saleName);
      if (count($saleParts) < 4) {
        continue;
      }

      $saleDate = isset($saleParts[0]) ? $saleParts[0] : "";
      $saleTime = isset($saleParts[1]) ? $saleParts[1] : "";
      $salePrice = isset($saleParts[3]) ? (float) str_replace(",", "", $saleParts[3]) : 0;
      $saleProfile = isset($saleParts[7]) && $saleParts[7] != "" ? $saleParts[7] : "-";

      $monthSalesCount++;
      $monthSalesIncome += $salePrice;
      $lastSaleTime = trim($saleDate . " " . $saleTime);

      if ($saleDate == $todaySalesId) {
        $todaySalesCount++;
        $todaySalesIncome += $salePrice;
      }

      if (!isset($profileSales[$saleProfile])) {
        $profileSales[$saleProfile] = 0;
      }
      $profileSales[$saleProfile] += $salePrice;
    }
  }

  foreach ($profileSales as $profileName => $profileIncome) {
    if ($profileIncome >= $topProfileIncome) {
      $topProfile = $profileName;
      $topProfileIncome = $profileIncome;
    }
  }

  $todaySalesMoney = tikras_dashboard_money($todaySalesIncome, $currency, $cekindo);
  $monthSalesMoney = tikras_dashboard_money($monthSalesIncome, $currency, $cekindo);
  $topProfileMoney = tikras_dashboard_money($topProfileIncome, $currency, $cekindo);
  $dashboardRoamingSessions = tikras_roaming_sessions($data);
  $dashboardRoamingRouterCount = max(0, count($dashboardRoamingSessions) - 1);
  $dashboardRoamingQueue = tikras_roaming_queue_summary();
}
?>

<?php
  $dashboardInterfaces = $API->comm("/interface/print");
  $dashboardInterfaceTotal = is_array($dashboardInterfaces) ? count($dashboardInterfaces) : 0;
  $dashboardIfaceIndex = max(0, ((int) $iface) - 1);
  if ($dashboardIfaceIndex >= $dashboardInterfaceTotal) {
    $dashboardIfaceIndex = 0;
  }
  $dashboardTrafficInterface = $dashboardInterfaceTotal > 0 && isset($dashboardInterfaces[$dashboardIfaceIndex]['name']) ? $dashboardInterfaces[$dashboardIfaceIndex]['name'] : '';
  $dashboardReportProfileRows = array();
  foreach ($profileSales as $profileName => $profileIncome) {
    $dashboardReportProfileRows[] = array(
      'name' => $profileName,
      'y' => (float) $profileIncome,
    );
  }
  usort($dashboardReportProfileRows, function ($a, $b) {
    if ($a['y'] == $b['y']) {
      return 0;
    }
    return $a['y'] < $b['y'] ? 1 : -1;
  });
  $dashboardReportProfileRows = array_slice($dashboardReportProfileRows, 0, 5);
?>
    
<div id="reloadHome">
  <?php
    $dashboardActions = tikras_ui_button('./?hotspot-user=generate&session=' . rawurlencode($session), 'ticket', $_generate, 'primary')
      . tikras_ui_button('./?hotspot-user=generate-roaming&session=' . rawurlencode($session), 'random', 'Tickets roaming', 'muted')
      . tikras_ui_button('./?interface=traffic-monitor&session=' . rawurlencode($session), 'area-chart', $_traffic_monitor, 'muted')
      . tikras_ui_button('./?report=selling&idbl=' . rawurlencode($monthSalesId) . '&session=' . rawurlencode($session), 'bar-chart', $_report, 'muted');
    echo tikras_ui_page_header(
      'tachometer',
      $hotspotname != '' ? $hotspotname : $_dashboard,
      'Session ' . $session . ' | RouterOS ' . $resource['version'] . ' | ' . $clientActive . ' client(s) actif(s)',
      $dashboardActions
    );
  ?>

  <section class="tikras-dashboard-modern">
    <div class="tikras-dashboard-hero">
      <div class="tikras-dashboard-identity">
        <span class="tikras-dashboard-mark"><i class="fa fa-wifi"></i></span>
        <div>
          <p>Routeur actif</p>
          <h2><?= htmlspecialchars($routerVisualLabel, ENT_QUOTES, 'UTF-8'); ?></h2>
          <span><?= htmlspecialchars($resource['board-name'], ENT_QUOTES, 'UTF-8'); ?> | <?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </div>
      <div class="tikras-dashboard-status">
        <div>
          <span>Uptime</span>
          <strong><?= htmlspecialchars($uptimeLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
        <div>
          <span>Dernière vente</span>
          <strong><?= htmlspecialchars($lastSaleTime, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
      </div>
    </div>

    <div class="tikras-dashboard-kpis">
      <a class="tikras-kpi-card" href="./?hotspot=active&session=<?= rawurlencode($session); ?>">
        <span><i class="fa fa-laptop"></i> Clients actifs</span>
        <strong><?= $clientActive; ?></strong>
        <small>Hotspot <?= $counthotspotactive; ?> | PPP <?= $countpppactive; ?></small>
      </a>
      <a class="tikras-kpi-card" href="./?hotspot=users&profile=all&session=<?= rawurlencode($session); ?>">
        <span><i class="fa fa-users"></i> Clients enregistrés</span>
        <strong><?= $clientTotal; ?></strong>
        <small>Hotspot <?= $countallusers; ?> | PPP <?= $countpppsecrets; ?></small>
      </a>
      <a class="tikras-kpi-card" href="./?report=selling&idhr=<?= date('Y-m-d'); ?>&session=<?= rawurlencode($session); ?>">
        <span><i class="fa fa-calendar-check-o"></i> Aujourd'hui</span>
        <strong><?= htmlspecialchars($todaySalesMoney, ENT_QUOTES, 'UTF-8'); ?></strong>
        <small><?= $todaySalesCount; ?> ticket(s)</small>
      </a>
      <a class="tikras-kpi-card" href="./?report=selling&idbl=<?= rawurlencode($monthSalesId); ?>&session=<?= rawurlencode($session); ?>">
        <span><i class="fa fa-line-chart"></i> Ce mois</span>
        <strong><?= htmlspecialchars($monthSalesMoney, ENT_QUOTES, 'UTF-8'); ?></strong>
        <small><?= $monthSalesCount; ?> ticket(s)</small>
      </a>
    </div>

    <div class="tikras-dashboard-layout">
      <article class="tikras-dashboard-card tikras-router-health">
        <div class="tikras-card-title">
          <h3><i class="fa fa-heartbeat"></i> Santé routeur</h3>
          <span><?= htmlspecialchars($clock['date'] . ' ' . $clock['time'], ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="tikras-health-grid">
          <div>
            <span>CPU</span>
            <strong><?= (int) $resource['cpu-load']; ?>%</strong>
            <div class="tikras-meter"><i style="width: <?= (int) $resource['cpu-load']; ?>%;"></i></div>
          </div>
          <div>
            <span>Mémoire utilisée</span>
            <strong><?= $memoryUsed; ?>%</strong>
            <div class="tikras-meter"><i style="width: <?= $memoryUsed; ?>%;"></i></div>
          </div>
          <div>
            <span>Stockage libre</span>
            <strong><?= $hddFree; ?>%</strong>
            <div class="tikras-meter tikras-meter-green"><i style="width: <?= $hddFree; ?>%;"></i></div>
          </div>
          <div>
            <span>Taux d'activité</span>
            <strong><?= $activeRatio; ?>%</strong>
            <div class="tikras-meter tikras-meter-blue"><i style="width: <?= $activeRatio; ?>%;"></i></div>
          </div>
        </div>
      </article>

      <article class="tikras-dashboard-card tikras-traffic-card">
        <div class="tikras-card-title">
          <h3><i class="fa fa-area-chart"></i> Trafic en direct</h3>
          <select id="dashboardTrafficInterface" class="form-control">
            <?php
              for ($i = 0; $i < $dashboardInterfaceTotal; $i++) {
                $ifName = isset($dashboardInterfaces[$i]['name']) ? $dashboardInterfaces[$i]['name'] : '';
                $selected = $ifName == $dashboardTrafficInterface ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($ifName, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($ifName, ENT_QUOTES, 'UTF-8') . '</option>';
              }
            ?>
          </select>
        </div>
        <div class="tikras-traffic-stats">
          <div><span>TX</span><strong id="dashboardTxRate">0 bps</strong></div>
          <div><span>RX</span><strong id="dashboardRxRate">0 bps</strong></div>
        </div>
        <div id="dashboardTrafficChart" class="tikras-traffic-chart"></div>
      </article>

      <article class="tikras-dashboard-card tikras-report-chart-card">
        <div class="tikras-card-title">
          <h3><i class="fa fa-pie-chart"></i> Statistiques rapports</h3>
          <a href="./?report=selling&idbl=<?= rawurlencode($monthSalesId); ?>&session=<?= rawurlencode($session); ?>">Voir le rapport</a>
        </div>
        <div id="dashboardReportChart" class="tikras-report-chart"></div>
      </article>

      <article class="tikras-dashboard-card tikras-roaming-compact">
        <div class="tikras-card-title">
          <h3><i class="fa fa-random"></i> Roaming</h3>
          <a href="./?hotspot-user=generate-roaming&session=<?= rawurlencode($session); ?>">Ouvrir</a>
        </div>
        <div class="tikras-mini-grid">
          <div><span>Routeurs distants</span><strong><?= $dashboardRoamingRouterCount; ?></strong></div>
          <div><span>Lots en reprise</span><strong><?= isset($dashboardRoamingQueue['items']) ? (int) $dashboardRoamingQueue['items'] : 0; ?></strong></div>
          <div><span>Tickets en attente</span><strong><?= isset($dashboardRoamingQueue['tickets']) ? (int) $dashboardRoamingQueue['tickets'] : 0; ?></strong></div>
          <div><span>Profil dominant</span><strong><?= htmlspecialchars($topProfile, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        </div>
      </article>
    </div>

    <div class="tikras-action-strip">
      <a href="./?hotspot-user=generate&session=<?= rawurlencode($session); ?>"><i class="fa fa-ticket"></i><span>Générer</span></a>
      <a href="./?hotspot-user=generate-roaming&session=<?= rawurlencode($session); ?>"><i class="fa fa-random"></i><span>Roaming</span></a>
      <a href="./?hotspot=users&profile=all&session=<?= rawurlencode($session); ?>"><i class="fa fa-users"></i><span>Utilisateurs</span></a>
      <a href="./?hotspot=active&session=<?= rawurlencode($session); ?>"><i class="fa fa-wifi"></i><span>Actifs</span></a>
      <a href="./?interface=traffic-monitor&session=<?= rawurlencode($session); ?>"><i class="fa fa-area-chart"></i><span>Trafic</span></a>
      <a href="./?report=selling&idbl=<?= rawurlencode($monthSalesId); ?>&session=<?= rawurlencode($session); ?>"><i class="fa fa-table"></i><span>Rapport</span></a>
    </div>
  </section>

  <script>
  (function () {
    var sessionName = <?= json_encode($session); ?>;
    var profileData = <?= json_encode($dashboardReportProfileRows); ?>;

    function rateLabel(value) {
      value = Number(value || 0);
      var units = ["bps", "kbps", "Mbps", "Gbps", "Tbps"];
      if (value <= 0) {
        return "0 bps";
      }
      var idx = Math.floor(Math.log(value) / Math.log(1024));
      idx = Math.max(0, Math.min(idx, units.length - 1));
      return (value / Math.pow(1024, idx)).toFixed(idx === 0 ? 0 : 2) + " " + units[idx];
    }

    function themeColors() {
      var dark = document.body.getAttribute("data-theme") === "dark";
      return {
        text: dark ? "#eef4ff" : "#101828",
        muted: dark ? "#93a4bf" : "#667085",
        grid: dark ? "#27364f" : "#dbe5f1",
        surface: dark ? "#111c2e" : "#ffffff"
      };
    }

    window.addEventListener("load", function () {
      if (typeof Highcharts === "undefined") {
        return;
      }
      var colors = themeColors();
      var ifaceSelect = document.getElementById("dashboardTrafficInterface");
      var txRate = document.getElementById("dashboardTxRate");
      var rxRate = document.getElementById("dashboardRxRate");
      var trafficChart = Highcharts.chart("dashboardTrafficChart", {
        chart: { type: "areaspline", height: 280, backgroundColor: "transparent", animation: true },
        title: { text: null },
        credits: { enabled: false },
        legend: { align: "left", itemStyle: { color: colors.text } },
        xAxis: { type: "datetime", lineColor: colors.grid, tickColor: colors.grid, labels: { style: { color: colors.muted } } },
        yAxis: { min: 0, gridLineColor: colors.grid, title: { text: null }, labels: { style: { color: colors.muted }, formatter: function () { return rateLabel(this.value); } } },
        tooltip: { shared: true, backgroundColor: colors.surface, borderColor: colors.grid, style: { color: colors.text }, formatter: function () {
          var rows = ["<b>" + Highcharts.dateFormat("%H:%M:%S", this.x) + "</b>"];
          this.points.forEach(function (point) { rows.push(point.series.name + " : <b>" + rateLabel(point.y) + "</b>"); });
          return rows.join("<br>");
        } },
        plotOptions: { areaspline: { fillOpacity: 0.16, lineWidth: 3, marker: { enabled: false } } },
        series: [
          { name: "TX", color: "#0b63ff", data: [] },
          { name: "RX", color: "#16a34a", data: [] }
        ]
      });
      var trafficPending = false;

      function pullTraffic() {
        if (!ifaceSelect || ifaceSelect.value === "") {
          return;
        }
        if (trafficPending) {
          return;
        }
        trafficPending = true;
        $.ajax({
          url: "./traffic/traffic.php?session=" + encodeURIComponent(sessionName) + "&iface=" + encodeURIComponent(ifaceSelect.value),
          dataType: "json",
          timeout: 3000,
          success: function (data) {
            var rows = typeof data === "string" ? JSON.parse(data) : data;
            if (!rows || rows.length < 2) {
              return;
            }
            var tx = parseInt(rows[0].data, 10) || 0;
            var rx = parseInt(rows[1].data, 10) || 0;
            var now = (new Date()).getTime();
            var shift = trafficChart.series[0].data.length > 24;
            trafficChart.series[0].addPoint([now, tx], false, shift);
            trafficChart.series[1].addPoint([now, rx], true, shift);
            if (txRate) { txRate.textContent = rateLabel(tx); }
            if (rxRate) { rxRate.textContent = rateLabel(rx); }
          },
          error: function () {
            if (txRate) { txRate.textContent = "0 bps"; }
            if (rxRate) { rxRate.textContent = "0 bps"; }
          },
          complete: function () {
            trafficPending = false;
          }
        });
      }

      if (ifaceSelect) {
        ifaceSelect.addEventListener("change", function () {
          trafficChart.series[0].setData([]);
          trafficChart.series[1].setData([]);
          pullTraffic();
        });
      }
      pullTraffic();
      window.tikrasDashboardTrafficTimer = setInterval(pullTraffic, 5000);

      Highcharts.chart("dashboardReportChart", {
        chart: { type: "bar", height: 280, backgroundColor: "transparent" },
        title: { text: null },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: { type: "category", lineColor: colors.grid, tickColor: colors.grid, labels: { style: { color: colors.muted } } },
        yAxis: { min: 0, gridLineColor: colors.grid, title: { text: null }, labels: { style: { color: colors.muted } } },
        tooltip: { pointFormat: "<b>{point.y}</b>" },
        plotOptions: { series: { borderRadius: 4, color: "#0b63ff" } },
        series: [{ name: "Ventes", data: profileData.length ? profileData : [{ name: "Aucune donnée", y: 0 }] }]
      });
    });
  })();
  </script>
</div>
<?php return; ?>

<div id="reloadHomeLegacy">
    <?php
    $dashboardActions = tikras_ui_button('./?hotspot-user=generate&session=' . rawurlencode($session), 'ticket', $_generate, 'primary')
      . tikras_ui_button('./?hotspot-user=generate-roaming&session=' . rawurlencode($session), 'random', 'Tickets roaming', 'muted')
      . tikras_ui_button('./?hotspot=users&profile=all&session=' . rawurlencode($session), 'users', $_users, 'muted')
      . tikras_ui_button('./?report=selling&idbl=' . rawurlencode($monthSalesId) . '&session=' . rawurlencode($session), 'bar-chart', $_report, 'muted');
    echo tikras_ui_page_header(
      'tachometer',
      $hotspotname != '' ? $hotspotname : $_dashboard,
      'Session ' . $session . ' | RouterOS ' . $resource['version'] . ' | ' . $clientActive . ' client(s) actif(s)',
      $dashboardActions
    );
    ?>

    <div id="r_1" class="row tikras-dashboard-top">
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-calendar"></i></div>
              <div class="box-group-area">
                <span ><?= $_system_date_time ?><br>
                    <?php 
                    echo ucfirst($clock['date']) . " " . $clock['time'] . "<br>
                    ".$_uptime." : " . formatDTM($resource['uptime']);
                    $_SESSION[$session.'sdate'] = $clock['date'];
                    ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
          <div class="tikras-router-visual" aria-hidden="true">
            <svg class="tikras-router-svg" viewBox="0 0 128 78" focusable="false">
              <rect x="9" y="18" width="110" height="42" rx="7" class="tikras-router-body"/>
              <rect x="18" y="58" width="15" height="7" rx="1" class="tikras-router-foot"/>
              <rect x="95" y="58" width="15" height="7" rx="1" class="tikras-router-foot"/>
              <circle cx="24" cy="30" r="3.2" class="tikras-router-led tikras-router-led-green"/>
              <circle cx="35" cy="30" r="3.2" class="tikras-router-led tikras-router-led-blue"/>
              <circle cx="46" cy="30" r="3.2" class="tikras-router-led tikras-router-led-muted"/>
              <g class="tikras-router-ports">
                <rect x="24" y="40" width="12" height="10" rx="2"/>
                <rect x="40" y="40" width="12" height="10" rx="2"/>
                <rect x="56" y="40" width="12" height="10" rx="2"/>
                <rect x="72" y="40" width="12" height="10" rx="2"/>
                <rect x="88" y="40" width="12" height="10" rx="2"/>
              </g>
              <text x="64" y="15" text-anchor="middle" class="tikras-router-name"><?= htmlspecialchars($routerVisualLabel, ENT_QUOTES, 'UTF-8'); ?></text>
            </svg>
          </div>
              <div class="box-group-area">
                <span >
                    <?php
                    echo $_board_name." : " . $resource['board-name'] . "<br/>
                    ".$_model." : " . $routerboard['model'] . "<br/>
                    Router OS : " . $resource['version'];
                    ?>
                </span>
              </div>
            </div>
          </div>
        </div>
    <div class="col-4">
      <div class="box bmh-75 box-bordered">
        <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-server"></i></div>
              <div class="box-group-area">
                <span >
                    <?php
                    echo $_cpu_load." : " . $resource['cpu-load'] . "%<br/>
                    ".$_free_memory." : " . formatBytes($resource['free-memory'], 2) . "<br/>
                    ".$_free_hdd." : " . formatBytes($resource['free-hdd-space'], 2)
                    ?>
                </span>
                </div>
              </div>
            </div>
          </div> 
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card tikras-follow-card">
            <div class="card-header">
              <h3><i class="fa fa-line-chart"></i> RESSOURCES ROUTEUR</h3>
            </div>
            <div class="card-body">
              <div class="tikras-follow-grid">
                <div class="tikras-follow-item">
                  <span><?= $_uptime ?></span>
                  <strong><?= $uptimeLabel; ?></strong>
                  <small><?= $resource['board-name']; ?> | <?= $routerboard['model']; ?></small>
                </div>
                <div class="tikras-follow-item">
                  <span>CPU</span>
                  <strong><?= $resource['cpu-load']; ?>%</strong>
                  <div class="tikras-meter"><i style="width: <?= (int) $resource['cpu-load']; ?>%;"></i></div>
                </div>
                <div class="tikras-follow-item">
                  <span>Mémoire utilisée</span>
                  <strong><?= $memoryUsed; ?>%</strong>
                  <div class="tikras-meter"><i style="width: <?= $memoryUsed; ?>%;"></i></div>
                </div>
                <div class="tikras-follow-item">
                  <span>Stockage libre</span>
                  <strong><?= $hddFree; ?>%</strong>
                  <div class="tikras-meter tikras-meter-green"><i style="width: <?= $hddFree; ?>%;"></i></div>
                </div>
                <div class="tikras-follow-item">
                  <span>Clients actifs</span>
                  <strong><?= $clientActive; ?> / <?= $clientTotal; ?></strong>
                  <small>Hotspot <?= $counthotspotactive; ?> | PPP <?= $countpppactive; ?></small>
                </div>
                <div class="tikras-follow-item">
                  <span>Taux d'utilisation</span>
                  <strong><?= $activeRatio; ?>%</strong>
                  <div class="tikras-meter tikras-meter-blue"><i style="width: <?= $activeRatio; ?>%;"></i></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card tikras-sales-card">
            <div class="card-header">
              <h3><i class="fa fa-money"></i> RAPPORT JOURNALIER</h3>
              <a class="btn bg-primary tikras-card-action" href="./?report=selling&idbl=<?= $monthSalesId; ?>&session=<?= $session; ?>"><i class="fa fa-table"></i> <?= $_report ?></a>
            </div>
            <div class="card-body">
              <div class="tikras-sales-grid">
                <div class="tikras-sales-item">
                  <span><?= $_today ?></span>
                  <strong><?= $todaySalesMoney; ?></strong>
                  <small><?= $todaySalesCount; ?> vouchers</small>
                </div>
                <div class="tikras-sales-item">
                  <span><?= $_this_month ?></span>
                  <strong><?= $monthSalesMoney; ?></strong>
                  <small><?= $monthSalesCount; ?> vouchers</small>
                </div>
                <div class="tikras-sales-item">
                  <span>Profil dominant</span>
                  <strong><?= htmlspecialchars($topProfile, ENT_QUOTES, 'UTF-8'); ?></strong>
                  <small><?= $topProfileMoney; ?></small>
                </div>
                <div class="tikras-sales-item">
                  <span>Dernière vente</span>
                  <strong><?= htmlspecialchars($lastSaleTime, ENT_QUOTES, 'UTF-8'); ?></strong>
                  <small><?= htmlspecialchars($hotspotname, ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <div class="card tikras-roaming-dashboard-card">
            <div class="card-header">
              <h3><i class="fa fa-random"></i> TICKETS ROAMING</h3>
              <a class="btn bg-primary tikras-card-action" href="./?hotspot-user=generate-roaming&session=<?= $session; ?>"><i class="fa fa-ticket"></i> Generer roaming</a>
            </div>
            <div class="card-body">
              <div class="tikras-sales-grid tikras-roaming-dashboard-grid">
                <div class="tikras-sales-item">
                  <span>Routeurs distants</span>
                  <strong><?= $dashboardRoamingRouterCount; ?></strong>
                  <small>Hors routeur actuel</small>
                </div>
                <div class="tikras-sales-item">
                  <span>Lots en reprise</span>
                  <strong><?= isset($dashboardRoamingQueue['items']) ? (int) $dashboardRoamingQueue['items'] : 0; ?></strong>
                  <small>Relance disponible dans la page roaming</small>
                </div>
                <div class="tikras-sales-item">
                  <span>Tickets en attente</span>
                  <strong><?= isset($dashboardRoamingQueue['tickets']) ? (int) $dashboardRoamingQueue['tickets'] : 0; ?></strong>
                  <small><?= isset($dashboardRoamingQueue['targets']) ? (int) $dashboardRoamingQueue['targets'] : 0; ?> cible(s)</small>
                </div>
                <div class="tikras-sales-item tikras-roaming-dashboard-actions">
                  <span>Actions</span>
                  <strong><a href="./?hotspot-user=generate-roaming&session=<?= $session; ?>">Ouvrir</a></strong>
                  <small><a href="./?report=routeros-log&session=<?= $session; ?>">Journal RouterOS</a></small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

        <div class="row tikras-dashboard-main">
          <div  class="col-8">
            <div id="r_2" class="row">
            <div class="card">
              <div class="card-header"><h3><i class="fa fa-wifi"></i> Hotspot</h3></div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-3 col-box-6">
                      <div class="box bg-blue bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot=active&session=<?= $session; ?>">
                          <h1><?= $counthotspotactive; ?>
                              <span style="font-size: 15px;"><?= $hunit; ?></span>
                            </h1>
                          <div>
                            <i class="fa fa-laptop"></i> <?= $_hotspot_active ?>
                          </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                    <div class="box bg-green bmh-75">
                      <a onclick="cancelPage()" href="./?hotspot=users&profile=all&session=<?= $session; ?>">
                            <h1><?= $countallusers; ?>
                              <span style="font-size: 15px;"><?= $uunit; ?></span>
                            </h1>
                      <div>
                            <i class="fa fa-users"></i> <?= $_hotspot_users ?>
                          </div>
                      </a>
                    </div>
                  </div>
                  <div class="col-3 col-box-6">
                    <div class="box bg-yellow bmh-75">
                      <a onclick="cancelPage()" href="./?hotspot-user=add&session=<?= $session; ?>">
                        <div>
                          <h1><i class="fa fa-user-plus"></i>
                              <span style="font-size: 15px;"><?= $_add ?></span>
                          </h1>
                        </div>
                        <div>
                            <i class="fa fa-user-plus"></i> <?= $_hotspot_users ?>
                        </div>
                      </a>
                    </div>
                  </div>
                  <div class="col-3 col-box-6">
                    <div class="box bg-red bmh-75">
                      <a onclick="cancelPage()" href="./?hotspot-user=generate&session=<?= $session; ?>">
                        <div>
                          <h1><i class="fa fa-user-plus"></i>
                              <span style="font-size: 15px;"><?= $_generate ?></span>
                          </h1>
                        </div>
                        <div>
                            <i class="fa fa-user-plus"></i> <?= $_hotspot_users ?>
                        </div>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
            <div class="card">
              <div class="card-header"><h3><i class="fa fa-exchange"></i> PPP</h3></div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-3 col-box-6">
                      <div class="box bg-blue bmh-75">
                        <a onclick="cancelPage()" href="./?ppp=active&session=<?= $session; ?>">
                          <h1><?= $countpppactive; ?>
                              <span style="font-size: 15px;"><?= $paunit; ?></span>
                          </h1>
                          <div>
                            <i class="fa fa-plug"></i> <?= $_ppp_active ?>
                          </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-green bmh-75">
                        <a onclick="cancelPage()" href="./?ppp=secrets&profile=all&session=<?= $session; ?>">
                          <h1><?= $countpppsecrets; ?>
                              <span style="font-size: 15px;"><?= $punit; ?></span>
                          </h1>
                          <div>
                            <i class="fa fa-key"></i> <?= $_ppp_secrets ?>
                          </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-yellow bmh-75">
                        <a onclick="cancelPage()" href="./?ppp=addsecret&session=<?= $session; ?>">
                          <div>
                            <h1><i class="fa fa-user-plus"></i>
                                <span style="font-size: 15px;"><?= $_add ?></span>
                            </h1>
                          </div>
                          <div>
                            <i class="fa fa-user-plus"></i> <?= $_ppp_secrets ?>
                          </div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-red bmh-75">
                        <a onclick="cancelPage()" href="./?ppp=profiles&session=<?= $session; ?>">
                          <h1><?= $countpppprofiles; ?>
                              <span style="font-size: 15px;"><?= $ppunit; ?></span>
                          </h1>
                          <div>
                            <i class="fa fa-pie-chart"></i> <?= $_ppp_profiles ?>
                          </div>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
          </div>
            <div class="card">
              <div class="card-header"><h3><i class="fa fa-area-chart"></i> <?= $_traffic ?> </h3></div>

              <div class="card-body">
  
                  <?php $getinterface = $API->comm("/interface/print");
                  $interface = $getinterface[$iface - 1]['name']; 
                  /*$TotalReg = count($getinterface);
                  for ($i = 0; $i < $TotalReg; $i++) {
                    echo $getinterface[$i]['name'].'<br>';
                  }*/
                  ?>
                  
                  <script type="text/javascript"> 
                    var chart;
                    var sessiondata = "<?= $session ?>";
                    var interface = "<?= $interface ?>";
                    var n = 3000;
                    function requestDatta(session,iface) {
                      $.ajax({
                        url: './traffic/traffic.php?session='+session+'&iface='+iface,
                        datatype: "json",
                        success: function(data) {
                          var midata = JSON.parse(data);
                          if( midata.length > 0 ) {
                            var TX=parseInt(midata[0].data);
                            var RX=parseInt(midata[1].data);
                            var x = (new Date()).getTime(); 
                            shift=chart.series[0].data.length > 19;
                            chart.series[0].addPoint([x, TX], true, shift);
                            chart.series[1].addPoint([x, RX], true, shift);
                          }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) { 
                          console.error("Status: " + textStatus + " request: " + XMLHttpRequest); console.error("Error: " + errorThrown); 
                        }       
                      });
                    }	

                    $(document).ready(function() {
                        Highcharts.setOptions({
                          global: {
                            useUTC: false
                          }
                        });

                        /*
                        Highcharts.addEvent(Highcharts.Series, 'afterInit', function () {
	                        this.symbolUnicode = {
    	                    circle: '●',
                          diamond: '♦',
                          square: '■',
                          triangle: '▲',
                          'triangle-down': '▼'
                          }[this.symbol] || '●';
                        });

                        */
                          chart = new Highcharts.Chart({
                          chart: {
                          renderTo: 'trafficMonitor',
                          animation: Highcharts.svg,
                          type: 'areaspline',
                          events: {
                            load: function () {
                              setInterval(function () {
                                requestDatta(sessiondata,interface);
                              }, 8000);
                            }				
                          }
                        },
                        title: {
                          text: '<?= $_interface ?> ' + interface
                        },
                        
                        xAxis: {
                          type: 'datetime',
                          tickPixelInterval: 150,
                          maxZoom: 20 * 1000,
                        },
                        yAxis: {
                            minPadding: 0.2,
                            maxPadding: 0.2,
                            title: {
                              text: null
                            },
                            labels: {
                              formatter: function () {      
                                var bytes = this.value;                          
                                var sizes = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
                                if (bytes == 0) return '0 bps';
                                var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
                                i = Math.max(0, Math.min(i, sizes.length - 1));
                                return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];                    
                              },
                            },       
                        },
                        
                        series: [{
                          name: 'Tx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }, {
                          name: 'Rx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }],

                        tooltip: {
                          formatter: function () { 
                            var sizes = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
                            var rows = [];
                            $.each(this.points, function () {
                              var value = this.y || 0;
                              var unit = value > 0 ? parseInt(Math.floor(Math.log(value) / Math.log(1024))) : 0;
                              unit = Math.max(0, Math.min(unit, sizes.length - 1));
                              var display = value === 0 ? '0 bps' : parseFloat((value / Math.pow(1024, unit)).toFixed(2)) + ' ' + sizes[unit];
                              rows.push('<span style="color:' + this.series.color + ';font-size:1.2em;">&bull;</span> <b>' + this.series.name + ':</b> ' + display);
                            });
                            return '<b>TIKRAS IT Traffic</b><br><b>Time: </b>' + Highcharts.dateFormat('%H:%M:%S', new Date(this.x)) + '<br>' + rows.join('<br>');
                            /*
                            var _0x2f7f=["\x70\x6F\x69\x6E\x74\x73","\x79","\x62\x70\x73","\x6B\x62\x70\x73","\x4D\x62\x70\x73","\x47\x62\x70\x73","\x54\x62\x70\x73","\x3C\x73\x70\x61\x6E\x20\x73\x74\x79\x6C\x65\x3D\x22\x63\x6F\x6C\x6F\x72\x3A","\x63\x6F\x6C\x6F\x72","\x73\x65\x72\x69\x65\x73","\x3B\x20\x66\x6F\x6E\x74\x2D\x73\x69\x7A\x65\x3A\x20\x31\x2E\x35\x65\x6D\x3B\x22\x3E","\x73\x79\x6D\x62\x6F\x6C\x55\x6E\x69\x63\x6F\x64\x65","\x3C\x2F\x73\x70\x61\x6E\x3E\x3C\x62\x3E","\x6E\x61\x6D\x65","\x3A\x3C\x2F\x62\x3E\x20\x30\x20\x62\x70\x73","\x70\x75\x73\x68","\x6C\x6F\x67","\x66\x6C\x6F\x6F\x72","\x3A\x3C\x2F\x62\x3E\x20","\x74\x6F\x46\x69\x78\x65\x64","\x70\x6F\x77","\x20","\x65\x61\x63\x68","\x3C\x62\x3E\x4D\x69\x6B\x68\x6D\x6F\x6E\x20\x54\x72\x61\x66\x66\x69\x63\x20\x4D\x6F\x6E\x69\x74\x6F\x72\x3C\x2F\x62\x3E\x3C\x62\x72\x20\x2F\x3E\x3C\x62\x3E\x54\x69\x6D\x65\x3A\x20\x3C\x2F\x62\x3E","\x25\x48\x3A\x25\x4D\x3A\x25\x53","\x78","\x64\x61\x74\x65\x46\x6F\x72\x6D\x61\x74","\x3C\x62\x72\x20\x2F\x3E","\x20\x3C\x62\x72\x2F\x3E\x20","\x6A\x6F\x69\x6E"];var s=[];$[_0x2f7f[22]](this[_0x2f7f[0]],function(_0x3735x2,_0x3735x3){var _0x3735x4=_0x3735x3[_0x2f7f[1]];var _0x3735x5=[_0x2f7f[2],_0x2f7f[3],_0x2f7f[4],_0x2f7f[5],_0x2f7f[6]];if(_0x3735x4== 0){s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[14])};var _0x3735x2=parseInt(Math[_0x2f7f[17]](Math[_0x2f7f[16]](_0x3735x4)/ Math[_0x2f7f[16]](1024)));s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[18]+ parseFloat((_0x3735x4/ Math[_0x2f7f[20]](1024,_0x3735x2))[_0x2f7f[19]](2))+ _0x2f7f[21]+ _0x3735x5[_0x3735x2])});return _0x2f7f[23]+ Highcharts[_0x2f7f[26]](_0x2f7f[24], new Date(this[_0x2f7f[25]]))+ _0x2f7f[27]+ s[_0x2f7f[29]](_0x2f7f[28])
                            */
                          },
                          shared: true                                                      
                        },
                      });
                    });
                  </script>
                  <div id="trafficMonitor"></div>
                </div> 
              </div>
            </div>  
            <div class="col-4">
            <div id="r_4" class="row">
              <div <?= $lreport; ?> class="box bmh-75 box-bordered">
                <div class="box-group">
                  <div class="box-group-icon"><i class="fa fa-money"></i></div>
                    <div class="box-group-area">
                      <span >
                        <div id="reloadLreport">
                          <?php 
                          if ($_SESSION[$session.'sdate'] == $_SESSION[$session.'idhr']){
                            echo $_income." <br/>" . "
                          ".$_today." " . $_SESSION[$session.'totalHr'] . "vcr : " . $currency . " " . $_SESSION[$session.'dincome']. "<br/>
                          ".$_this_month." " . $_SESSION[$session.'totalBl'] . "vcr : " . $currency . " " . $_SESSION[$session.'mincome']; 
                          }else{
                            echo "<div id='loader' ><i><span> <i class='fa fa-circle-o-notch fa-spin'></i> ". $_processing." </i></div>";
                          }
                          ?>                       
                        </div>
                    </span>
                </div>
              </div>
            </div>
            </div>
            <div id="r_3" class="row">
            <div class="card">
              <div class="card-header">
                <h3><a onclick="cancelPage()" href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log" ><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3></div>
                  <div class="card-body">
                    <div style="padding: 5px; height: <?= $logh; ?> ;" class="mr-t-10 overflow">
                      <table class="table table-sm table-bordered table-hover" style="font-size: 12px; td.padding:2px;">
                        <thead>
                          <tr>
                            <th><?= $_time ?></th>
                            <th><?= $_users ?> (IP)</th>
                            <th><?= $_messages ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td colspan="3" class="text-center">
                            <div id="loader" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></div>
                            </td>
                          </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              </div>
            </div>
</div>
</div>
