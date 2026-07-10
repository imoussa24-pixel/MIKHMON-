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

  if (tikras_has_post('submit')) {
    include_once(dirname(__DIR__) . '/lib/tikras_routeros.php');
    $API = tikras_routeros_create();
    if (tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
      $API->write('/system/reboot');
      $API->read();
    }
    session_destroy();
    tikras_redirect('./admin.php?id=login');
  }
}
?>
<div style="padding-top:10%;" class="register-box">
  <div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa fa-power-off"></i> Reboot MikroTik</h3>
    </div>
  	<div class="card-body text-center">
  		<form action="" method="post" enctype="multipart/form-data">
        <div>
          <h3><?= $_reboot.' '. $session; ?>?</h3>
        </div>
  	  <button class="btn bg-warning" type="submit" title="Yes" name="submit"><?= $_yes ?></button>
      <a class="btn bg-primary" href="./?hotspot=dashboard&session=<?= $session; ?>" title="No"> <?= $_no ?> </a>
      
    </form>
  </div>
</div>
</div>
