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

ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {
	$qpid = tikras_get('qpid');
	$rem = tikras_has_get('remove');
	$charup = array(
		"lower" => "abcd",
		"upper" => " ABCD",
		"upplow" => " aBcD",
		"mix" => " 5ab2c34d",
		"mix1" => " 5AB2C34D",
		"mix2" => "5aB2c34D",
	);

	$charvc = array(
		"lower" => " abcd2345",
		"upper" => " ABCD2345",
		"upplow" => " aBcD2345",
		"num" => " 1234",
	);

if($qpid != "" && $rem){
	$API->comm("/system/script/remove", array(
		".id" => "$qpid",
));
tikras_redirect('./?hotspot=list-quick-print&session=' . $session);
}	

	// get quick print
$package = "";
$server = "all";
$usermode = "up";
$userlength = "4";
$prefix = "";
$char = "mix";
$profile = "";
$timelimit = "";
$datalimit = "0";
$comment = "";
if ($qpid != "") {
  $getquickprint = $API->comm("/system/script/print", array("?.id" => "$qpid"));
  $quickprintdetails = isset($getquickprint[0]) ? $getquickprint[0] : array();
  $qpid = tikras_array_get($quickprintdetails, '.id', $qpid);
  $quickprintsource = explode("#", tikras_array_get($quickprintdetails, 'source', ''));
  $package = tikras_array_get($quickprintsource, 1, "");
  $server = tikras_array_get($quickprintsource, 2, "all");
  $usermode = tikras_array_get($quickprintsource, 3, "up");
  $userlength = tikras_array_get($quickprintsource, 4, "4");
  $prefix = tikras_array_get($quickprintsource, 5, "");
  $char = tikras_array_get($quickprintsource, 6, "mix");
  $profile = tikras_array_get($quickprintsource, 7, "");
  $timelimit = tikras_array_get($quickprintsource, 8, "");
  $datalimit = tikras_array_get($quickprintsource, 9, "0");
	$comment = tikras_array_get($quickprintsource, 10, "");
}
	if($usermode == "up"){
		$tusermode =  $_user_pass;
		$tchar = isset($charup[$char]) ? $charup[$char] : "";
	}elseif($usermode == "vc"){
		$tusermode =  $_user_user;
		$tchar = isset($charvc[$char]) ? $charvc[$char] : "";
	}
	if (substr(formatBytes2($datalimit, 2), -2) == "MB") {
		$udatalimit = $datalimit / 1048576;
		$xdatalimit = 1048576;
    $MG = "MB";
  } elseif (substr(formatBytes2($datalimit, 2), -2) == "GB") {
		$udatalimit = $datalimit / 1073741824;
		$xdatalimit = 1073741824;
    $MG = "GB";
  } else{
		$udatalimit = "";
		$xdatalimit = 1048576;
    $MG = "MB";
  }

	// array color
    $color = array('1' => 'bg-blue', 'bg-indigo', 'bg-purple', 'bg-pink', 'bg-red', 'bg-yellow', 'bg-green', 'bg-teal', 'bg-cyan', 'bg-grey', 'bg-light-blue');

    $srvlist = $API->comm("/ip/hotspot/print");
    $getprofile = $API->comm("/ip/hotspot/user/profile/print");
	

	if (tikras_has_post('name')) {
        $name = tikras_post('name');
        $sname = "Quick_Print_".(preg_replace('/\s+/', '-', tikras_post('name')));
		$server = tikras_post('server');
		$user = tikras_post('user');
		$userl = tikras_post('userl');
		$prefix = tikras_post('prefix');
		$char = tikras_post('char');
		$profile = tikras_post('profile');
		$timelimit = tikras_post('timelimit');
		$datalimit = tikras_post('datalimit');
		$adcomment = tikras_post('adcomment');
		$mbgb = tikras_post('mbgb');
		if ($timelimit == "") {
			$timelimit = "0";
		} else {
			$timelimit = $timelimit;
		}
		if ($datalimit == "") {
			$datalimit = "0";
		} else {
			$datalimit = $datalimit * $mbgb;
		}
		if ($adcomment == "") {
			$adcomment = "";
		} else {
			$adcomment = $adcomment;
		}
		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$profile"));
		$profileDetails = isset($getprofile[0]) ? $getprofile[0] : array();
		$ponlogin = tikras_array_get($profileDetails, 'on-login', '');
		$onLoginParts = explode(",", $ponlogin);
		$getvalid = tikras_array_get($onLoginParts, 3, "");
		$getprice = tikras_array_get($onLoginParts, 2, "0");
		$getsprice = tikras_array_get($onLoginParts, 4, "0");
		$getlock = tikras_array_get($onLoginParts, 6, "");

        $source = '#'.$name.'#'.$server.'#'.$user.'#'.$userl.'#'.$prefix.'#'.$char.'#'.$profile.'#'.$timelimit.'#'.$datalimit.'#'.$adcomment.'#'.$getvalid.'#'.$getprice.'_'.$getsprice.'#'.$getlock;

		if ($qpid != ""){
			$API->comm("/system/script/set", array(
				".id" => "$qpid",
				"name" => "$sname",
				"source" => "$source",
				"comment" => "QuickPrintMikhmon",
		));
		}else{
        $API->comm("/system/script/add", array(
            "name" => "$sname",
            "source" => "$source",
            "comment" => "QuickPrintMikhmon",
				));
			}

		tikras_redirect('./?hotspot=list-quick-print&session=' . $session);
		
	}



}
?>
<div class="row">
	
<div class="col-4">
<div class="card box-bordered">
	<div class="card-header">
	<h3><i class="fa fa-ticket"></i> <?php if($qpid != ""){echo $_edit;}else{echo $_add;} echo ' '. $_quick_print ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3> 
	</div>
	<div class="card-body">
<form autocomplete="off" method="post" action="">
	<div>
<?php if($qpid != ""){echo "
		<a class='btn bg-warning' href='./?hotspot=list-quick-print&session=".$session."'> <i class='fa fa-close'></i> ".$_cancel."</a>";
}else{
	echo "<a class='btn bg-warning' href='./?hotspot=quick-print&session=".$session."'> <i class='fa fa-close'></i> ".$_close."</a>";
} ?>

    <button type="submit" name="save" onclick="loader()" class="btn bg-primary" title="Generate User"> <i class="fa fa-save"></i> <?= $_save ?></button>
</div>
<table class="table">
  <tr>
    <td class="align-middle"><?= $_name ?></td><td><div><input class="form-control " type="text" name="name" value="<?= $package ?>" required="1"></div></td>
  </tr>
  <tr>
    <td class="align-middle">Server</td>
    <td>
		<select class="form-control " name="server" required="1">
			<?php if($qpid != ""){echo '<option>'. $server .'</option>';}else{echo '<option>all</option>';} ?>
				<?php $TotalReg = count($srvlist);
			for ($i = 0; $i < $TotalReg; $i++) {
				echo "<option>" . tikras_array_get($srvlist[$i], 'name', '') . "</option>";
			}
			?>
		</select>
	</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_user_mode ?></td><td>
			<select class="form-control " id="user" name="user" required="1">
				<?php if($qpid != ""){echo '<option value="'.$usermode.'">'.$tusermode.'</option>';}?>
				<option value="up"><?= $_user_pass ?></option>
				<option value="vc"><?= $_user_user ?></option>
			</select>
		</td>
	</tr>
  <tr>
    <td class="align-middle"><?= $_user_length ?></td><td>
      <select class="form-control " id="userl" name="userl" required="1">
			<?php if($qpid != ""){echo '<option>'.$userlength.'</option>';}?>
				<option>3</option>
				<option selected>4</option>
				<option>5</option>
				<option>6</option>
				<option>7</option>
				<option>8</option>
			</select>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_prefix ?></td><td><input class="form-control " type="text" size="4" maxlength="4" autocomplete="off" name="prefix" value="<?= $prefix ?>"></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_character ?></td><td>
      <select class="form-control " name="char" required="1">
			<?php if($qpid != ""){echo '<option value="'.$char.'">'.$_random.' '.$tchar.'</option>';}?>
				<option value="lower">Lettres minuscules — abcd</option>
				<option value="upper">Lettres majuscules — ABCD</option>
				<option value="upplow">Lettres mélangées — aBcD</option>
				<option value="mix">Minuscules + chiffres — abcd2345</option>
				<option value="mix1">Majuscules + chiffres — ABCD2345</option>
				<option value="mix2">Mélangé + chiffres — aBcD2345</option>
				<option value="num">Chiffres uniquement — 2345</option>
			</select>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_profile ?></td><td>
			<select class="form-control " onchange="GetVP();" id="uprof" name="profile" required="1">
				<?php if ($qpid != "") {
				echo "<option>" . $profile . "</option>";
			} else {
			}
			$TotalReg = count($getprofile);
			for ($i = 0; $i < $TotalReg; $i++) {
				echo "<option>" . tikras_array_get($getprofile[$i], 'name', '') . "</option>";
			}
			?>
			</select>
		</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_time_limit ?></td><td><input class="form-control " type="text" size="4" autocomplete="off" name="timelimit" value="<?= $timelimit ?>"></td>
  </tr>
	<tr>
    <td class="align-middle"><?= $_data_limit ?></td><td>
      <div class="input-group">
      	<div class="input-group-8 col-box-9">
        	<input class="group-item group-item-l" type="number" min="0" max="9999" name="datalimit" value="<?= $udatalimit; ?>">
    	</div>
          <div class="input-group-4 col-box-3">
							<select style="padding:4.2px;" class="group-item group-item-r" name="mbgb" required="1">
							<?php if($qpid != ""){echo '<option value="'.$xdatalimit.'">'.$MG.'</option>';}?>
				        <option value=1048576>MB</option>
				        <option value=1073741824>GB</option>
			        </select>
          </div>
      </div>
    </td>
  </tr>
	<tr>
    <td class="align-middle"><?= $_comment ?></td><td><input class="form-control " type="text" title="No special characters" id="comment" autocomplete="off" name="adcomment" value="<?= $comment ?>"></td>
  </tr>
   <tr >
    <td  colspan="4" class="align-middle w-12"  id="GetValidPrice">
    	<?php if ($genprof != "") {
					echo $ValidPrice;
				} ?>
    </td>
  </tr>
</table>
</form>
</div>
</div>
</div>

<div class="col-8">
	<div class="card">
		<div class="card-header">
			<h3><i class="fa fa-ticket"></i> <?= $_package.' '.  $_quick_print ?></h3>
		</div>
		<div class="card-body">
            <div class="row">
                <div class="overflow box-bordered">
                <table class="table table-bordered table-hover text-nowrap">
                    <tr>
										<th></th>	
                    <th><?= $_package ?></th>
                    <th>Server</th>
                    <th><?= $_user_mode ?></th>
                    <th><?= $_user_length ?></th>
                    <th><?= $_prefix ?></th>
                    <th><?= $_profile ?></th>
                    <th><?= $_time_limit ?></th>
                    <th><?= $_data_limit ?></th>
                    <th><?= $_validity ?></th>
					<th><?= $_price ?></th>
					<th><?= $_selling_price ?></th>
                    <th><?= $_lock_user ?></th>
                    <th><?= $_comment ?></th>
                    </tr>
<?php
// get quick print
$getquickprint = $API->comm("/system/script/print", array("?comment" => "QuickPrintMikhmon"));
$TotalReg = count($getquickprint);
for ($i = 0; $i < $TotalReg; $i++) {
  $quickprintdetails = $getquickprint[$i];
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
    $price = $currency . " " . number_format($getprice, 0, ",", ".");
    $sprice = $currency . " " . number_format($getsprice, 0, ",", ".");
} else {
    $price = $currency . " " . number_format($getprice);
    $sprice = $currency . " " . number_format($getsprice);
}
?>
<tr>
<td><i class='fa fa-minus-square text-danger pointer' onclick="if(confirm('Are you sure to delete (<?= $package; ?>)?')){loadpage('./?hotspot=list-quick-print&remove&qpid=<?= $qpid; ?>&session=<?= $session; ?>')}else{}" title='Remove <?= $package; ?>'></i>&nbsp</td>	
<td><a title="Edit <?= $_package.' '. $package; ?>" href="./?hotspot=list-quick-print&qpid=<?= $qpid; ?>&session=<?= $session; ?>"><i class="fa fa-edit"></i> <?= $package; ?></a></td>
<td><?= $server ?></td>
<td><?= $usermode ?></td>
<td><?= $userlength ?></td>
<td><?= $prefix ?></td>
<td><?= $profile ?></td>
<td><?= $timelimit ?></td>
<td><?= formatBytes($datalimit, 2) ?></td>
<td><?= $validity ?></td>
<td><?= $price ?></td>
<td><?= $sprice ?></td>
<td><?= $userlock ?></td>
<td><?= $comment ?></td>
              </tr>
        <?php 
      }
    ?>
    </table>
    </div>
    </div>
</div>
</div>
</div>
<script>
// get valid $ price
function GetVP(){
  var prof = document.getElementById('uprof').value;
  var url = "./process/getvalidprice.php?name=";
  var session = "&session=<?= $session; ?>"
  var getvalidprice = url+prof+session
  $("#GetValidPrice").load(getvalidprice);
}
</script>
</div>
