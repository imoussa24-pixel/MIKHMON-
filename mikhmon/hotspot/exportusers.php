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
tikras_bootstrap_errors(false);
ini_set('max_execution_time', 300);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
  $exportMode = tikras_get('export', 'csv');

  if ($prof == "all") {
    $getuser = $API->comm("/ip/hotspot/user/print");
    $TotalReg = count($getuser);

    $counttuser = $API->comm("/ip/hotspot/user/print", array(
      "count-only" => ""
    ));
  } elseif ($prof != "all") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?profile" => "$prof",
    ));
    $TotalReg = count($getuser);

    $counttuser = $API->comm("/ip/hotspot/user/print", array(
      "count-only" => "",
      "?profile" => "$prof",
    ));

  }
  if ($comm != "") {
    $getuser = $API->comm("/ip/hotspot/user/print", array(
      "?comment" => "$comm",
    //"?uptime" => "00:00:00"
    ));
    $TotalReg = count($getuser);

    $counttuser = $API->comm("/ip/hotspot/user/print", array(
      "count-only" => "",
      "?comment" => "$comm",
    ));
  }
}
?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-users"></i> Export Hotspot Users | <strong class="pointer" onclick="exportTableToCSV('export-user-hotspot-tikras-it-<?= date("Y-m-d"); ?>.<?php if ($exportMode == "csv") {
                                                                                                                                                                  echo "csv";
                                                                                                                                                                } else {
                                                                                                                                                                  echo "txt";
                                                                                                                                                                } ?>')" title="Download User List"><i class="fa fa-download"></i> Download</strong>
    </h3>
    
</div>

<div class="card-body overflow">		   
<table id="export" class="text-nowrap <?php if ($exportMode == "csv") {
                                        echo "table table-bordered";
                                      } ?>">
  <?php if ($exportMode == "script") { ?>
  <tr>
    <td>/ip hotspot user</td>
  </tr>
<?php
for ($i = 0; $i < $TotalReg; $i++) {
  $userdetails = $getuser[$i];
  $uid = tikras_array_get($userdetails, '.id', '');
  $userver = tikras_array_get($userdetails, 'server', '');
  $uname = tikras_array_get($userdetails, 'name', '');
  $upass = tikras_array_get($userdetails, 'password', '');
  $uprofile = tikras_array_get($userdetails, 'profile', '');
  $uuptime = formatDTM(tikras_array_get($userdetails, 'uptime', '0s'));
  $ubyteso = formatBytes(tikras_array_get($userdetails, 'bytes-out', 0), 2);
  if ($ubyteso == 0) {
    $ubyteso = "";
  } else {
    $ubyteso = $ubyteso;
  }
  $ucomment = tikras_array_get($userdetails, 'comment', '');
  $udisabled = tikras_array_get($userdetails, 'disabled', '');
  $utimelimit = tikras_array_get($userdetails, 'limit-uptime', '');
  $udatalimit = tikras_array_get($userdetails, 'limit-bytes-total', '');

  if ($utimelimit == "") {
    $timelimit = "";
  } else {
    $timelimit = 'limit-uptime="' . $utimelimit . '"';
  }
  if ($udatalimit == "") {
    $datalimit = "";
  } else {
    $datalimit = 'limit-bytes-total="' . $udatalimit . '"';
  }
  if ($ucomment == "") {
    $comment = "";
  } else {
    $comment = 'comment="' . $ucomment . '"';
  }

  echo '
  <tr>
    <td>add name="' . $uname . '" password="' . $upass . '" profile="' . $uprofile . '" ' . $comment . ' ' . $timelimit . ' ' . $datalimit . '</td>
  </tr>
	';
}
} else if ($exportMode == "csv") { ?>
  <tr>
    <th>Username</th>
    <th>Password</th>
    <th>Profile</th>
    <th>Time Limit</th>
    <th>Data Limit</th>
    <th>Comment</th>
  </tr>
  <?php
  for ($i = 0; $i < $TotalReg; $i++) {
    $userdetails = $getuser[$i];
    $uid = tikras_array_get($userdetails, '.id', '');
    $userver = tikras_array_get($userdetails, 'server', '');
    $uname = tikras_array_get($userdetails, 'name', '');
    $upass = tikras_array_get($userdetails, 'password', '');
    $uprofile = tikras_array_get($userdetails, 'profile', '');
    $uuptime = formatDTM(tikras_array_get($userdetails, 'uptime', '0s'));
    $ubyteso = formatBytes(tikras_array_get($userdetails, 'bytes-out', 0), 2);
    if ($ubyteso == 0) {
      $ubyteso = "";
    } else {
      $ubyteso = $ubyteso;
    }
    $ucomment = tikras_array_get($userdetails, 'comment', '');
    $udisabled = tikras_array_get($userdetails, 'disabled', '');
    $utimelimit = tikras_array_get($userdetails, 'limit-uptime', '');
    $udatalimit = tikras_array_get($userdetails, 'limit-bytes-total', '');

    echo '
  <tr>
    <td>' . $uname . '</td>
    <td>' . $upass . '</td>
    <td>' . $uprofile . '</td>
    <td>' . $utimelimit . '</td>
    <td>' . $udatalimit . '</td>
    <td>' . $ucomment . '</td>
  </tr>
  ';
  }
}
?>

</table>
</div>
</div>
</div>
</div>
<script>
      function downloadCSV(csv, filename) {
        var csvFile;
        var downloadLink;
        // CSV file
        csvFile = new Blob([csv], {type: "text/csv"});
        // Download link
        downloadLink = document.createElement("a");
        // File name
        downloadLink.download = filename;
        // Create a link to the file
        downloadLink.href = window.URL.createObjectURL(csvFile);
        // Hide download link
        downloadLink.style.display = "none";
        // Add the link to DOM
        document.body.appendChild(downloadLink);
        // Click download link
        downloadLink.click();
        }
        
        function exportTableToCSV(filename) {
          var csv = [];
          var rows = document.querySelectorAll("#export tr");
          
         for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll("td, th");
         for (var j = 0; j < cols.length; j++)
            row.push(cols[j].innerText);
        csv.push(row.join(","));
        }
        // Download CSV file
        downloadCSV(csv.join("\n"), filename);
        }
</script>
	
	
