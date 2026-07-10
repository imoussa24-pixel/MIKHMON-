<?php
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -17) == "notify_config.php") {
  header("Location:./");
};

$tikrasNotify = array (
  'default' => 
  array (
    'mail_from' => 'tickets@tikras-it.local',
    'mail_from_name' => 'TIKRAS IT',
    'smtp_host' => '',
    'smtp_port' => '25',
    'whatsapp_api_url' => '',
    'whatsapp_api_token' => '',
    'whatsapp_method' => 'POST',
    'whatsapp_payload' => 'token={token}&to={phone}&message={message}&pdf={pdf_url}',
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',
    'telegram_parse_mode' => '',
  ),
  'NASS-HOUSE-SERVER' => 
  array (
    'mail_from' => 'tickets@tikras-it.local',
    'mail_from_name' => 'TIKRAS IT',
    'smtp_host' => false,
    'smtp_port' => '25',
    'whatsapp_api_url' => false,
    'whatsapp_api_token' => false,
    'whatsapp_method' => 'POST',
    'whatsapp_payload' => 'token={token}&to={phone}&message={message}&pdf={pdf_url}',
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',
    'telegram_parse_mode' => '',
  ),
  'simnet' => 
  array (
    'mail_from' => 'tickets@tikras-it.local',
    'mail_from_name' => 'TIKRAS IT',
    'smtp_host' => false,
    'smtp_port' => '25',
    'whatsapp_api_url' => false,
    'whatsapp_api_token' => false,
    'whatsapp_method' => 'POST',
    'whatsapp_payload' => 'token={token}&to={phone}&message={message}&pdf={pdf_url}',
    'telegram_bot_token' => false,
    'telegram_chat_id' => false,
    'telegram_parse_mode' => '',
  ),
  'ARAFAT-SERVER' => 
  array (
    'mail_from' => 'tickets@tikras-it.local',
    'mail_from_name' => 'TIKRAS IT',
    'smtp_host' => false,
    'smtp_port' => '25',
    'whatsapp_api_url' => false,
    'whatsapp_api_token' => false,
    'whatsapp_method' => 'POST',
    'whatsapp_payload' => 'token={token}&to={phone}&message={message}&pdf={pdf_url}',
    'telegram_bot_token' => false,
    'telegram_chat_id' => false,
    'telegram_parse_mode' => '',
  ),
  'RACHID-SERVER' => 
  array (
    'mail_from' => 'tickets@tikras-it.local',
    'mail_from_name' => 'TIKRAS IT',
    'smtp_host' => false,
    'smtp_port' => '25',
    'whatsapp_api_url' => false,
    'whatsapp_api_token' => false,
    'whatsapp_method' => 'POST',
    'whatsapp_payload' => 'token={token}&to={phone}&message={message}&pdf={pdf_url}',
    'telegram_bot_token' => false,
    'telegram_chat_id' => false,
    'telegram_parse_mode' => '',
  ),
  'BAYIS-LACROUSSOU-SERVER' => 
  array (
    'mail_from' => 'tickets@tikras-it.local',
    'mail_from_name' => 'TIKRAS IT',
    'smtp_host' => '',
    'smtp_port' => '25',
    'whatsapp_api_url' => '',
    'whatsapp_api_token' => '',
    'whatsapp_method' => 'POST',
    'whatsapp_payload' => 'token={token}&to={phone}&message={message}&pdf={pdf_url}',
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',
    'telegram_parse_mode' => '',
  ),
);
?>
