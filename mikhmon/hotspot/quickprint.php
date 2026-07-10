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
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
// array color
  $color = array('1' => 'bg-blue', 'bg-indigo', 'bg-purple', 'bg-pink', 'bg-red', 'bg-yellow', 'bg-green', 'bg-teal', 'bg-cyan', 'bg-grey', 'bg-light-blue');

  ?>
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
	<h3><i class=" fa fa-print"></i> <?= $_quick_print ?> &nbsp;&nbsp; | &nbsp;&nbsp;<a class="pointer" onclick="location.href='./?hotspot=list-quick-print&session=<?= $session ?>';"  title="Quick Print List"><i class="fa fa-list"></i> List</a></h3>
</div>
<div class="card-body">
<div class="overflow" style="max-height: 80vh">	
<div class="row">
<?php
// get quick print
$getquickprint = $API->comm("/system/script/print", array("?comment" => "QuickPrintMikhmon"));
$TotalReg = count($getquickprint);
for ($i = 0; $i < $TotalReg; $i++) {
  $quickprintdetails = $getquickprint[$i];
  $qpname = tikras_array_get($quickprintdetails, 'name', '');
  $qpid = tikras_array_get($quickprintdetails, '.id', '');
  $quickprintsource = explode("#", tikras_array_get($quickprintdetails, 'source', ''));
  $package = tikras_array_get($quickprintsource, 1, '');
  $server = tikras_array_get($quickprintsource, 2, '');
  $usermode = tikras_array_get($quickprintsource, 3, '');
  $userlength = tikras_array_get($quickprintsource, 4, '');
  $prefix = tikras_array_get($quickprintsource, 5, '');
  $char = tikras_array_get($quickprintsource, 6, '');
  $profile = tikras_array_get($quickprintsource, 7, '');
  $timelimit = tikras_array_get($quickprintsource, 8, '');
  $datalimit = tikras_array_get($quickprintsource, 9, '0');
  $comment = tikras_array_get($quickprintsource, 10, '');
  $validity = tikras_array_get($quickprintsource, 11, '');
  $priceParts = explode("_", tikras_array_get($quickprintsource, 12, '0_0'), 2);
  $getprice = tikras_array_get($priceParts, 0, '0');
  $getsprice = tikras_array_get($priceParts, 1, '0');
  $userlock = tikras_array_get($quickprintsource, 13, '');
  if ($currency == in_array($currency, $cekindo['indo'])) {
    $price = $currency . " " . number_format((float)$getprice, 0, ",", ".");
    $sprice = $currency . " " . number_format((float)$getsprice, 0, ",", ".");
} else {
    $price = $currency . " " . number_format((float)$getprice);
    $sprice = $currency . " " . number_format((float)$getsprice);
}
  ?>
	     <div class="col-4">
        <div id='./hotspot/quickuser.php?quickprint=<?= $qpname ?>&session=<?= $session; ?>' class="quick pointer box bmh-75 box-bordered <?= $color[rand(1, 11)]; ?>" title='<?= $_print.' '.$_package.' '. $package; ?>'>
          <div class="box-group">
            <div class="box-group-icon">
            	<i class="fa fa-print"></i>
            </div>
              <div class="box-group-area">
                <h3 ><?= $_package ?> : <?= $package; ?> <br></h3>
                <span><?= $_time_limit ?>  : <?= $timelimit ?> | <?= $_data_limit ?>  : <?= formatBytes($datalimit, 2) ?> <br> <?= $_validity ?>  : <?= $validity ?> | <?= $_price ?>  : <?= $price ?> | <?= $_selling_price ?>  : <?= $sprice ?></span>
              </div>
            </div>
            
          </div>
        </div>
        <?php 
      }
    }
    ?>
    </div>
    </div>
</div>
</div>
</div>
</div>

<script>
$(document).ready(function(){
  $(".quick").click(function(){

    loadpage(this.id);
    
  });

});
</script>
