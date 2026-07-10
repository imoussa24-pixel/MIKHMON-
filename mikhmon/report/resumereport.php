<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
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

function mikhmon_resume_h($value)
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mikhmon_resume_days_in_month($month, $year)
{
  return $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : (($month - 1) % 7 % 2 ? 30 : 31);
}

function mikhmon_resume_per_day($date)
{
  $data = isset($_SESSION['dataresume']) ? $_SESSION['dataresume'] : "";
  $evalue = explode($date, $data);
  $x = count($evalue);
  $result = 0;
  for ($i = 0; $i < $x; $i++) {
    $result += (float) $evalue[$i];
  }
  return array("count" => ($x - 1), "total" => $result);
}

function mikhmon_resume_money($amount, $currency, $cekindo)
{
  $indoCurrencies = isset($cekindo['indo']) ? $cekindo['indo'] : array();
  if (is_array($indoCurrencies) && in_array($currency, $indoCurrencies)) {
    return $currency . " " . number_format((float) $amount, 0, ",", ".");
  }
  return $currency . " " . number_format((float) $amount, 2, ".", ",");
}

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  // load session MikroTik
  $session = tikras_get('session');

  // load config
  include('../include/config.php');
  include('../include/readcfg.php');

  $idbl = tikras_get('idbl');
  $ms = array(1 => "jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec");
  $mf = array(1 => "janvier", "fevrier", "mars", "avril", "mai", "juin", "juillet", "aout", "septembre", "octobre", "novembre", "decembre");

  if (strlen($idbl) == 6 && is_numeric(substr($idbl, 0, 2))) {
    $mn = (int) substr($idbl, 0, 2);
    $thisY = substr($idbl, 2, 4);
  } else {
    $thisM = strtolower(substr($idbl, 0, 3));
    $mn = array_search($thisM, $ms);
    $thisY = substr($idbl, -4);
  }

  if ($mn < 1 || $mn > 12) {
    $mn = (int) date("n");
  }
  if ($thisY == "") {
    $thisY = date("Y");
  }

  $thisM = $ms[$mn];
  $thisMonthName = $mf[$mn];
  $monthNumber = strlen($mn) == 1 ? "0" . $mn : $mn;

  if ($mn == date("n") && $thisY == date("Y")) {
    $totD = (date('d') + 1);
  } else {
    $totD = (mikhmon_resume_days_in_month($mn, $thisY) + 1);
  }

  $totalParts = explode("/", tikras_session_get('totalresume', '0/0'));
  $totalvrc = isset($totalParts[0]) ? $totalParts[0] : 0;
  $totalincome = isset($totalParts[1]) ? $totalParts[1] : 0;
  $totalreport = "Total " . $totalvrc . " ticket(s) : " . mikhmon_resume_money($totalincome, $currency, $cekindo);

  $chartRows = array();
  $activeDays = 0;
  $bestDay = "-";
  $bestDayIncome = 0;
  for ($i = 1; $i < $totD; $i++) {
    $thisD = strlen($i) == 1 ? "0" . $i : $i;
    $isoDate = $thisY . "-" . $monthNumber . "-" . $thisD;
    $legacyDate = strtolower($thisM . '/' . $thisD . '/' . $thisY);
    $daily = mikhmon_resume_per_day($isoDate);
    if ($legacyDate != $isoDate) {
      $legacyDaily = mikhmon_resume_per_day($legacyDate);
      $daily["count"] += $legacyDaily["count"];
      $daily["total"] += $legacyDaily["total"];
    }
    if ($daily["count"] > 0) {
      $activeDays++;
    }
    if ($daily["total"] > $bestDayIncome) {
      $bestDay = $thisD . " " . $thisMonthName;
      $bestDayIncome = $daily["total"];
    }
    $chartRows[] = array(
      "label" => "<b>" . $thisD . " " . $thisMonthName . " " . $daily["count"] . " ticket(s)</b>",
      "total" => $daily["total"],
      "count" => $daily["count"],
    );
  }

  $averageDayIncome = $activeDays > 0 ? ($totalincome / $activeDays) : 0;
}
?>

<div class="report-page resume-report-page">
  <div class="report-hero">
    <div>
      <div class="report-eyebrow"><?= $_resume ?> <?= $_selling_report ?></div>
      <h2 class="report-title"><?= mikhmon_resume_h($thisMonthName . " " . $thisY); ?></h2>
      <div class="report-meta"><?= mikhmon_resume_h($totalreport); ?></div>
    </div>
    <div class="report-actions">
      <a class="btn bg-primary" href="./?report=selling&idbl=<?= mikhmon_resume_h($monthNumber . $thisY); ?>&session=<?= mikhmon_resume_h($session); ?>"><i class="fa fa-table"></i> <?= $_selling_report ?></a>
    </div>
  </div>

  <div class="report-stat-grid">
    <div class="report-stat report-stat-income">
      <span><?= $_income ?></span>
      <strong><?= mikhmon_resume_money($totalincome, $currency, $cekindo); ?></strong>
      <small><?= $totalvrc; ?> <?= $_vouchers; ?></small>
    </div>
    <div class="report-stat report-stat-count">
      <span>Jours actifs</span>
      <strong><?= $activeDays; ?></strong>
      <small><?= $thisMonthName . " " . $thisY; ?></small>
    </div>
    <div class="report-stat report-stat-average">
      <span>Moyenne par jour</span>
      <strong><?= mikhmon_resume_money($averageDayIncome, $currency, $cekindo); ?></strong>
      <small><?= $_income; ?></small>
    </div>
    <div class="report-stat report-stat-profile">
      <span>Meilleur jour</span>
      <strong><?= mikhmon_resume_h($bestDay); ?></strong>
      <small><?= mikhmon_resume_money($bestDayIncome, $currency, $cekindo); ?></small>
    </div>
  </div>

  <div class="report-panel">
    <script src="./js/highcharts/highcharts.js"></script>
    <script src="./js/highcharts/themes/hc.<?= $theme; ?>.js"></script>
    <div class="col-12" id="container"></div>
  </div>
</div>

<script type="text/javascript">
Highcharts.chart('container', {
    chart: {
      height: 500,
      type: 'column'
    },
    title: {
      text: '<?= mikhmon_resume_h($_selling_report . " " . $thisMonthName . " " . $thisY); ?>'
    },
    subtitle: {
      text: '<?= mikhmon_resume_h($totalreport); ?>'
    },
    xAxis: {
      type: 'category',
      tickInterval: 1
    },
    yAxis: {
      min: 0,
      title: {
        text: '<?= mikhmon_resume_h($_income); ?>'
      }
    },
    legend: {
      enabled: false
    },
    plotOptions: {
      column: {
        borderWidth: 0,
        colorByPoint: true
      }
    },
    series: [{
      name: '<?= mikhmon_resume_h($_income); ?>',
      data: [
<?php
for ($i = 0; $i < count($chartRows); $i++) {
  echo "['" . addslashes($chartRows[$i]["label"]) . "'," . (float) $chartRows[$i]["total"] . "],";
}
?>
      ]
    }],
    tooltip: {
      pointFormat: 'Total ventes : <b>{point.y}</b>'
    },
    responsive: {
      rules: [{
        condition: {
          maxWidth: 500
        },
        chartOptions: {
          chart: {
            height: 380
          },
          xAxis: {
            labels: {
              rotation: -45
            }
          }
        }
      }]
    }
});
</script>
