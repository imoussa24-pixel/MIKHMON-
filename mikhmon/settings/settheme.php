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
$urlParts = explode("&set-theme", $currentUrl, 2);
$url2 = $urlParts[0];

$gettheme = tikras_get('set-theme');
$mtheme = array(
    "light",
    "dark",
);
$theme_color = array(
    "#0b63ff",
    "#0b63ff",
);

if (empty($gettheme) && tikras_cookie('tikras_theme') != "" && in_array(tikras_cookie('tikras_theme'), $mtheme)) {
    $cookiethemenum = array_search(tikras_cookie('tikras_theme'), $mtheme);
    $_SESSION['theme'] = tikras_cookie('tikras_theme');
    $_SESSION['themecolor'] = $theme_color[$cookiethemenum];
}


if (empty($gettheme)) {  

} else {
    if (in_array($gettheme, $mtheme)) {
        $themenum = array_search($gettheme, $mtheme);
        $getthemecolor = $theme_color[$themenum];
        $gen = '<?php $theme="' . $gettheme . '"; $themecolor="'.$getthemecolor.'";?>';
        $stheme = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'theme.php';
        @file_put_contents($stheme, $gen, LOCK_EX);
        setcookie('tikras_theme', $gettheme, time() + 31536000, '/');
        $_SESSION['theme'] = $gettheme;
        $_SESSION['themecolor'] = $getthemecolor;
        include_once('./include/headhtml.php');
        echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>Chargement du mode '.tikras_h($gettheme).'...</h3></center>';
        tikras_redirect($url2);
        
    } else {
        include_once('./include/headhtml.php');
        echo '<center><div style="padding-top:10%;"><i class="fa fa-circle-o-notch fa-spin" style="font-size:40px"></i></div><h3>Mode '.tikras_h($gettheme).' introuvable...</h3></center>';
        tikras_redirect($url2);
    }
}

?>
