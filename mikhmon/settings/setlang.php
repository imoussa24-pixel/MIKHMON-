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

// check url
$currentUrl = isset($url) ? $url : tikras_server('REQUEST_URI');
$urlParts = explode("&setlang", $currentUrl, 2);
$url2 = $urlParts[0];

$getlang = tikras_get('setlang');

if (empty($getlang)) {

} else {
    if (!empty($isocodelang[$getlang])) {
        include_once('./include/headhtml.php');
        $gen = '<?php $langid="' . $getlang . '";?>';
        $slang = './include/lang.php';
        file_put_contents($slang, $gen, LOCK_EX);
        $_SESSION['lang'] = $getlang;
        echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>Load '.tikras_h($getlang).' lang...</h3></center>';
        tikras_redirect($url2);
        
    } else {
        include_once('./include/headhtml.php');
        echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>'.tikras_h($getlang).' lang not found...</h3></center>';
        tikras_redirect($url2);
    }
}

?>
