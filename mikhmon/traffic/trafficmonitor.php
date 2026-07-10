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
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {


}
?>
<?php
  $trafficInterfaces = $API->comm("/interface/print");
  $trafficInterfaceTotal = is_array($trafficInterfaces) ? count($trafficInterfaces) : 0;
  $trafficIfaceIndex = max(0, ((int) $iface) - 1);
  if ($trafficIfaceIndex >= $trafficInterfaceTotal) {
    $trafficIfaceIndex = 0;
  }
  $trafficDefaultInterface = $trafficInterfaceTotal > 0 && isset($trafficInterfaces[$trafficIfaceIndex]['name']) ? $trafficInterfaces[$trafficIfaceIndex]['name'] : '';
?>
<section class="tikras-traffic-page">
  <div class="tikras-page-header">
    <div class="tikras-page-title">
      <span class="tikras-page-icon"><i class="fa fa-area-chart"></i></span>
      <div>
        <h2><?= $_traffic_monitor ?></h2>
        <p>Surveillance claire du débit TX/RX en temps réel</p>
      </div>
    </div>
    <div class="tikras-page-actions">
      <a class="tikras-btn tikras-btn-muted" href="./?session=<?= rawurlencode($session); ?>"><i class="fa fa-dashboard"></i><span><?= $_dashboard ?></span></a>
    </div>
  </div>

  <div class="tikras-traffic-shell">
    <article class="tikras-dashboard-card tikras-traffic-main-card">
      <div class="tikras-card-title">
        <h3><i class="fa fa-exchange"></i> Interface</h3>
        <select id="trafficInterfaceSelect" class="form-control">
          <?php
            for ($i = 0; $i < $trafficInterfaceTotal; $i++) {
              $ifName = isset($trafficInterfaces[$i]['name']) ? $trafficInterfaces[$i]['name'] : '';
              $selected = $ifName == $trafficDefaultInterface ? ' selected' : '';
              echo '<option value="' . htmlspecialchars($ifName, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>[' . ($i + 1) . '] ' . htmlspecialchars($ifName, ENT_QUOTES, 'UTF-8') . '</option>';
            }
          ?>
        </select>
      </div>
      <div class="tikras-traffic-stats tikras-traffic-stats-large">
        <div><span>TX</span><strong id="trafficTxRate">0 bps</strong><small>Sortant</small></div>
        <div><span>RX</span><strong id="trafficRxRate">0 bps</strong><small>Entrant</small></div>
        <div><span>Dernière mesure</span><strong id="trafficLastTime">--:--:--</strong><small>Actualisation 6 s</small></div>
      </div>
      <div id="trafficMonitor" class="tikras-traffic-chart tikras-traffic-chart-large"></div>
    </article>

    <aside class="tikras-dashboard-card tikras-traffic-side-card">
      <div class="tikras-card-title">
        <h3><i class="fa fa-info-circle"></i> Lecture rapide</h3>
      </div>
      <div class="tikras-mini-grid">
        <div><span>Routeur</span><strong><?= htmlspecialchars($session, ENT_QUOTES, 'UTF-8'); ?></strong></div>
        <div><span>Interfaces</span><strong><?= $trafficInterfaceTotal; ?></strong></div>
        <div><span>Mode</span><strong>Temps réel</strong></div>
        <div><span>Graphique</span><strong>TX / RX</strong></div>
      </div>
    </aside>
  </div>
</section>

<script>
(function () {
  var sessionName = <?= json_encode($session); ?>;
  var defaultInterface = <?= json_encode($trafficDefaultInterface); ?>;
  var selectId = "Interface_" + sessionName;

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

    var ifaceSelect = document.getElementById("trafficInterfaceSelect");
    var storedInterface = sessionStorage.getItem(selectId);
    if (ifaceSelect && storedInterface) {
      ifaceSelect.value = storedInterface;
    } else if (ifaceSelect && defaultInterface !== "") {
      ifaceSelect.value = defaultInterface;
      sessionStorage.setItem(selectId, defaultInterface);
    }

    var txRate = document.getElementById("trafficTxRate");
    var rxRate = document.getElementById("trafficRxRate");
    var lastTime = document.getElementById("trafficLastTime");
    var colors = themeColors();
    var chart = Highcharts.chart("trafficMonitor", {
      chart: { type: "areaspline", height: 430, backgroundColor: "transparent", animation: true },
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
      plotOptions: { areaspline: { fillOpacity: 0.18, lineWidth: 3, marker: { enabled: false } } },
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
          var shift = chart.series[0].data.length > 40;
          chart.series[0].addPoint([now, tx], false, shift);
          chart.series[1].addPoint([now, rx], true, shift);
          txRate.textContent = rateLabel(tx);
          rxRate.textContent = rateLabel(rx);
          lastTime.textContent = Highcharts.dateFormat("%H:%M:%S", now);
        },
        error: function () {
          txRate.textContent = "0 bps";
          rxRate.textContent = "0 bps";
          lastTime.textContent = "hors ligne";
        },
        complete: function () {
          trafficPending = false;
        }
      });
    }

    if (ifaceSelect) {
      ifaceSelect.addEventListener("change", function () {
        sessionStorage.setItem(selectId, ifaceSelect.value);
        chart.series[0].setData([]);
        chart.series[1].setData([]);
        pullTraffic();
      });
    }
    pullTraffic();
    window.tikrasTrafficMonitorTimer = setInterval(pullTraffic, 6000);
  });
})();
</script>
<?php return; ?>

<script>
   var _0x381f=["\x63\x68\x61\x6E\x67\x65","\x76\x61\x6C","\x49\x6E\x74\x65\x72\x66\x61\x63\x65\x5F","\x76\x61\x6C\x75\x65","\x4D\x69\x6B\x68\x6D\x6F\x6E\x53\x65\x73\x73\x69\x6F\x6E","\x67\x65\x74\x45\x6C\x65\x6D\x65\x6E\x74\x42\x79\x49\x64","\x75\x6E\x64\x65\x66\x69\x6E\x65\x64","\x73\x65\x74\x49\x74\x65\x6D","\x50\x6C\x65\x61\x73\x65\x20\x75\x73\x65\x20\x47\x6F\x6F\x67\x6C\x65\x20\x43\x68\x72\x6F\x6D\x65","\x72\x65\x6C\x6F\x61\x64","\x6C\x6F\x63\x61\x74\x69\x6F\x6E","\x6F\x6E","\x23\x64\x5F\x69\x6E\x74\x65\x72\x66\x61\x63\x65"];$(function(){$(_0x381f[12])[_0x381f[11]](_0x381f[0],function(){var _0xd273x1=$(this)[_0x381f[1]]();var _0xd273x2=_0x381f[2]+ document[_0x381f[5]](_0x381f[4])[_0x381f[3]];if(_0xd273x1){if( typeof (Storage)!== _0x381f[6]){sessionStorage[_0x381f[7]](_0xd273x2,_0xd273x1)}else {alert(_0x381f[8])};window[_0x381f[10]][_0x381f[9]]()};return false})})
</script>
          <div class="card">
            <div class="card-header"><h3><i class="fa fa-area-chart"></i> <?= $_traffic_monitor ?> </h3></div>
          
              <div class="card-body">
                <div class="row">
                  <?php $getinterface = $API->comm("/interface/print");
                  $interface = $getinterface[$iface - 1]['name'];
                  $TotalReg = count($getinterface);

                  ?>
                  <div class="col-12">
                  <select id="d_interface" class="dropd pd-5" >
                    <option><?= $_select_interface ?></option>
                    <?php 
                      for ($i = 0; $i < $TotalReg; $i++) {
                        echo '<option value="' . $getinterface[$i]['name'] . '">['.($i+1).'] ' . $getinterface[$i]['name'] . '</option>';
                    }
                    ?>
                  </select>
                  </div>
                  <script type="text/javascript"> 
                    var chart;
                    var sessiondata = "<?= $session ?>";

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
                          },
                          chart: {
                            height: 500,

                          },
                        });

                        Highcharts.addEvent(Highcharts.Series, 'afterInit', function () {
	                        this.symbolUnicode = {
    	                    circle: '●',
                          diamond: '♦',
                          square: '■',
                          triangle: '▲',
                          'triangle-down': '▼'
                          }[this.symbol] || '●';
                        });

                          chart = new Highcharts.Chart({
                          chart: {
                          renderTo: 'trafficMonitor',
                          animation: Highcharts.svg,
                          type: 'areaspline',
                          events: {
                            load: function () {
                              setInterval(function () {
                                var _0xe05e=["\x49\x6E\x74\x65\x72\x66\x61\x63\x65\x5F","\x76\x61\x6C\x75\x65","\x4D\x69\x6B\x68\x6D\x6F\x6E\x53\x65\x73\x73\x69\x6F\x6E","\x67\x65\x74\x45\x6C\x65\x6D\x65\x6E\x74\x42\x79\x49\x64","\x67\x65\x74\x49\x74\x65\x6D"];var sesIface=_0xe05e[0]+ document[_0xe05e[3]](_0xe05e[2])[_0xe05e[1]];var interface=sessionStorage[_0xe05e[4]](sesIface)
                                requestDatta(sessiondata,interface);
                                chart.setTitle({ text: '<?= $_interface ?> ' + interface });
                              }, 3000);
                            }				
                          }
                        },
                        title: {
                          text: '<?= $_loading_interface ?>...'
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
                            var _0x2f7f=["\x70\x6F\x69\x6E\x74\x73","\x79","\x62\x70\x73","\x6B\x62\x70\x73","\x4D\x62\x70\x73","\x47\x62\x70\x73","\x54\x62\x70\x73","\x3C\x73\x70\x61\x6E\x20\x73\x74\x79\x6C\x65\x3D\x22\x63\x6F\x6C\x6F\x72\x3A","\x63\x6F\x6C\x6F\x72","\x73\x65\x72\x69\x65\x73","\x3B\x20\x66\x6F\x6E\x74\x2D\x73\x69\x7A\x65\x3A\x20\x31\x2E\x35\x65\x6D\x3B\x22\x3E","\x73\x79\x6D\x62\x6F\x6C\x55\x6E\x69\x63\x6F\x64\x65","\x3C\x2F\x73\x70\x61\x6E\x3E\x3C\x62\x3E","\x6E\x61\x6D\x65","\x3A\x3C\x2F\x62\x3E\x20\x30\x20\x62\x70\x73","\x70\x75\x73\x68","\x6C\x6F\x67","\x66\x6C\x6F\x6F\x72","\x3A\x3C\x2F\x62\x3E\x20","\x74\x6F\x46\x69\x78\x65\x64","\x70\x6F\x77","\x20","\x65\x61\x63\x68","\x3C\x62\x3E\x4D\x69\x6B\x68\x6D\x6F\x6E\x20\x54\x72\x61\x66\x66\x69\x63\x20\x4D\x6F\x6E\x69\x74\x6F\x72\x3C\x2F\x62\x3E\x3C\x62\x72\x20\x2F\x3E\x3C\x62\x3E\x54\x69\x6D\x65\x3A\x20\x3C\x2F\x62\x3E","\x25\x48\x3A\x25\x4D\x3A\x25\x53","\x78","\x64\x61\x74\x65\x46\x6F\x72\x6D\x61\x74","\x3C\x62\x72\x20\x2F\x3E","\x20\x3C\x62\x72\x2F\x3E\x20","\x6A\x6F\x69\x6E"];var s=[];$[_0x2f7f[22]](this[_0x2f7f[0]],function(_0x3735x2,_0x3735x3){var _0x3735x4=_0x3735x3[_0x2f7f[1]];var _0x3735x5=[_0x2f7f[2],_0x2f7f[3],_0x2f7f[4],_0x2f7f[5],_0x2f7f[6]];if(_0x3735x4== 0){s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[14])};var _0x3735x2=parseInt(Math[_0x2f7f[17]](Math[_0x2f7f[16]](_0x3735x4)/ Math[_0x2f7f[16]](1024)));s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[18]+ parseFloat((_0x3735x4/ Math[_0x2f7f[20]](1024,_0x3735x2))[_0x2f7f[19]](2))+ _0x2f7f[21]+ _0x3735x5[_0x3735x2])});return _0x2f7f[23]+ Highcharts[_0x2f7f[26]](_0x2f7f[24], new Date(this[_0x2f7f[25]]))+ _0x2f7f[27]+ s[_0x2f7f[29]](_0x2f7f[28])
                          },
                          shared: true                                                      
                        },
                      });
                    });
                  </script>
                  <div class="col-12" id="trafficMonitor"></div>
                </div>
              </div>  
