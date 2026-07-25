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
include_once(dirname(__DIR__) . '/lib/tikras_ui.php');


?>

<script>
document.body.className = (document.body.className ? document.body.className + ' ' : '') + 'login-screen';
</script>
<div class="tikras-login-shell">
  <div class="tikras-login-card">
    <div class="tikras-login-brand">
      <span class="tikras-login-mark"><i class="fa fa-lock"></i></span>
      <div>
        <h1>TIKRAS IT</h1>
        <p>Network Manager</p>
      </div>
    </div>
    <form class="tikras-login-form" autocomplete="off" action="" method="post">
      <input class="form-control" type="text" name="user" id="_username" placeholder="Username" required="1" autofocus>
      <input class="form-control" type="password" name="pass" placeholder="Password" required="1">
      <input class="tikras-login-submit" type="submit" name="login" value="<?= tikras_h($_please_login); ?>">
      <?= isset($error) ? $error : ''; ?>
    </form>
  </div>
</div>

</body>
</html>
