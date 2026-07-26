<?php
/*
 * RouterOS callback used by the ticket automation script.
 * It creates a compact PDF from the generated ticket batch and sends/prepares it.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

function tikras_auto_param($key, $fallback = "")
{
  if (tikras_has_post($key)) {
    return trim((string) tikras_post($key));
  }
  if (tikras_has_get($key)) {
    return trim((string) tikras_get($key));
  }
  static $raw = null;
  if ($raw === null) {
    $raw = array();
    parse_str(file_get_contents("php://input"), $raw);
  }
  if (isset($raw[$key])) {
    return trim((string) $raw[$key]);
  }
  return $fallback;
}

function tikras_auto_clean_phone($value)
{
  return substr(preg_replace('/[^0-9]/', '', (string) $value), 0, 20);
}

function tikras_auto_clean_telegram_chat($value)
{
  $value = trim((string) $value);
  $value = str_replace(array("\r", "\n", "\t", " "), "", $value);
  if ($value != "" && preg_match('/^-?[0-9A-Za-z_@:-]+$/', $value)) {
    return substr($value, 0, 80);
  }
  return "";
}

function tikras_auto_channel($value)
{
  $value = trim((string) $value);
  if ($value == "email" || $value == "telegram") {
    return $value;
  }
  return "whatsapp";
}

function tikras_auto_public_url($relativePath)
{
  $scheme = (tikras_server('HTTPS') != "" && tikras_server('HTTPS') != 'off') ? 'https' : 'http';
  $host = tikras_server('HTTP_HOST', 'localhost');
  $script = tikras_server('SCRIPT_NAME', '/process/ticketautoshare.php');
  $script = str_replace('\\', '/', $script);
  $base = rtrim(dirname($script), '/');
  $base = preg_replace('#/process$#', '', $base);
  if ($base == "/" || $base == ".") {
    $base = "";
  }
  return $scheme . "://" . $host . $base . "/" . ltrim($relativePath, "/");
}

$session = tikras_auto_param("session");
$token = tikras_auto_param("token");
$comment = tikras_auto_param("comment");
$channel = tikras_auto_channel(tikras_auto_param("channel", "whatsapp"));
$target = tikras_auto_param("target");

if ($session == "" || $token == "" || $comment == "" || !preg_match('/^[0-9A-Za-z_.:-]+$/', $comment)) {
  header("HTTP/1.1 400 Bad Request");
  echo "INVALID_REQUEST";
  exit;
}

include('../include/config.php');
if (!isset($data[$session])) {
  header("HTTP/1.1 404 Not Found");
  echo "SESSION_NOT_FOUND";
  exit;
}
include('../include/readcfg.php');

$expectedToken = sha1($session . "|" . $passwdhost . "|tikras-ticket-share");
if ($token !== $expectedToken) {
  header("HTTP/1.1 403 Forbidden");
  echo "INVALID_TOKEN";
  exit;
}

include_once('../lib/tikras_routeros.php');
include_once('../lib/formatbytesbites.php');
include_once('../lib/tikras_ticket_pdf.php');
include_once('../lib/tikras_notify.php');

$API = tikras_routeros_create();
if (!tikras_routeros_connect($API, $iphost, $userhost, decrypt($passwdhost), $session)) {
  header("HTTP/1.1 502 Bad Gateway");
  echo "ROUTER_CONNECT_FAILED";
  exit;
}

$getuser = $API->comm("/ip/hotspot/user/print", array("?comment" => $comment));
if (!is_array($getuser) || count($getuser) < 1) {
  echo "NO_TICKETS";
  exit;
}

$first = $getuser[0];
$profile = isset($first['profile']) ? $first['profile'] : "";
$timelimit = isset($first['limit-uptime']) ? $first['limit-uptime'] : "";
$datalimitRaw = isset($first['limit-bytes-total']) ? $first['limit-bytes-total'] : "0";
$datalimit = ($datalimitRaw == "" || $datalimitRaw == "0") ? "" : formatBytes($datalimitRaw, 2);
$validity = "";
$price = "";

if ($profile != "") {
  $getprofile = $API->comm("/ip/hotspot/user/profile/print", array("?name" => $profile));
  if (isset($getprofile[0])) {
    $ponlogin = isset($getprofile[0]['on-login']) ? $getprofile[0]['on-login'] : "";
    $onLoginParts = explode(",", $ponlogin);
    $validity = tikras_array_get($onLoginParts, 3, "");
    $getprice = tikras_array_get($onLoginParts, 2, "0");
    $getsprice = tikras_array_get($onLoginParts, 4, "0");
    $realPrice = ($getsprice != "" && $getsprice != "0") ? $getsprice : $getprice;
    if ($realPrice != "" && $realPrice != "0") {
      if ($currency == in_array($currency, $cekindo['indo'])) {
        $price = $currency . " " . number_format((float) $realPrice, 0, ",", ".");
      } else {
        $price = $currency . " " . number_format((float) $realPrice);
      }
    }
  }
}

$tickets = array();
for ($i = 0; $i < count($getuser); $i++) {
  $tickets[] = array(
    "username" => isset($getuser[$i]['name']) ? $getuser[$i]['name'] : "",
    "password" => isset($getuser[$i]['password']) ? $getuser[$i]['password'] : "",
  );
}

$pdfName = "auto-" . preg_replace('/[^a-zA-Z0-9_-]/', '-', $session) . "-" . preg_replace('/[^a-zA-Z0-9_-]/', '-', $comment) . ".pdf";
$pdfRelative = "share/tickets/" . $pdfName;
$pdfPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . "share" . DIRECTORY_SEPARATOR . "tickets" . DIRECTORY_SEPARATOR . $pdfName;
$meta = array(
  "hotspotname" => $hotspotname,
  "dnsname" => $dnsname,
  "profile" => $profile,
  "validity" => $validity,
  "timelimit" => $timelimit,
  "datalimit" => $datalimit,
  "price" => $price,
  // La devise accompagne le prix sur le ticket imprime.
  "currency" => isset($currency) ? $currency : "",
);

if (!tikras_ticket_pdf_generate($pdfPath, $tickets, $meta)) {
  header("HTTP/1.1 500 Internal Server Error");
  echo "PDF_FAILED";
  exit;
}

$pdfUrl = tikras_auto_public_url($pdfRelative);
$message = $hotspotname . "\r\nTickets générés automatiquement\r\nProfil : " . $profile . "\r\nPDF : " . $pdfUrl;
$status = "PDF_READY";

if ($channel == "email") {
  if (filter_var($target, FILTER_VALIDATE_EMAIL)) {
    $sent = tikras_notify_send_ticket_mail($session, $target, "Tickets " . $hotspotname . " - " . $profile, $message, $pdfPath, $pdfName);
    $status = $sent ? "EMAIL_SENT" : "EMAIL_NOT_SENT_PDF_READY";
  }
} elseif ($channel == "telegram") {
  $chatId = tikras_auto_clean_telegram_chat($target);
  $notifyConfig = tikras_notify_get($session);
  if ($notifyConfig['telegram_bot_token'] == "" || ($chatId == "" && $notifyConfig['telegram_chat_id'] == "")) {
    $status = "TELEGRAM_NOT_CONFIGURED_PDF_READY";
  } else {
    $sent = tikras_notify_send_telegram($session, $message, $pdfUrl, $chatId);
    $status = $sent ? "TELEGRAM_SENT" : "TELEGRAM_NOT_SENT_PDF_READY";
  }
} else {
  $phone = tikras_auto_clean_phone($target);
  if ($phone != "") {
    $notifyConfig = tikras_notify_get($session);
    if ($notifyConfig['whatsapp_api_url'] == "") {
      $status = "WHATSAPP_API_NOT_CONFIGURED_PDF_READY";
    } else {
      $sent = tikras_notify_send_whatsapp($session, $phone, $message, $pdfUrl);
      $status = $sent ? "WHATSAPP_SENT" : "WHATSAPP_NOT_SENT_PDF_READY";
    }
  }
}

header("Content-Type: text/plain; charset=utf-8");
echo $status . " " . $pdfUrl;
?>
