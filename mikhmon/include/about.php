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
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {
}
?>
<style>
.iFWrapper {
	position: relative;
	padding-bottom: 56.25%; /* 16:9 */
	padding-top: 25px;
	height: 0;
}
.iFWrapper iframe {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
  height: 100%;
  border :none;
}
</style>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-info-circle"></i> About</h3>
      </div>
      <div class="card-body">
        <h3>TIKRAS IT V<?= $_SESSION['v']; ?></h3>
<p>
  Plateforme de gestion hotspot, PPP et MikroTik personnalisee pour une exploitation professionnelle.
</p>
<p>
  <ul>
    <li>
      Author : TIKRAS IT
    </li>
    <li>
      API Class : <a href="https://github.com/BenMenking/routeros-api">routeros-api</a>
    </li>
    <li>
      Branding : TIKRAS IT
    </li>
  </ul>
</p>
<p>
  Merci a tous ceux qui soutiennent le deploiement et l'exploitation de TIKRAS IT.
</p>
<div>
    <i>Copyright &copy; <i> 2026 TIKRAS IT</i></i>
</div>
</div>
</div>
</div>
<div class="col-12">
<div class="card">
  <div class="card-header">
  <h3><i class="fa fa-info-circle"></i> Changelog</h3>
  </div>
  <div class="card-body">
  <div class="iFWrapper">
    <iframe src="https://laksa19.github.io/mikhmonv3" ></iframe>
  </div>
  </div>
</div>
</div>
</div>
