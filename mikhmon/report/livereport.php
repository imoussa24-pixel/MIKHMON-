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
// load session MikroTik
  $session = tikras_get('session');
// set  timezone
date_default_timezone_set(tikras_session_get('timezone', date_default_timezone_get()));

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
  tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session);

  if ($livereport == "disable") {
    $logh = "457px";
    $lreport = "style='display:none;'";
  } else {
    $logh = "350px";
    $lreport = "style='display:block;'";
// get selling report
    $thisD = date("d");
    $thisM = date("m");
    $thisY = date("Y");

    if (strlen($thisD) == 1) {
      $thisD = "0" . $thisD;
    } else {
      $thisD = $thisD;
    }

    $idhr = $thisY . "-" . $thisM . "-" . $thisD;
    $idbl = $thisM . $thisY;
    $tHr = 0;
    $tBl = 0;
    $TotalRHr = 0;

    $_SESSION[$session.'idhr'] = $idhr;

    $getSRBl = $API->comm("/system/script/print", array(
      "?owner" => "$idbl",
    ));
    $TotalRBl = count($getSRBl);
    $_SESSION[$session.'totalBl'] = $TotalRBl;
    foreach($getSRBl as $row){
      $rowName = tikras_array_get($row, 'name', '');
      $rowParts = explode("-|-", $rowName);
      $rowDate = tikras_array_get($rowParts, 0, '');
      $rowPrice = tikras_array_get($rowParts, 3, 0);
      if($rowDate == $idhr){
         $tHr += (float) $rowPrice;
         $TotalRHr += count((array) tikras_array_get($row, 'source', array())); /*Modif line add (array) by github https://github.com/MasKawer*/
 
       }
       $tBl += (float) $rowPrice;

      if($TotalRHr == ""){
        $TotalRHr = "0";
        $_SESSION[$session.'totalHr'] = "0";
      }else{
        $_SESSION[$session.'totalHr'] = $TotalRHr;
      }
      
    }
  }
}
?>

            <div id="r_4" class="row">
              <div <?= $lreport; ?> class="box bmh-75 box-bordered">
                <div class="box-group">
                  <div class="box-group-icon"><i class="fa fa-money"></i></div>
                    <div class="box-group-area">
                      <span >
                        <div id="reloadLreport">
                        <?php 
                          $isIndoCurrency = isset($cekindo['indo']) && is_array($cekindo['indo']) && in_array($currency, $cekindo['indo']);
                          if ($isIndoCurrency) {
                            $dincome = number_format((float)$tHr, 0, ",", ".");
                            $mincome = number_format((float)$tBl, 0, ",", ".");
                            $_SESSION[$session.'dincome'] = $dincome;
                            $_SESSION[$session.'mincome'] = $mincome;
                          }else{
                            $dincome = number_format((float)$tHr, 2);
                            $mincome = number_format((float)$tBl, 2);
                            $_SESSION[$session.'dincome'] = $dincome;
                            $_SESSION[$session.'mincome'] = $mincome;
                          }
                            echo $_income."<br/>" . "
                          ".$_today." " . $TotalRHr . "vcr : " . $currency . " " . $dincome . "<br/>
                          ".$_this_month." " . $TotalRBl . "vcr : " . $currency . " " . $mincome;
                          ?>
                        </div>
                    </span>
                </div>
              </div>
            </div>
            </div>
            
