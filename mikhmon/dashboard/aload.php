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
if (!function_exists('tikras_get')) {
  require_once(dirname(__DIR__) . '/lib/tikras_core.php');
}
tikras_start_session();
tikras_bootstrap_errors(false);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
// load session MikroTik
  $session = tikras_get('session');
  $load = tikras_get('load');

// lang
include('../include/lang.php');
include('../lang/'.$langid.'.php');

// load config
  include('../include/config.php');
  include('../include/readcfg.php');

// routeros api
  include_once('../lib/tikras_routeros.php');
  include_once('../lib/formatbytesbites.php');
  $API = tikras_routeros_create();



  if ($load == "sysresource") {

    tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);

// get MikroTik system clock
    $getclock = $API->comm("/system/clock/print");
    $clock = $getclock[0];
    $timezone = $getclock[0]['time-zone-name'];
    date_default_timezone_set($timezone);

// get system resource MikroTik
    $getresource = $API->comm("/system/resource/print");
    $resource = $getresource[0];

// get routeboard info
    $getrouterboard = $API->comm("/system/routerboard/print");
    $routerboard = $getrouterboard[0];
    ?>
    
    <div id="r_1" class="row">
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-calendar"></i></div>
              <div class="box-group-area">
              <span ><?= $_system_date_time ?><br>
                    <?php 
                    echo ucfirst($clock['date']) . " " . $clock['time'] . "<br>
                    ".$_uptime." : " . formatDTM($resource['uptime']);
                    ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-info-circle"></i></div>
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

<?php 
} else if ($load == "hotspot") {

  tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);
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

  ?>
    
            <div id="r_2" class="row">
            <div class="card">
              <div class="card-header"><h3><i class="fa fa-wifi"></i> Hotspot</h3></div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-3 col-box-6">
                      <div class="box bg-blue bmh-75">
                        <a href="./?hotspot=active&session=<?= $session; ?>">
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
                      <a href="./?hotspot=users&profile=all&session=<?= $session; ?>">
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
                      <a href="./?hotspot-user=add&session=<?= $session; ?>">
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
                      <a href="./?hotspot-user=generate&session=<?= $session; ?>">
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
                        <a href="./?ppp=active&session=<?= $session; ?>">
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
                        <a href="./?ppp=secrets&profile=all&session=<?= $session; ?>">
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
                        <a href="./?ppp=addsecret&session=<?= $session; ?>">
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
                        <a href="./?ppp=profiles&session=<?= $session; ?>">
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

<?php 
} else if ($load == "logs") {

  tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);

  // move hotspot log to disk
  $getlogging = $API->comm("/system/logging/print", array("?prefix" => "->", ));
  $logging = $getlogging[0];
  if ($logging['prefix'] == "->") {
  } else {
    $API->comm("/system/logging/add", array("action" => "disk", "prefix" => "->", "topics" => "hotspot,info,debug", ));
  }
  
  // get hotspot log
  $getlog = $API->comm("/log/print", array("?topics" => "hotspot,info,debug", ));
  $log = array_reverse($getlog);
  //$THotspotLog = count($getlog);

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
  }



  ?>
  
              <div id="r_3" class="row">
              <div class="card">
                <div class="card-header">
                  <h3><a href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log" ><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3></div>
                    <div class="card-body">
                      <div style="padding: 5px; height: <?= $logh; ?> ;" class="mr-t-10 overflow">
                        <table class="table table-sm table-bordered table-hover" style="font-size: 12px; td.padding:2px;">
                          <thead>
                            <tr>
                            <th><?= $_time; ?></th>
                            <th><?= $_users ?> (IP)</th>
                            <th><?= $_messages ?></th>
                            </tr>
                          </thead>
                          <tbody>
                      
  <?php


  $nlog = min(20, count($log));
  for ($i = 0; $i < $nlog; $i++) {
    $message = isset($log[$i]['message']) ? (string) $log[$i]['message'] : '';
    $mess = explode(":", $message);
    $time = isset($log[$i]['time']) ? $log[$i]['time'] : '';
    echo "<tr>";
    if (substr($message, 0, 2) == "->") {
      echo "<td>" . tikras_h($time) . "</td>";
      echo "<td>";
      if (count($mess) > 6) {
        echo tikras_h(($mess[1] ?? '') . ":" . ($mess[2] ?? '') . ":" . ($mess[3] ?? '') . ":" . ($mess[4] ?? '') . ":" . ($mess[5] ?? '') . ":" . ($mess[6] ?? ''));
      } else {
        echo tikras_h($mess[1] ?? '');
      }
      echo "</td>";
      echo "<td>";
      if (count($mess) > 6) {
        echo tikras_h(str_replace("trying to", "", ($mess[7] ?? '') . " " . ($mess[8] ?? '') . " " . ($mess[9] ?? '') . " " . ($mess[10] ?? '')));
      } else {
        echo tikras_h(str_replace("trying to", "", ($mess[2] ?? '') . " " . ($mess[3] ?? '') . " " . ($mess[4] ?? '') . " " . ($mess[5] ?? '')));
      }
      echo "</td>";
    } else {
    }
    echo "</tr>";
  }
  ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                </div>

<?php 
}

}

?>
