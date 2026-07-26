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
include_once(dirname(__DIR__) . '/lib/tikras_routeros.php');
tikras_start_session();
tikras_bootstrap_errors(false);

function mikhmon_report_h($value)
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mikhmon_report_price_value($value)
{
	return (float) str_replace(',', '', $value);
}

function mikhmon_report_is_indo_currency($currency, $cekindo)
{
	$indoCurrencies = isset($cekindo['indo']) ? $cekindo['indo'] : array();
	return is_array($indoCurrencies) && in_array($currency, $indoCurrencies);
}

function mikhmon_report_money($amount, $currency, $cekindo)
{
	if (mikhmon_report_is_indo_currency($currency, $cekindo)) {
		return $currency . ' ' . number_format((float) $amount, 0, ',', '.');
	}

	return $currency . ' ' . number_format((float) $amount, 2, '.', ',');
}

function mikhmon_report_day($date)
{
	$parts = explode('-', $date);
	if (count($parts) >= 3) {
		return (int) $parts[2];
	}

	$parts = explode('/', $date);
	if (count($parts) >= 2) {
		return (int) $parts[1];
	}

	return 0;
}

function mikhmon_report_sort_summary($a, $b)
{
	if ($a['total'] == $b['total']) {
		return 0;
	}

	return ($a['total'] < $b['total']) ? 1 : -1;
}

function mikhmon_report_month_label($idbl, $monthsShort, $monthsFull)
{
	if ($idbl == '') {
		return 'Toutes les données';
	}

	if (strlen($idbl) == 6 && is_numeric(substr($idbl, 0, 2))) {
		$month = (int) substr($idbl, 0, 2);
		$year = substr($idbl, 2, 4);
		return isset($monthsFull[$month]) ? $monthsFull[$month] . ' ' . $year : $idbl;
	}

	$monthText = strtolower(substr($idbl, 0, 3));
	$month = array_search($monthText, $monthsShort);
	$year = substr($idbl, 3, 4);
	return $month ? $monthsFull[$month] . ' ' . $year : $idbl;
}

if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {

	$idhr = tikras_get('idhr');
	$idbl = tikras_get('idbl');
	$idblParts = explode("-", $idhr);
	$idbl2 = (count($idblParts) >= 2) ? $idblParts[1] . $idblParts[0] : date("mY");
	if ($idhr != ""){
		$_SESSION['report'] = "&idhr=".$idhr;
	} elseif ($idbl != ""){
		$_SESSION['report'] = "&idbl=".$idbl;
	} else {
		$_SESSION['report'] = "";
	}
	$_SESSION['idbl'] = $idbl;
	$remdata = tikras_post('remdata');
	$removeReportData = tikras_has_post('remdata');
	$prefix = trim(tikras_get('prefix'));
	$fcomment = trim(tikras_get('comment'));
	$range = trim(tikras_get('range'));
	$pcomment = substr($prefix, 0, 2);

	if ($pcomment == "!!") {
		$commentParts = explode("!!", $prefix);
		$fcomment = isset($commentParts[1]) ? $commentParts[1] : $fcomment;
		$prefix = "";
	}

	$gettimezone = $API->comm("/system/clock/print");
	$timezone = isset($gettimezone[0]['time-zone-name']) ? $gettimezone[0]['time-zone-name'] : tikras_session_get('timezone', date_default_timezone_get());
	date_default_timezone_set($timezone);

	if ($removeReportData) {
		if (strlen($idhr) > "0") {
			if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
				$API->write('/system/script/print', false);
				$API->write('?source=' . $idhr . '', false);
				$API->write('=.proplist=.id');
				$ARREMD = $API->read();
				for ($i = 0; $i < count($ARREMD); $i++) {
					$API->write('/system/script/remove', false);
					$API->write('=.id=' . $ARREMD[$i]['.id']);
					$READ = $API->read();

				}
			}
		} elseif (strlen($idbl) > "0") {
			if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
				$API->write('/system/script/print', false);
				$API->write('?owner=' . $idbl . '', false);
				$API->write('=.proplist=.id');
				$ARREMD = $API->read();
				for ($i = 0; $i < count($ARREMD); $i++) {
					$API->write('/system/script/remove', false);
					$API->write('=.id=' . $ARREMD[$i]['.id']);
					$READ = $API->read();

				}
			}

		}
		tikras_redirect('./?report=selling&session=' . $session);
	}

	$reportSuffix = array();
	if ($prefix != "") {
		$reportSuffix[] = "prefix-[" . $prefix . "]";
	}
	if ($fcomment != "") {
		$reportSuffix[] = "comment-[" . $fcomment . "]";
	}
	if ($range != "") {
		$reportSuffix[] = "range-[" . $range . "]";
	}
	$fprefix = count($reportSuffix) > 0 ? "-" . implode("-", $reportSuffix) : "";

	$getData = array();
	$TotalReg = 0;
	if (strlen($idhr) > "0") {
		if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
			$getData = $API->comm("/system/script/print", array(
				"?source" => "$idhr",
				".proplist" => "name",
			));
			$TotalReg = count($getData);
		}
		$filedownload = $idhr;
		$shf = "hidden";
		$shd = "inline-block";
	} elseif (strlen($idbl) > "0") {
		if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
			// Seul le nom du script porte les donnees de vente: sans .proplist,
			// RouterOS renvoie aussi tout le code source de chaque enregistrement.
			$getData = $API->comm("/system/script/print", array(
				"?owner" => "$idbl",
				".proplist" => "name",
			));
			$TotalReg = count($getData);
		}
		$filedownload = $idbl;
		$shf = "hidden";
		$shd = "inline-block";
	} elseif ($idhr == "" || $idbl == "") {
		if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
			$getData = $API->comm("/system/script/print", array(
				"?comment" => "mikhmon",
				".proplist" => "name",
			));
			$TotalReg = count($getData);
		}
		$filedownload = "all";
		$shf = "text";
		$shd = "none";
	} elseif (strlen($idbl) > "0" ) {
		if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
			// Seul le nom du script porte les donnees de vente: sans .proplist,
			// RouterOS renvoie aussi tout le code source de chaque enregistrement.
			$getData = $API->comm("/system/script/print", array(
				"?owner" => "$idbl",
				".proplist" => "name",
			));
			$TotalReg = count($getData);
		}
		$filedownload = $idbl;
		$shf = "hidden";
		$shd = "inline-block";
	}

	$idbls = array(1 => "01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12");
	$idblf = array(1 => "Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre");
	$monthsShort = array(1 => "jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec");
	$periodLabel = $idhr != "" ? $idhr : mikhmon_report_month_label($idbl, $monthsShort, $idblf);
	$printQuery = str_replace("report=selling&", "", tikras_server('QUERY_STRING'));
	$printQuery = str_replace("report=selling", "", $printQuery);
	$printQuery = ltrim($printQuery, "&");

	$rangeStart = "";
	$rangeEnd = "";
	if ($range != "" && strpos($range, "-") !== false) {
		$rangeParts = explode("-", $range);
		$rangeStart = (int) $rangeParts[0];
		$rangeEnd = (int) $rangeParts[1];
		if ($rangeStart > $rangeEnd) {
			$tmpRange = $rangeStart;
			$rangeStart = $rangeEnd;
			$rangeEnd = $tmpRange;
		}
	}

	$salesRows = array();
	$profileSummary = array();
	$dailySummary = array();
	$totalIncome = 0;
	$todayIncome = 0;
	$todayCount = 0;
	$totalresume = 0;
	$dataresume = "";
	$resumeCount = 0;
	$todayId = date("Y-m-d");

	if (is_array($getData)) {
		for ($i = 0; $i < count($getData); $i++) {
			$getname = explode("-|-", $getData[$i]['name']);
			if (count($getname) < 4) {
				continue;
			}

			$tgl = isset($getname[0]) ? $getname[0] : "";
			$ltime = isset($getname[1]) ? $getname[1] : "";
			$username = isset($getname[2]) ? $getname[2] : "";
			$price = isset($getname[3]) ? mikhmon_report_price_value($getname[3]) : 0;
			$profile = isset($getname[7]) ? $getname[7] : "-";
			$comment = isset($getname[8]) ? $getname[8] : "";

			$dataresume .= $tgl . $price;
			$totalresume += $price;
			$resumeCount++;

			if ($prefix != "" && substr($username, 0, strlen($prefix)) != $prefix) {
				continue;
			}
			if ($fcomment != "" && stripos($comment, $fcomment) === false) {
				continue;
			}
			if ($rangeStart !== "") {
				$saleDay = mikhmon_report_day($tgl);
				if ($saleDay < $rangeStart || $saleDay > $rangeEnd) {
					continue;
				}
			}

			$salesRows[] = array(
				"date" => $tgl,
				"time" => $ltime,
				"username" => $username,
				"profile" => $profile,
				"comment" => $comment,
				"amount" => $price,
			);

			$totalIncome += $price;
			if ($tgl == $todayId) {
				$todayIncome += $price;
				$todayCount++;
			}

			$profileKey = $profile == "" ? "-" : $profile;
			if (!isset($profileSummary[$profileKey])) {
				$profileSummary[$profileKey] = array("count" => 0, "total" => 0);
			}
			$profileSummary[$profileKey]["count"]++;
			$profileSummary[$profileKey]["total"] += $price;

			if (!isset($dailySummary[$tgl])) {
				$dailySummary[$tgl] = array("count" => 0, "total" => 0);
			}
			$dailySummary[$tgl]["count"]++;
			$dailySummary[$tgl]["total"] += $price;
		}
	}

	$_SESSION['dataresume'] = $dataresume;
	$_SESSION['totalresume'] = $resumeCount . '/' . $totalresume;
	$salesCacheResult = array('ok' => false, 'rows' => 0);
	if ($prefix == "" && $fcomment == "" && $range == "" && ($idbl != "" || $idhr != "")) {
		$salesCacheKey = $idbl != "" ? "month:" . $idbl : "day:" . $idhr;
		$salesCacheResult = tikras_storage_replace_sales_cache($session, $salesCacheKey, $salesRows, $currency);
	}

	uasort($profileSummary, "mikhmon_report_sort_summary");
	ksort($dailySummary);

	$salesCount = count($salesRows);
	$averageIncome = $salesCount > 0 ? ($totalIncome / $salesCount) : 0;
	$bestProfile = "-";
	$bestProfileCount = 0;
	$bestProfileIncome = 0;
	foreach ($profileSummary as $profileName => $summary) {
		$bestProfile = $profileName;
		$bestProfileCount = $summary["count"];
		$bestProfileIncome = $summary["total"];
		break;
	}

	$lastSale = $salesCount > 0 ? $salesRows[$salesCount - 1] : array("date" => "-", "time" => "-");
	$isIndoCurrency = mikhmon_report_is_indo_currency($currency, $cekindo);
	$moneyDecimals = $isIndoCurrency ? 0 : 2;
	$moneyDecimal = $isIndoCurrency ? "," : ".";
	$moneyThousands = $isIndoCurrency ? "." : ",";
	$reportProfileChartRows = array();
	$reportDailyChartRows = array();
	$profileChartLimit = 0;
	foreach ($profileSummary as $profileName => $summary) {
		if ($profileChartLimit >= 8) {
			break;
		}
		$reportProfileChartRows[] = array(
			"name" => $profileName,
			"y" => (float) $summary["total"],
			"tickets" => (int) $summary["count"],
		);
		$profileChartLimit++;
	}
	foreach ($dailySummary as $dayLabel => $summary) {
		$reportDailyChartRows[] = array(
			"name" => $dayLabel,
			"y" => (float) $summary["total"],
			"tickets" => (int) $summary["count"],
		);
	}
	if (count($reportDailyChartRows) > 18) {
		$reportDailyChartRows = array_slice($reportDailyChartRows, -18);
	}

	$selectedDay = "";
	$selectedMonth = date("m");
	$selectedYear = date("Y");
	$periodScope = $idhr != "" ? "day" : ($idbl != "" ? "month" : "all");
	$dayParts = explode("-", $idhr);
	if (count($dayParts) >= 3) {
		$selectedYear = $dayParts[0];
		$selectedMonth = $dayParts[1];
		$selectedDay = $dayParts[2];
	} elseif ($idbl != "") {
		if (strlen($idbl) == 6 && is_numeric(substr($idbl, 0, 2))) {
			$selectedMonth = substr($idbl, 0, 2);
			$selectedYear = substr($idbl, 2, 4);
		} else {
			$monthIndex = array_search(strtolower(substr($idbl, 0, 3)), $monthsShort);
			$selectedMonth = $monthIndex ? $idbls[$monthIndex] : $selectedMonth;
			$selectedYear = substr($idbl, 3, 4) != "" ? substr($idbl, 3, 4) : $selectedYear;
		}
	}
}
?>
<script>
function downloadCSV(csv, filename) {
  var csvFile = new Blob([csv], {type: "text/csv"});
  var downloadLink = document.createElement("a");
  downloadLink.download = filename;
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = "none";
  document.body.appendChild(downloadLink);
  downloadLink.click();
}

function csvCell(text) {
  text = (text || "").replace(/\s+/g, " ").trim();
  return '"' + text.replace(/"/g, '""') + '"';
}

function exportTableToCSV(filename) {
  var csv = [];
  var rows = [];
  var headerRows = document.querySelectorAll("#dataTable thead tr");
  var bodyRows = document.querySelectorAll("#salesRows tr[data-sale-row]");
  var footerRows = document.querySelectorAll("#dataTable tfoot tr");
  var i;

  for (i = 0; i < headerRows.length; i++) {
    rows.push(headerRows[i]);
  }
  for (i = 0; i < bodyRows.length; i++) {
    if (bodyRows[i].style.display !== "none") {
      rows.push(bodyRows[i]);
    }
  }
  for (i = 0; i < footerRows.length; i++) {
    rows.push(footerRows[i]);
  }

  for (i = 0; i < rows.length; i++) {
    var row = [];
    var cols = rows[i].querySelectorAll("td, th");
    for (var j = 0; j < cols.length; j++) {
      row.push(csvCell(cols[j].innerText || cols[j].textContent));
    }
    csv.push(row.join(","));
  }

  downloadCSV(csv.join("\n"), filename);
}

function number_format(number, decimals, dec_point, thousands_sep) {
  number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
  var n = !isFinite(+number) ? 0 : +number;
  var prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
  var sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep;
  var dec = (typeof dec_point === 'undefined') ? '.' : dec_point;
  var s = '';
  var toFixedFix = function(n, prec) {
    var k = Math.pow(10, prec);
    return '' + (Math.round(n * k) / k).toFixed(prec);
  };
  s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
  if (s[0].length > 3) {
    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
  }
  if ((s[1] || '').length < prec) {
    s[1] = s[1] || '';
    s[1] += new Array(prec - s[1].length + 1).join('0');
  }
  return s.join(dec);
}

function reportMoney(amount) {
  return <?= json_encode($currency); ?> + " " + number_format(amount, <?= (int) $moneyDecimals; ?>, <?= json_encode($moneyDecimal); ?>, <?= json_encode($moneyThousands); ?>);
}

var reportRowsCache = null;
var reportSearchTimer = null;

function getReportRowsCache() {
  if (reportRowsCache !== null) {
    return reportRowsCache;
  }

  var rows = document.querySelectorAll("#salesRows tr[data-sale-row]");
  reportRowsCache = [];
  for (var i = 0; i < rows.length; i++) {
    reportRowsCache.push({
      row: rows[i],
      text: rows[i].getAttribute("data-search") || (rows[i].textContent || "").toLowerCase(),
      amount: Number(rows[i].getAttribute("data-amount") || 0)
    });
  }
  return reportRowsCache;
}

function applyTableSearch() {
  var search = document.getElementById("tableSearch");
  var total = 0;
  var count = 0;
  var term = search ? search.value.toLowerCase().trim() : "";
  var rows = getReportRowsCache();

  for (var i = 0; i < rows.length; i++) {
    var match = term === "" || rows[i].text.indexOf(term) !== -1;
    rows[i].row.style.display = match ? "" : "none";
    if (match) {
      count++;
      total += rows[i].amount;
    }
  }

  var visibleTotal = document.getElementById("visibleTotal");
  var visibleCount = document.getElementById("visibleCount");
  var tableTotal = document.getElementById("total");
  var footerTotal = document.getElementById("footerTotal");
  if (visibleTotal) {
    visibleTotal.innerHTML = reportMoney(total);
  }
  if (visibleCount) {
    visibleCount.innerHTML = count + " " + <?= json_encode($_vouchers); ?>;
  }
  if (tableTotal) {
    tableTotal.innerHTML = reportMoney(total);
  }
  if (footerTotal) {
    footerTotal.innerHTML = reportMoney(total);
  }
}

function scheduleTableSearch() {
  clearTimeout(reportSearchTimer);
  reportSearchTimer = setTimeout(applyTableSearch, 90);
}

function syncPeriodFields() {
  var scope = document.getElementById('periodScope');
  var D = document.getElementById('D');
  var M = document.getElementById('M');
  var Y = document.getElementById('Y');
  var mode = scope ? scope.value : 'month';

  if (D) {
    D.disabled = mode !== 'day';
  }
  if (M) {
    M.disabled = mode === 'all';
  }
  if (Y) {
    Y.disabled = mode === 'all';
  }
}

function filterR(){
  var D = document.getElementById('D').value;
  var M = document.getElementById('M').value;
  var Y = document.getElementById('Y').value;
  var X = document.getElementById('prefixFilter').value;
  var C = document.getElementById('commentFilter').value;
  var R = document.getElementById('rangeFilter').value;
  var scopeEl = document.getElementById('periodScope');
  var periodScope = scopeEl ? scopeEl.value : (D !== "" ? "day" : "month");
  var params = [];

  if(periodScope === "day" && D !== ""){
    params.push('idhr=' + encodeURIComponent(Y + '-' + M + '-' + D));
  } else if (periodScope === "month" || (periodScope === "day" && D === "")) {
    params.push('idbl=' + encodeURIComponent(M + Y));
  }
  if (X !== "") {
    params.push('prefix=' + encodeURIComponent(X));
  }
  if (C !== "") {
    params.push('comment=' + encodeURIComponent(C));
  }
  if (R !== "") {
    params.push('range=' + encodeURIComponent(R));
  }
  params.push('session=<?= rawurlencode($session); ?>');
  window.location = './?report=selling&' + params.join('&');
}

$(document).ready(function(){
  $("#openResume").click(function(){
    notify("Calcul des données");
    window.location = "./?report=resume-report&idbl=<?= mikhmon_report_h($idbl); ?>&session=<?= mikhmon_report_h($session); ?>";
  });
  $("#periodScope").on("change", syncPeriodFields);
  $("#tableSearch").on("input", scheduleTableSearch);
  $("#tableSearch").on("change", applyTableSearch);
  syncPeriodFields();
  applyTableSearch();
});
</script>

<div class="report-page">
  <div class="report-hero">
    <div>
      <div class="report-eyebrow"><?= $_selling_report; ?></div>
      <h2 class="report-title"><?= mikhmon_report_h($periodLabel); ?></h2>
      <div class="report-meta">
        <?= mikhmon_report_h($hotspotname); ?> | <?= mikhmon_report_h($timezone); ?>
        <?php if ($fprefix != "") { echo " | " . mikhmon_report_h(trim(str_replace("-", " ", $fprefix))); } ?>
        <small id="loader" style="display: none;"><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small>
      </div>
    </div>
    <div class="report-actions">
      <button name="help" class="btn bg-secondary" onclick="location.href='#help';" title="<?= $_help ?>"><i class="fa fa-question-circle"></i> <?= $_help ?></button>
      <button class="btn bg-success" onclick="exportTableToCSV('report-tikras-it-<?= mikhmon_report_h($filedownload . $fprefix); ?>.csv')" title="Télécharger le rapport de vente"><i class="fa fa-download"></i> CSV</button>
      <button name="print" class="btn bg-primary" onclick="window.open('./report/print.php?<?= mikhmon_report_h($printQuery); ?>','_blank');" title="Print"><i class="fa fa-print"></i> <?= $_print ?></button>
      <?php if(!empty($idbl)){echo '<button name="resume" id="openResume" class="btn bg-primary" title="Resume Report"><i class="fa fa-area-chart"></i> '.$_resume.'</button>';}else{
        echo '<a class="btn bg-primary" href="./?report=selling&idbl='.mikhmon_report_h($idbl2).'&session='.mikhmon_report_h($session).'" title="Afficher '.mikhmon_report_h(mikhmon_report_month_label($idbl2, $monthsShort, $idblf)).'"><i class="fa fa-search"></i> '.mikhmon_report_h(mikhmon_report_month_label($idbl2, $monthsShort, $idblf)).'</a>';}?>
      <button style="display: <?= $shd; ?>;" name="remdata" class="btn bg-danger" onclick="location.href='#remdata';" title="Delete Data <?= mikhmon_report_h($filedownload); ?>"><i class="fa fa-trash"></i> <?= $_delete_data.' '. mikhmon_report_h($filedownload); ?></button>
    </div>
  </div>

  <div class="report-stat-grid">
    <div class="report-stat report-stat-income">
      <span><?= $_income ?></span>
      <strong id="visibleTotal"><?= mikhmon_report_money($totalIncome, $currency, $cekindo); ?></strong>
      <small id="visibleCount"><?= $salesCount . " " . $_vouchers; ?></small>
    </div>
    <div class="report-stat report-stat-count">
      <span><?= $_today ?></span>
      <strong><?= mikhmon_report_money($todayIncome, $currency, $cekindo); ?></strong>
      <small><?= $todayCount . " " . $_vouchers; ?></small>
    </div>
    <div class="report-stat report-stat-average">
      <span>Moyenne</span>
      <strong><?= mikhmon_report_money($averageIncome, $currency, $cekindo); ?></strong>
      <small><?= $_price ?> / ticket</small>
    </div>
    <div class="report-stat report-stat-profile">
      <span>Meilleur <?= $_profile ?></span>
      <strong><?= mikhmon_report_h($bestProfile); ?></strong>
      <small><?= $bestProfileCount . " " . $_vouchers; ?> | <?= mikhmon_report_money($bestProfileIncome, $currency, $cekindo); ?></small>
    </div>
  </div>

  <div class="report-toolbar">
    <div class="report-toolbar-row">
      <input id="tableSearch" type="text" class="form-control report-search" placeholder="<?= $_search ?> ticket, profil, commentaire">
      <button class="btn bg-primary" onclick="location.href='./?report=selling&session=<?= mikhmon_report_h($session); ?>';" title="Toutes les données"><i class="fa fa-list"></i> <?= $_all ?></button>
      <button class="btn bg-info" onclick="location.href='./?report=selling&idhr=<?= date("Y-m-d"); ?>&session=<?= mikhmon_report_h($session); ?>';" title="<?= $_today ?>"><i class="fa fa-calendar-check-o"></i> <?= $_today ?></button>
    </div>
    <div class="report-meta">
      Dernière vente : <?= mikhmon_report_h($lastSale["date"]); ?> <?= mikhmon_report_h($lastSale["time"]); ?>
    </div>
  </div>

  <div class="report-filters">
    <div class="report-filter-row">
      <div class="report-filter-field">
        <span class="report-filter-label">Période</span>
        <select class="form-control" title="Période" id="periodScope">
          <option value="all" <?php if ($periodScope == "all") { echo "selected"; } ?>>Toutes les données</option>
          <option value="month" <?php if ($periodScope == "month") { echo "selected"; } ?>>Mois</option>
          <option value="day" <?php if ($periodScope == "day") { echo "selected"; } ?>>Jour</option>
        </select>
      </div>
      <div class="report-filter-field">
        <span class="report-filter-label">Jour</span>
        <select class="form-control" title="<?= $_days ?>" id="D">
          <?php
          if ($selectedDay != "") {
            echo "<option value='" . mikhmon_report_h($selectedDay) . "'>" . mikhmon_report_h($selectedDay) . "</option>";
          }
          echo "<option value=''>Jour</option>";
          for ($x = 1; $x <= 31; $x++) {
            $dayValue = strlen($x) == 1 ? "0" . $x : $x;
            echo "<option value='" . $dayValue . "'>" . $dayValue . "</option>";
          }
          ?>
        </select>
      </div>
      <div class="report-filter-field">
        <span class="report-filter-label">Mois</span>
        <select class="form-control" title="Mois" id="M">
          <?php
          $selectedMonthIndex = array_search($selectedMonth, $idbls);
          if ($selectedMonthIndex) {
            echo "<option value='" . $selectedMonth . "'>" . $idblf[$selectedMonthIndex] . "</option>";
          }
          for ($x = 1; $x <= 12; $x++) {
            echo "<option value='" . $idbls[$x] . "'>" . $idblf[$x] . "</option>";
          }
          ?>
        </select>
      </div>
      <div class="report-filter-field">
        <span class="report-filter-label">Année</span>
        <select class="form-control" title="Année" id="Y">
          <?php
          echo "<option value='" . mikhmon_report_h($selectedYear) . "'>" . mikhmon_report_h($selectedYear) . "</option>";
          for ($Y = 2018; $Y <= date("Y"); $Y++) {
            if ($Y != $selectedYear) {
              echo "<option value='" . $Y . "'>" . $Y . "</option>";
            }
          }
          ?>
        </select>
      </div>
      <div class="report-filter-field-wide">
        <span class="report-filter-label">Préfixe ticket</span>
        <input id="prefixFilter" type="text" class="form-control" value="<?= mikhmon_report_h($prefix); ?>" placeholder="Préfixe">
      </div>
      <div class="report-filter-field-wide">
        <span class="report-filter-label"><?= $_comment ?></span>
        <input id="commentFilter" type="text" class="form-control" value="<?= mikhmon_report_h($fcomment); ?>" placeholder="<?= $_comment ?>">
      </div>
      <div class="report-filter-field">
        <span class="report-filter-label">Plage jours</span>
        <input id="rangeFilter" type="text" class="form-control" value="<?= mikhmon_report_h($range); ?>" placeholder="1-15">
      </div>
      <button class="btn bg-primary" onclick="filterR(); loader();" title="Filtrer"><i class="fa fa-search"></i> Filtrer</button>
    </div>
  </div>

  <div class="report-insights">
    <div class="report-panel">
      <h3><i class="fa fa-pie-chart"></i> <?= $_profile ?></h3>
      <?php if (count($profileSummary) > 0) { ?>
      <table class="table table-bordered report-mini-table">
        <thead>
          <tr>
            <th><?= $_profile ?></th>
            <th class="text-right"><?= $_vouchers ?></th>
            <th class="text-right"><?= $_income ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $profileLimit = 0;
          foreach ($profileSummary as $profileName => $summary) {
            if ($profileLimit >= 5) {
              break;
            }
            echo "<tr>";
            echo "<td>" . mikhmon_report_h($profileName) . "</td>";
            echo "<td class='text-right'>" . $summary["count"] . "</td>";
            echo "<td class='text-right'>" . mikhmon_report_money($summary["total"], $currency, $cekindo) . "</td>";
            echo "</tr>";
            $profileLimit++;
          }
          ?>
        </tbody>
      </table>
      <?php } else { ?>
      <div class="report-empty">Aucune vente trouvée.</div>
      <?php } ?>
    </div>
    <div class="report-panel">
      <h3><i class="fa fa-calendar"></i> <?= $_date ?></h3>
      <?php if (count($dailySummary) > 0) { ?>
      <table class="table table-bordered report-mini-table">
        <thead>
          <tr>
            <th><?= $_date ?></th>
            <th class="text-right"><?= $_vouchers ?></th>
            <th class="text-right"><?= $_income ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $dailyKeys = array_keys($dailySummary);
          $dailyStart = count($dailyKeys) > 7 ? count($dailyKeys) - 7 : 0;
          for ($i = $dailyStart; $i < count($dailyKeys); $i++) {
            $dayKey = $dailyKeys[$i];
            echo "<tr>";
            echo "<td>" . mikhmon_report_h($dayKey) . "</td>";
            echo "<td class='text-right'>" . $dailySummary[$dayKey]["count"] . "</td>";
            echo "<td class='text-right'>" . mikhmon_report_money($dailySummary[$dayKey]["total"], $currency, $cekindo) . "</td>";
            echo "</tr>";
          }
          ?>
        </tbody>
      </table>
      <?php } else { ?>
      <div class="report-empty">Aucune vente trouvée.</div>
      <?php } ?>
    </div>
  </div>

  <div class="report-panel tikras-report-analytics">
    <div class="tikras-card-title">
      <h3><i class="fa fa-line-chart"></i> Graphique statistique</h3>
      <span><?= mikhmon_report_h($salesCount); ?> ticket(s)</span>
    </div>
    <div class="tikras-report-chart-grid">
      <div id="reportDailyChart" class="tikras-report-chart"></div>
      <div id="reportProfileChart" class="tikras-report-chart"></div>
    </div>
  </div>

  <div class="report-table-wrap">
    <div class="report-table-scroll">
      <table id="dataTable" class="table table-bordered table-hover text-nowrap report-table">
        <thead class="thead-light">
          <tr>
            <th colspan="5"><?= $_selling_report ?> <?= mikhmon_report_h($filedownload . $fprefix); ?><b style="font-size:0;">,,,,</b></th>
            <th style="text-align:right;"><?= $_total ?></th>
            <th style="text-align:right;" id="total"><?= mikhmon_report_money($totalIncome, $currency, $cekindo); ?></th>
          </tr>
          <tr>
            <th>&#8470;</th>
            <th><?= $_date ?></th>
            <th><?= $_time ?></th>
            <th><?= $_user_name ?></th>
            <th><?= $_profile ?></th>
            <th><?= $_comment ?></th>
            <th style="text-align:right;"> <?= $_price ?></th>
          </tr>
        </thead>
        <tbody id="salesRows">
          <?php
          if ($salesCount == 0) {
            echo "<tr><td colspan='7' class='report-empty'>Aucune vente trouvée.</td></tr>";
          } else {
            for ($i = 0; $i < $salesCount; $i++) {
              $row = $salesRows[$i];
              $rowSearchText = strtolower($row["date"] . " " . $row["time"] . " " . $row["username"] . " " . $row["profile"] . " " . $row["comment"] . " " . $row["amount"]);
              echo "<tr data-sale-row='1' data-amount='" . mikhmon_report_h($row["amount"]) . "' data-search='" . mikhmon_report_h($rowSearchText) . "'>";
              echo "<td>" . ($i + 1) . "</td>";
              echo "<td>" . mikhmon_report_h($row["date"]) . "</td>";
              echo "<td>" . mikhmon_report_h($row["time"]) . "</td>";
              echo "<td>" . mikhmon_report_h($row["username"]) . "</td>";
              echo "<td>" . mikhmon_report_h($row["profile"]) . "</td>";
              echo "<td>" . mikhmon_report_h($row["comment"]) . "</td>";
              echo "<td style='text-align:right;'>" . mikhmon_report_money($row["amount"], $currency, $cekindo) . "</td>";
              echo "</tr>";
            }
          }
          ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="6" style="text-align:right;"><?= $_total ?></th>
            <th style="text-align:right;" id="footerTotal"><?= mikhmon_report_money($totalIncome, $currency, $cekindo); ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="modal-window" id="remdata" aria-hidden="true">
    <div>
      <header><h1><?= $_confirm ?></h1></header>
      <a style="font-weight:bold;" href="#" title="Fermer" class="modal-close">X</a>
      <p><?= $_delete_report ?></p>
      <form autocomplete="off" method="post" action="">
        <center>
          <button type="submit" name="remdata" title="Oui" class="btn bg-primary">Oui</button>&nbsp;
          <a class="btn bg-secondary" href="#" title="Fermer" class="modal-close">Non</a>
        </center>
      </form>
    </div>
  </div>
  <div class="modal-window" id="help" aria-hidden="true">
    <div>
      <header><h1><?= $_help ?></h1></header>
      <a style="font-weight:bold;" href="#" title="Fermer" class="modal-close">X</a>
      <p><?= $_help_report ?></p>
    </div>
  </div>
</div>

<script>
(function () {
  var dailyData = <?= json_encode($reportDailyChartRows); ?>;
  var profileData = <?= json_encode($reportProfileChartRows); ?>;
  var currency = <?= json_encode($currency); ?>;
  var decimals = <?= (int) $moneyDecimals; ?>;
  var decimalPoint = <?= json_encode($moneyDecimal); ?>;
  var thousandsSep = <?= json_encode($moneyThousands); ?>;

  function moneyLabel(amount) {
    amount = Number(amount || 0);
    return currency + " " + number_format(amount, decimals, decimalPoint, thousandsSep);
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
    Highcharts.chart("reportDailyChart", {
      chart: { type: "areaspline", height: 320, backgroundColor: "transparent" },
      title: { text: "Ventes par jour", align: "left", style: { color: colors.text, fontWeight: "800" } },
      credits: { enabled: false },
      legend: { enabled: false },
      xAxis: { type: "category", lineColor: colors.grid, tickColor: colors.grid, labels: { style: { color: colors.muted } } },
      yAxis: { min: 0, gridLineColor: colors.grid, title: { text: null }, labels: { style: { color: colors.muted }, formatter: function () { return moneyLabel(this.value); } } },
      tooltip: { backgroundColor: colors.surface, borderColor: colors.grid, style: { color: colors.text }, pointFormatter: function () { return "<b>" + moneyLabel(this.y) + "</b><br>" + (this.tickets || 0) + " ticket(s)"; } },
      plotOptions: { areaspline: { color: "#0b63ff", fillOpacity: 0.15, lineWidth: 3, marker: { radius: 3 } } },
      series: [{ name: "Ventes", data: dailyData.length ? dailyData : [{ name: "Aucune donnée", y: 0, tickets: 0 }] }]
    });

    Highcharts.chart("reportProfileChart", {
      chart: { type: "bar", height: 320, backgroundColor: "transparent" },
      title: { text: "Top profils", align: "left", style: { color: colors.text, fontWeight: "800" } },
      credits: { enabled: false },
      legend: { enabled: false },
      xAxis: { type: "category", lineColor: colors.grid, tickColor: colors.grid, labels: { style: { color: colors.muted } } },
      yAxis: { min: 0, gridLineColor: colors.grid, title: { text: null }, labels: { style: { color: colors.muted }, formatter: function () { return moneyLabel(this.value); } } },
      tooltip: { backgroundColor: colors.surface, borderColor: colors.grid, style: { color: colors.text }, pointFormatter: function () { return "<b>" + moneyLabel(this.y) + "</b><br>" + (this.tickets || 0) + " ticket(s)"; } },
      plotOptions: { series: { color: "#0b63ff", borderRadius: 4 } },
      series: [{ name: "Profils", data: profileData.length ? profileData : [{ name: "Aucune donnée", y: 0, tickets: 0 }] }]
    });
  });
})();
</script>
