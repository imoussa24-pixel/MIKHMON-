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
require_once(dirname(__DIR__) . '/lib/tikras_core.php');
require_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
tikras_start_session();
tikras_bootstrap_errors(false);
tikras_session_defaults(array(
	'timezone' => date_default_timezone_get(),
	'ubp' => '',
	'vcr' => '',
));

ini_set('max_execution_time', 300);

if (!isset($_SESSION["mikhmon"])) {
	header("Location:../admin.php?id=login");
} else {
// time zone
tikras_apply_session_timezone();
	$ticketRoamingPage = isset($tikrasTicketRoamingPage) && $tikrasTicketRoamingPage;
	$ticketGenerateRoute = $ticketRoamingPage ? "generate-roaming" : "generate";
	$ticketGenerateTitle = $ticketRoamingPage ? "Générer des tickets roaming" : $_generate_user;
	$ticketPreviewTitle = $ticketRoamingPage ? "Aperçu roaming" : "Aperçu génération";

	if (!function_exists('tikras_ticket_h')) {
		function tikras_ticket_h($value)
		{
			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		}
	}

	if (!function_exists('tikras_ticket_clean_email')) {
		function tikras_ticket_clean_email($value)
		{
			$value = trim((string) $value);
			return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : "";
		}
	}

	if (!function_exists('tikras_ticket_clean_phone')) {
		function tikras_ticket_clean_phone($value)
		{
			$value = preg_replace('/[^0-9]/', '', (string) $value);
			return substr($value, 0, 20);
		}
	}

	if (!function_exists('tikras_ticket_clean_channel')) {
		function tikras_ticket_clean_channel($value)
		{
			$value = trim((string) $value);
			if ($value == "email" || $value == "telegram") {
				return $value;
			}
			return "whatsapp";
		}
	}

	if (!function_exists('tikras_ticket_clean_telegram_chat')) {
		function tikras_ticket_clean_telegram_chat($value)
		{
			$value = trim((string) $value);
			$value = str_replace(array("\r", "\n", "\t", " "), "", $value);
			if ($value != "" && preg_match('/^-?[0-9A-Za-z_@:-]+$/', $value)) {
				return substr($value, 0, 80);
			}
			return "";
		}
	}

	if (!function_exists('tikras_ticket_build_share_text')) {
		function tikras_ticket_build_share_text($tickets, $hotspotname, $dnsname, $profile, $validity, $timelimit, $datalimit, $price, $sellingPrice)
		{
			if (!is_array($tickets) || count($tickets) < 1) {
				return "";
			}

			$lines = array();
			$lines[] = $hotspotname;
			$lines[] = "Tickets générés";
			$lines[] = "Profil : " . $profile;
			if ($validity != "" && $validity != "-") {
				$lines[] = "Validité : " . $validity;
			}
			if ($timelimit != "" && $timelimit != "-") {
				$lines[] = "Temps : " . $timelimit;
			}
			if ($datalimit != "" && $datalimit != "-") {
				$lines[] = "Données : " . $datalimit;
			}
			if ($sellingPrice != "" && $sellingPrice != "-") {
				$lines[] = "Prix : " . $sellingPrice;
			} elseif ($price != "" && $price != "-") {
				$lines[] = "Prix : " . $price;
			}
			if ($dnsname != "") {
				$lines[] = "Login : http://" . $dnsname;
			}
			$lines[] = "";

			foreach ($tickets as $index => $ticket) {
				$username = isset($ticket['username']) ? $ticket['username'] : "";
				$password = isset($ticket['password']) ? $ticket['password'] : "";
				if ($username == "") {
					continue;
				}
				if ($username == $password || $password == "") {
					$lines[] = ($index + 1) . ". Voucher : " . $username;
				} else {
					$lines[] = ($index + 1) . ". User : " . $username . " | Pass : " . $password;
				}
			}

			return implode("\r\n", $lines);
		}
	}

	if (!function_exists('tikras_ticket_part')) {
		function tikras_ticket_part($value, $separator, $index, $default)
		{
			$parts = explode($separator, (string) $value);
			return isset($parts[$index]) ? $parts[$index] : $default;
		}
	}

	if (!function_exists('tikras_ticket_temp_file')) {
		function tikras_ticket_temp_file()
		{
			return tikras_config_local_dir() . DIRECTORY_SEPARATOR . 'voucher-temp.php';
		}
	}

	if (!function_exists('tikras_ticket_legacy_temp_file')) {
		function tikras_ticket_legacy_temp_file()
		{
			return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'voucher' . DIRECTORY_SEPARATOR . 'temp.php';
		}
	}

	if (!function_exists('tikras_ticket_read_temp_file')) {
		function tikras_ticket_read_temp_file()
		{
			$temp = tikras_ticket_temp_file();
			if (is_file($temp)) {
				return $temp;
			}
			return tikras_ticket_legacy_temp_file();
		}
	}

	if (!function_exists('tikras_ticket_write_temp')) {
		function tikras_ticket_write_temp($content)
		{
			$file = tikras_ticket_temp_file();
			$dir = dirname($file);
			if (!is_dir($dir)) {
				@mkdir($dir, 0777, true);
			}

			$tmp = $file . '.tmp-' . str_replace('.', '-', uniqid('', true));
			$written = @file_put_contents($tmp, $content);
			if ($written !== false) {
				if (@rename($tmp, $file)) {
					return true;
				}
				@unlink($file);
				if (@rename($tmp, $file)) {
					return true;
				}
			}

			@unlink($tmp);
			return @file_put_contents($file, $content) !== false;
		}
	}

	if (!function_exists('tikras_ticket_random_chars')) {
		function tikras_ticket_random_chars($length, $chars)
		{
			$length = max(1, (int) $length);
			$chars = (string) $chars;
			$count = strlen($chars);
			$value = "";
			if ($count < 1) {
				return $value;
			}
			// Les codes ont une valeur marchande: rand() est previsible, on tire
			// donc au sort de maniere cryptographique quand c'est possible.
			for ($i = 0; $i < $length; $i++) {
				if (function_exists('random_int')) {
					try {
						$index = random_int(0, $count - 1);
					} catch (Exception $e) {
						$index = rand(0, $count - 1);
					}
				} else {
					$index = rand(0, $count - 1);
				}
				$value .= $chars[$index];
			}
			return $value;
		}
	}

	if (!function_exists('tikras_ticket_code_chars')) {
		function tikras_ticket_code_chars($char)
		{
			$sets = array(
				"lower" => "abcdefghijkmnprstuvwxyz",
				"upper" => "ABCDEFGHJKLMNPRSTUVWXYZ",
				"upplow" => "ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz",
				"mix" => "23456789abcdefghijkmnprstuvwxyz",
				"mix1" => "23456789ABCDEFGHJKLMNPRSTUVWXYZ",
				"mix2" => "23456789ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz",
				"num" => "23456789",
			);
			return isset($sets[$char]) ? $sets[$char] : $sets["mix"];
		}
	}

	if (!function_exists('tikras_ticket_build_one')) {
		function tikras_ticket_build_one($mode, $length, $char, $prefix)
		{
			$mode = $mode == "vc" ? "vc" : "up";
			$length = max(3, min(8, (int) $length));
			$prefix = substr((string) $prefix, 0, 12);
			if ($mode == "vc") {
				$username = $prefix . tikras_ticket_random_chars($length, tikras_ticket_code_chars($char));
				return array("username" => $username, "password" => $username);
			}
			$username = $prefix . tikras_ticket_random_chars($length, tikras_ticket_code_chars($char));
			$password = tikras_ticket_random_chars($length, "23456789");
			return array("username" => $username, "password" => $password);
		}
	}

	if (!function_exists('tikras_ticket_existing_names')) {
		function tikras_ticket_existing_names($api)
		{
			$names = array();
			if (!is_object($api)) {
				return $names;
			}
			$rows = $api->comm("/ip/hotspot/user/print", array(".proplist" => "name"));
			if (!is_array($rows)) {
				return $names;
			}
			foreach ($rows as $row) {
				if (isset($row['name']) && $row['name'] != "") {
					$names[(string) $row['name']] = true;
				}
			}
			return $names;
		}
	}

	if (!function_exists('tikras_ticket_generate_batch')) {
		function tikras_ticket_generate_batch($qty, $mode, $length, $char, $prefix, $existingNames)
		{
			$qty = max(1, min(3000, (int) $qty));
			$length = max(3, min(8, (int) $length));
			$seen = is_array($existingNames) ? $existingNames : array();
			$tickets = array();
			$attempts = 0;
			$maxAttempts = max(200, $qty * 40);

			while (count($tickets) < $qty && $attempts < $maxAttempts) {
				$attempts++;
				$ticket = tikras_ticket_build_one($mode, $length, $char, $prefix);
				$username = isset($ticket['username']) ? (string) $ticket['username'] : "";
				if ($username == "" || isset($seen[$username])) {
					continue;
				}
				$seen[$username] = true;
				$tickets[] = $ticket;
			}

			return array(
				"tickets" => $tickets,
				"requested" => $qty,
				"generated" => count($tickets),
				"attempts" => $attempts,
				"ok" => count($tickets) == $qty,
			);
		}
	}

	include_once('./lib/tikras_roaming.php');
	include_once('./lib/tikras_radius.php');
	$roamingRouterSessions = tikras_roaming_sessions($data);
	$radiusConfig = tikras_radius_config();
	$radiusEnabled = tikras_radius_enabled($radiusConfig);
	$radiusManagerSession = tikras_radius_manager_session($radiusConfig, $session);
	$radiusDefaultEngine = $radiusEnabled ? tikras_radius_clean_engine($radiusConfig['default_engine']) : "api";

	$genprof = tikras_get('genprof');
	$ValidPrice = "";
	if ($genprof != "") {
		$getprofile = $API->comm("/ip/hotspot/user/profile/print", array(
			"?name" => "$genprof",
		));
		if (isset($getprofile[0]['on-login'])) {
			$ponlogin = $getprofile[0]['on-login'];
			$getprice = tikras_ticket_part($ponlogin, ",", 2, "0");
			if ($getprice == "0") {
				$getprice = "";
			}

			$getvalid = tikras_ticket_part($ponlogin, ",", 3, "");

			$getlocku = tikras_ticket_part($ponlogin, ",", 6, "");
			if ($getlocku == "") {
				$getprice = "Disable";
			}

			if ($currency == in_array($currency, $cekindo['indo'])) {
				$getprice = $currency . " " . number_format((float)$getprice, 0, ",", ".");
			} else {
				$getprice = $currency . " " . number_format((float)$getprice);
			}
			$ValidPrice = "<b>Validity : " . $getvalid . " | Price : " . $getprice . " | Lock User : " . $getlocku . "</b>";
		} else {
			$ValidPrice = "<b>Profil introuvable : " . tikras_ticket_h($genprof) . "</b>";
		}
	}

	$srvlist = $API->comm("/ip/hotspot/print");
	$roamingQueueRetry = array("processed" => 0, "results" => array(), "remaining" => 0);
	if (tikras_post('retry_roaming_queue') != "") {
		$roamingQueueRetry = tikras_roaming_retry_queue($data, $session, $API, 5);
	}
	$roamingQueueSummary = tikras_roaming_queue_summary();
	$formQty = tikras_post('qty', "1");
	$formServer = tikras_post('server', "all");
	$formUserMode = tikras_post('user', "up");
	$formUserLength = tikras_post('userl', "4");
	$formChar = tikras_post('char', "lower");
	$formPrefix = tikras_post('prefix', "");
	$formProfile = tikras_post('profile', ($genprof != "" ? $genprof : ""));
	$formTimeLimit = tikras_post('timelimit', "");
	$formDataLimit = tikras_post('datalimit', "0");
	$formMbGb = tikras_post('mbgb', "1048576");
	$formComment = tikras_post('adcomment', "");
	$defaultRoamingMode = $ticketRoamingPage && count($roamingRouterSessions) > 1 ? "all" : "local";
	$formRoamingMode = $ticketRoamingPage ? tikras_roaming_clean_mode(tikras_post('roaming_mode', $defaultRoamingMode)) : "local";
	$formRoamingSessions = $ticketRoamingPage ? tikras_post_array('roaming_sessions') : array();
	$formRoamingSyncProfile = $ticketRoamingPage && (tikras_post('qty') == "" || tikras_post('roaming_sync_profile') == "yes");
	$formRoamingEngine = "api";
	if ($ticketRoamingPage) {
		$postedRoamingEngine = tikras_post('roaming_engine', "");
		$formRoamingEngine = $postedRoamingEngine == "" ? $radiusDefaultEngine : tikras_radius_clean_engine($postedRoamingEngine);
	}
	$formShareChannel = tikras_ticket_clean_channel(tikras_post('share_channel', "whatsapp"));
	$formShareTarget = tikras_post('share_target', "");
	$formAfterGenerate = tikras_post('after_generate', "summary");
	$roamingHealth = array();
	$ticketGenerationError = "";

	if ($ticketRoamingPage && tikras_post('test_roaming') != "" && tikras_post('qty') != "") {
		if ($formRoamingEngine == "radius") {
			$radiusHealth = tikras_radius_manager_status($data, $session, $API);
			$radiusHealth['profile'] = $formProfile;
			$roamingHealth = array($radiusHealth);
		} else {
			$healthTargets = tikras_roaming_target_sessions($data, $session, $formRoamingMode, $formRoamingSessions);
			$healthProfileRow = $API->comm("/ip/hotspot/user/profile/print", array("?name" => "$formProfile"));
			$healthProfilePayload = isset($healthProfileRow[0]) ? tikras_roaming_profile_payload($healthProfileRow[0]) : array();
			$roamingHealth = tikras_roaming_check_targets($data, $healthTargets, $session, $API, array(
				"server" => $formServer,
				"profile" => $formProfile,
				"sync_profile" => $formRoamingSyncProfile ? "yes" : "no",
				"profile_payload" => $healthProfilePayload,
			));
		}
	}

	if (tikras_post('qty') != "" && tikras_post('test_roaming') == "") {
		
		$qty = max(1, min(3000, (int) $formQty));
		$server = $formServer;
		$user = $formUserMode;
		$userl = max(3, min(8, (int) $formUserLength));
		$prefix = $formPrefix;
		$char = $formChar;
		$profile = $formProfile;
		$timelimit = $formTimeLimit;
		$datalimit = $formDataLimit;
		$adcomment = $formComment;
		$mbgb = $formMbGb;
		$shareChannel = $formShareChannel;
		$shareTarget = $formShareTarget;
		$shareEmail = $shareChannel == "email" ? tikras_ticket_clean_email($shareTarget) : "";
		$shareWhatsapp = $shareChannel == "whatsapp" ? tikras_ticket_clean_phone($shareTarget) : "";
		$shareTelegram = $shareChannel == "telegram" ? tikras_ticket_clean_telegram_chat($shareTarget) : "";
		$afterGenerate = $formAfterGenerate;
		$roamingMode = $formRoamingMode;
		$roamingEngine = $formRoamingEngine;
		$roamingTargets = tikras_roaming_target_sessions($data, $session, $roamingMode, $formRoamingSessions);
		if ($roamingEngine == "radius") {
			$roamingTargets = array($radiusManagerSession);
		}
		if ($afterGenerate != "single") {
			$afterGenerate = "summary";
		}
		if (($shareEmail != "" || $shareWhatsapp != "" || $shareTelegram != "" || $shareChannel == "telegram") && $afterGenerate == "single") {
			$afterGenerate = "summary";
		}
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
		$profilePayload = isset($getprofile[0]) ? tikras_roaming_profile_payload($getprofile[0]) : array();
		$syncProfile = $formRoamingSyncProfile ? "yes" : "no";
		if ($roamingEngine == "radius") {
			$syncProfile = "yes";
		}
		$ponlogin = isset($getprofile[0]['on-login']) ? $getprofile[0]['on-login'] : "";
		$getvalid = tikras_ticket_part($ponlogin, ",", 3, "");
		$getprice = tikras_ticket_part($ponlogin, ",", 2, "0");
		$getsprice = tikras_ticket_part($ponlogin, ",", 4, "0");
		$getlock = tikras_ticket_part($ponlogin, ",", 6, "");
		$_SESSION['ubp'] = $profile;
		$commt = $user . "-" . rand(100, 999) . "-" . date("m.d.y") . "-" . $adcomment;
		$gentemp = $commt . "|~" . $profile . "~" . $getvalid . "~" . $getprice . "!".$getsprice."~" . $timelimit . "~" . $datalimit . "~" . $getlock;
		$generatedTickets = array();
		$genmeta = array(
			"channel" => $shareChannel,
			"email" => $shareEmail,
			"whatsapp" => $shareWhatsapp,
			"telegram" => $shareTelegram,
			"qty" => $qty,
		);
		$batchBuild = tikras_ticket_generate_batch($qty, $user, $userl, $char, $prefix, tikras_ticket_existing_names($API));
		$generatedTickets = isset($batchBuild['tickets']) && is_array($batchBuild['tickets']) ? $batchBuild['tickets'] : array();
		$genmeta['requested_qty'] = isset($batchBuild['requested']) ? $batchBuild['requested'] : $qty;
		$genmeta['generated_qty'] = isset($batchBuild['generated']) ? $batchBuild['generated'] : count($generatedTickets);
		$genmeta['generation_attempts'] = isset($batchBuild['attempts']) ? $batchBuild['attempts'] : 0;
		if (!isset($batchBuild['ok']) || !$batchBuild['ok']) {
			$ticketGenerationError = "Impossible de generer " . $qty . " ticket(s) uniques avec ces reglages. Augmentez la longueur ou choisissez un alphabet plus large.";
		}
		if ($ticketGenerationError == "" && $ticketRoamingPage && $roamingEngine == "radius" && !$radiusEnabled) {
			$ticketGenerationError = "Module RADIUS central non actif. Activez-le dans Admin > RADIUS central ou repassez le moteur roaming en copie API.";
		}
		if ($ticketGenerationError == "" && $ticketRoamingPage && $roamingEngine == "radius" && $radiusManagerSession == "") {
			$ticketGenerationError = "Routeur User Manager non configure. Choisissez le serveur central dans Admin > RADIUS central.";
		}

		if ($ticketGenerationError == "") {
			$roamingParams = array(
				"source_session" => $session,
				"tickets_are_new_on_source" => "yes",
				"server" => $server,
				"profile" => $profile,
				"timelimit" => $timelimit,
				"datalimit" => $datalimit,
				"comment" => $commt,
				"validity" => $getvalid,
				"price" => $getprice,
				"selling_price" => $getsprice,
				"currency" => $currency,
				"sync_profile" => $syncProfile,
				"profile_payload" => $profilePayload,
			);
			if ($ticketRoamingPage && $roamingEngine == "radius") {
				$radiusStatus = tikras_radius_sync_tickets($data, $session, $API, $generatedTickets, $roamingParams);
				$roamingSync = array($radiusStatus);
				$roamingQueued = array();
			} else {
				$roamingSync = tikras_roaming_sync_tickets($data, $roamingTargets, $session, $API, $generatedTickets, $roamingParams);
				$roamingQueued = tikras_roaming_queue_failed($session, $session, $roamingSync, $generatedTickets, $roamingParams);
			}
			$genmeta['roaming_engine'] = $roamingEngine;
			$genmeta['roaming_mode'] = $roamingMode;
			$genmeta['roaming_targets'] = $roamingTargets;
			$genmeta['roaming_status'] = $roamingSync;
			$genmeta['roaming_queued'] = $roamingQueued;
			$genmeta['storage'] = tikras_storage_record_ticket_batch($session, $commt, $generatedTickets, $roamingParams, $roamingSync, $genmeta);

			$gen = '<?php $genu="'.encrypt($gentemp).'"; $genlist="'.encrypt(serialize($generatedTickets)).'"; $genmeta="'.encrypt(serialize($genmeta)).'";?>';
			tikras_ticket_write_temp($gen);

			if ($qty < 2 && $afterGenerate == "single") {
				$firstTicket = isset($generatedTickets[0]['username']) ? $generatedTickets[0]['username'] : "";
				tikras_redirect('./?hotspot-user=' . rawurlencode($firstTicket) . '&session=' . $session);
			} else {
				tikras_redirect('./?hotspot-user=' . $ticketGenerateRoute . '&session=' . $session);
			}
		}
	}

	$getprofile = $API->comm("/ip/hotspot/user/profile/print");
	$genu = "";
	$genlist = "";
	$genmeta = "";
	$ticketTempFile = tikras_ticket_read_temp_file();
	if (file_exists($ticketTempFile)) {
		include($ticketTempFile);
	}
	$generatedList = array();
	$generatedMeta = array();
	if (isset($genlist) && $genlist != "") {
		$decodedList = @unserialize(decrypt($genlist));
		if (is_array($decodedList)) {
			$generatedList = $decodedList;
		}
	}
	if (isset($genmeta) && $genmeta != "") {
		$decodedMeta = @unserialize(decrypt($genmeta));
		if (is_array($decodedMeta)) {
			$generatedMeta = $decodedMeta;
		}
	}
	$roamingStatus = array();
	if (isset($generatedMeta['roaming_status']) && is_array($generatedMeta['roaming_status'])) {
		$roamingStatus = $generatedMeta['roaming_status'];
	}
	$generatedRequestedQty = isset($generatedMeta['requested_qty']) ? (int) $generatedMeta['requested_qty'] : (isset($generatedMeta['qty']) ? (int) $generatedMeta['qty'] : count($generatedList));
	$generatedActualQty = count($generatedList);
	$genPlain = $genu != "" ? decrypt($genu) : "";
	$umode = tikras_ticket_part($genPlain, "-", 0, "");
	$ucode = tikras_ticket_part($genPlain, "-", 1, "");
	$udate = tikras_ticket_part($genPlain, "-", 2, "");
	$uprofile = tikras_ticket_part($genPlain, "~", 1, "");
	$uvalid = tikras_ticket_part($genPlain, "~", 2, "");
	$ucommt = tikras_ticket_part($genPlain, "-", 3, "");
	if ($uvalid == "") {
		$uvalid = "-";
	} else {
		$uvalid = $uvalid;
	}
	$pricePart = tikras_ticket_part($genPlain, "~", 3, "0!0");
	$uprice = tikras_ticket_part($pricePart, "!", 0, "0");
	if ($uprice == "0") {
		$uprice = "-";
	} else {
		$uprice = $uprice;
	}
	$suprice = tikras_ticket_part($pricePart, "!", 1, "0");
	if ($suprice == "0") {
		$suprice = "-";
	} else {
		$suprice = $suprice;
	}
	$utlimit = tikras_ticket_part($genPlain, "~", 4, "0");
	if ($utlimit == "0") {
		$utlimit = "-";
	} else {
		$utlimit = $utlimit;
	}
	$udlimit = tikras_ticket_part($genPlain, "~", 5, "0");
	if ($udlimit == "0") {
		$udlimit = "-";
	} else {
		$udlimit = formatBytes($udlimit, 2);
	}
	$ulock = tikras_ticket_part($genPlain, "~", 6, "");
	//$urlprint = "$umode-$ucode-$udate-$ucommt";
	$urlprint = tikras_ticket_part($genPlain, "|", 0, "");
	if ($currency == in_array($currency, $cekindo['indo'])) {
		$uprice = $currency . " " . number_format((float)$uprice, 0, ",", ".");
		$suprice = $currency . " " . number_format((float)$suprice, 0, ",", ".");
	} else {
		$uprice = $currency . " " . number_format((float)$uprice);
		$suprice = $currency . " " . number_format((float)$suprice);

	}

	include_once('./lib/tikras_ticket_pdf.php');
	include_once('./lib/tikras_notify.php');
	$ticketPdfUrl = "";
	$ticketPdfRelative = "";
	$ticketPdfName = "";
	$ticketPdfPath = "";
	$ticketDeliveryStatus = "";
	$ticketDeliveryClass = "bg-info";
	$ticketDeliveryAlreadyDone = isset($generatedMeta['delivery_done']) && $generatedMeta['delivery_done'] == "1";
	if ($ticketDeliveryAlreadyDone && isset($generatedMeta['delivery_status'])) {
		$ticketDeliveryStatus = $generatedMeta['delivery_status'];
		$ticketDeliveryClass = isset($generatedMeta['delivery_class']) ? $generatedMeta['delivery_class'] : "bg-info";
	}
	$ticketShareChannel = isset($generatedMeta['channel']) ? tikras_ticket_clean_channel($generatedMeta['channel']) : "whatsapp";
	$ticketShareEmail = isset($generatedMeta['email']) ? tikras_ticket_clean_email($generatedMeta['email']) : "";
	$ticketShareWhatsapp = isset($generatedMeta['whatsapp']) ? tikras_ticket_clean_phone($generatedMeta['whatsapp']) : "";
	$ticketShareTelegram = isset($generatedMeta['telegram']) ? tikras_ticket_clean_telegram_chat($generatedMeta['telegram']) : "";
	$ticketNeedsPdf = ($ticketShareChannel == "email" && $ticketShareEmail != "") || ($ticketShareChannel == "telegram") || ($ticketShareChannel == "whatsapp" && $ticketShareWhatsapp != "");
	if (count($generatedList) > 0) {
		$ticketPdfName = "tickets-" . preg_replace('/[^a-zA-Z0-9_-]/', '-', $session) . "-" . preg_replace('/[^a-zA-Z0-9_-]/', '-', $urlprint) . ".pdf";
		$ticketPdfRelative = "share/tickets/" . $ticketPdfName;
		$ticketPdfPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "share" . DIRECTORY_SEPARATOR . "tickets" . DIRECTORY_SEPARATOR . $ticketPdfName;
		$ticketPdfMeta = array(
			"hotspotname" => $hotspotname,
			"dnsname" => $dnsname,
			"profile" => $uprofile,
			"validity" => $uvalid == "-" ? "" : $uvalid,
			"timelimit" => $utlimit == "-" ? "" : $utlimit,
			"datalimit" => $udlimit == "-" ? "" : $udlimit,
			"price" => $suprice != "-" ? $suprice : ($uprice == "-" ? "" : $uprice),
		);
		if (is_file($ticketPdfPath) || ($ticketNeedsPdf && tikras_ticket_pdf_generate($ticketPdfPath, $generatedList, $ticketPdfMeta))) {
			$ticketPdfUrl = tikras_ticket_public_url($ticketPdfRelative);
		}
	}

	$ticketShareText = tikras_ticket_build_share_text($generatedList, $hotspotname, $dnsname, $uprofile, $uvalid, $utlimit, $udlimit, $uprice, $suprice);
	$ticketShareMessage = $ticketShareText;
	if ($ticketPdfUrl != "") {
		$ticketShareMessage .= "\r\n\r\nPDF tickets : " . $ticketPdfUrl;
	}
	$ticketShareEncoded = rawurlencode($ticketShareMessage);
	$ticketMailHref = "mailto:" . $ticketShareEmail . "?subject=" . rawurlencode("Tickets " . $hotspotname . " - " . $uprofile) . "&body=" . $ticketShareEncoded;
	$ticketWhatsappHref = $ticketShareWhatsapp != "" ? "https://wa.me/" . $ticketShareWhatsapp . "?text=" . $ticketShareEncoded : "https://wa.me/?text=" . $ticketShareEncoded;
	$ticketTelegramHref = "https://t.me/share/url?url=" . rawurlencode($ticketPdfUrl) . "&text=" . $ticketShareEncoded;
	$ticketShareHref = $ticketShareChannel == "email" ? $ticketMailHref : ($ticketShareChannel == "telegram" ? $ticketTelegramHref : $ticketWhatsappHref);
	$ticketShareLabel = $ticketShareChannel == "email" ? "Envoyer par email" : ($ticketShareChannel == "telegram" ? "Envoyer par Telegram" : "Envoyer par WhatsApp");
	$ticketShareIcon = $ticketShareChannel == "email" ? "fa-envelope" : ($ticketShareChannel == "telegram" ? "fa-telegram" : "fa-whatsapp");
	if (!$ticketDeliveryAlreadyDone && $ticketPdfUrl != "") {
		if ($ticketShareChannel == "email" && $ticketShareEmail != "") {
			$sent = tikras_notify_send_ticket_mail($session, $ticketShareEmail, "Tickets " . $hotspotname . " - " . $uprofile, $ticketShareMessage, $ticketPdfPath, $ticketPdfName);
			$ticketDeliveryStatus = $sent ? "Email envoyé avec le PDF." : "Email non envoyé. Le PDF reste disponible.";
			$ticketDeliveryClass = $sent ? "bg-success" : "bg-warning";
		} elseif ($ticketShareChannel == "telegram") {
			$notifyConfig = tikras_notify_get($session);
			if ($notifyConfig['telegram_bot_token'] == "" || ($ticketShareTelegram == "" && $notifyConfig['telegram_chat_id'] == "")) {
				$ticketDeliveryStatus = "Bot Telegram non configuré. Le lien Telegram reste disponible.";
				$ticketDeliveryClass = "bg-warning";
			} else {
				$sent = tikras_notify_send_telegram($session, $ticketShareMessage, $ticketPdfUrl, $ticketShareTelegram);
				$ticketDeliveryStatus = $sent ? "Telegram envoyé avec le lien PDF." : "Telegram non envoyé. Le PDF reste disponible.";
				$ticketDeliveryClass = $sent ? "bg-success" : "bg-warning";
			}
		} elseif ($ticketShareChannel == "whatsapp" && $ticketShareWhatsapp != "") {
			$notifyConfig = tikras_notify_get($session);
			if ($notifyConfig['whatsapp_api_url'] == "") {
				$ticketDeliveryStatus = "API WhatsApp non configurée. Le lien WhatsApp reste disponible.";
				$ticketDeliveryClass = "bg-warning";
			} else {
				$sent = tikras_notify_send_whatsapp($session, $ticketShareWhatsapp, $ticketShareMessage, $ticketPdfUrl);
				$ticketDeliveryStatus = $sent ? "WhatsApp envoyé avec le lien PDF." : "WhatsApp non envoyé. Le PDF reste disponible.";
				$ticketDeliveryClass = $sent ? "bg-success" : "bg-warning";
			}
		}
		if ($ticketDeliveryStatus != "" && isset($genu) && isset($genlist)) {
			$generatedMeta['delivery_done'] = "1";
			$generatedMeta['delivery_status'] = $ticketDeliveryStatus;
			$generatedMeta['delivery_class'] = $ticketDeliveryClass;
			$generatedMeta['delivery_at'] = date("Y-m-d H:i:s");
			$gen = '<?php $genu="'.$genu.'"; $genlist="'.$genlist.'"; $genmeta="'.encrypt(serialize($generatedMeta)).'";?>';
			tikras_ticket_write_temp($gen);
		}
	}

}
?>
<div class="row tikras-ticket-page<?= $ticketRoamingPage ? ' tikras-ticket-roaming-page' : ''; ?>">
	
<div class="col-8">
<div class="card box-bordered">
	<div class="card-header">
	<h3><i class="fa <?= $ticketRoamingPage ? 'fa-random' : 'fa-user-plus'; ?>"></i> <?= $ticketGenerateTitle ?> <small id="loader" style="display: none;" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></small></h3> 
	</div>
	<div class="card-body">
<form autocomplete="off" method="post" action="" class="tikras-ticket-form">
	<div>
		<?php if ($_SESSION['ubp'] != "") {
		echo "    <a class='btn bg-warning' href='./?hotspot=users&profile=" . $_SESSION['ubp'] . "&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	} elseif ($_SESSION['vcr'] == "active") {
		echo "    <a class='btn bg-warning' href='./?hotspot=users-by-profile&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	} else {
		echo "    <a class='btn bg-warning' href='./?hotspot=users&profile=all&session=" . $session . "'> <i class='fa fa-close'></i> ".$_close."</a>";
	}

	?>
	<a class="btn bg-pink" title="Open User List by Profile 
<?php if ($_SESSION['ubp'] == "") {
	echo "all";
} else {
	echo $uprofile;
} ?>" href="./?hotspot=users&profile=
<?php if ($_SESSION['ubp'] == "") {
	echo "all";
} else {
	echo $uprofile;
} ?>&session=<?= $session; ?>"> <i class="fa fa-users"></i> <?= $_user_list ?></a>
    <?php if ($ticketRoamingPage) { ?>
    <a class="btn bg-secondary" href="./?hotspot-user=generate&session=<?= $session; ?>" title="Génération locale"> <i class="fa fa-user-plus"></i> Génération locale</a>
    <button type="submit" name="test_roaming" value="1" class="btn bg-info" title="Tester le roaming"> <i class="fa fa-heartbeat"></i> Tester roaming</button>
    <?php } else { ?>
    <a class="btn bg-info" href="./?hotspot-user=generate-roaming&session=<?= $session; ?>" title="Tickets roaming"> <i class="fa fa-random"></i> Tickets roaming</a>
    <?php } ?>
    <button type="submit" name="save" onclick="loader()" class="btn bg-primary" title="Generate User"> <i class="fa fa-save"></i> <?= $_generate ?></button>
    <a class="btn bg-secondary" title="Print Default" href="./voucher/print.php?id=<?= $urlprint; ?>&qr=no&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> <?= $_print ?></a>
    <a class="btn bg-danger" title="Print QR" href="./voucher/print.php?id=<?= $urlprint; ?>&qr=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-qrcode"></i> <?= $_print_qr ?></a>
    <a class="btn bg-info" title="Print Small" href="./voucher/print.php?id=<?= $urlprint; ?>&small=yes&session=<?= $session; ?>" target="_blank"> <i class="fa fa-print"></i> <?= $_print_small ?></a>
</div>
<?php if ($ticketGenerationError != "") { ?>
<div class="box bg-warning pd-5 mr-t-10">
	<i class="fa fa-warning"></i> <?= tikras_ticket_h($ticketGenerationError); ?>
</div>
<?php } ?>
<table class="table">
  <tr>
    <td class="tikras-ticket-section" colspan="2"><i class="fa fa-ticket"></i> Création</td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_qty ?></td><td><div><input class="form-control " id="ticketQty" type="number" name="qty" min="1" max="3000" value="<?= tikras_ticket_h($formQty); ?>" required="1"></div></td>
  </tr>
  <tr>
    <td class="align-middle">Serveur</td>
    <td>
		<select class="form-control " id="ticketServer" name="server" required="1">
			<option value="all" <?php if ($formServer == "all") { echo "selected"; } ?>>Tous</option>
				<?php $TotalReg = count($srvlist);
			for ($i = 0; $i < $TotalReg; $i++) {
				$srvName = $srvlist[$i]['name'];
				echo "<option value='" . tikras_ticket_h($srvName) . "'";
				if ($formServer == $srvName) {
					echo " selected";
				}
				echo ">" . tikras_ticket_h($srvName) . "</option>";
			}
			?>
		</select>
	</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_user_mode ?></td><td>
			<select class="form-control " onchange="tikrasUserModeChange();" id="user" name="user" required="1">
				<option value="up" <?php if ($formUserMode == "up") { echo "selected"; } ?>><?= $_user_pass ?></option>
				<option value="vc" <?php if ($formUserMode == "vc") { echo "selected"; } ?>><?= $_user_user ?></option>
			</select>
		</td>
	</tr>
  <tr>
    <td class="align-middle"><?= $_user_length ?></td><td>
      <select class="form-control " id="userl" name="userl" required="1">
        <?php for ($lenOption = 3; $lenOption <= 8; $lenOption++) { ?>
				<option value="<?= $lenOption; ?>" <?php if ((string) $formUserLength == (string) $lenOption) { echo "selected"; } ?>><?= $lenOption; ?></option>
				<?php } ?>
			</select>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_prefix ?></td><td><input class="form-control " id="ticketPrefix" type="text" size="6" maxlength="6" autocomplete="off" name="prefix" value="<?= tikras_ticket_h($formPrefix); ?>"></td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_character ?></td><td>
      <select class="form-control " id="ticketChar" name="char" required="1">
				<option value="lower" <?php if ($formChar == "lower") { echo "selected"; } ?>>Lettres minuscules — abcd</option>
				<option value="upper" <?php if ($formChar == "upper") { echo "selected"; } ?>>Lettres majuscules — ABCD</option>
				<option value="upplow" <?php if ($formChar == "upplow") { echo "selected"; } ?>>Lettres mélangées — aBcD</option>
				<option value="mix" <?php if ($formChar == "mix") { echo "selected"; } ?>>Minuscules + chiffres — abcd2345</option>
				<option value="mix1" <?php if ($formChar == "mix1") { echo "selected"; } ?>>Majuscules + chiffres — ABCD2345</option>
				<option value="mix2" <?php if ($formChar == "mix2") { echo "selected"; } ?>>Mélangé + chiffres — aBcD2345</option>
				<option value="num" <?php if ($formChar == "num") { echo "selected"; } ?>>Chiffres uniquement — 2345</option>
			</select>
			<small class="tikras-field-hint">Les caractères ambigus (0, 1, O, I, l, q) sont toujours exclus pour éviter les erreurs de saisie.</small>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_profile ?></td><td>
			<select class="form-control " onchange="GetVP();" id="uprof" name="profile" required="1">
				<?php if ($genprof != "") {
				echo "<option value='" . tikras_ticket_h($genprof) . "'";
				if ($formProfile == $genprof) {
					echo " selected";
				}
				echo ">" . tikras_ticket_h($genprof) . "</option>";
			} else {
			}
			$TotalReg = count($getprofile);
			for ($i = 0; $i < $TotalReg; $i++) {
				$profileName = $getprofile[$i]['name'];
				echo "<option value='" . tikras_ticket_h($profileName) . "'";
				if ($formProfile == $profileName) {
					echo " selected";
				}
				echo ">" . tikras_ticket_h($profileName) . "</option>";
			}
			?>
			</select>
		</td>
	</tr>
	<tr>
    <td class="align-middle"><?= $_time_limit ?></td><td><input class="form-control " id="ticketTimeLimit" type="text" size="4" autocomplete="off" name="timelimit" value="<?= tikras_ticket_h($formTimeLimit); ?>"></td>
  </tr>
	<tr>
    <td class="align-middle"><?= $_data_limit ?></td><td>
      <div class="input-group">
      	<div class="input-group-10 col-box-9">
        	<input class="group-item group-item-l" id="ticketDataLimit" type="number" min="0" max="9999" name="datalimit" value="<?= tikras_ticket_h($formDataLimit); ?>">
    	</div>
          <div class="input-group-2 col-box-3">
              <select style="padding:4.2px;" class="group-item group-item-r" id="ticketDataUnit" name="mbgb" required="1">
				        <option value="1048576" <?php if ($formMbGb == "1048576") { echo "selected"; } ?>>MB</option>
				        <option value="1073741824" <?php if ($formMbGb == "1073741824") { echo "selected"; } ?>>GB</option>
			        </select>
          </div>
      </div>
    </td>
  </tr>
  <tr>
    <td class="align-middle"><?= $_comment ?></td><td><input class="form-control " type="text" title="No special characters" id="comment" autocomplete="off" name="adcomment" value="<?= tikras_ticket_h($formComment); ?>"></td>
  </tr>
  <?php if (!$ticketRoamingPage) { ?>
  <tr style="display:none;">
    <td colspan="2"><input type="hidden" name="roaming_mode" value="local"><input type="hidden" name="roaming_engine" value="api"></td>
  </tr>
  <?php } else { ?>
  <tr>
    <td class="tikras-ticket-section" colspan="2"><i class="fa fa-random"></i> Roaming tickets</td>
  </tr>
  <tr>
    <td class="align-middle">Moteur roaming</td>
    <td>
      <select class="form-control" id="roamingEngine" name="roaming_engine" onchange="updateRoamingEngine();">
        <option value="radius" <?php if ($formRoamingEngine == "radius") { echo "selected"; } ?>>RADIUS central (User Manager)</option>
        <option value="api" <?php if ($formRoamingEngine != "radius") { echo "selected"; } ?>>Copie API actuelle</option>
      </select>
      <small class="tikras-script-help" id="roamingEngineHelp">
        <?php if ($radiusEnabled) { ?>
          Serveur central: <?= tikras_radius_h($radiusManagerSession != "" ? $radiusManagerSession : "non configure"); ?>.
        <?php } else { ?>
          RADIUS central inactif. Configurez-le dans <a href="./admin.php?id=radius">Admin &gt; RADIUS central</a>.
        <?php } ?>
      </small>
    </td>
  </tr>
  <tr id="roamingModeRow">
    <td class="align-middle">Mode roaming</td>
    <td>
      <select class="form-control" id="roamingMode" name="roaming_mode" onchange="updateRoamingRouters();">
        <option value="local" <?php if ($formRoamingMode == "local") { echo "selected"; } ?>>Routeur actuel seulement</option>
        <option value="selected" <?php if ($formRoamingMode == "selected") { echo "selected"; } ?>>Routeurs sélectionnés</option>
        <option value="all" <?php if ($formRoamingMode == "all") { echo "selected"; } ?>>Tous les routeurs</option>
      </select>
      <small class="tikras-script-help">Les mêmes tickets seront synchronisés sur les routeurs choisis. Le profil doit exister sur chaque routeur.</small>
    </td>
  </tr>
  <tr id="roamingRoutersRow" style="display:none;">
    <td class="align-middle">Routeurs</td>
    <td>
      <select class="form-control" name="roaming_sessions[]" multiple size="8">
        <?php
        for ($ri = 0; $ri < count($roamingRouterSessions); $ri++) {
          $routerSession = $roamingRouterSessions[$ri];
          if ($routerSession == $session) {
            continue;
          }
          $routerCfg = tikras_roaming_session_config($data, $routerSession);
          $routerLabel = $routerSession;
          if ($routerCfg['hotspot'] != "") {
            $routerLabel .= " - " . $routerCfg['hotspot'];
          }
          echo "<option value='" . tikras_roaming_h($routerSession) . "'";
          if (in_array($routerSession, $formRoamingSessions)) {
            echo " selected";
          }
          echo ">" . tikras_roaming_h($routerLabel) . "</option>";
        }
        ?>
      </select>
      <small class="tikras-script-help">Maintenez Ctrl pour choisir plusieurs routeurs. Le routeur actuel est toujours inclus.</small>
    </td>
  </tr>
  <tr id="roamingProfileRow">
    <td class="align-middle">Profil distant</td>
    <td>
      <label>
        <input type="checkbox" id="roamingSyncProfile" name="roaming_sync_profile" value="yes" <?php if ($formRoamingSyncProfile) { echo "checked"; } ?>>
        Créer automatiquement le profil Hotspot s'il manque sur un routeur distant
      </label>
      <small class="tikras-script-help">Mikhmon copie les principaux réglages du profil choisi avant d'ajouter les tickets.</small>
    </td>
  </tr>
  <?php } ?>
  <tr>
    <td class="tikras-ticket-section" colspan="2"><i class="fa fa-share-alt"></i> Envoi après génération</td>
  </tr>
  <tr>
    <td class="align-middle">Canal</td>
    <td>
      <select class="form-control" id="shareChannel" name="share_channel" onchange="updateShareTarget();">
        <option value="whatsapp" <?php if ($formShareChannel == "whatsapp") { echo "selected"; } ?>>WhatsApp</option>
        <option value="telegram" <?php if ($formShareChannel == "telegram") { echo "selected"; } ?>>Telegram</option>
        <option value="email" <?php if ($formShareChannel == "email") { echo "selected"; } ?>>Email</option>
      </select>
    </td>
  </tr>
  <tr>
    <td class="align-middle">Destinataire</td>
    <td>
      <input class="form-control" type="text" autocomplete="off" id="shareTarget" name="share_target" value="<?= tikras_ticket_h($formShareTarget); ?>" placeholder="22790000000">
      <small class="tikras-script-help" id="shareTargetHelp">Numéro au format international, ex: 22790000000.</small>
    </td>
  </tr>
  <tr>
    <td class="align-middle">Après génération</td>
    <td>
      <select class="form-control" id="ticketAfterGenerate" name="after_generate">
        <option value="summary" <?php if ($formAfterGenerate == "summary") { echo "selected"; } ?>>Synthèse + partage</option>
        <option value="single" <?php if ($formAfterGenerate == "single") { echo "selected"; } ?>>Ouvrir le ticket si unique</option>
      </select>
    </td>
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

<div class="col-4">
	<div class="card tikras-ticket-preview-card">
		<div class="card-header">
			<h3><i class="fa fa-eye"></i> <?= $ticketPreviewTitle ?></h3>
		</div>
		<div class="card-body">
			<div class="tikras-ticket-preview" data-ticket-preview>
				<div class="tikras-ticket-preview-grid">
					<div>
						<span>Quantité</span>
						<strong data-ticket-preview-field="qty">-</strong>
					</div>
					<div>
						<span>Mode</span>
						<strong data-ticket-preview-field="mode">-</strong>
					</div>
					<div>
						<span>Profil</span>
						<strong data-ticket-preview-field="profile">-</strong>
					</div>
					<div>
						<span>Serveur</span>
						<strong data-ticket-preview-field="server">-</strong>
					</div>
					<div>
						<span>Code</span>
						<strong data-ticket-preview-field="code">-</strong>
					</div>
					<div>
						<span>Limites</span>
						<strong data-ticket-preview-field="limits">-</strong>
					</div>
					<div>
						<span>Roaming</span>
						<strong data-ticket-preview-field="roaming">-</strong>
					</div>
					<div>
						<span>Partage</span>
						<strong data-ticket-preview-field="share">-</strong>
					</div>
				</div>
				<div class="tikras-ticket-preview-note" data-ticket-preview-note>Vérification en cours...</div>
				<ul class="tikras-ticket-warnings" data-ticket-preview-warnings></ul>
			</div>
		</div>
	</div>
	<div class="card">
		<div class="card-header">
			<h3><i class="fa fa-ticket"></i> <?= $_last_generate ?></h3>
		</div>
		<div class="card-body">
<table class="table table-bordered">
  <tr>
  	<td><?= $_generate_code ?></td><td><?= $ucode ?></td>
  </tr>
  <tr>
  	<td><?= $_date ?></td><td><?= $udate ?></td>
  </tr>
  <tr>
  	<td><?= $_profile ?></td><td><?= $uprofile ?></td>
  </tr>
  <tr>
  	<td>Tickets</td><td><?= $generatedActualQty; ?> / <?= $generatedRequestedQty; ?></td>
  </tr>
  <tr>
  	<td><?= $_validity ?></td><td><?= $uvalid ?></td>
  </tr>
  <tr>
  	<td><?= $_time_limit ?></td><td><?= $utlimit ?></td>
  </tr>
  <tr>
  	<td><?= $_data_limit ?></td><td><?= $udlimit ?></td>
  </tr>
  <tr>
  	<td><?= $_price ?></td><td><?= $uprice ?></td>
  </tr>
  <tr>
  	<td><?= $_selling_price ?></td><td><?= $suprice ?></td>
  </tr>
  <tr>
  	<td><?= $_lock_user ?></td><td><?= $ulock ?></td>
  </tr>
  <tr>
    <td colspan="2">
		<p style="padding:0px 5px;">
      <?= $_format_time_limit ?>
    </p>
    <p style="padding:0px 5px;">
      <?= $_details_add_user ?>
    </p>
    </td>
  </tr>
</table>
</div>
</div>
<?php if (count($roamingHealth) > 0) { ?>
	<div class="card tikras-ticket-share-card">
		<div class="card-header">
			<h3><i class="fa fa-heartbeat"></i> Sante roaming</h3>
		</div>
		<div class="card-body">
			<table class="table table-bordered">
				<thead>
					<tr>
						<th>Routeur</th>
						<th>API</th>
						<th>Profil</th>
						<th>Serveur</th>
						<th>Etat</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($roamingHealth as $healthRow) {
						$healthOk = isset($healthRow['ok']) && $healthRow['ok'];
						$healthWarn = isset($healthRow['warning']) && $healthRow['warning'];
						$healthClass = $healthOk ? ($healthWarn ? 'text-warning' : 'text-green') : 'text-danger';
					?>
					<tr>
						<td><b><?= tikras_roaming_h(isset($healthRow['session']) ? $healthRow['session'] : ""); ?></b><br><small><?= tikras_roaming_h(isset($healthRow['hotspot']) ? $healthRow['hotspot'] : ""); ?></small></td>
						<td><?= tikras_roaming_h(isset($healthRow['api']) ? $healthRow['api'] : "-"); ?></td>
						<td><?= tikras_roaming_h(isset($healthRow['profile']) ? $healthRow['profile'] : "-"); ?></td>
						<td><?= tikras_roaming_h(isset($healthRow['server']) ? $healthRow['server'] : "-"); ?></td>
						<td><span class="<?= $healthClass; ?>"><?= tikras_roaming_h(isset($healthRow['message']) ? $healthRow['message'] : ""); ?></span></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
<?php } ?>
<?php if (count($roamingStatus) > 0) { ?>
	<div class="card tikras-ticket-share-card">
		<div class="card-header">
			<h3><i class="fa fa-random"></i> Roaming tickets</h3>
		</div>
		<div class="card-body">
			<table class="table table-bordered">
				<thead>
					<tr>
						<th>Routeur</th>
						<th>Etat</th>
						<th>Ajoutes</th>
						<th>MAJ</th>
						<th>Erreurs</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($roamingStatus as $roamRow) {
						$roamOk = isset($roamRow['ok']) && $roamRow['ok'];
						$roamSession = isset($roamRow['session']) ? $roamRow['session'] : "";
						$roamHotspot = isset($roamRow['hotspot']) ? $roamRow['hotspot'] : "";
						$roamMessage = isset($roamRow['message']) ? $roamRow['message'] : "";
						$roamCreated = isset($roamRow['created']) ? $roamRow['created'] : 0;
						$roamUpdated = isset($roamRow['updated']) ? $roamRow['updated'] : 0;
						$roamFailed = isset($roamRow['failed']) ? $roamRow['failed'] : 0;
					?>
					<tr>
						<td><b><?= tikras_roaming_h($roamSession); ?></b><br><small><?= tikras_roaming_h($roamHotspot); ?></small></td>
						<td><span class="<?= $roamOk ? 'text-green' : 'text-warning'; ?>"><?= tikras_roaming_h($roamMessage); ?></span></td>
						<td><?= tikras_roaming_h($roamCreated); ?></td>
						<td><?= tikras_roaming_h($roamUpdated); ?></td>
						<td><?= tikras_roaming_h($roamFailed); ?></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
			<?php if (isset($generatedMeta['roaming_queued']) && is_array($generatedMeta['roaming_queued']) && isset($generatedMeta['roaming_queued']['id'])) { ?>
				<p class="text-warning" style="padding:0 5px;">
					Certains routeurs ont ete places en file de reprise roaming. Lot <?= tikras_roaming_h($generatedMeta['roaming_queued']['id']); ?>.
				</p>
			<?php } ?>
		</div>
	</div>
<?php } ?>
<?php if ((isset($roamingQueueSummary['items']) && $roamingQueueSummary['items'] > 0) || (isset($roamingQueueRetry['results']) && count($roamingQueueRetry['results']) > 0)) { ?>
	<div class="card tikras-ticket-share-card">
		<div class="card-header">
			<h3><i class="fa fa-refresh"></i> Reprise roaming</h3>
		</div>
		<div class="card-body">
			<p style="padding:0 5px;">
				En attente : <?= tikras_roaming_h(isset($roamingQueueSummary['items']) ? $roamingQueueSummary['items'] : 0); ?> lot(s),
				<?= tikras_roaming_h(isset($roamingQueueSummary['targets']) ? $roamingQueueSummary['targets'] : 0); ?> routeur(s),
				<?= tikras_roaming_h(isset($roamingQueueSummary['tickets']) ? $roamingQueueSummary['tickets'] : 0); ?> ticket(s).
			</p>
			<?php if (isset($roamingQueueSummary['items']) && $roamingQueueSummary['items'] > 0) { ?>
				<form method="post" action="">
					<button type="submit" name="retry_roaming_queue" value="1" class="btn bg-primary">
						<i class="fa fa-refresh"></i> Retenter maintenant
					</button>
				</form>
			<?php } ?>
			<?php if (isset($roamingQueueRetry['results']) && count($roamingQueueRetry['results']) > 0) { ?>
				<table class="table table-bordered mr-t-10">
					<thead>
						<tr>
							<th>Routeur</th>
							<th>Etat</th>
							<th>Ajoutes</th>
							<th>MAJ</th>
							<th>Erreurs</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($roamingQueueRetry['results'] as $retryRow) {
							$retryOk = isset($retryRow['ok']) && $retryRow['ok'];
						?>
						<tr>
							<td><?= tikras_roaming_h(isset($retryRow['session']) ? $retryRow['session'] : ""); ?></td>
							<td><span class="<?= $retryOk ? 'text-green' : 'text-warning'; ?>"><?= tikras_roaming_h(isset($retryRow['message']) ? $retryRow['message'] : ""); ?></span></td>
							<td><?= tikras_roaming_h(isset($retryRow['created']) ? $retryRow['created'] : 0); ?></td>
							<td><?= tikras_roaming_h(isset($retryRow['updated']) ? $retryRow['updated'] : 0); ?></td>
							<td><?= tikras_roaming_h(isset($retryRow['failed']) ? $retryRow['failed'] : 0); ?></td>
						</tr>
						<?php } ?>
					</tbody>
				</table>
			<?php } ?>
		</div>
	</div>
<?php } ?>
<?php if (count($generatedList) > 0 && $ticketShareText != "") { ?>
	<div class="card tikras-ticket-share-card">
		<div class="card-header">
			<h3><i class="fa fa-share-alt"></i> Partage tickets</h3>
		</div>
		<div class="card-body">
			<?php if ($ticketDeliveryStatus != "") { ?>
			<div class="box <?= tikras_ticket_h($ticketDeliveryClass); ?> pd-5 mr-b-10"><?= tikras_ticket_h($ticketDeliveryStatus); ?></div>
			<?php } ?>
			<div class="tikras-ticket-actions">
				<?php if ($ticketPdfUrl != "") { ?>
				<a class="btn bg-danger" target="_blank" href="<?= tikras_ticket_h($ticketPdfRelative); ?>"><i class="fa fa-file-pdf-o"></i> PDF petit</a>
				<?php } ?>
				<a class="btn <?= $ticketShareChannel == "email" ? "bg-primary" : ($ticketShareChannel == "telegram" ? "bg-info" : "bg-green"); ?>" target="_blank" href="<?= tikras_ticket_h($ticketShareHref); ?>"><i class="fa <?= tikras_ticket_h($ticketShareIcon); ?>"></i> <?= tikras_ticket_h($ticketShareLabel); ?></a>
				<button class="btn bg-info" type="button" onclick="copyTicketLink();"><i class="fa fa-link"></i> Copier lien</button>
				<button class="btn bg-secondary" type="button" onclick="copyTicketShare();"><i class="fa fa-copy"></i> Copier</button>
			</div>
			<input id="ticketShareLink" class="form-control tikras-ticket-link" type="text" readonly value="<?= tikras_ticket_h($ticketShareHref); ?>">
			<textarea id="ticketShareText" class="tikras-ticket-share-text" readonly><?= tikras_ticket_h($ticketShareMessage); ?></textarea>
			<div class="overflow box-bordered mr-t-10 tikras-ticket-list">
				<table class="table table-bordered">
					<thead>
						<tr>
							<th>#</th>
							<th>Ticket</th>
							<th>Password</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($generatedList as $ticketIndex => $ticketRow) {
							$ticketUsername = isset($ticketRow['username']) ? $ticketRow['username'] : "";
							$ticketPassword = isset($ticketRow['password']) ? $ticketRow['password'] : "";
						?>
						<tr>
							<td><?= $ticketIndex + 1; ?></td>
							<td><?= tikras_ticket_h($ticketUsername); ?></td>
							<td><?= tikras_ticket_h($ticketPassword); ?></td>
						</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php } ?>
</div>
<script>
// get valid $ price
function GetVP(){
  var prof = document.getElementById('uprof').value;
  $("#GetValidPrice").load("./process/getvalidprice.php?name="+prof+"&session=<?= $session; ?> #getdata");
} 
function updateShareTarget(){
  var channel = document.getElementById('shareChannel');
  var target = document.getElementById('shareTarget');
  var help = document.getElementById('shareTargetHelp');
  if (!channel || !target || !help) {
    return;
  }
  if (channel.value === 'email') {
    target.type = 'email';
    target.placeholder = 'client@example.com';
    help.innerHTML = 'Adresse email du destinataire.';
  } else if (channel.value === 'telegram') {
    target.type = 'text';
    target.placeholder = '-1001234567890';
    help.innerHTML = 'Chat ID Telegram. Laissez vide pour utiliser le Chat ID configuré.';
  } else {
    target.type = 'text';
    target.placeholder = '22790000000';
    help.innerHTML = 'Numéro au format international, ex: 22790000000.';
  }
}
function updateRoamingRouters(){
  var mode = document.getElementById('roamingMode');
  var row = document.getElementById('roamingRoutersRow');
  if (!mode || !row) {
    return;
  }
  row.style.display = mode.value === 'selected' ? '' : 'none';
}
function updateRoamingEngine(){
  var engine = document.getElementById('roamingEngine');
  var modeRow = document.getElementById('roamingModeRow');
  var routerRow = document.getElementById('roamingRoutersRow');
  var profileRow = document.getElementById('roamingProfileRow');
  var isRadius = engine && engine.value === 'radius';
  if (modeRow) {
    modeRow.style.display = isRadius ? 'none' : '';
  }
  if (profileRow) {
    profileRow.style.display = isRadius ? 'none' : '';
  }
  if (routerRow) {
    routerRow.style.display = isRadius ? 'none' : '';
  }
  if (!isRadius) {
    updateRoamingRouters();
  }
}
function copyTicketShare(){
  var field = document.getElementById('ticketShareText');
  if (!field) {
    return;
  }
  field.focus();
  field.select();
  document.execCommand('copy');
}
function copyTicketLink(){
  var field = document.getElementById('ticketShareLink');
  if (!field) {
    return;
  }
  field.focus();
  field.select();
  document.execCommand('copy');
}
/*
 * Longueur conseillee selon le mode: un voucher (identifiant unique) doit etre
 * plus long qu'un couple utilisateur/mot de passe. On change la valeur reelle
 * du champ, pas seulement son libelle.
 */
function tikrasUserModeChange(){
  var mode = document.getElementById('user');
  var longueur = document.getElementById('userl');
  if (!mode || !longueur || longueur.getAttribute('data-touche') === '1') {
    return;
  }
  var souhaitee = mode.value === 'vc' ? '8' : '4';
  for (var i = 0; i < longueur.options.length; i++) {
    if (longueur.options[i].value === souhaitee) {
      longueur.selectedIndex = i;
      break;
    }
  }
}
(function(){
  var longueur = document.getElementById('userl');
  if (longueur) {
    // Une fois la longueur choisie a la main, on ne la remplace plus.
    longueur.addEventListener('change', function(){
      this.setAttribute('data-touche', '1');
    });
  }
})();
updateShareTarget();
updateRoamingRouters();
updateRoamingEngine();
</script>
</div>
