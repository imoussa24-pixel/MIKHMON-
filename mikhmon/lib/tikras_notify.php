<?php
/*
 * Notification helper for ticket PDF sharing.
 * Settings are stored per TIKRAS IT session/router in include/notify_config.php.
 */

if (!function_exists('tikras_notify_config_file')) {
  function tikras_notify_config_file()
  {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . "include" . DIRECTORY_SEPARATOR . "notify_config.php";
  }
}

if (!function_exists('tikras_notify_defaults')) {
  function tikras_notify_defaults()
  {
    return array(
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
    );
  }
}

if (!function_exists('tikras_notify_h')) {
  function tikras_notify_h($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('tikras_notify_read_all')) {
  function tikras_notify_read_all()
  {
    $tikrasNotify = array();
    $file = tikras_notify_config_file();
    if (is_file($file)) {
      include($file);
    }
    if (!is_array($tikrasNotify)) {
      $tikrasNotify = array();
    }
    if (!isset($tikrasNotify['default']) || !is_array($tikrasNotify['default'])) {
      $tikrasNotify['default'] = tikras_notify_defaults();
    }
    return $tikrasNotify;
  }
}

if (!function_exists('tikras_notify_clean_line')) {
  function tikras_notify_clean_line($value, $max)
  {
    $value = trim((string) $value);
    $value = str_replace(array("\r", "\n", "\t"), " ", $value);
    return substr($value, 0, $max);
  }
}

if (!function_exists('tikras_notify_clean_config')) {
  function tikras_notify_clean_config($input)
  {
    $defaults = tikras_notify_defaults();
    if (!is_array($input)) {
      $input = array();
    }

    $mailFrom = isset($input['mail_from']) ? trim((string) $input['mail_from']) : $defaults['mail_from'];
    if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
      $mailFrom = $defaults['mail_from'];
    }

    $smtpHost = isset($input['smtp_host']) ? tikras_notify_clean_line($input['smtp_host'], 180) : "";
    if ($smtpHost != "" && !preg_match('/^[0-9A-Za-z\.\-_:]+$/', $smtpHost)) {
      $smtpHost = "";
    }

    $smtpPort = isset($input['smtp_port']) ? trim((string) $input['smtp_port']) : $defaults['smtp_port'];
    if (!ctype_digit($smtpPort) || (int) $smtpPort < 1 || (int) $smtpPort > 65535) {
      $smtpPort = $defaults['smtp_port'];
    }

    $apiUrl = isset($input['whatsapp_api_url']) ? tikras_notify_clean_line($input['whatsapp_api_url'], 500) : "";
    if ($apiUrl != "" && !preg_match('/^https?\:\/\/[^\s"\']+$/', $apiUrl)) {
      $apiUrl = "";
    }

    $method = isset($input['whatsapp_method']) ? strtoupper(trim((string) $input['whatsapp_method'])) : "POST";
    if ($method != "GET") {
      $method = "POST";
    }

    $payload = isset($input['whatsapp_payload']) ? trim((string) $input['whatsapp_payload']) : $defaults['whatsapp_payload'];
    $payload = str_replace(array("\r\n", "\r"), "\n", $payload);
    if ($payload == "") {
      $payload = $defaults['whatsapp_payload'];
    }
    $payload = substr($payload, 0, 2000);

    $telegramBotToken = isset($input['telegram_bot_token']) ? tikras_notify_clean_line($input['telegram_bot_token'], 180) : "";
    if ($telegramBotToken != "" && !preg_match('/^[0-9A-Za-z:_-]+$/', $telegramBotToken)) {
      $telegramBotToken = "";
    }

    $telegramChatId = isset($input['telegram_chat_id']) ? tikras_notify_clean_line($input['telegram_chat_id'], 80) : "";
    if ($telegramChatId != "" && !preg_match('/^-?[0-9A-Za-z_@:-]+$/', $telegramChatId)) {
      $telegramChatId = "";
    }

    $telegramParseMode = isset($input['telegram_parse_mode']) ? trim((string) $input['telegram_parse_mode']) : "";
    if ($telegramParseMode != "Markdown" && $telegramParseMode != "HTML") {
      $telegramParseMode = "";
    }

    return array(
      'mail_from' => $mailFrom,
      'mail_from_name' => tikras_notify_clean_line(isset($input['mail_from_name']) ? $input['mail_from_name'] : $defaults['mail_from_name'], 120),
      'smtp_host' => $smtpHost,
      'smtp_port' => $smtpPort,
      'whatsapp_api_url' => $apiUrl,
      'whatsapp_api_token' => tikras_notify_clean_line(isset($input['whatsapp_api_token']) ? $input['whatsapp_api_token'] : "", 500),
      'whatsapp_method' => $method,
      'whatsapp_payload' => $payload,
      'telegram_bot_token' => $telegramBotToken,
      'telegram_chat_id' => $telegramChatId,
      'telegram_parse_mode' => $telegramParseMode,
    );
  }
}

if (!function_exists('tikras_notify_get')) {
  function tikras_notify_get($session)
  {
    $all = tikras_notify_read_all();
    $defaults = tikras_notify_defaults();
    $global = isset($all['default']) && is_array($all['default']) ? $all['default'] : array();
    $sessionConfig = isset($all[$session]) && is_array($all[$session]) ? $all[$session] : array();
    return array_merge($defaults, $global, $sessionConfig);
  }
}

if (!function_exists('tikras_notify_write_session')) {
  function tikras_notify_write_session($session, $config, $oldSession = "")
  {
    $session = trim((string) $session);
    if ($session == "") {
      return false;
    }

    $all = tikras_notify_read_all();
    if ($oldSession != "" && $oldSession != $session && isset($all[$oldSession])) {
      unset($all[$oldSession]);
    }
    $all[$session] = tikras_notify_clean_config($config);

    $content = "<?php\r\n";
    $content .= "if (isset(\$_SERVER[\"REQUEST_URI\"]) && substr(\$_SERVER[\"REQUEST_URI\"], -17) == \"notify_config.php\") {\r\n";
    $content .= "  header(\"Location:./\");\r\n";
    $content .= "};\r\n\r\n";
    $content .= "\$tikrasNotify = " . var_export($all, true) . ";\r\n";
    $content .= "?>\r\n";

    return file_put_contents(tikras_notify_config_file(), $content, LOCK_EX) !== false;
  }
}

if (!function_exists('tikras_notify_apply_php_mail')) {
  function tikras_notify_apply_php_mail($config)
  {
    if (!is_array($config)) {
      return;
    }
    if (isset($config['smtp_host']) && $config['smtp_host'] != "") {
      @ini_set("SMTP", $config['smtp_host']);
    }
    if (isset($config['smtp_port']) && $config['smtp_port'] != "") {
      @ini_set("smtp_port", $config['smtp_port']);
    }
    if (isset($config['mail_from']) && filter_var($config['mail_from'], FILTER_VALIDATE_EMAIL)) {
      @ini_set("sendmail_from", $config['mail_from']);
    }
  }
}

if (!function_exists('tikras_notify_send_ticket_mail')) {
  function tikras_notify_send_ticket_mail($session, $to, $subject, $body, $pdfPath, $pdfName)
  {
    $config = tikras_notify_get($session);
    tikras_notify_apply_php_mail($config);
    return tikras_ticket_send_pdf_mail($to, $subject, $body, $pdfPath, $pdfName, $config['mail_from'], $config['mail_from_name']);
  }
}

if (!function_exists('tikras_notify_template')) {
  function tikras_notify_template($template, $vars)
  {
    foreach ($vars as $key => $value) {
      $template = str_replace("{" . $key . "}", rawurlencode((string) $value), $template);
    }
    return $template;
  }
}

if (!function_exists('tikras_notify_http_post')) {
  function tikras_notify_http_post($url, $payload)
  {
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, 20);
      curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      return $code >= 200 && $code < 300;
    }

    $context = stream_context_create(array(
      'http' => array(
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $payload,
        'timeout' => 20,
      ),
    ));
    @file_get_contents($url, false, $context);
    if (function_exists('http_get_last_response_headers')) {
      $headers = http_get_last_response_headers();
    } else {
      $headerVar = 'http_response_header';
      $headers = isset($$headerVar) ? $$headerVar : array();
    }
    if (isset($headers[0]) && preg_match('/\s([0-9]{3})\s/', $headers[0], $match)) {
      $code = (int) $match[1];
      return $code >= 200 && $code < 300;
    }
    return false;
  }
}

if (!function_exists('tikras_notify_http_get')) {
  function tikras_notify_http_get($url)
  {
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
      curl_setopt($ch, CURLOPT_TIMEOUT, 20);
      curl_exec($ch);
      $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      return $code >= 200 && $code < 300;
    }

    @file_get_contents($url);
    if (function_exists('http_get_last_response_headers')) {
      $headers = http_get_last_response_headers();
    } else {
      $headerVar = 'http_response_header';
      $headers = isset($$headerVar) ? $$headerVar : array();
    }
    if (isset($headers[0]) && preg_match('/\s([0-9]{3})\s/', $headers[0], $match)) {
      $code = (int) $match[1];
      return $code >= 200 && $code < 300;
    }
    return false;
  }
}

if (!function_exists('tikras_notify_send_whatsapp')) {
  function tikras_notify_send_whatsapp($session, $phone, $message, $pdfUrl)
  {
    $config = tikras_notify_get($session);
    if ($config['whatsapp_api_url'] == "") {
      return false;
    }

    $vars = array(
      'token' => $config['whatsapp_api_token'],
      'phone' => $phone,
      'message' => $message,
      'pdf_url' => $pdfUrl,
    );
    $url = tikras_notify_template($config['whatsapp_api_url'], $vars);
    $payload = tikras_notify_template($config['whatsapp_payload'], $vars);

    if ($config['whatsapp_method'] == "GET") {
      $separator = strpos($url, "?") === false ? "?" : "&";
      return tikras_notify_http_get($url . $separator . $payload);
    }
    return tikras_notify_http_post($url, $payload);
  }
}

if (!function_exists('tikras_notify_clean_telegram_chat')) {
  function tikras_notify_clean_telegram_chat($value)
  {
    $value = tikras_notify_clean_line($value, 80);
    if ($value != "" && preg_match('/^-?[0-9A-Za-z_@:-]+$/', $value)) {
      return $value;
    }
    return "";
  }
}

if (!function_exists('tikras_notify_send_telegram')) {
  function tikras_notify_send_telegram($session, $message, $pdfUrl, $chatId = "")
  {
    $config = tikras_notify_get($session);
    if ($config['telegram_bot_token'] == "") {
      return false;
    }

    $chatId = tikras_notify_clean_telegram_chat($chatId);
    if ($chatId == "") {
      $chatId = tikras_notify_clean_telegram_chat($config['telegram_chat_id']);
    }
    if ($chatId == "") {
      return false;
    }

    if ($pdfUrl != "" && strpos($message, $pdfUrl) === false) {
      $message .= "\r\n\r\nPDF : " . $pdfUrl;
    }

    $payload = array(
      'chat_id' => $chatId,
      'text' => $message,
      'disable_web_page_preview' => '0',
    );
    if ($config['telegram_parse_mode'] != "") {
      $payload['parse_mode'] = $config['telegram_parse_mode'];
    }

    $url = "https://api.telegram.org/bot" . $config['telegram_bot_token'] . "/sendMessage";
    return tikras_notify_http_post($url, http_build_query($payload, "", "&"));
  }
}
?>
