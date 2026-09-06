<?php
/*
 *		Copyright (C) 2026 TIKRAS IT.
 *
 *		This program is free software; you can redistribute it and/or modify
 *		it under the terms of the GNU General Public License as published by
 *		the Free Software Foundation; either version 2 of the License, or
 *		(at your option) any later version.
 *
 *		This program is distributed in the hope that it will be useful,
 *		but WITHOUT ANY WARRANTY; without even the implied warranty of
 *		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.		See the
 *		GNU General Public License for more details.
 *
 *		You should have received a copy of the GNU General Public License
 *		along with this program.		If not, see <http://www.gnu.org/licenses/>.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);
?>
<!DOCTYPE html>
<html>
	<head>
		<title>TIKRAS IT <?= isset($hotspotname) ? tikras_h($hotspotname) : ''; ?></title>
		<meta charset="utf-8">
		<meta http-equiv="cache-control" content="private" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<!-- Tell the browser to be responsive to screen width -->
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- Theme color -->
		<meta name="theme-color" content="<?= $themecolor ?>" />
		<?php
		/*
		 * Chaque ressource porte le numero de version tire de sa date de
		 * modification. Le serveur peut ainsi la faire conserver longtemps par
		 * le navigateur: une modification change l'adresse, donc le fichier est
		 * recharge aussitot. Sans ce numero, une ressource mise en cache
		 * resterait figee chez l'utilisateur apres une mise a jour.
		 */
		if (!function_exists('tikras_asset')) {
			function tikras_asset($chemin)
			{
				$absolu = dirname(__DIR__) . '/' . ltrim($chemin, '/');
				$version = @filemtime($absolu);
				return $chemin . ($version ? '?v=' . $version : '');
			}
		}
		?>
		<!-- Font Awesome -->
		<link rel="stylesheet" type="text/css" href="<?= tikras_asset('css/font-awesome/css/font-awesome.min.css'); ?>" />
		<!-- TIKRAS IT UI -->
		<link rel="stylesheet" href="<?= tikras_asset('css/mikhmon-ui.' . $theme . '.min.css'); ?>">
		<!-- Custom improvements -->
		<link rel="stylesheet" href="<?= tikras_asset('css/mikhmon-custom.css'); ?>">
		<link rel="stylesheet" href="<?= tikras_asset('css/tikras-modern.css'); ?>">
		<link rel="stylesheet" href="<?= tikras_asset('css/tikras-oneui.css'); ?>">
		<link rel="stylesheet" href="<?= tikras_asset('css/tikras-flux.css'); ?>">
		<!-- favicon -->
		<link rel="icon" href="./img/favicon.png" />
		<!-- jQuery -->
		<script src="<?= tikras_asset('js/jquery.min.js'); ?>"></script>
		<!-- pace -->
		<link href="<?= tikras_asset('css/pace.' . $theme . '.css'); ?>" rel="stylesheet" />
		<script src="<?= tikras_asset('js/pace.min.js'); ?>"></script>

		
	</head>
	<?php $bodyTheme = preg_replace('/[^a-z0-9_-]/i', '', (string) $theme); ?>
	<body data-theme="<?= $bodyTheme; ?>">
		<div class="wrapper">

			
