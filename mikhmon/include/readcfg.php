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
if (substr(tikras_server("REQUEST_URI"), -11) == "readcfg.php") {
    header("Location:./");
};
// read config

// Serveur en ligne: si include/config.php est absent (secrets non versionnes),
// la liste des routeurs vit dans le stockage persistant (TIKRAS_DATA_DIR).
if (!isset($data) || !is_array($data) || count($data) < 1) {
    if (!function_exists('tikras_config_apply_local')) {
        require_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
    }
    $data = array();
    tikras_config_apply_local($data);
}

if (!function_exists('tikras_cfg_value')) {
    function tikras_cfg_value($data, $session, $index, $separator, $default)
    {
        if (!is_array($data) || !isset($data[$session]) || !isset($data[$session][$index])) {
            return $default;
        }
        $parts = explode($separator, (string) $data[$session][$index], 2);
        return isset($parts[1]) ? $parts[1] : $default;
    }
}

$iphost = tikras_cfg_value($data, $session, 1, '!', '');
$userhost = tikras_cfg_value($data, $session, 2, '@|@', '');
$passwdhost = tikras_cfg_value($data, $session, 3, '#|#', '');
$hotspotname = tikras_cfg_value($data, $session, 4, '%', '');
$dnsname = tikras_cfg_value($data, $session, 5, '^', '');
$currency = tikras_cfg_value($data, $session, 6, '&', '');
$areload = tikras_cfg_value($data, $session, 7, '*', '10');
$iface = tikras_cfg_value($data, $session, 8, '(', '1');
$infolp = tikras_cfg_value($data, $session, 9, ')', '');
$idleto = tikras_cfg_value($data, $session, 10, '=', '10');
$sesname = tikras_cfg_value($data, $session, 10, '+', $session);
$useradm = tikras_cfg_value($data, 'mikhmon', 1, '<|<', 'admin');
$passadm = tikras_cfg_value($data, 'mikhmon', 2, '>|>', '');
$livereport = tikras_cfg_value($data, $session, 11, '@!@', 'disable');

$cekindo['indo'] = array(
    'RP', 'Rp', 'rp', 'IDR', 'idr', 'RP.', 'Rp.', 'rp.', 'IDR.', 'idr.',
);

