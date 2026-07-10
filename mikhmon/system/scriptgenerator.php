<?php
/*
 *  Copyright (C) 2026 TIKRAS IT.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
include_once(dirname(__DIR__) . '/lib/tikras_routeros.php');
include_once(dirname(__DIR__) . '/lib/tikras_storage.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {

  if (!function_exists('tikras_script_h')) {
    function tikras_script_h($value)
    {
      return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
  }

  if (!function_exists('tikras_script_clean_rate')) {
    function tikras_script_clean_rate($value, $fallback)
    {
      $value = trim((string) $value);
      if (preg_match('/^[0-9]+([kKmMgG])?$/', $value)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_risk_label')) {
    function tikras_script_risk_label($risk)
    {
      $labels = array(
        'faible' => 'faible',
        'modere' => 'modere',
        'eleve' => 'eleve',
      );
      return isset($labels[$risk]) ? $labels[$risk] : 'modere';
    }
  }

  if (!function_exists('tikras_script_routeros_error')) {
    function tikras_script_routeros_error($response)
    {
      if (!is_array($response)) {
        return '';
      }
      if (isset($response['!trap'][0]['message'])) {
        return (string) $response['!trap'][0]['message'];
      }
      if (isset($response[0]['!trap'][0]['message'])) {
        return (string) $response[0]['!trap'][0]['message'];
      }
      foreach ($response as $row) {
        if (is_array($row) && isset($row['message']) && (isset($row['!trap']) || isset($row['category']))) {
          return (string) $row['message'];
        }
      }
      return '';
    }
  }

  if (!function_exists('tikras_script_clean_target')) {
    function tikras_script_clean_target($value, $fallback)
    {
      $value = trim((string) $value);
      if ($value !== "" && preg_match('/^[0-9a-zA-Z\.\:\/_-]+$/', $value)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_time')) {
    function tikras_script_clean_time($value, $fallback)
    {
      $value = trim((string) $value);
      if (preg_match('/^([01][0-9]|2[0-3])\:[0-5][0-9]\:[0-5][0-9]$/', $value)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_interval')) {
    function tikras_script_clean_interval($value, $fallback)
    {
      $value = trim((string) $value);
      if (preg_match('/^[1-9][0-9]*[smhdw]$/i', $value) || preg_match('/^([01][0-9]|2[0-3])\:[0-5][0-9]\:[0-5][0-9]$/', $value)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_duration')) {
    function tikras_script_clean_duration($value, $fallback)
    {
      $value = trim((string) $value);
      if ($value === "0" || preg_match('/^[0-9]+[smhdw]$/i', $value) || preg_match('/^([01][0-9]|2[0-3])\:[0-5][0-9]\:[0-5][0-9]$/', $value)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_number')) {
    function tikras_script_clean_number($value, $fallback, $min, $max)
    {
      $value = trim((string) $value);
      if (ctype_digit($value)) {
        $number = (int) $value;
        if ($number >= $min && $number <= $max) {
          return (string) $number;
        }
      }
      return (string) $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_choice')) {
    function tikras_script_clean_choice($value, $allowed, $fallback)
    {
      $value = trim((string) $value);
      if (in_array($value, $allowed, true)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_channel')) {
    function tikras_script_clean_channel($value)
    {
      $value = trim((string) $value);
      if ($value == "email" || $value == "telegram") {
        return $value;
      }
      return "whatsapp";
    }
  }

  if (!function_exists('tikras_script_clean_share_target')) {
    function tikras_script_clean_share_target($value, $channel)
    {
      $value = trim((string) $value);
      if ($channel == "email") {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : "";
      }
      if ($channel == "telegram") {
        $value = str_replace(array("\r", "\n", "\t", " "), "", $value);
        return preg_match('/^-?[0-9A-Za-z_@:-]{1,80}$/', $value) ? $value : "";
      }
      return substr(preg_replace('/[^0-9]/', '', $value), 0, 20);
    }
  }

  if (!function_exists('tikras_script_clean_url')) {
    function tikras_script_clean_url($value, $fallback)
    {
      $value = trim((string) $value);
      if ($value == "") {
        return "";
      }
      if (preg_match('/^https?\:\/\/[^\s"\']+$/', $value)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_default_callback_url')) {
    function tikras_script_default_callback_url()
    {
      $scheme = (tikras_server('HTTPS') != '' && tikras_server('HTTPS') != 'off') ? 'https' : 'http';
      $host = tikras_server('HTTP_HOST');
      if ($host == "") {
        return "";
      }
      $script = str_replace('\\', '/', tikras_server('SCRIPT_NAME', '/index.php'));
      $base = rtrim(dirname($script), '/');
      if ($base == "/" || $base == ".") {
        $base = "";
      }
      return $scheme . "://" . $host . $base . "/process/ticketautoshare.php";
    }
  }

  if (!function_exists('tikras_script_clean_digits')) {
    function tikras_script_clean_digits($value, $fallback, $maxLength)
    {
      $value = trim((string) $value);
      if (ctype_digit($value) && strlen($value) <= $maxLength) {
        $value = ltrim($value, "0");
        return $value === "" ? "0" : $value;
      }
      return (string) $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_label')) {
    function tikras_script_clean_label($value, $fallback)
    {
      $value = trim((string) $value);
      if ($value !== "" && preg_match('/^[0-9a-zA-Z \.\:\/_-]+$/', $value)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_prefix')) {
    function tikras_script_clean_prefix($value, $fallback)
    {
      $value = trim((string) $value);
      if (preg_match('/^[0-9a-zA-Z_-]{0,12}$/', $value)) {
        return $value;
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_optional_target')) {
    function tikras_script_clean_optional_target($value)
    {
      $value = trim((string) $value);
      if ($value === "" || preg_match('/^[0-9a-zA-Z\.\:\/_-]+$/', $value)) {
        return $value;
      }
      return "";
    }
  }

  if (!function_exists('tikras_script_clean_target_list')) {
    function tikras_script_clean_target_list($value, $fallback)
    {
      $value = trim((string) $value);
      if ($value === "") {
        return $fallback;
      }
      $parts = preg_split('/\s*,\s*/', $value);
      $clean = array();
      foreach ($parts as $part) {
        if ($part !== "" && preg_match('/^[0-9a-zA-Z\.\:\/_-]+$/', $part)) {
          $clean[] = $part;
        }
      }
      return count($clean) > 0 ? implode(",", $clean) : $fallback;
    }
  }

  if (!function_exists('tikras_script_clean_secret')) {
    function tikras_script_clean_secret($value, $fallback)
    {
      $value = trim((string) $value);
      $value = str_replace(array('"', "'", "\\", "\r", "\n", "\t"), "", $value);
      if ($value === "") {
        return $fallback;
      }
      return substr($value, 0, 96);
    }
  }

  if (!function_exists('tikras_script_clean_wireguard_key')) {
    function tikras_script_clean_wireguard_key($value)
    {
      $value = trim((string) $value);
      if ($value === "") {
        return "";
      }
      if (preg_match('/^[A-Za-z0-9+\/=]{20,120}$/', $value)) {
        return $value;
      }
      return "";
    }
  }

  if (!function_exists('tikras_script_first_network')) {
    function tikras_script_first_network($addresses, $fallback)
    {
      if (!is_array($addresses)) {
        return $fallback;
      }
      foreach ($addresses as $addressRow) {
        if (!isset($addressRow['address'])) {
          continue;
        }
        $address = (string) $addressRow['address'];
        if (strpos($address, '/') === false) {
          continue;
        }
        $prefix = substr($address, strpos($address, '/') + 1);
        if (isset($addressRow['network']) && $addressRow['network'] != "" && ctype_digit($prefix)) {
          return $addressRow['network'] . "/" . $prefix;
        }
      }
      return $fallback;
    }
  }

  if (!function_exists('tikras_script_first_gateway')) {
    function tikras_script_first_gateway($routes, $index)
    {
      if (!is_array($routes) || !isset($routes[$index]['gateway'])) {
        return "";
      }
      return preg_replace('/[^0-9a-zA-Z\.\:\/_-]/', '', (string) $routes[$index]['gateway']);
    }
  }

  if (!function_exists('tikras_script_build_templates')) {
    function tikras_script_build_templates($params)
    {
      $cake = <<<'ROS'
:log info "TIKRAS IT - application du modele CAKE QoS";
:local uploadLimit "{{UPLOAD_LIMIT}}";
:local downloadLimit "{{DOWNLOAD_LIMIT}}";
:local targetNetwork "{{TARGET_NETWORK}}";
:local downQueue "tikras-cake-download";
:local upQueue "tikras-cake-upload";

:local downQueueId [/queue type find name=$downQueue];
:if ([:len $downQueueId] = 0) do={
  /queue type add name=$downQueue kind=cake cake-bandwidth=$downloadLimit cake-diffserv=diffserv4 cake-flowmode=dual-dsthost cake-nat=yes
} else={
  :do {
    /queue type set $downQueueId cake-bandwidth=$downloadLimit cake-diffserv=diffserv4 cake-flowmode=dual-dsthost cake-nat=yes
  } on-error={ :log warning "TIKRAS IT - CAKE indisponible sur cette version RouterOS"; }
}

:local upQueueId [/queue type find name=$upQueue];
:if ([:len $upQueueId] = 0) do={
  /queue type add name=$upQueue kind=cake cake-bandwidth=$uploadLimit cake-diffserv=diffserv4 cake-flowmode=dual-srchost cake-nat=yes
} else={
  :do {
    /queue type set $upQueueId cake-bandwidth=$uploadLimit cake-diffserv=diffserv4 cake-flowmode=dual-srchost cake-nat=yes
  } on-error={ :log warning "TIKRAS IT - CAKE indisponible sur cette version RouterOS"; }
}

:if ([:len [/queue simple find name="TIKRAS-IT-CAKE-GLOBAL"]] = 0) do={
  /queue simple add name="TIKRAS-IT-CAKE-GLOBAL" target=$targetNetwork max-limit=($uploadLimit . "/" . $downloadLimit) queue=($upQueue . "/" . $downQueue) comment="TIKRAS IT - CAKE global QoS"
} else={
  /queue simple set [find name="TIKRAS-IT-CAKE-GLOBAL"] target=$targetNetwork max-limit=($uploadLimit . "/" . $downloadLimit) queue=($upQueue . "/" . $downQueue) comment="TIKRAS IT - CAKE global QoS"
}

:log info "TIKRAS IT - CAKE QoS termine";
ROS;

      $social = <<<'ROS'
:log info "TIKRAS IT - optimisation bande passante reseaux sociaux";
:local downloadLimit "{{DOWNLOAD_LIMIT}}";
:local wanInterface "{{WAN_INTERFACE}}";
:local socialList "tikras-social-media";
:local socialHosts {"facebook.com";"fbcdn.net";"instagram.com";"cdninstagram.com";"whatsapp.com";"whatsapp.net";"tiktok.com";"tiktokcdn.com";"youtube.com";"googlevideo.com";"snapchat.com";"twitter.com";"x.com"};

:foreach host in=$socialHosts do={
  :if ([:len [/ip firewall address-list find list=$socialList address=$host]] = 0) do={
    :do {
      /ip firewall address-list add list=$socialList address=$host timeout=1d comment="TIKRAS IT - social media dynamic"
    } on-error={ :log warning ("TIKRAS IT - adresse sociale ignoree: " . $host); }
  }
}

:if ([:len [/ip firewall mangle find comment="TIKRAS IT - mark social connection"]] = 0) do={
  /ip firewall mangle add chain=prerouting dst-address-list=$socialList action=mark-connection new-connection-mark=tikras-social-media passthrough=yes comment="TIKRAS IT - mark social connection"
}

:if ([:len [/ip firewall mangle find comment="TIKRAS IT - mark social packet"]] = 0) do={
  /ip firewall mangle add chain=prerouting connection-mark=tikras-social-media action=mark-packet new-packet-mark=tikras-social-media-packet passthrough=no comment="TIKRAS IT - mark social packet"
}

:do {
  :if ([:len [/queue tree find name="TIKRAS-IT-SOCIAL-DOWN"]] = 0) do={
    /queue tree add name="TIKRAS-IT-SOCIAL-DOWN" parent=$wanInterface packet-mark=tikras-social-media-packet max-limit=$downloadLimit priority=5 queue=default comment="TIKRAS IT - social bandwidth"
  } else={
    /queue tree set [find name="TIKRAS-IT-SOCIAL-DOWN"] parent=$wanInterface packet-mark=tikras-social-media-packet max-limit=$downloadLimit priority=5 queue=default comment="TIKRAS IT - social bandwidth"
  }
} on-error={ :log warning "TIKRAS IT - queue tree social non appliquee"; }

:log info "TIKRAS IT - optimisation reseaux sociaux terminee";
ROS;

      $protection = <<<'ROS'
:log info "TIKRAS IT - application protections routeur";

:foreach service in={"telnet";"ftp";"www"} do={
  :do {
    /ip service set [find name=$service] disabled=yes
  } on-error={ :log warning ("TIKRAS IT - service introuvable: " . $service); }
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - accept established input"]] = 0) do={
  /ip firewall filter add chain=input action=accept connection-state=established,related comment="TIKRAS IT - accept established input"
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - drop invalid input"]] = 0) do={
  /ip firewall filter add chain=input action=drop connection-state=invalid comment="TIKRAS IT - drop invalid input"
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - drop invalid forward"]] = 0) do={
  /ip firewall filter add chain=forward action=drop connection-state=invalid comment="TIKRAS IT - drop invalid forward"
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - block insecure management ports"]] = 0) do={
  /ip firewall filter add chain=input action=drop protocol=tcp dst-port=21,23 comment="TIKRAS IT - block insecure management ports"
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - drop port scanners"]] = 0) do={
  /ip firewall filter add chain=input action=drop src-address-list=tikras-port-scanners comment="TIKRAS IT - drop port scanners"
}

:log info "TIKRAS IT - protections routeur terminees";
ROS;

      $automation = <<<'ROS'
:log info "TIKRAS IT - automatisation routeur";
:local schedulerTime "{{SCHEDULER_TIME}}";
:local backupEvent "/system backup save name=(\"tikras-auto-\" . [/system identity get name]); /export file=(\"tikras-export-\" . [/system identity get name]); /ip dns cache flush; /log info \"TIKRAS IT - sauvegarde automatique terminee\"";

:if ([:len [/system scheduler find name="TIKRAS-IT-AUTO-BACKUP"]] = 0) do={
  /system scheduler add name="TIKRAS-IT-AUTO-BACKUP" interval=1d start-time=$schedulerTime on-event=$backupEvent comment="TIKRAS IT - sauvegarde et maintenance auto"
} else={
  /system scheduler set [find name="TIKRAS-IT-AUTO-BACKUP"] interval=1d start-time=$schedulerTime on-event=$backupEvent comment="TIKRAS IT - sauvegarde et maintenance auto"
}

:do {
  /system logging action set memory memory-lines=1000
} on-error={ :log warning "TIKRAS IT - journal memoire non modifie"; }

:do {
  /ip dns cache flush
} on-error={ :log warning "TIKRAS IT - cache DNS non vide"; }

:log info "TIKRAS IT - automatisation routeur terminee";
ROS;

      $hotspotComplete = <<<'ROS'
:log warning "TIKRAS IT - configuration complete Bridge + DHCP + Hotspot";
:local wanInterface "{{HOTSPOT_WAN_INTERFACE}}";
:local bridgeName "{{BRIDGE_NAME}}";
:local bridgePorts "{{BRIDGE_PORTS}}";
:local lanAddress "{{LAN_ADDRESS}}";
:local lanNetwork "{{LAN_NETWORK}}";
:local dhcpPoolName "{{DHCP_POOL_NAME}}";
:local dhcpPoolRange "{{DHCP_POOL_RANGE}}";
:local dnsServers "{{DNS_SERVERS}}";
:local hotspotServer "{{HOTSPOT_SERVER_NAME}}";
:local hotspotProfile "{{HOTSPOT_SERVER_PROFILE}}";
:local userProfile "{{HOTSPOT_USER_PROFILE}}";
:local hotspotDnsName "{{HOTSPOT_DNS_NAME}}";
:local testUser "{{HOTSPOT_TEST_USER}}";
:local testPassword "{{HOTSPOT_TEST_PASSWORD}}";
:local sharedUsers "{{HOTSPOT_SHARED_USERS}}";
:local rateLimit "{{HOTSPOT_USER_UPLOAD}}/{{HOTSPOT_USER_DOWNLOAD}}";
:local slash [:find $lanAddress "/"];
:local gatewayAddress $lanAddress;
:if ($slash != nil) do={ :set gatewayAddress [:pick $lanAddress 0 $slash]; }

:if ([:len [/interface bridge find name=$bridgeName]] = 0) do={
  /interface bridge add name=$bridgeName protocol-mode=rstp comment="TIKRAS IT - bridge Hotspot"
} else={
  /interface bridge set [find name=$bridgeName] protocol-mode=rstp comment="TIKRAS IT - bridge Hotspot"
}

:local remaining $bridgePorts;
:while ([:len $remaining] > 0) do={
  :local comma [:find $remaining ","];
  :local port "";
  :if ($comma = nil) do={
    :set port $remaining;
    :set remaining "";
  } else={
    :set port [:pick $remaining 0 $comma];
    :set remaining [:pick $remaining ($comma + 1) [:len $remaining]];
  }
  :if ($port != "") do={
    :if ([:len [/interface find name=$port]] > 0) do={
      :if ([:len [/interface bridge port find interface=$port]] = 0) do={
        /interface bridge port add bridge=$bridgeName interface=$port comment="TIKRAS IT - port LAN Hotspot"
      } else={
        /interface bridge port set [find interface=$port] bridge=$bridgeName disabled=no comment="TIKRAS IT - port LAN Hotspot"
      }
    } else={
      :log warning ("TIKRAS IT - port LAN introuvable: " . $port);
    }
  }
}

:local lanAddressId [/ip address find address=$lanAddress];
:if ([:len $lanAddressId] = 0) do={
  /ip address add address=$lanAddress interface=$bridgeName comment="TIKRAS IT - gateway Hotspot"
} else={
  /ip address set $lanAddressId interface=$bridgeName disabled=no comment="TIKRAS IT - gateway Hotspot"
}

:if ([:len [/ip pool find name=$dhcpPoolName]] = 0) do={
  /ip pool add name=$dhcpPoolName ranges=$dhcpPoolRange comment="TIKRAS IT - pool Hotspot"
} else={
  /ip pool set [find name=$dhcpPoolName] ranges=$dhcpPoolRange comment="TIKRAS IT - pool Hotspot"
}

:if ([:len [/ip dhcp-server find name="TIKRAS-IT-HOTSPOT-DHCP"]] = 0) do={
  /ip dhcp-server add name="TIKRAS-IT-HOTSPOT-DHCP" interface=$bridgeName address-pool=$dhcpPoolName lease-time=1h disabled=no comment="TIKRAS IT - DHCP Hotspot"
} else={
  /ip dhcp-server set [find name="TIKRAS-IT-HOTSPOT-DHCP"] interface=$bridgeName address-pool=$dhcpPoolName lease-time=1h disabled=no comment="TIKRAS IT - DHCP Hotspot"
}

:if ([:len [/ip dhcp-server network find address=$lanNetwork]] = 0) do={
  /ip dhcp-server network add address=$lanNetwork gateway=$gatewayAddress dns-server=$gatewayAddress comment="TIKRAS IT - DHCP network Hotspot"
} else={
  /ip dhcp-server network set [find address=$lanNetwork] gateway=$gatewayAddress dns-server=$gatewayAddress comment="TIKRAS IT - DHCP network Hotspot"
}

:do {
  /ip dns set allow-remote-requests=yes servers=$dnsServers cache-size=4096KiB cache-max-ttl=1d
} on-error={ :log warning "TIKRAS IT - DNS non modifie"; }

:if ($hotspotDnsName != "") do={
  :if ([:len [/ip dns static find name=$hotspotDnsName]] = 0) do={
    /ip dns static add name=$hotspotDnsName address=$gatewayAddress ttl=1h comment="TIKRAS IT - portail Hotspot"
  } else={
    /ip dns static set [find name=$hotspotDnsName] address=$gatewayAddress ttl=1h comment="TIKRAS IT - portail Hotspot"
  }
}

:if ([:len [/ip firewall nat find comment="TIKRAS IT - NAT Hotspot WAN"]] = 0) do={
  /ip firewall nat add chain=srcnat out-interface=$wanInterface action=masquerade comment="TIKRAS IT - NAT Hotspot WAN"
} else={
  /ip firewall nat set [find comment="TIKRAS IT - NAT Hotspot WAN"] chain=srcnat out-interface=$wanInterface action=masquerade disabled=no
}

:if ([:len [/ip hotspot profile find name=$hotspotProfile]] = 0) do={
  /ip hotspot profile add name=$hotspotProfile hotspot-address=$gatewayAddress dns-name=$hotspotDnsName html-directory=hotspot login-by=http-chap,http-pap,cookie use-radius=no
} else={
  /ip hotspot profile set [find name=$hotspotProfile] hotspot-address=$gatewayAddress dns-name=$hotspotDnsName html-directory=hotspot login-by=http-chap,http-pap,cookie use-radius=no
}

:if ([:len [/ip hotspot find name=$hotspotServer]] = 0) do={
  /ip hotspot add name=$hotspotServer interface=$bridgeName address-pool=$dhcpPoolName profile=$hotspotProfile disabled=no
} else={
  /ip hotspot set [find name=$hotspotServer] interface=$bridgeName address-pool=$dhcpPoolName profile=$hotspotProfile disabled=no
}

:if ([:len [/ip hotspot user profile find name=$userProfile]] = 0) do={
  /ip hotspot user profile add name=$userProfile rate-limit=$rateLimit shared-users=$sharedUsers keepalive-timeout=2m status-autorefresh=1m
} else={
  /ip hotspot user profile set [find name=$userProfile] rate-limit=$rateLimit shared-users=$sharedUsers keepalive-timeout=2m status-autorefresh=1m
}

:if ([:len [/ip hotspot user find name=$testUser]] = 0) do={
  /ip hotspot user add server=all name=$testUser password=$testPassword profile=$userProfile comment="TIKRAS IT - utilisateur test Hotspot"
}

:log warning ("TIKRAS IT - Hotspot fonctionnel cree sur bridge " . $bridgeName . " avec gateway " . $gatewayAddress);
ROS;

      $hotspotBoost = <<<'ROS'
:log info "TIKRAS IT - optimisation experience utilisateurs Hotspot";
:local lanNetwork "{{LAN_NETWORK}}";
:local userProfile "{{HOTSPOT_USER_PROFILE}}";
:local uploadRate "{{HOTSPOT_USER_UPLOAD}}";
:local downloadRate "{{HOTSPOT_USER_DOWNLOAD}}";
:local sharedUsers "{{HOTSPOT_SHARED_USERS}}";
:local dnsServers "{{DNS_SERVERS}}";
:local disableFastTrack "{{HOTSPOT_DISABLE_FASTTRACK}}";
:local upQueue "tikras-pcq-hotspot-upload";
:local downQueue "tikras-pcq-hotspot-download";
:local rateLimit ($uploadRate . "/" . $downloadRate);

:do {
  /ip dns set allow-remote-requests=yes servers=$dnsServers cache-size=4096KiB cache-max-ttl=1d
} on-error={ :log warning "TIKRAS IT - DNS cache non optimise"; }

:local upQueueId [/queue type find name=$upQueue];
:if ([:len $upQueueId] = 0) do={
  /queue type add name=$upQueue kind=pcq pcq-rate=$uploadRate pcq-classifier=src-address pcq-total-limit=2000KiB
} else={
  :do { /queue type set $upQueueId pcq-rate=$uploadRate pcq-classifier=src-address pcq-total-limit=2000KiB } on-error={ :log warning "TIKRAS IT - PCQ upload non applique"; }
}

:local downQueueId [/queue type find name=$downQueue];
:if ([:len $downQueueId] = 0) do={
  /queue type add name=$downQueue kind=pcq pcq-rate=$downloadRate pcq-classifier=dst-address pcq-total-limit=2000KiB
} else={
  :do { /queue type set $downQueueId pcq-rate=$downloadRate pcq-classifier=dst-address pcq-total-limit=2000KiB } on-error={ :log warning "TIKRAS IT - PCQ download non applique"; }
}

:if ([:len [/queue simple find name="TIKRAS-IT-HOTSPOT-FAIRNESS"]] = 0) do={
  /queue simple add name="TIKRAS-IT-HOTSPOT-FAIRNESS" target=$lanNetwork max-limit=0/0 queue=($upQueue . "/" . $downQueue) comment="TIKRAS IT - repartition equitable Hotspot"
} else={
  /queue simple set [find name="TIKRAS-IT-HOTSPOT-FAIRNESS"] target=$lanNetwork max-limit=0/0 queue=($upQueue . "/" . $downQueue) disabled=no comment="TIKRAS IT - repartition equitable Hotspot"
}

:do {
  /ip hotspot user profile set [find name=$userProfile] rate-limit=$rateLimit shared-users=$sharedUsers keepalive-timeout=2m status-autorefresh=1m
} on-error={
  :do { /ip hotspot user profile add name=$userProfile rate-limit=$rateLimit shared-users=$sharedUsers keepalive-timeout=2m status-autorefresh=1m } on-error={ :log warning "TIKRAS IT - profil Hotspot non optimise"; }
}

:if ([:len [/ip firewall mangle find comment="TIKRAS IT - clamp TCP MSS Hotspot"]] = 0) do={
  /ip firewall mangle add chain=forward protocol=tcp tcp-flags=syn action=change-mss new-mss=clamp-to-pmtu passthrough=yes comment="TIKRAS IT - clamp TCP MSS Hotspot"
}

:if ($disableFastTrack = "yes") do={
  :foreach rule in=[/ip firewall filter find action=fasttrack-connection] do={
    /ip firewall filter disable $rule;
  }
  :log warning "TIKRAS IT - FastTrack desactive pour laisser les queues Hotspot agir";
}

:log info ("TIKRAS IT - boost Hotspot applique: profil " . $userProfile . " = " . $rateLimit);
ROS;

      $dnsHealth = <<<'ROS'
:log info "TIKRAS IT - configuration DNS, NTP et sante routeur";
:local lanNetwork "{{LAN_NETWORK}}";
:local dnsServers "{{DNS_SERVERS}}";
:local ntpServers "{{NTP_SERVERS}}";

:do {
  /ip dns set allow-remote-requests=yes servers=$dnsServers cache-size=4096KiB cache-max-ttl=1d
} on-error={ :log warning "TIKRAS IT - DNS non modifie"; }

:do {
  /system ntp client set enabled=yes servers=$ntpServers
} on-error={ :log warning "TIKRAS IT - client NTP non modifie"; }

:if ([:len [/ip firewall filter find comment="TIKRAS IT - allow DNS LAN UDP"]] = 0) do={
  /ip firewall filter add chain=input action=accept protocol=udp src-address=$lanNetwork dst-port=53 comment="TIKRAS IT - allow DNS LAN UDP"
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - allow DNS LAN TCP"]] = 0) do={
  /ip firewall filter add chain=input action=accept protocol=tcp src-address=$lanNetwork dst-port=53 comment="TIKRAS IT - allow DNS LAN TCP"
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - block public DNS UDP"]] = 0) do={
  /ip firewall filter add chain=input action=drop protocol=udp dst-port=53 comment="TIKRAS IT - block public DNS UDP"
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - block public DNS TCP"]] = 0) do={
  /ip firewall filter add chain=input action=drop protocol=tcp dst-port=53 comment="TIKRAS IT - block public DNS TCP"
}

:do {
  /system logging action set memory memory-lines=1500
} on-error={ :log warning "TIKRAS IT - journal memoire non modifie"; }

:log info "TIKRAS IT - DNS, NTP et sante routeur termines";
ROS;

      $hotspotHygiene = <<<'ROS'
:log info "TIKRAS IT - planification maintenance Hotspot et DHCP";
:local schedulerTime "{{SCHEDULER_TIME}}";
:local cleanupInterval "{{CLEANUP_INTERVAL}}";
:local cleanupEvent ":local removedCookies 0; :local removedHosts 0; :local removedLeases 0; :foreach c in=[/ip hotspot cookie find] do={ :do { /ip hotspot cookie remove \$c; :set removedCookies (\$removedCookies + 1); } on-error={}; }; :foreach h in=[/ip hotspot host find authorized=no] do={ :do { /ip hotspot host remove \$h; :set removedHosts (\$removedHosts + 1); } on-error={}; }; :foreach l in=[/ip dhcp-server lease find dynamic=yes status=waiting] do={ :do { /ip dhcp-server lease remove \$l; :set removedLeases (\$removedLeases + 1); } on-error={}; }; :log info (\"TIKRAS IT - maintenance Hotspot/DHCP: cookies=\" . \$removedCookies . \", hosts=\" . \$removedHosts . \", leases=\" . \$removedLeases);";

:if ([:len [/system scheduler find name="TIKRAS-IT-HOTSPOT-HYGIENE"]] = 0) do={
  /system scheduler add name="TIKRAS-IT-HOTSPOT-HYGIENE" interval=$cleanupInterval start-time=$schedulerTime on-event=$cleanupEvent comment="TIKRAS IT - maintenance Hotspot et DHCP"
} else={
  /system scheduler set [find name="TIKRAS-IT-HOTSPOT-HYGIENE"] interval=$cleanupInterval start-time=$schedulerTime on-event=$cleanupEvent comment="TIKRAS IT - maintenance Hotspot et DHCP"
}

:log info "TIKRAS IT - maintenance Hotspot et DHCP planifiee";
ROS;

      $managementHardening = <<<'ROS'
:log warning "TIKRAS IT - durcissement acces administration";
:local adminNetwork "{{ADMIN_NETWORK}}";

:foreach service in={"telnet";"ftp";"www"} do={
  :do {
    /ip service set [find name=$service] disabled=yes
  } on-error={ :log warning ("TIKRAS IT - service introuvable: " . $service); }
}

:foreach service in={"ssh";"winbox";"api"} do={
  :do {
    /ip service set [find name=$service] address=$adminNetwork disabled=no
  } on-error={ :log warning ("TIKRAS IT - restriction admin non appliquee: " . $service); }
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - allow admin services trusted"]] = 0) do={
  /ip firewall filter add chain=input action=accept protocol=tcp src-address=$adminNetwork dst-port=22,8291,8728 comment="TIKRAS IT - allow admin services trusted"
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - drop admin services untrusted"]] = 0) do={
  /ip firewall filter add chain=input action=drop protocol=tcp dst-port=22,8291,8728 comment="TIKRAS IT - drop admin services untrusted"
}

:log warning ("TIKRAS IT - acces admin limite au reseau " . $adminNetwork);
ROS;

      $loadbalancing = <<<'ROS'
:log info "TIKRAS IT - configuration load-balancing multi-sites et failover Internet";
:local wanPrimary "{{WAN_PRIMARY_INTERFACE}}";
:local wanSecondary "{{WAN_SECONDARY_INTERFACE}}";
:local gatewayPrimary "{{WAN_PRIMARY_GATEWAY}}";
:local gatewaySecondary "{{WAN_SECONDARY_GATEWAY}}";
:local monitorPrimary "{{WAN_PRIMARY_MONITOR}}";
:local monitorSecondary "{{WAN_SECONDARY_MONITOR}}";
:local healthInterval "{{FAILOVER_INTERVAL}}";
:local schedulerTime "{{SCHEDULER_TIME}}";
:local primaryComment "TIKRAS IT - LB primary";
:local secondaryComment "TIKRAS IT - LB secondary";
:local natComment "TIKRAS IT - NAT WAN list";

:do {
  /interface list add name="TIKRAS-WAN" comment="TIKRAS IT - interfaces Internet multi-sites"
} on-error={ :log info "TIKRAS IT - liste WAN deja presente"; }

:if ($wanPrimary != "") do={
  :do {
    /interface list member add list="TIKRAS-WAN" interface=$wanPrimary comment="TIKRAS IT - WAN primaire"
  } on-error={ :log info ("TIKRAS IT - membre WAN deja present: " . $wanPrimary); }
}

:if ($wanSecondary != "") do={
  :do {
    /interface list member add list="TIKRAS-WAN" interface=$wanSecondary comment="TIKRAS IT - WAN secondaire"
  } on-error={ :log info ("TIKRAS IT - membre WAN deja present: " . $wanSecondary); }
}

:if ([:len [/ip firewall nat find comment=$natComment]] = 0) do={
  /ip firewall nat add chain=srcnat out-interface-list="TIKRAS-WAN" action=masquerade comment=$natComment
} else={
  /ip firewall nat set [find comment=$natComment] chain=srcnat out-interface-list="TIKRAS-WAN" action=masquerade
}

:if ($gatewayPrimary != "") do={
  :if ([:len [/ip route find comment=$primaryComment]] = 0) do={
    :do {
      /ip route add dst-address=0.0.0.0/0 gateway=$gatewayPrimary distance=1 check-gateway=ping comment=$primaryComment
    } on-error={
      /ip route add dst-address=0.0.0.0/0 gateway=$gatewayPrimary distance=1 comment=$primaryComment
    }
  } else={
    :do {
      /ip route set [find comment=$primaryComment] gateway=$gatewayPrimary distance=1 check-gateway=ping disabled=no
    } on-error={
      /ip route set [find comment=$primaryComment] gateway=$gatewayPrimary distance=1 disabled=no
    }
  }
} else={
  :log warning "TIKRAS IT - passerelle primaire vide, route primaire non creee";
}

:if ($gatewaySecondary != "") do={
  :if ([:len [/ip route find comment=$secondaryComment]] = 0) do={
    :do {
      /ip route add dst-address=0.0.0.0/0 gateway=$gatewaySecondary distance=1 check-gateway=ping comment=$secondaryComment
    } on-error={
      /ip route add dst-address=0.0.0.0/0 gateway=$gatewaySecondary distance=1 comment=$secondaryComment
    }
  } else={
    :do {
      /ip route set [find comment=$secondaryComment] gateway=$gatewaySecondary distance=1 check-gateway=ping disabled=no
    } on-error={
      /ip route set [find comment=$secondaryComment] gateway=$gatewaySecondary distance=1 disabled=no
    }
  }
} else={
  :log warning "TIKRAS IT - passerelle secondaire vide, route secondaire non creee";
}

:local healthEvent (":local primaryRoutes [/ip route find comment=\"TIKRAS IT - LB primary\"]; :if ([:len \$primaryRoutes] > 0) do={ :if ([/ping " . $monitorPrimary . " count=3] = 0) do={ /ip route disable \$primaryRoutes; :log warning \"TIKRAS IT - WAN primaire hors ligne\"; } else={ /ip route enable \$primaryRoutes; }; }; :local secondaryRoutes [/ip route find comment=\"TIKRAS IT - LB secondary\"]; :if ([:len \$secondaryRoutes] > 0) do={ :if ([/ping " . $monitorSecondary . " count=3] = 0) do={ /ip route disable \$secondaryRoutes; :log warning \"TIKRAS IT - WAN secondaire hors ligne\"; } else={ /ip route enable \$secondaryRoutes; }; };");

:if ([:len [/system scheduler find name="TIKRAS-IT-WAN-FAILOVER"]] = 0) do={
  /system scheduler add name="TIKRAS-IT-WAN-FAILOVER" interval=$healthInterval start-time=$schedulerTime on-event=$healthEvent comment="TIKRAS IT - controle failover Internet"
} else={
  /system scheduler set [find name="TIKRAS-IT-WAN-FAILOVER"] interval=$healthInterval start-time=$schedulerTime on-event=$healthEvent comment="TIKRAS IT - controle failover Internet"
}

:log info "TIKRAS IT - load-balancing ECMP et failover Internet termines";
ROS;

      $wireguard = <<<'ROS'
:log warning "TIKRAS IT - configuration interconnexion WireGuard";
:local wgRole "{{WG_ROLE}}";
:local wgName "{{WG_INTERFACE}}";
:local wgAddress "{{WG_ADDRESS}}";
:local wgPort "{{WG_LISTEN_PORT}}";
:local wanInterface "{{WG_WAN_INTERFACE}}";
:local trustedNetwork "{{WG_TRUSTED_NETWORK}}";
:local peerName "{{WG_PEER_NAME}}";
:local peerPublicKey "{{WG_PEER_PUBLIC_KEY}}";
:local peerEndpoint "{{WG_PEER_ENDPOINT}}";
:local peerEndpointPort "{{WG_PEER_ENDPOINT_PORT}}";
:local peerAllowed "{{WG_ALLOWED_ADDRESS}}";
:local routeNetworks "{{WG_ROUTE_NETWORKS}}";
:local keepalive "{{WG_KEEPALIVE}}";
:local allowManagement "{{WG_ALLOW_MANAGEMENT}}";
:local natBypass "{{WG_NAT_BYPASS}}";
:local adminNetwork "{{ADMIN_NETWORK}}";

:if ([:len [/interface wireguard find name=$wgName]] = 0) do={
  /interface wireguard add name=$wgName listen-port=$wgPort mtu=1420 comment=("TIKRAS IT - WireGuard " . $wgRole)
} else={
  /interface wireguard set [find name=$wgName] listen-port=$wgPort mtu=1420 disabled=no comment=("TIKRAS IT - WireGuard " . $wgRole)
}

:if ([:len [/ip address find address=$wgAddress interface=$wgName]] = 0) do={
  /ip address add address=$wgAddress interface=$wgName comment="TIKRAS IT - adresse tunnel WireGuard"
} else={
  /ip address set [find address=$wgAddress interface=$wgName] disabled=no comment="TIKRAS IT - adresse tunnel WireGuard"
}

:local publicKey "";
:do { :set publicKey [/interface wireguard get [find name=$wgName] public-key]; } on-error={};
:if ($publicKey != "") do={ :log warning ("TIKRAS IT - cle publique locale WireGuard: " . $publicKey); }

:if ($peerPublicKey = "") do={
  :log warning "TIKRAS IT - peer WireGuard non cree: renseigner la cle publique du routeur distant";
} else={
  :local peerComment ("TIKRAS IT - WG peer " . $peerName);
  :local peerId [/interface wireguard peers find comment=$peerComment];
  :if ([:len $peerId] = 0) do={
    /interface wireguard peers add interface=$wgName public-key=$peerPublicKey allowed-address=$peerAllowed persistent-keepalive=$keepalive comment=$peerComment
  } else={
    /interface wireguard peers set $peerId interface=$wgName public-key=$peerPublicKey allowed-address=$peerAllowed persistent-keepalive=$keepalive disabled=no comment=$peerComment
  }
  :set peerId [/interface wireguard peers find comment=$peerComment];
  :if ($peerEndpoint != "") do={
    /interface wireguard peers set $peerId endpoint-address=$peerEndpoint endpoint-port=$peerEndpointPort
  }
}

:if ($wanInterface != "") do={
  :if ([:len [/ip firewall filter find comment="TIKRAS IT - allow WireGuard UDP"]] = 0) do={
    :do {
      /ip firewall filter add chain=input action=accept protocol=udp dst-port=$wgPort in-interface=$wanInterface place-before=0 comment="TIKRAS IT - allow WireGuard UDP"
    } on-error={
      /ip firewall filter add chain=input action=accept protocol=udp dst-port=$wgPort in-interface=$wanInterface comment="TIKRAS IT - allow WireGuard UDP"
    }
  } else={
    /ip firewall filter set [find comment="TIKRAS IT - allow WireGuard UDP"] chain=input action=accept protocol=udp dst-port=$wgPort in-interface=$wanInterface disabled=no
  }
}

:if ($trustedNetwork != "") do={
  :if ([:len [/ip firewall filter find comment="TIKRAS IT - allow WireGuard tunnel input"]] = 0) do={
    :do {
      /ip firewall filter add chain=input action=accept in-interface=$wgName src-address=$trustedNetwork place-before=0 comment="TIKRAS IT - allow WireGuard tunnel input"
    } on-error={
      /ip firewall filter add chain=input action=accept in-interface=$wgName src-address=$trustedNetwork comment="TIKRAS IT - allow WireGuard tunnel input"
    }
  } else={
    /ip firewall filter set [find comment="TIKRAS IT - allow WireGuard tunnel input"] chain=input action=accept in-interface=$wgName src-address=$trustedNetwork disabled=no
  }
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - allow WireGuard forward in"]] = 0) do={
  :do {
    /ip firewall filter add chain=forward action=accept in-interface=$wgName place-before=0 comment="TIKRAS IT - allow WireGuard forward in"
  } on-error={
    /ip firewall filter add chain=forward action=accept in-interface=$wgName comment="TIKRAS IT - allow WireGuard forward in"
  }
} else={
  /ip firewall filter set [find comment="TIKRAS IT - allow WireGuard forward in"] chain=forward action=accept in-interface=$wgName disabled=no
}

:if ([:len [/ip firewall filter find comment="TIKRAS IT - allow WireGuard forward out"]] = 0) do={
  :do {
    /ip firewall filter add chain=forward action=accept out-interface=$wgName place-before=0 comment="TIKRAS IT - allow WireGuard forward out"
  } on-error={
    /ip firewall filter add chain=forward action=accept out-interface=$wgName comment="TIKRAS IT - allow WireGuard forward out"
  }
} else={
  /ip firewall filter set [find comment="TIKRAS IT - allow WireGuard forward out"] chain=forward action=accept out-interface=$wgName disabled=no
}

:local remainingRoutes $routeNetworks;
:while ([:len $remainingRoutes] > 0) do={
  :local comma [:find $remainingRoutes ","];
  :local remoteNet "";
  :if ($comma = nil) do={
    :set remoteNet $remainingRoutes;
    :set remainingRoutes "";
  } else={
    :set remoteNet [:pick $remainingRoutes 0 $comma];
    :set remainingRoutes [:pick $remainingRoutes ($comma + 1) [:len $remainingRoutes]];
  }
  :if ($remoteNet != "") do={
    :local routeComment ("TIKRAS IT - WG route " . $remoteNet);
    :if ([:len [/ip route find comment=$routeComment]] = 0) do={
      /ip route add dst-address=$remoteNet gateway=$wgName comment=$routeComment
    } else={
      /ip route set [find comment=$routeComment] dst-address=$remoteNet gateway=$wgName disabled=no
    }
    :if ($natBypass = "yes") do={
      :local natComment ("TIKRAS IT - no NAT WG " . $remoteNet);
      :if ([:len [/ip firewall nat find comment=$natComment]] = 0) do={
        :do {
          /ip firewall nat add chain=srcnat dst-address=$remoteNet action=accept place-before=0 comment=$natComment
        } on-error={
          /ip firewall nat add chain=srcnat dst-address=$remoteNet action=accept comment=$natComment
        }
      } else={
        /ip firewall nat set [find comment=$natComment] chain=srcnat dst-address=$remoteNet action=accept disabled=no
      }
    }
  }
}

:if ($allowManagement = "yes") do={
  :local serviceAddresses $trustedNetwork;
  :if (($adminNetwork != "") and ($trustedNetwork != "")) do={
    :set serviceAddresses ($adminNetwork . "," . $trustedNetwork);
  } else={
    :if ($adminNetwork != "") do={ :set serviceAddresses $adminNetwork; }
  }
  :if ($serviceAddresses != "") do={
    :foreach service in={"ssh";"winbox";"api"} do={
      :do { /ip service set [find name=$service] address=$serviceAddresses disabled=no } on-error={ :log warning ("TIKRAS IT - service admin non modifie: " . $service); }
    }
  }
}

:log warning ("TIKRAS IT - WireGuard " . $wgRole . " pret sur " . $wgName . ". Conserver la cle publique locale pour configurer le routeur distant.");
ROS;

      $radiusRoaming = <<<'ROS'
:log warning "TIKRAS IT - configuration roaming RADIUS central";
:local radiusAddress "{{RADIUS_SERVER_ADDRESS}}";
:local radiusSecret "{{RADIUS_SECRET}}";
:local authPort "{{RADIUS_AUTH_PORT}}";
:local acctPort "{{RADIUS_ACCT_PORT}}";
:local radiusTimeout "{{RADIUS_TIMEOUT}}";
:local radiusServices "{{RADIUS_SERVICES}}";
:local hotspotMode "{{RADIUS_HOTSPOT_MODE}}";
:local hotspotProfile "{{RADIUS_HOTSPOT_PROFILE}}";
:local enablePpp "{{RADIUS_ENABLE_PPP}}";
:local interimUpdate "{{RADIUS_INTERIM_UPDATE}}";
:local srcAddress "{{RADIUS_SRC_ADDRESS}}";
:local useAccounting "{{RADIUS_ACCOUNTING}}";
:local radiusComment "TIKRAS IT - central RADIUS";

:if (($radiusAddress = "") or ($radiusSecret = "")) do={
  :log warning "TIKRAS IT - adresse RADIUS ou secret vide, configuration annulee";
} else={
  :local radiusId [/radius find comment=$radiusComment];
  :if ([:len $radiusId] = 0) do={
    :if ($srcAddress != "") do={
      /radius add service=$radiusServices address=$radiusAddress secret=$radiusSecret authentication-port=$authPort accounting-port=$acctPort timeout=$radiusTimeout src-address=$srcAddress comment=$radiusComment disabled=no
    } else={
      /radius add service=$radiusServices address=$radiusAddress secret=$radiusSecret authentication-port=$authPort accounting-port=$acctPort timeout=$radiusTimeout comment=$radiusComment disabled=no
    }
  } else={
    :if ($srcAddress != "") do={
      /radius set $radiusId service=$radiusServices address=$radiusAddress secret=$radiusSecret authentication-port=$authPort accounting-port=$acctPort timeout=$radiusTimeout src-address=$srcAddress disabled=no
    } else={
      /radius set $radiusId service=$radiusServices address=$radiusAddress secret=$radiusSecret authentication-port=$authPort accounting-port=$acctPort timeout=$radiusTimeout src-address=0.0.0.0 disabled=no
    }
  }

  :if (($hotspotMode = "all") or ($hotspotMode = "selected")) do={
    :if ($hotspotMode = "all") do={
      :foreach hp in=[/ip hotspot profile find] do={
        :do {
          /ip hotspot profile set $hp use-radius=yes radius-accounting=$useAccounting radius-interim-update=$interimUpdate
        } on-error={ :log warning "TIKRAS IT - profil Hotspot non modifie pour RADIUS"; }
      }
      :log warning "TIKRAS IT - RADIUS active sur tous les profils Hotspot";
    }
    :if ($hotspotMode = "selected") do={
      :local hpId [/ip hotspot profile find name=$hotspotProfile];
      :if ([:len $hpId] > 0) do={
        /ip hotspot profile set $hpId use-radius=yes radius-accounting=$useAccounting radius-interim-update=$interimUpdate
        :log warning ("TIKRAS IT - RADIUS active sur profil Hotspot " . $hotspotProfile);
      } else={
        :log warning ("TIKRAS IT - profil Hotspot introuvable: " . $hotspotProfile);
      }
    }
  }

  :if ($enablePpp = "yes") do={
    :do {
      /ppp aaa set use-radius=yes accounting=yes interim-update=$interimUpdate
      :log warning "TIKRAS IT - RADIUS active pour PPP";
    } on-error={ :log warning "TIKRAS IT - PPP AAA non modifie"; }
  }

  :do {
    /radius incoming set accept=yes
  } on-error={ :log info "TIKRAS IT - RADIUS incoming non modifie"; }

  :log warning ("TIKRAS IT - routeur pret comme client RADIUS vers " . $radiusAddress . ". Ajouter ce routeur/NAS dans le serveur RADIUS avec le meme secret.");
}
ROS;

      $ticketAutomation = <<<'ROS'
:log info "TIKRAS IT - planification generation automatique de tickets";
:local ticketInterval "{{TICKET_INTERVAL}}";
:local schedulerTime "{{SCHEDULER_TIME}}";
:local generatorSource ":local ticketQty {{TICKET_QTY}}; :local ticketLength {{TICKET_LENGTH}}; :local ticketPrefix \"{{TICKET_PREFIX}}\"; :local ticketProfile \"{{TICKET_PROFILE}}\"; :local ticketServer \"{{TICKET_SERVER}}\"; :local ticketUptime \"{{TICKET_UPTIME}}\"; :local ticketBytes \"{{TICKET_BYTES}}\"; :local ticketTrigger \"{{TICKET_TRIGGER}}\"; :local ticketStockMin {{TICKET_STOCK_MIN}}; :local ticketStockTarget {{TICKET_STOCK_TARGET}}; :local ticketMode \"{{TICKET_MODE}}\"; :local notifyUrl \"{{TICKET_NOTIFY_URL}}\"; :local notifySession \"{{TICKET_NOTIFY_SESSION}}\"; :local notifyToken \"{{TICKET_NOTIFY_TOKEN}}\"; :local shareChannel \"{{TICKET_SHARE_CHANNEL}}\"; :local shareTarget \"{{TICKET_SHARE_TARGET}}\"; :local alphabet \"{{TICKET_ALPHABET}}\"; :local alphabetLen [:len \$alphabet]; :local nowTime [/system clock get time]; :local currentStock [:len [/ip hotspot user find profile=\$ticketProfile uptime=0s]]; :if (\$ticketServer != \"all\") do={ :set currentStock [:len [/ip hotspot user find profile=\$ticketProfile server=\$ticketServer uptime=0s]]; }; :if (\$ticketStockTarget < \$ticketStockMin) do={ :set ticketStockTarget \$ticketStockMin; }; :if ((\$ticketTrigger = \"stock\") and (\$currentStock > \$ticketStockMin)) do={ :log info (\"TIKRAS IT - stock tickets suffisant pour \" . \$ticketProfile . \": \" . \$currentStock); } else={ :local ticketsToCreate \$ticketQty; :if (\$ticketTrigger = \"stock\") do={ :set ticketsToCreate (\$ticketStockTarget - \$currentStock); :if (\$ticketsToCreate < 1) do={ :set ticketsToCreate 1; }; }; :local hh [:tonum [:pick \$nowTime 0 2]]; :local mm [:tonum [:pick \$nowTime 3 5]]; :local ss [:tonum [:pick \$nowTime 6 8]]; :local currentUsers [:len [/ip hotspot user find]]; :local batchComment (\"tikras-auto-\" . [:pick \$nowTime 0 2] . [:pick \$nowTime 3 5] . [:pick \$nowTime 6 8] . \"-\" . \$currentUsers); :for i from=1 to=\$ticketsToCreate do={ :local seed ((\$hh * 3600) + (\$mm * 60) + \$ss + \$i + \$currentUsers); :local code \"\"; :for j from=1 to=\$ticketLength do={ :local idx ((\$seed + (\$j * 7) + (\$i * 13)) % \$alphabetLen); :set code (\$code . [:pick \$alphabet \$idx (\$idx + 1)]); :set seed ((\$seed * 17 + \$j + \$i) % 9973); }; :local username (\$ticketPrefix . \$code); :local password \$username; :if (\$ticketMode = \"up\") do={ :local passCode \"\"; :set seed (\$seed + 7919); :for j from=1 to=\$ticketLength do={ :local idx ((\$seed + (\$j * 11) + (\$i * 19)) % \$alphabetLen); :set passCode (\$passCode . [:pick \$alphabet \$idx (\$idx + 1)]); :set seed ((\$seed * 23 + \$j + \$i) % 9949); }; :set password \$passCode; }; :if ([:len [/ip hotspot user find name=\$username]] = 0) do={ :do { /ip hotspot user add server=\$ticketServer name=\$username password=\$password profile=\$ticketProfile limit-uptime=\$ticketUptime limit-bytes-total=\$ticketBytes comment=\$batchComment; :log info (\"TIKRAS IT - ticket cree: \" . \$username); } on-error={ :log warning (\"TIKRAS IT - ticket ignore: \" . \$username); }; } else={ :log warning (\"TIKRAS IT - ticket deja existant: \" . \$username); }; }; :if ((\$notifyUrl != \"\") and ((\$shareTarget != \"\") or (\$shareChannel = \"telegram\"))) do={ :local notifyData (\"session=\" . \$notifySession . \"&token=\" . \$notifyToken . \"&comment=\" . \$batchComment . \"&channel=\" . \$shareChannel . \"&target=\" . \$shareTarget); :do { /tool fetch url=\$notifyUrl http-method=post http-data=\$notifyData keep-result=no; :log info \"TIKRAS IT - demande d'envoi PDF tickets transmise\"; } on-error={ :log warning \"TIKRAS IT - envoi PDF tickets non transmis\"; }; }; :log info (\"TIKRAS IT - generation tickets terminee: \" . \$ticketsToCreate . \" cree(s), stock avant \" . \$currentStock); }";

:if ([:len [/system script find name="TIKRAS-IT-GENERATE-TICKETS"]] = 0) do={
  /system script add name="TIKRAS-IT-GENERATE-TICKETS" source=$generatorSource comment="TIKRAS IT - generation tickets automatisee"
} else={
  /system script set [find name="TIKRAS-IT-GENERATE-TICKETS"] source=$generatorSource comment="TIKRAS IT - generation tickets automatisee"
}

:if ([:len [/system scheduler find name="TIKRAS-IT-AUTO-TICKETS"]] = 0) do={
  /system scheduler add name="TIKRAS-IT-AUTO-TICKETS" interval=$ticketInterval start-time=$schedulerTime on-event="/system script run TIKRAS-IT-GENERATE-TICKETS" comment="TIKRAS IT - tickets automatiques"
} else={
  /system scheduler set [find name="TIKRAS-IT-AUTO-TICKETS"] interval=$ticketInterval start-time=$schedulerTime on-event="/system script run TIKRAS-IT-GENERATE-TICKETS" comment="TIKRAS IT - tickets automatiques"
}

:log info "TIKRAS IT - generation automatique de tickets planifiee";
ROS;

      $replace = array(
        '{{UPLOAD_LIMIT}}' => $params['upload_limit'],
        '{{DOWNLOAD_LIMIT}}' => $params['download_limit'],
        '{{TARGET_NETWORK}}' => $params['target_network'],
        '{{WAN_INTERFACE}}' => $params['wan_interface'],
        '{{WAN_PRIMARY_INTERFACE}}' => $params['wan_primary_interface'],
        '{{WAN_SECONDARY_INTERFACE}}' => $params['wan_secondary_interface'],
        '{{WAN_PRIMARY_GATEWAY}}' => $params['wan_primary_gateway'],
        '{{WAN_SECONDARY_GATEWAY}}' => $params['wan_secondary_gateway'],
        '{{WAN_PRIMARY_MONITOR}}' => $params['wan_primary_monitor'],
        '{{WAN_SECONDARY_MONITOR}}' => $params['wan_secondary_monitor'],
        '{{FAILOVER_INTERVAL}}' => $params['failover_interval'],
        '{{LAN_NETWORK}}' => $params['lan_network'],
        '{{ADMIN_NETWORK}}' => $params['admin_network'],
        '{{LAN_ADDRESS}}' => $params['lan_address'],
        '{{HOTSPOT_WAN_INTERFACE}}' => $params['hotspot_wan_interface'],
        '{{BRIDGE_NAME}}' => $params['bridge_name'],
        '{{BRIDGE_PORTS}}' => $params['bridge_ports'],
        '{{DHCP_POOL_NAME}}' => $params['dhcp_pool_name'],
        '{{DHCP_POOL_RANGE}}' => $params['dhcp_pool_range'],
        '{{HOTSPOT_SERVER_NAME}}' => $params['hotspot_server_name'],
        '{{HOTSPOT_SERVER_PROFILE}}' => $params['hotspot_server_profile'],
        '{{HOTSPOT_USER_PROFILE}}' => $params['hotspot_user_profile'],
        '{{HOTSPOT_DNS_NAME}}' => $params['hotspot_dns_name'],
        '{{HOTSPOT_TEST_USER}}' => $params['hotspot_test_user'],
        '{{HOTSPOT_TEST_PASSWORD}}' => $params['hotspot_test_password'],
        '{{HOTSPOT_USER_UPLOAD}}' => $params['hotspot_user_upload'],
        '{{HOTSPOT_USER_DOWNLOAD}}' => $params['hotspot_user_download'],
        '{{HOTSPOT_SHARED_USERS}}' => $params['hotspot_shared_users'],
        '{{HOTSPOT_DISABLE_FASTTRACK}}' => $params['hotspot_disable_fasttrack'],
        '{{DNS_SERVERS}}' => $params['dns_servers'],
        '{{NTP_SERVERS}}' => $params['ntp_servers'],
        '{{CLEANUP_INTERVAL}}' => $params['cleanup_interval'],
        '{{SCHEDULER_TIME}}' => $params['scheduler_time'],
        '{{TICKET_INTERVAL}}' => $params['ticket_interval'],
        '{{TICKET_QTY}}' => $params['ticket_qty'],
        '{{TICKET_PREFIX}}' => $params['ticket_prefix'],
        '{{TICKET_LENGTH}}' => $params['ticket_length'],
        '{{TICKET_PROFILE}}' => $params['ticket_profile'],
        '{{TICKET_SERVER}}' => $params['ticket_server'],
        '{{TICKET_UPTIME}}' => $params['ticket_uptime'],
        '{{TICKET_BYTES}}' => $params['ticket_bytes'],
        '{{TICKET_TRIGGER}}' => $params['ticket_trigger'],
        '{{TICKET_STOCK_MIN}}' => $params['ticket_stock_min'],
        '{{TICKET_STOCK_TARGET}}' => $params['ticket_stock_target'],
        '{{TICKET_MODE}}' => $params['ticket_mode'],
        '{{TICKET_ALPHABET}}' => $params['ticket_alphabet'],
        '{{TICKET_NOTIFY_URL}}' => $params['ticket_notify_url'],
        '{{TICKET_NOTIFY_SESSION}}' => $params['ticket_notify_session'],
        '{{TICKET_NOTIFY_TOKEN}}' => $params['ticket_notify_token'],
        '{{TICKET_SHARE_CHANNEL}}' => $params['ticket_share_channel'],
        '{{TICKET_SHARE_TARGET}}' => $params['ticket_share_target'],
        '{{WG_ROLE}}' => $params['wg_role'],
        '{{WG_INTERFACE}}' => $params['wg_interface'],
        '{{WG_LISTEN_PORT}}' => $params['wg_listen_port'],
        '{{WG_WAN_INTERFACE}}' => $params['wg_wan_interface'],
        '{{WG_ADDRESS}}' => $params['wg_address'],
        '{{WG_TRUSTED_NETWORK}}' => $params['wg_trusted_network'],
        '{{WG_PEER_NAME}}' => $params['wg_peer_name'],
        '{{WG_PEER_PUBLIC_KEY}}' => $params['wg_peer_public_key'],
        '{{WG_PEER_ENDPOINT}}' => $params['wg_peer_endpoint'],
        '{{WG_PEER_ENDPOINT_PORT}}' => $params['wg_peer_endpoint_port'],
        '{{WG_ALLOWED_ADDRESS}}' => $params['wg_allowed_address'],
        '{{WG_ROUTE_NETWORKS}}' => $params['wg_route_networks'],
        '{{WG_KEEPALIVE}}' => $params['wg_keepalive'],
        '{{WG_ALLOW_MANAGEMENT}}' => $params['wg_allow_management'],
        '{{WG_NAT_BYPASS}}' => $params['wg_nat_bypass'],
        '{{RADIUS_SERVER_ADDRESS}}' => $params['radius_server_address'],
        '{{RADIUS_SECRET}}' => $params['radius_secret'],
        '{{RADIUS_AUTH_PORT}}' => $params['radius_auth_port'],
        '{{RADIUS_ACCT_PORT}}' => $params['radius_acct_port'],
        '{{RADIUS_TIMEOUT}}' => $params['radius_timeout'],
        '{{RADIUS_SERVICES}}' => $params['radius_services'],
        '{{RADIUS_HOTSPOT_MODE}}' => $params['radius_hotspot_mode'],
        '{{RADIUS_HOTSPOT_PROFILE}}' => $params['radius_hotspot_profile'],
        '{{RADIUS_ENABLE_PPP}}' => $params['radius_enable_ppp'],
        '{{RADIUS_INTERIM_UPDATE}}' => $params['radius_interim_update'],
        '{{RADIUS_SRC_ADDRESS}}' => $params['radius_src_address'],
        '{{RADIUS_ACCOUNTING}}' => $params['radius_accounting'],
      );

      $cake = strtr($cake, $replace);
      $social = strtr($social, $replace);
      $protection = strtr($protection, $replace);
      $automation = strtr($automation, $replace);
      $hotspotComplete = strtr($hotspotComplete, $replace);
      $hotspotBoost = strtr($hotspotBoost, $replace);
      $dnsHealth = strtr($dnsHealth, $replace);
      $hotspotHygiene = strtr($hotspotHygiene, $replace);
      $managementHardening = strtr($managementHardening, $replace);
      $loadbalancing = strtr($loadbalancing, $replace);
      $wireguard = strtr($wireguard, $replace);
      $radiusRoaming = strtr($radiusRoaming, $replace);
      $ticketAutomation = strtr($ticketAutomation, $replace);

      return array(
        'cake' => array(
          'title' => 'Optimisation CAKE QoS',
          'icon' => 'fa-tachometer',
          'description' => 'Cree ou met a jour les queues CAKE globales pour reduire la latence et lisser la bande passante.',
          'risk' => 'modere',
          'impact' => 'Queue types, simple queue globale',
          'source' => $cake,
        ),
        'social' => array(
          'title' => 'Bande passante reseaux sociaux',
          'icon' => 'fa-share-alt',
          'description' => 'Marque le trafic social connu et applique une queue dediee pour mieux controler les usages lourds.',
          'risk' => 'modere',
          'impact' => 'Address-list, mangle, queue tree',
          'source' => $social,
        ),
        'protection' => array(
          'title' => 'Protections routeur',
          'icon' => 'fa-shield',
          'description' => 'Desactive les services non securises et ajoute des regles firewall de base non destructives.',
          'risk' => 'modere',
          'impact' => 'IP services, firewall input/forward',
          'source' => $protection,
        ),
        'automation' => array(
          'title' => 'Automatisation maintenance',
          'icon' => 'fa-clock-o',
          'description' => 'Planifie une sauvegarde quotidienne, exporte la configuration et nettoie le cache DNS.',
          'risk' => 'faible',
          'impact' => 'Scheduler, backup, export, cache DNS',
          'source' => $automation,
        ),
        'dns-health' => array(
          'title' => 'DNS, NTP et sante routeur',
          'icon' => 'fa-heartbeat',
          'description' => 'Configure DNS/NTP, protege le routeur contre le DNS public ouvert et augmente le journal memoire.',
          'risk' => 'modere',
          'impact' => 'DNS cache, NTP, firewall input DNS, journaux',
          'source' => $dnsHealth,
        ),
        'hotspot-hygiene' => array(
          'title' => 'Maintenance Hotspot et DHCP',
          'icon' => 'fa-recycle',
          'description' => 'Planifie le nettoyage des cookies Hotspot, hosts non autorises et baux DHCP dynamiques en attente.',
          'risk' => 'faible',
          'impact' => 'Scheduler, cookies Hotspot, hosts, DHCP leases',
          'source' => $hotspotHygiene,
        ),
        'management-hardening' => array(
          'title' => 'Durcissement acces administration',
          'icon' => 'fa-lock',
          'description' => 'Limite Winbox, SSH et API au reseau admin choisi. A utiliser seulement si le reseau est correct.',
          'risk' => 'eleve',
          'impact' => 'IP services, firewall input admin',
          'source' => $managementHardening,
        ),
        'hotspot-complete' => array(
          'title' => 'Hotspot complet Bridge + DHCP',
          'icon' => 'fa-wifi',
          'description' => 'Construit le bridge LAN, DHCP, DNS, NAT et un Hotspot fonctionnel avec profil utilisateur et compte test.',
          'risk' => 'eleve',
          'impact' => 'Bridge, ports LAN, IP LAN, DHCP, NAT, Hotspot',
          'source' => $hotspotComplete,
        ),
        'hotspot-boost' => array(
          'title' => 'Boost connexion Hotspot',
          'icon' => 'fa-rocket',
          'description' => 'Améliore l’expérience Hotspot avec PCQ, cache DNS, profil utilisateur, MSS et option FastTrack.',
          'risk' => 'modere',
          'impact' => 'Queue PCQ, profil Hotspot, DNS, mangle TCP MSS',
          'source' => $hotspotBoost,
        ),
        'hotspot-complete-boost' => array(
          'title' => 'Hotspot complet + boost',
          'icon' => 'fa-magic',
          'description' => 'Déploie un Hotspot complet puis applique les optimisations de répartition équitable et de fluidité.',
          'risk' => 'eleve',
          'impact' => 'Bridge, DHCP, NAT, Hotspot, queues PCQ, DNS, mangle',
          'source' => $hotspotComplete . "\n\n" . $hotspotBoost,
        ),
        'multiwan-failover' => array(
          'title' => 'Load-balancing multi-sites + failover Internet',
          'icon' => 'fa-random',
          'description' => 'Crée une liste WAN, active le NAT multi-WAN, ajoute deux routes ECMP et surveille automatiquement les liens Internet.',
          'risk' => 'eleve',
          'impact' => 'Routes par défaut, NAT, scheduler failover',
          'source' => $loadbalancing,
        ),
        'wireguard-interconnect' => array(
          'title' => 'Interconnexion WireGuard',
          'icon' => 'fa-link',
          'description' => 'Crée un tunnel WireGuard site-à-site pour joindre les routeurs, Mikhmon, API MikroTik et futurs services RADIUS.',
          'risk' => 'modere',
          'impact' => 'WireGuard, routes, firewall, accès API',
          'source' => $wireguard,
        ),
        'radius-roaming' => array(
          'title' => 'Roaming RADIUS Hotspot',
          'icon' => 'fa-key',
          'description' => 'Configure le routeur comme client RADIUS central pour authentifier les tickets Hotspot et PPP sur tous les sites.',
          'risk' => 'eleve',
          'impact' => 'Radius client, Hotspot profiles, PPP AAA',
          'source' => $radiusRoaming,
        ),
        'ticket-automation' => array(
          'title' => 'Génération automatique de tickets',
          'icon' => 'fa-ticket',
          'description' => 'Crée un script RouterOS, remplit le stock de tickets et appelle Mikhmon pour préparer le PDF petit ticket.',
          'risk' => 'modere',
          'impact' => 'Hotspot users, system script, scheduler, callback PDF',
          'source' => $ticketAutomation,
        ),
        'professional-pack' => array(
          'title' => 'Pack professionnel complet',
          'icon' => 'fa-magic',
          'description' => 'Applique QoS, optimisation sociale, protections, DNS/NTP, failover, tickets sous seuil, maintenance Hotspot et sauvegardes.',
          'risk' => 'eleve',
          'impact' => 'QoS, firewall, DNS/NTP, routes, tickets, schedulers',
          'source' => $cake . "\n\n" . $social . "\n\n" . $protection . "\n\n" . $dnsHealth . "\n\n" . $loadbalancing . "\n\n" . $ticketAutomation . "\n\n" . $hotspotHygiene . "\n\n" . $automation,
        ),
      );
    }
  }

  $interfaces = tikras_routeros_comm($API, "/interface/print", array(), array());
  $ipAddresses = tikras_routeros_comm($API, "/ip/address/print", array(), array());
  $defaultRoutes = tikras_routeros_comm($API, "/ip/route/print", array("?dst-address" => "0.0.0.0/0"), array());
  $routerResources = tikras_routeros_comm($API, "/system/resource/print", array(), array());
  $routerResource = isset($routerResources[0]) ? $routerResources[0] : array();
  $routerOsVersion = isset($routerResource['version']) ? (string) $routerResource['version'] : '';
  $routerOsMajor = 0;
  if (preg_match('/^([0-9]+)/', $routerOsVersion, $versionMatch)) {
    $routerOsMajor = (int) $versionMatch[1];
  }
  $routerOs7Ready = ($routerOsMajor >= 7);
  $defaultLanNetwork = tikras_script_first_network($ipAddresses, "192.168.88.0/24");
  $defaultPrimaryGateway = tikras_script_first_gateway($defaultRoutes, 0);
  $defaultSecondaryGateway = tikras_script_first_gateway($defaultRoutes, 1);
  $defaultInterface = "";
  if (isset($interfaces[$iface - 1]['name'])) {
    $defaultInterface = $interfaces[$iface - 1]['name'];
  } elseif (isset($interfaces[0]['name'])) {
    $defaultInterface = $interfaces[0]['name'];
  }
  $defaultSecondaryInterface = "";
  if (isset($interfaces[1]['name']) && $interfaces[1]['name'] != $defaultInterface) {
    $defaultSecondaryInterface = $interfaces[1]['name'];
  } elseif (isset($interfaces[0]['name']) && $interfaces[0]['name'] != $defaultInterface) {
    $defaultSecondaryInterface = $interfaces[0]['name'];
  }
  $defaultBridgePorts = array();
  if (is_array($interfaces)) {
    foreach ($interfaces as $ifaceRow) {
      if (!isset($ifaceRow['name'])) {
        continue;
      }
      $ifname = $ifaceRow['name'];
      $itype = isset($ifaceRow['type']) ? $ifaceRow['type'] : "";
      if ($ifname == $defaultInterface || stripos($ifname, "bridge") === 0) {
        continue;
      }
      if ($itype == "ether" || stripos($ifname, "ether") === 0 || stripos($ifname, "lan") === 0 || stripos($ifname, "wlan") === 0) {
        $defaultBridgePorts[] = $ifname;
      }
    }
  }
  if (count($defaultBridgePorts) == 0 && $defaultSecondaryInterface != "") {
    $defaultBridgePorts[] = $defaultSecondaryInterface;
  }
  $defaultBridgePortsText = implode(",", $defaultBridgePorts);
  $defaultLanAddress = "192.168.88.1/24";
  if (is_array($ipAddresses)) {
    foreach ($ipAddresses as $addressRow) {
      if (!isset($addressRow['address'])) {
        continue;
      }
      if (isset($addressRow['interface']) && $addressRow['interface'] == $defaultInterface) {
        continue;
      }
      $address = (string) $addressRow['address'];
      if ($address != "" && strpos($address, '/') !== false) {
        $defaultLanAddress = $address;
        if (isset($addressRow['network'])) {
          $defaultLanNetwork = $addressRow['network'] . "/" . substr($address, strpos($address, '/') + 1);
        }
        break;
      }
    }
  }

  $hotspotProfiles = tikras_routeros_comm($API, "/ip/hotspot/user/profile/print", array(), array());
  $defaultTicketProfile = "default";
  if (isset($hotspotProfiles[0]['name'])) {
    $defaultTicketProfile = $hotspotProfiles[0]['name'];
  }

  $hotspotServers = tikras_routeros_comm($API, "/ip/hotspot/print", array(), array());
  $hotspotServerProfiles = tikras_routeros_comm($API, "/ip/hotspot/profile/print", array(), array());
  $defaultRadiusHotspotProfile = "default";
  if (isset($hotspotServerProfiles[0]['name'])) {
    $defaultRadiusHotspotProfile = $hotspotServerProfiles[0]['name'];
  }
  $ticketAlphabets = array(
    'mix' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
    'upper' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
    'num' => '23456789',
  );
  $defaultNotifyUrl = tikras_script_default_callback_url();
  $defaultNotifyToken = sha1($session . "|" . $passwdhost . "|tikras-ticket-share");
  $ticketShareChannel = tikras_script_clean_channel(tikras_post('ticket_share_channel', 'whatsapp'));
  $ticketShareTarget = tikras_script_clean_share_target(tikras_post('ticket_share_target'), $ticketShareChannel);
  $wireguardRole = tikras_script_clean_choice(tikras_post('wg_role', 'hub'), array('hub', 'spoke'), 'hub');
  $defaultWgAddress = $wireguardRole == 'spoke' ? '10.252.0.2/24' : '10.252.0.1/24';
  $defaultWgAllowed = $wireguardRole == 'spoke' ? '10.252.0.1/32' : '10.252.0.2/32';
  $defaultRadiusServer = $wireguardRole == 'spoke' ? '10.252.0.1' : '127.0.0.1';

  $selectedTemplate = tikras_post('script_model', 'automation');
  $params = array(
    'upload_limit' => tikras_script_clean_rate(tikras_post('upload_limit', '20M'), '20M'),
    'download_limit' => tikras_script_clean_rate(tikras_post('download_limit', '50M'), '50M'),
    'target_network' => tikras_script_clean_target(tikras_post('target_network', '0.0.0.0/0'), '0.0.0.0/0'),
    'wan_interface' => tikras_script_clean_target(tikras_post('wan_interface', $defaultInterface), $defaultInterface),
    'wan_primary_interface' => tikras_script_clean_target(tikras_post('wan_primary_interface', $defaultInterface), $defaultInterface),
    'wan_secondary_interface' => tikras_script_clean_target(tikras_post('wan_secondary_interface', $defaultSecondaryInterface), $defaultSecondaryInterface),
    'wan_primary_gateway' => tikras_script_clean_optional_target(tikras_post('wan_primary_gateway', $defaultPrimaryGateway)),
    'wan_secondary_gateway' => tikras_script_clean_optional_target(tikras_post('wan_secondary_gateway', $defaultSecondaryGateway)),
    'wan_primary_monitor' => tikras_script_clean_target(tikras_post('wan_primary_monitor', '1.1.1.1'), '1.1.1.1'),
    'wan_secondary_monitor' => tikras_script_clean_target(tikras_post('wan_secondary_monitor', '8.8.8.8'), '8.8.8.8'),
    'failover_interval' => tikras_script_clean_interval(tikras_post('failover_interval', '1m'), '1m'),
    'hotspot_wan_interface' => tikras_script_clean_target(tikras_post('hotspot_wan_interface', $defaultInterface), $defaultInterface),
    'bridge_name' => tikras_script_clean_target(tikras_post('bridge_name', 'bridge-hotspot'), 'bridge-hotspot'),
    'bridge_ports' => tikras_script_clean_target_list(tikras_post('bridge_ports', $defaultBridgePortsText), $defaultBridgePortsText),
    'lan_address' => tikras_script_clean_target(tikras_post('lan_address', $defaultLanAddress), $defaultLanAddress),
    'lan_network' => tikras_script_clean_target(tikras_post('lan_network', $defaultLanNetwork), $defaultLanNetwork),
    'admin_network' => tikras_script_clean_target(tikras_post('admin_network', $defaultLanNetwork), $defaultLanNetwork),
    'dhcp_pool_name' => tikras_script_clean_target(tikras_post('dhcp_pool_name', 'tikras-hotspot-pool'), 'tikras-hotspot-pool'),
    'dhcp_pool_range' => tikras_script_clean_target(tikras_post('dhcp_pool_range', '192.168.88.10-192.168.88.254'), '192.168.88.10-192.168.88.254'),
    'hotspot_server_name' => tikras_script_clean_target(tikras_post('hotspot_server_name', 'tikras-hotspot'), 'tikras-hotspot'),
    'hotspot_server_profile' => tikras_script_clean_target(tikras_post('hotspot_server_profile', 'tikras-hsprof'), 'tikras-hsprof'),
    'hotspot_user_profile' => tikras_script_clean_label(tikras_post('hotspot_user_profile', $defaultTicketProfile), $defaultTicketProfile),
    'hotspot_dns_name' => tikras_script_clean_target(tikras_post('hotspot_dns_name', 'login.tikras.it'), 'login.tikras.it'),
    'hotspot_test_user' => tikras_script_clean_target(tikras_post('hotspot_test_user', 'test'), 'test'),
    'hotspot_test_password' => tikras_script_clean_target(tikras_post('hotspot_test_password', 'tikras123'), 'tikras123'),
    'hotspot_user_upload' => tikras_script_clean_rate(tikras_post('hotspot_user_upload', '2M'), '2M'),
    'hotspot_user_download' => tikras_script_clean_rate(tikras_post('hotspot_user_download', '10M'), '10M'),
    'hotspot_shared_users' => tikras_script_clean_number(tikras_post('hotspot_shared_users', '1'), '1', 1, 20),
    'hotspot_disable_fasttrack' => tikras_script_clean_choice(tikras_post('hotspot_disable_fasttrack', 'no'), array('yes', 'no'), 'no'),
    'dns_servers' => tikras_script_clean_target_list(tikras_post('dns_servers', '1.1.1.1,8.8.8.8'), '1.1.1.1,8.8.8.8'),
    'ntp_servers' => tikras_script_clean_target_list(tikras_post('ntp_servers', 'time.cloudflare.com,pool.ntp.org'), 'time.cloudflare.com,pool.ntp.org'),
    'cleanup_interval' => tikras_script_clean_interval(tikras_post('cleanup_interval', '1d'), '1d'),
    'scheduler_time' => tikras_script_clean_time(tikras_post('scheduler_time', '03:00:00'), '03:00:00'),
    'ticket_interval' => tikras_script_clean_interval(tikras_post('ticket_interval', '1d'), '1d'),
    'ticket_qty' => tikras_script_clean_number(tikras_post('ticket_qty', '20'), '20', 1, 500),
    'ticket_prefix' => tikras_script_clean_prefix(tikras_post('ticket_prefix', 'AUTO-'), 'AUTO-'),
    'ticket_length' => tikras_script_clean_number(tikras_post('ticket_length', '6'), '6', 3, 12),
    'ticket_profile' => tikras_script_clean_label(tikras_post('ticket_profile', $defaultTicketProfile), $defaultTicketProfile),
    'ticket_server' => tikras_script_clean_label(tikras_post('ticket_server', 'all'), 'all'),
    'ticket_uptime' => tikras_script_clean_duration(tikras_post('ticket_uptime', '0'), '0'),
    'ticket_bytes' => tikras_script_clean_digits(tikras_post('ticket_bytes', '0'), '0', 12),
    'ticket_trigger' => tikras_script_clean_choice(tikras_post('ticket_trigger', 'stock'), array('stock', 'interval'), 'stock'),
    'ticket_stock_min' => tikras_script_clean_number(tikras_post('ticket_stock_min', '10'), '10', 0, 5000),
    'ticket_stock_target' => tikras_script_clean_number(tikras_post('ticket_stock_target', '50'), '50', 1, 5000),
    'ticket_mode' => tikras_script_clean_choice(tikras_post('ticket_mode', 'vc'), array('vc', 'up'), 'vc'),
    'ticket_charset' => tikras_script_clean_choice(tikras_post('ticket_charset', 'mix'), array('mix', 'upper', 'num'), 'mix'),
    'ticket_notify_url' => tikras_script_clean_url(tikras_post('ticket_notify_url', $defaultNotifyUrl), $defaultNotifyUrl),
    'ticket_notify_session' => $session,
    'ticket_notify_token' => $defaultNotifyToken,
    'ticket_share_channel' => $ticketShareChannel,
    'ticket_share_target' => $ticketShareTarget,
    'wg_role' => $wireguardRole,
    'wg_interface' => tikras_script_clean_target(tikras_post('wg_interface', 'wg-tikras'), 'wg-tikras'),
    'wg_listen_port' => tikras_script_clean_number(tikras_post('wg_listen_port', '13231'), '13231', 1, 65535),
    'wg_wan_interface' => tikras_script_clean_target(tikras_post('wg_wan_interface', $defaultInterface), $defaultInterface),
    'wg_address' => tikras_script_clean_target(tikras_post('wg_address', $defaultWgAddress), $defaultWgAddress),
    'wg_trusted_network' => tikras_script_clean_target(tikras_post('wg_trusted_network', '10.252.0.0/24'), '10.252.0.0/24'),
    'wg_peer_name' => tikras_script_clean_label(tikras_post('wg_peer_name', 'site-distance'), 'site-distance'),
    'wg_peer_public_key' => tikras_script_clean_wireguard_key(tikras_post('wg_peer_public_key')),
    'wg_peer_endpoint' => tikras_script_clean_optional_target(tikras_post('wg_peer_endpoint')),
    'wg_peer_endpoint_port' => tikras_script_clean_number(tikras_post('wg_peer_endpoint_port', '13231'), '13231', 1, 65535),
    'wg_allowed_address' => tikras_script_clean_target_list(tikras_post('wg_allowed_address', $defaultWgAllowed), $defaultWgAllowed),
    'wg_route_networks' => tikras_script_clean_target_list(tikras_post('wg_route_networks'), ''),
    'wg_keepalive' => tikras_script_clean_duration(tikras_post('wg_keepalive', '25s'), '25s'),
    'wg_allow_management' => tikras_script_clean_choice(tikras_post('wg_allow_management', 'yes'), array('yes', 'no'), 'yes'),
    'wg_nat_bypass' => tikras_script_clean_choice(tikras_post('wg_nat_bypass', 'yes'), array('yes', 'no'), 'yes'),
    'radius_server_address' => tikras_script_clean_target(tikras_post('radius_server_address', $defaultRadiusServer), $defaultRadiusServer),
    'radius_secret' => tikras_script_clean_secret(tikras_post('radius_secret', 'ChangeMeRadiusSecret'), 'ChangeMeRadiusSecret'),
    'radius_auth_port' => tikras_script_clean_number(tikras_post('radius_auth_port', '1812'), '1812', 1, 65535),
    'radius_acct_port' => tikras_script_clean_number(tikras_post('radius_acct_port', '1813'), '1813', 1, 65535),
    'radius_timeout' => tikras_script_clean_interval(tikras_post('radius_timeout', '3s'), '3s'),
    'radius_services' => tikras_script_clean_choice(tikras_post('radius_services', 'hotspot'), array('hotspot', 'hotspot,ppp', 'ppp'), 'hotspot'),
    'radius_hotspot_mode' => tikras_script_clean_choice(tikras_post('radius_hotspot_mode', 'all'), array('all', 'selected', 'none'), 'all'),
    'radius_hotspot_profile' => tikras_script_clean_label(tikras_post('radius_hotspot_profile', $defaultRadiusHotspotProfile), $defaultRadiusHotspotProfile),
    'radius_enable_ppp' => tikras_script_clean_choice(tikras_post('radius_enable_ppp', 'no'), array('yes', 'no'), 'no'),
    'radius_interim_update' => tikras_script_clean_interval(tikras_post('radius_interim_update', '5m'), '5m'),
    'radius_src_address' => tikras_script_clean_optional_target(tikras_post('radius_src_address')),
    'radius_accounting' => tikras_script_clean_choice(tikras_post('radius_accounting', 'yes'), array('yes', 'no'), 'yes'),
  );
  if ((int) $params['ticket_stock_target'] < (int) $params['ticket_stock_min']) {
    $params['ticket_stock_target'] = $params['ticket_stock_min'];
  }
  $params['ticket_alphabet'] = $ticketAlphabets[$params['ticket_charset']];

  $templates = tikras_script_build_templates($params);
  $routerOs7Guard = <<<'ROS'
:local tikrasRosVersion [/system resource get version];
:local tikrasRosDot [:find $tikrasRosVersion "."];
:if ($tikrasRosDot = nil) do={ :set tikrasRosDot [:len $tikrasRosVersion]; }
:local tikrasRosMajor [:tonum [:pick $tikrasRosVersion 0 $tikrasRosDot]];
:if ($tikrasRosMajor < 7) do={
  :log error ("TIKRAS IT - RouterOS " . $tikrasRosVersion . " detecte. Ce script exige RouterOS 7 ou plus.");
  :error "TIKRAS IT - RouterOS 7 requis";
}
ROS;
  $templateOverrides = array(
    'hotspot-boost' => array(
      'description' => 'Ameliore l experience Hotspot avec PCQ, cache DNS, profil utilisateur, MSS et option FastTrack.',
      'risk' => 'modere',
    ),
    'hotspot-complete-boost' => array(
      'description' => 'Deploie un Hotspot complet puis applique les optimisations de repartition equitable et de fluidite.',
      'risk' => 'eleve',
    ),
    'multiwan-failover' => array(
      'description' => 'Cree une liste WAN, active le NAT multi-WAN, ajoute deux routes ECMP et surveille automatiquement les liens Internet.',
      'risk' => 'eleve',
      'impact' => 'Routes par defaut, NAT, scheduler failover',
    ),
    'wireguard-interconnect' => array(
      'description' => 'Cree un tunnel WireGuard site-a-site pour joindre les routeurs, Mikhmon, API MikroTik et futurs services RADIUS.',
      'risk' => 'modere',
      'impact' => 'WireGuard, routes, firewall, acces API',
    ),
    'radius-roaming' => array(
      'risk' => 'eleve',
    ),
    'ticket-automation' => array(
      'title' => 'Generation automatique de tickets',
      'description' => 'Cree un script RouterOS, remplit le stock de tickets et appelle Mikhmon pour preparer le PDF petit ticket.',
      'risk' => 'modere',
    ),
    'professional-pack' => array(
      'risk' => 'eleve',
    ),
  );
  foreach ($templateOverrides as $templateKey => $templateData) {
    if (!isset($templates[$templateKey])) {
      continue;
    }
    foreach ($templateData as $field => $value) {
      $templates[$templateKey][$field] = $value;
    }
  }
  foreach ($templates as $templateKey => $templateData) {
    if (!isset($templateData['risk']) || !in_array($templateData['risk'], array('faible', 'modere', 'eleve'), true)) {
      $templates[$templateKey]['risk'] = 'modere';
    }
    if (isset($templateData['source'])) {
      $templates[$templateKey]['source'] = $routerOs7Guard . "\n\n" . $templateData['source'];
    }
  }
  if (!isset($templates[$selectedTemplate])) {
    $selectedTemplate = 'automation';
  }

  $scriptSource = $templates[$selectedTemplate]['source'];
  $scriptLineCount = substr_count(trim($scriptSource), "\n") + 1;
  $scriptSizeKb = round(strlen($scriptSource) / 1024, 1);
  $selectedRisk = isset($templates[$selectedTemplate]['risk']) ? $templates[$selectedTemplate]['risk'] : 'modere';
  $selectedRiskLabel = tikras_script_risk_label($selectedRisk);
  $selectedImpact = isset($templates[$selectedTemplate]['impact']) ? $templates[$selectedTemplate]['impact'] : 'Configuration routeur';
  $notifyStatus = "Non configuré";
  if (file_exists('./lib/tikras_notify.php')) {
    include_once('./lib/tikras_notify.php');
    if (function_exists('tikras_notify_get')) {
      $notifyConfig = tikras_notify_get($session);
      $channels = array();
      if (isset($notifyConfig['whatsapp_api_url']) && $notifyConfig['whatsapp_api_url'] != "") {
        $channels[] = "WhatsApp";
      }
      if (isset($notifyConfig['telegram_bot_token']) && $notifyConfig['telegram_bot_token'] != "") {
        $channels[] = "Telegram";
      }
      if (isset($notifyConfig['mail_from']) && $notifyConfig['mail_from'] != "") {
        $channels[] = "Email";
      }
      $notifyStatus = count($channels) > 0 ? implode(", ", $channels) : "Non configuré";
    }
  }
  $tikrasDiagnostics = array(
    array('label' => 'Routeur', 'value' => $session, 'detail' => $identity . ' | ' . $iphost, 'icon' => 'fa-server', 'state' => 'ok'),
    array('label' => 'RouterOS', 'value' => $routerOsVersion != '' ? $routerOsVersion : '-', 'detail' => $routerOs7Ready ? (isset($routerResource['board-name']) ? $routerResource['board-name'] : 'RouterOS 7 compatible') : 'RouterOS 7 ou plus requis', 'icon' => 'fa-microchip', 'state' => $routerOs7Ready ? 'ok' : 'warn'),
    array('label' => 'Interfaces', 'value' => is_array($interfaces) ? count($interfaces) : 0, 'detail' => 'WAN detecte: ' . ($defaultInterface == "" ? '-' : $defaultInterface), 'icon' => 'fa-sitemap', 'state' => is_array($interfaces) && count($interfaces) > 0 ? 'ok' : 'warn'),
    array('label' => 'Hotspot', 'value' => is_array($hotspotProfiles) ? count($hotspotProfiles) . ' profils' : '0 profil', 'detail' => 'Profil tickets: ' . $params['ticket_profile'], 'icon' => 'fa-ticket', 'state' => is_array($hotspotProfiles) && count($hotspotProfiles) > 0 ? 'ok' : 'warn'),
    array('label' => 'LAN admin', 'value' => $params['lan_network'], 'detail' => 'Acces admin: ' . $params['admin_network'], 'icon' => 'fa-lock', 'state' => $params['lan_network'] != "" ? 'ok' : 'warn'),
    array('label' => 'Notifications', 'value' => $notifyStatus, 'detail' => 'PDF tickets automatiques', 'icon' => 'fa-paper-plane', 'state' => $notifyStatus == "Non configuré" ? 'warn' : 'ok'),
  );
  $applyMessage = "";
  $applyClass = "bg-info";

  if (tikras_has_post('apply_script')) {
    $scriptName = 'tikras-it-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($selectedTemplate)) . '-' . date('YmdHis');
    if (tikras_post('apply_confirm') != '1') {
      $applyMessage = "Application annulee. Cochez la confirmation apres avoir verifie le scenario, le risque et le script genere.";
      $applyClass = "bg-danger";
    } else {
      $addResponse = tikras_routeros_comm($API, "/system/script/add", array(
        "name" => $scriptName,
        "source" => $scriptSource,
        "comment" => "TIKRAS IT - generateur automatique",
      ), array());
      $addError = tikras_script_routeros_error($addResponse);
      if ($addError != '') {
        $applyMessage = "Erreur RouterOS pendant la creation du script : " . $addError;
        $applyClass = "bg-danger";
      } else {
        $runResponse = tikras_routeros_comm($API, "/system/script/run", array("number" => $scriptName), array());
        $runError = tikras_script_routeros_error($runResponse);
        $createdScript = tikras_routeros_comm($API, "/system/script/print", array("?name" => $scriptName), array());
        if (isset($createdScript[0]['.id'])) {
          tikras_routeros_comm($API, "/system/script/remove", array(".id" => $createdScript[0]['.id']), array());
        }

        if ($runError != '') {
          $applyMessage = "Erreur RouterOS pendant l execution : " . $runError;
          $applyClass = "bg-danger";
        } else {
          $applyMessage = "Script applique sur le routeur " . $session . ". Verifiez le journal RouterOS pour les details.";
          $applyClass = "bg-success";
        }
      }

      tikras_storage_audit(
        $applyClass == "bg-success" ? 'script.apply.ok' : 'script.apply.error',
        'router_script',
        $selectedTemplate,
        $session,
        $applyMessage,
        array('script_name' => $scriptName, 'risk' => $selectedRisk, 'lines' => $scriptLineCount)
      );
    }
  }
}
?>

<div class="row tikras-script-page">
  <div class="col-12">
    <?php if ($applyMessage != "") { ?>
      <div class="box <?= $applyClass; ?> bmh-50">
        <i class="fa fa-info-circle"></i> <?= tikras_script_h($applyMessage); ?>
      </div>
    <?php } ?>
  </div>

  <div class="col-12">
    <div class="tikras-script-hero">
      <div>
        <span class="tikras-script-kicker">Assistant de déploiement TIKRAS IT</span>
        <h2>Générateur professionnel de scripts MikroTik</h2>
        <p>Choisissez un scénario, vérifiez les prérequis, prévisualisez le script RouterOS puis appliquez-le uniquement quand le résumé est cohérent.</p>
      </div>
      <div class="tikras-script-hero-meta">
        <span><i class="fa fa-file-code-o"></i> <?= $scriptLineCount; ?> lignes</span>
        <span><i class="fa fa-database"></i> <?= $scriptSizeKb; ?> Ko</span>
        <span class="tikras-risk tikras-risk-<?= tikras_script_h($selectedRisk); ?>"><i class="fa fa-shield"></i> Risque <?= tikras_script_h($selectedRiskLabel); ?></span>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="tikras-script-diagnostics">
      <?php foreach ($tikrasDiagnostics as $diag) { ?>
        <div class="tikras-script-diag tikras-script-diag-<?= tikras_script_h($diag['state']); ?>">
          <i class="fa <?= tikras_script_h($diag['icon']); ?>"></i>
          <div>
            <span><?= tikras_script_h($diag['label']); ?></span>
            <strong><?= tikras_script_h($diag['value']); ?></strong>
            <small><?= tikras_script_h($diag['detail']); ?></small>
          </div>
        </div>
      <?php } ?>
    </div>
  </div>

  <div class="col-5">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-code"></i> Générateur de scripts routeur</h3>
      </div>
      <div class="card-body">
        <form autocomplete="off" method="post" action="">
          <div class="tikras-script-model-grid">
            <?php foreach ($templates as $key => $template) { ?>
              <button class="tikras-script-model-card <?php if ($key == $selectedTemplate) { echo 'active'; } ?> tikras-risk-border-<?= tikras_script_h($template['risk']); ?>" type="button" data-script-card="<?= tikras_script_h($key); ?>">
                <i class="fa <?= tikras_script_h($template['icon']); ?>"></i>
                <span><?= tikras_script_h($template['title']); ?></span>
                <small>Risque <?= tikras_script_h(tikras_script_risk_label($template['risk'])); ?> | <?= tikras_script_h($template['impact']); ?></small>
              </button>
            <?php } ?>
          </div>
          <table class="table table-sm">
            <tr>
              <td class="align-middle">Routeur cible</td>
              <td>
                <strong><?= tikras_script_h($session); ?></strong><br>
                <small><?= tikras_script_h($identity); ?> | <?= tikras_script_h($iphost); ?></small>
              </td>
            </tr>
            <tr>
              <td class="align-middle">Modele</td>
              <td>
                <select class="form-control" name="script_model">
                  <?php foreach ($templates as $key => $template) { ?>
                    <option value="<?= tikras_script_h($key); ?>" <?php if ($key == $selectedTemplate) { echo 'selected'; } ?>>
                      <?= tikras_script_h($template['title']); ?>
                    </option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr data-script-templates="cake professional-pack">
              <td class="align-middle">Upload max</td>
              <td><input class="form-control" type="text" name="upload_limit" value="<?= tikras_script_h($params['upload_limit']); ?>" placeholder="20M"></td>
            </tr>
            <tr data-script-templates="cake social professional-pack">
              <td class="align-middle">Download max</td>
              <td><input class="form-control" type="text" name="download_limit" value="<?= tikras_script_h($params['download_limit']); ?>" placeholder="50M"></td>
            </tr>
            <tr data-script-templates="cake professional-pack">
              <td class="align-middle">Reseau cible</td>
              <td><input class="form-control" type="text" name="target_network" value="<?= tikras_script_h($params['target_network']); ?>" placeholder="0.0.0.0/0"></td>
            </tr>
            <tr data-script-templates="cake social professional-pack">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-tachometer"></i> QoS et controle de bande passante</td>
            </tr>
            <tr data-script-templates="cake social professional-pack">
              <td class="align-middle">Interface WAN QoS</td>
              <td>
                <select class="form-control" name="wan_interface">
                  <?php
                  if (is_array($interfaces)) {
                    foreach ($interfaces as $ifaceRow) {
                      if (!isset($ifaceRow['name'])) {
                        continue;
                      }
                      $ifname = $ifaceRow['name'];
                      echo '<option value="' . tikras_script_h($ifname) . '"';
                      if ($ifname == $params['wan_interface']) {
                        echo ' selected';
                      }
                      echo '>' . tikras_script_h($ifname) . '</option>';
                    }
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-boost hotspot-complete-boost">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-wifi"></i> Hotspot complet et boost utilisateurs</td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-complete-boost">
              <td class="align-middle">WAN Internet</td>
              <td>
                <select class="form-control" name="hotspot_wan_interface">
                  <?php
                  if (is_array($interfaces)) {
                    foreach ($interfaces as $ifaceRow) {
                      if (!isset($ifaceRow['name'])) {
                        continue;
                      }
                      $ifname = $ifaceRow['name'];
                      echo '<option value="' . tikras_script_h($ifname) . '"';
                      if ($ifname == $params['hotspot_wan_interface']) {
                        echo ' selected';
                      }
                      echo '>' . tikras_script_h($ifname) . '</option>';
                    }
                  }
                  ?>
                </select>
                <small class="tikras-script-help">Interface qui sort vers Internet et recoit le NAT masquerade.</small>
              </td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-complete-boost">
              <td class="align-middle">Nom du bridge</td>
              <td><input class="form-control" type="text" name="bridge_name" value="<?= tikras_script_h($params['bridge_name']); ?>" placeholder="bridge-hotspot"></td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-complete-boost">
              <td class="align-middle">Ports LAN bridge</td>
              <td>
                <input class="form-control" type="text" name="bridge_ports" value="<?= tikras_script_h($params['bridge_ports']); ?>" placeholder="ether2,ether3,ether4,wlan1">
                <small class="tikras-script-help">Liste separee par virgule. Ces ports seront rattaches au bridge Hotspot.</small>
              </td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-complete-boost">
              <td class="align-middle">IP LAN gateway</td>
              <td><input class="form-control" type="text" name="lan_address" value="<?= tikras_script_h($params['lan_address']); ?>" placeholder="192.168.88.1/24"></td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-boost hotspot-complete-boost">
              <td class="align-middle">Reseau LAN Hotspot</td>
              <td><input class="form-control" type="text" name="lan_network" value="<?= tikras_script_h($params['lan_network']); ?>" placeholder="192.168.88.0/24"></td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-complete-boost">
              <td class="align-middle">Pool DHCP</td>
              <td>
                <input class="form-control" type="text" name="dhcp_pool_range" value="<?= tikras_script_h($params['dhcp_pool_range']); ?>" placeholder="192.168.88.10-192.168.88.254">
                <input class="form-control mr-t-5" type="text" name="dhcp_pool_name" value="<?= tikras_script_h($params['dhcp_pool_name']); ?>" placeholder="tikras-hotspot-pool">
              </td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-complete-boost">
              <td class="align-middle">Serveur Hotspot</td>
              <td>
                <input class="form-control" type="text" name="hotspot_server_name" value="<?= tikras_script_h($params['hotspot_server_name']); ?>" placeholder="tikras-hotspot">
                <input class="form-control mr-t-5" type="text" name="hotspot_server_profile" value="<?= tikras_script_h($params['hotspot_server_profile']); ?>" placeholder="tikras-hsprof">
              </td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-boost hotspot-complete-boost">
              <td class="align-middle">Profil utilisateurs</td>
              <td>
                <input class="form-control" type="text" name="hotspot_user_profile" value="<?= tikras_script_h($params['hotspot_user_profile']); ?>" placeholder="default">
                <small class="tikras-script-help">Profil auquel seront appliquees les limites et optimisations Hotspot.</small>
              </td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-complete-boost">
              <td class="align-middle">DNS portail</td>
              <td><input class="form-control" type="text" name="hotspot_dns_name" value="<?= tikras_script_h($params['hotspot_dns_name']); ?>" placeholder="login.tikras.it"></td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-complete-boost">
              <td class="align-middle">Utilisateur test</td>
              <td>
                <input class="form-control" type="text" name="hotspot_test_user" value="<?= tikras_script_h($params['hotspot_test_user']); ?>" placeholder="test">
                <input class="form-control mr-t-5" type="text" name="hotspot_test_password" value="<?= tikras_script_h($params['hotspot_test_password']); ?>" placeholder="tikras123">
              </td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-boost hotspot-complete-boost">
              <td class="align-middle">Debit par utilisateur</td>
              <td>
                <input class="form-control" type="text" name="hotspot_user_upload" value="<?= tikras_script_h($params['hotspot_user_upload']); ?>" placeholder="2M">
                <input class="form-control mr-t-5" type="text" name="hotspot_user_download" value="<?= tikras_script_h($params['hotspot_user_download']); ?>" placeholder="10M">
                <small class="tikras-script-help">Upload puis download. Utilise dans le profil Hotspot et les queues PCQ.</small>
              </td>
            </tr>
            <tr data-script-templates="hotspot-complete hotspot-boost hotspot-complete-boost">
              <td class="align-middle">Sessions partagees</td>
              <td><input class="form-control" type="number" min="1" max="20" name="hotspot_shared_users" value="<?= tikras_script_h($params['hotspot_shared_users']); ?>" placeholder="1"></td>
            </tr>
            <tr data-script-templates="hotspot-boost hotspot-complete-boost">
              <td class="align-middle">FastTrack</td>
              <td>
                <select class="form-control" name="hotspot_disable_fasttrack">
                  <option value="no" <?php if ($params['hotspot_disable_fasttrack'] == "no") { echo 'selected'; } ?>>Ne pas toucher</option>
                  <option value="yes" <?php if ($params['hotspot_disable_fasttrack'] == "yes") { echo 'selected'; } ?>>Désactiver pour queues Hotspot</option>
                </select>
                <small class="tikras-script-help">Les queues Hotspot sont plus fiables sans FastTrack, mais ce choix peut changer le comportement firewall.</small>
              </td>
            </tr>
            <tr data-script-templates="dns-health management-hardening professional-pack">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-heartbeat"></i> DNS, NTP et acces administration</td>
            </tr>
            <tr data-script-templates="dns-health management-hardening professional-pack">
              <td class="align-middle">Reseau LAN autorise</td>
              <td>
                <input class="form-control" type="text" name="lan_network" value="<?= tikras_script_h($params['lan_network']); ?>" placeholder="192.168.88.0/24">
                <small class="tikras-script-help">Utilise pour autoriser les clients LAN a joindre le DNS du routeur.</small>
              </td>
            </tr>
            <tr data-script-templates="management-hardening">
              <td class="align-middle">Reseau admin</td>
              <td>
                <input class="form-control" type="text" name="admin_network" value="<?= tikras_script_h($params['admin_network']); ?>" placeholder="192.168.88.0/24">
                <small class="tikras-script-help">Winbox, SSH et API seront limites a ce reseau. Verifiez avant application.</small>
              </td>
            </tr>
            <tr data-script-templates="dns-health professional-pack">
              <td class="align-middle">Serveurs DNS</td>
              <td><input class="form-control" type="text" name="dns_servers" value="<?= tikras_script_h($params['dns_servers']); ?>" placeholder="1.1.1.1,8.8.8.8"></td>
            </tr>
            <tr data-script-templates="dns-health professional-pack">
              <td class="align-middle">Serveurs NTP</td>
              <td><input class="form-control" type="text" name="ntp_servers" value="<?= tikras_script_h($params['ntp_servers']); ?>" placeholder="time.cloudflare.com,pool.ntp.org"></td>
            </tr>
            <tr data-script-templates="multiwan-failover professional-pack">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-random"></i> Internet multi-sites et failover</td>
            </tr>
            <tr data-script-templates="multiwan-failover professional-pack">
              <td class="align-middle">WAN primaire</td>
              <td>
                <select class="form-control" name="wan_primary_interface">
                  <?php
                  if (is_array($interfaces)) {
                    foreach ($interfaces as $ifaceRow) {
                      if (!isset($ifaceRow['name'])) {
                        continue;
                      }
                      $ifname = $ifaceRow['name'];
                      echo '<option value="' . tikras_script_h($ifname) . '"';
                      if ($ifname == $params['wan_primary_interface']) {
                        echo ' selected';
                      }
                      echo '>' . tikras_script_h($ifname) . '</option>';
                    }
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr data-script-templates="multiwan-failover professional-pack">
              <td class="align-middle">Passerelle primaire</td>
              <td>
                <input class="form-control" type="text" name="wan_primary_gateway" value="<?= tikras_script_h($params['wan_primary_gateway']); ?>" placeholder="ex: 192.168.1.1 ou pppoe-out1">
                <small class="tikras-script-help">Route ECMP distance 1, avec check-gateway ping.</small>
              </td>
            </tr>
            <tr data-script-templates="multiwan-failover professional-pack">
              <td class="align-middle">IP test primaire</td>
              <td><input class="form-control" type="text" name="wan_primary_monitor" value="<?= tikras_script_h($params['wan_primary_monitor']); ?>" placeholder="1.1.1.1"></td>
            </tr>
            <tr data-script-templates="multiwan-failover professional-pack">
              <td class="align-middle">WAN secondaire</td>
              <td>
                <select class="form-control" name="wan_secondary_interface">
                  <option value="">Aucun</option>
                  <?php
                  if (is_array($interfaces)) {
                    foreach ($interfaces as $ifaceRow) {
                      if (!isset($ifaceRow['name'])) {
                        continue;
                      }
                      $ifname = $ifaceRow['name'];
                      echo '<option value="' . tikras_script_h($ifname) . '"';
                      if ($ifname == $params['wan_secondary_interface']) {
                        echo ' selected';
                      }
                      echo '>' . tikras_script_h($ifname) . '</option>';
                    }
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr data-script-templates="multiwan-failover professional-pack">
              <td class="align-middle">Passerelle secondaire</td>
              <td><input class="form-control" type="text" name="wan_secondary_gateway" value="<?= tikras_script_h($params['wan_secondary_gateway']); ?>" placeholder="ex: 192.168.2.1 ou lte1"></td>
            </tr>
            <tr data-script-templates="multiwan-failover professional-pack">
              <td class="align-middle">IP test secondaire</td>
              <td><input class="form-control" type="text" name="wan_secondary_monitor" value="<?= tikras_script_h($params['wan_secondary_monitor']); ?>" placeholder="8.8.8.8"></td>
            </tr>
            <tr data-script-templates="multiwan-failover professional-pack">
              <td class="align-middle">Controle failover</td>
              <td><input class="form-control" type="text" name="failover_interval" value="<?= tikras_script_h($params['failover_interval']); ?>" placeholder="1m"></td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-link"></i> Interconnexion WireGuard</td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Role du routeur</td>
              <td>
                <select class="form-control" name="wg_role">
                  <option value="hub" <?php if ($params['wg_role'] == "hub") { echo 'selected'; } ?>>Hub central</option>
                  <option value="spoke" <?php if ($params['wg_role'] == "spoke") { echo 'selected'; } ?>>Site distant</option>
                </select>
                <small class="tikras-script-help">Le hub recoit les sites. Le site distant pointe vers l'adresse publique du hub.</small>
              </td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Interface tunnel</td>
              <td>
                <input class="form-control" type="text" name="wg_interface" value="<?= tikras_script_h($params['wg_interface']); ?>" placeholder="wg-tikras">
                <input class="form-control mr-t-5" type="text" name="wg_address" value="<?= tikras_script_h($params['wg_address']); ?>" placeholder="10.252.0.1/24">
                <small class="tikras-script-help">Nom WireGuard puis adresse IP locale du tunnel.</small>
              </td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Port et WAN</td>
              <td>
                <input class="form-control" type="number" min="1" max="65535" name="wg_listen_port" value="<?= tikras_script_h($params['wg_listen_port']); ?>" placeholder="13231">
                <select class="form-control mr-t-5" name="wg_wan_interface">
                  <?php
                  if (is_array($interfaces)) {
                    foreach ($interfaces as $ifaceRow) {
                      if (!isset($ifaceRow['name'])) {
                        continue;
                      }
                      $ifname = $ifaceRow['name'];
                      echo '<option value="' . tikras_script_h($ifname) . '"';
                      if ($ifname == $params['wg_wan_interface']) {
                        echo ' selected';
                      }
                      echo '>' . tikras_script_h($ifname) . '</option>';
                    }
                  }
                  ?>
                </select>
                <small class="tikras-script-help">Le firewall autorise UDP sur cette interface WAN.</small>
              </td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Reseau tunnel</td>
              <td>
                <input class="form-control" type="text" name="wg_trusted_network" value="<?= tikras_script_h($params['wg_trusted_network']); ?>" placeholder="10.252.0.0/24">
                <small class="tikras-script-help">Reseau autorise a joindre le routeur via WireGuard et les services d'administration si active.</small>
              </td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Pair distant</td>
              <td>
                <input class="form-control" type="text" name="wg_peer_name" value="<?= tikras_script_h($params['wg_peer_name']); ?>" placeholder="site-distance">
                <input class="form-control mr-t-5" type="text" name="wg_peer_public_key" value="<?= tikras_script_h($params['wg_peer_public_key']); ?>" placeholder="Cle publique WireGuard du routeur distant">
                <small class="tikras-script-help">Si la cle publique est vide, le script cree l'interface et affiche la cle locale dans les logs.</small>
              </td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Endpoint distant</td>
              <td>
                <input class="form-control" type="text" name="wg_peer_endpoint" value="<?= tikras_script_h($params['wg_peer_endpoint']); ?>" placeholder="ip-ou-dns-du-hub">
                <input class="form-control mr-t-5" type="number" min="1" max="65535" name="wg_peer_endpoint_port" value="<?= tikras_script_h($params['wg_peer_endpoint_port']); ?>" placeholder="13231">
                <small class="tikras-script-help">A remplir surtout sur les sites distants. Le hub peut laisser vide pour des sites avec IP dynamique.</small>
              </td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Allowed address</td>
              <td>
                <input class="form-control" type="text" name="wg_allowed_address" value="<?= tikras_script_h($params['wg_allowed_address']); ?>" placeholder="10.252.0.2/32,192.168.20.0/24">
                <small class="tikras-script-help">Adresses joignables derriere le pair. Exemple hub: IP tunnel du site + LAN du site.</small>
              </td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Routes distantes</td>
              <td>
                <input class="form-control" type="text" name="wg_route_networks" value="<?= tikras_script_h($params['wg_route_networks']); ?>" placeholder="192.168.20.0/24,192.168.30.0/24">
                <small class="tikras-script-help">Routes ajoutees vers le tunnel. Laissez vide si ce routeur n'a pas besoin de routes statiques.</small>
              </td>
            </tr>
            <tr data-script-templates="wireguard-interconnect">
              <td class="align-middle">Options</td>
              <td>
                <input class="form-control" type="text" name="wg_keepalive" value="<?= tikras_script_h($params['wg_keepalive']); ?>" placeholder="25s">
                <select class="form-control mr-t-5" name="wg_allow_management">
                  <option value="yes" <?php if ($params['wg_allow_management'] == "yes") { echo 'selected'; } ?>>Autoriser API/Winbox/SSH depuis le tunnel</option>
                  <option value="no" <?php if ($params['wg_allow_management'] == "no") { echo 'selected'; } ?>>Ne pas modifier les services admin</option>
                </select>
                <select class="form-control mr-t-5" name="wg_nat_bypass">
                  <option value="yes" <?php if ($params['wg_nat_bypass'] == "yes") { echo 'selected'; } ?>>Ajouter exception NAT vers routes distantes</option>
                  <option value="no" <?php if ($params['wg_nat_bypass'] == "no") { echo 'selected'; } ?>>Ne pas toucher au NAT</option>
                </select>
              </td>
            </tr>
            <tr data-script-templates="radius-roaming">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-key"></i> Roaming RADIUS central</td>
            </tr>
            <tr data-script-templates="radius-roaming">
              <td class="align-middle">Serveur RADIUS</td>
              <td>
                <input class="form-control" type="text" name="radius_server_address" value="<?= tikras_script_h($params['radius_server_address']); ?>" placeholder="10.252.0.1">
                <input class="form-control mr-t-5" type="text" name="radius_secret" value="<?= tikras_script_h($params['radius_secret']); ?>" placeholder="secret-partage">
                <small class="tikras-script-help">Adresse du serveur RADIUS central et secret partage. Utilisez de preference l'IP WireGuard du serveur.</small>
              </td>
            </tr>
            <tr data-script-templates="radius-roaming">
              <td class="align-middle">Ports et timeout</td>
              <td>
                <input class="form-control" type="number" min="1" max="65535" name="radius_auth_port" value="<?= tikras_script_h($params['radius_auth_port']); ?>" placeholder="1812">
                <input class="form-control mr-t-5" type="number" min="1" max="65535" name="radius_acct_port" value="<?= tikras_script_h($params['radius_acct_port']); ?>" placeholder="1813">
                <input class="form-control mr-t-5" type="text" name="radius_timeout" value="<?= tikras_script_h($params['radius_timeout']); ?>" placeholder="3s">
                <small class="tikras-script-help">Port auth, port accounting, puis timeout RouterOS.</small>
              </td>
            </tr>
            <tr data-script-templates="radius-roaming">
              <td class="align-middle">Services RADIUS</td>
              <td>
                <select class="form-control" name="radius_services">
                  <option value="hotspot" <?php if ($params['radius_services'] == "hotspot") { echo 'selected'; } ?>>Hotspot seulement</option>
                  <option value="hotspot,ppp" <?php if ($params['radius_services'] == "hotspot,ppp") { echo 'selected'; } ?>>Hotspot + PPP</option>
                  <option value="ppp" <?php if ($params['radius_services'] == "ppp") { echo 'selected'; } ?>>PPP seulement</option>
                </select>
              </td>
            </tr>
            <tr data-script-templates="radius-roaming">
              <td class="align-middle">Profils Hotspot</td>
              <td>
                <select class="form-control" name="radius_hotspot_mode">
                  <option value="all" <?php if ($params['radius_hotspot_mode'] == "all") { echo 'selected'; } ?>>Activer sur tous les profils Hotspot</option>
                  <option value="selected" <?php if ($params['radius_hotspot_mode'] == "selected") { echo 'selected'; } ?>>Activer sur un profil seulement</option>
                  <option value="none" <?php if ($params['radius_hotspot_mode'] == "none") { echo 'selected'; } ?>>Ne pas modifier les profils Hotspot</option>
                </select>
                <select class="form-control mr-t-5" name="radius_hotspot_profile">
                  <?php
                  if (is_array($hotspotServerProfiles)) {
                    foreach ($hotspotServerProfiles as $hsProfileRow) {
                      if (!isset($hsProfileRow['name'])) {
                        continue;
                      }
                      $hsProfileName = $hsProfileRow['name'];
                      echo '<option value="' . tikras_script_h($hsProfileName) . '"';
                      if ($hsProfileName == $params['radius_hotspot_profile']) {
                        echo ' selected';
                      }
                      echo '>' . tikras_script_h($hsProfileName) . '</option>';
                    }
                  }
                  ?>
                </select>
                <small class="tikras-script-help">Profil Hotspot serveur, pas profil utilisateur voucher. Le script met `use-radius=yes`.</small>
              </td>
            </tr>
            <tr data-script-templates="radius-roaming">
              <td class="align-middle">Accounting</td>
              <td>
                <select class="form-control" name="radius_accounting">
                  <option value="yes" <?php if ($params['radius_accounting'] == "yes") { echo 'selected'; } ?>>Activer accounting Hotspot</option>
                  <option value="no" <?php if ($params['radius_accounting'] == "no") { echo 'selected'; } ?>>Désactiver accounting Hotspot</option>
                </select>
                <input class="form-control mr-t-5" type="text" name="radius_interim_update" value="<?= tikras_script_h($params['radius_interim_update']); ?>" placeholder="5m">
                <small class="tikras-script-help">L'interim update remonte l'usage au serveur central pour suivre temps/données.</small>
              </td>
            </tr>
            <tr data-script-templates="radius-roaming">
              <td class="align-middle">PPP et source</td>
              <td>
                <select class="form-control" name="radius_enable_ppp">
                  <option value="no" <?php if ($params['radius_enable_ppp'] == "no") { echo 'selected'; } ?>>Ne pas modifier PPP</option>
                  <option value="yes" <?php if ($params['radius_enable_ppp'] == "yes") { echo 'selected'; } ?>>Activer PPP AAA RADIUS</option>
                </select>
                <input class="form-control mr-t-5" type="text" name="radius_src_address" value="<?= tikras_script_h($params['radius_src_address']); ?>" placeholder="IP source optionnelle">
                <small class="tikras-script-help">L'IP source est utile si le serveur RADIUS attend l'IP WireGuard du routeur.</small>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-ticket"></i> Tickets Hotspot automatisés</td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Déclenchement</td>
              <td>
                <select class="form-control" name="ticket_trigger" id="ticketTrigger">
                  <option value="stock" <?php if ($params['ticket_trigger'] == "stock") { echo 'selected'; } ?>>Stock minimum</option>
                  <option value="interval" <?php if ($params['ticket_trigger'] == "interval") { echo 'selected'; } ?>>Quantité fixe</option>
                </select>
                <small class="tikras-script-help">Stock minimum créé seulement quand les tickets inutilisés du profil arrivent au seuil.</small>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Période de génération</td>
              <td>
                <input class="form-control" type="text" name="ticket_interval" value="<?= tikras_script_h($params['ticket_interval']); ?>" placeholder="1d">
                <small class="tikras-script-help">Exemples: 30m, 6h, 1d ou 02:00:00.</small>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Profil tickets</td>
              <td>
                <select class="form-control" name="ticket_profile">
                  <?php
                  if (is_array($hotspotProfiles)) {
                    foreach ($hotspotProfiles as $profileRow) {
                      if (!isset($profileRow['name'])) {
                        continue;
                      }
                      $profileName = $profileRow['name'];
                      echo '<option value="' . tikras_script_h($profileName) . '"';
                      if ($profileName == $params['ticket_profile']) {
                        echo ' selected';
                      }
                      echo '>' . tikras_script_h($profileName) . '</option>';
                    }
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Serveur Hotspot</td>
              <td>
                <select class="form-control" name="ticket_server">
                  <option value="all" <?php if ($params['ticket_server'] == "all") { echo 'selected'; } ?>>all</option>
                  <?php
                  if (is_array($hotspotServers)) {
                    foreach ($hotspotServers as $serverRow) {
                      if (!isset($serverRow['name'])) {
                        continue;
                      }
                      $serverName = $serverRow['name'];
                      echo '<option value="' . tikras_script_h($serverName) . '"';
                      if ($serverName == $params['ticket_server']) {
                        echo ' selected';
                      }
                      echo '>' . tikras_script_h($serverName) . '</option>';
                    }
                  }
                  ?>
                </select>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Mode tickets</td>
              <td>
                <select class="form-control" name="ticket_mode">
                  <option value="vc" <?php if ($params['ticket_mode'] == "vc") { echo 'selected'; } ?>>Code unique</option>
                  <option value="up" <?php if ($params['ticket_mode'] == "up") { echo 'selected'; } ?>>Utilisateur + mot de passe</option>
                </select>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Format code</td>
              <td>
                <select class="form-control" name="ticket_charset">
                  <option value="mix" <?php if ($params['ticket_charset'] == "mix") { echo 'selected'; } ?>>Lettres + chiffres lisibles</option>
                  <option value="upper" <?php if ($params['ticket_charset'] == "upper") { echo 'selected'; } ?>>Lettres majuscules</option>
                  <option value="num" <?php if ($params['ticket_charset'] == "num") { echo 'selected'; } ?>>Chiffres uniquement</option>
                </select>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Quantité fixe</td>
              <td><input class="form-control" type="number" min="1" max="500" name="ticket_qty" value="<?= tikras_script_h($params['ticket_qty']); ?>" placeholder="20"></td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack" data-ticket-stock="1">
              <td class="align-middle">Stock minimum</td>
              <td><input class="form-control" type="number" min="0" max="5000" name="ticket_stock_min" value="<?= tikras_script_h($params['ticket_stock_min']); ?>" placeholder="10"></td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack" data-ticket-stock="1">
              <td class="align-middle">Stock cible</td>
              <td>
                <input class="form-control" type="number" min="1" max="5000" name="ticket_stock_target" value="<?= tikras_script_h($params['ticket_stock_target']); ?>" placeholder="50">
                <small class="tikras-script-help">Quand le stock est bas, le script complète jusqu'à cette valeur.</small>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Préfixe tickets</td>
              <td><input class="form-control" type="text" name="ticket_prefix" value="<?= tikras_script_h($params['ticket_prefix']); ?>" placeholder="AUTO-"></td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Longueur code</td>
              <td><input class="form-control" type="number" min="3" max="12" name="ticket_length" value="<?= tikras_script_h($params['ticket_length']); ?>" placeholder="6"></td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Durée ticket</td>
              <td><input class="form-control" type="text" name="ticket_uptime" value="<?= tikras_script_h($params['ticket_uptime']); ?>" placeholder="0"></td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Limite données</td>
              <td>
                <input class="form-control" type="number" min="0" name="ticket_bytes" value="<?= tikras_script_h($params['ticket_bytes']); ?>" placeholder="0">
                <small class="tikras-script-help">Valeur en octets. 0 laisse le ticket sans limite de données.</small>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-file-pdf-o"></i> Envoi automatique PDF</td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Canal</td>
              <td>
                <select class="form-control" name="ticket_share_channel" id="ticketShareChannel">
                  <option value="whatsapp" <?php if ($params['ticket_share_channel'] == "whatsapp") { echo 'selected'; } ?>>WhatsApp</option>
                  <option value="telegram" <?php if ($params['ticket_share_channel'] == "telegram") { echo 'selected'; } ?>>Telegram</option>
                  <option value="email" <?php if ($params['ticket_share_channel'] == "email") { echo 'selected'; } ?>>Email</option>
                </select>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">Destinataire</td>
              <td>
                <input class="form-control" type="text" name="ticket_share_target" id="ticketShareTarget" value="<?= tikras_script_h($params['ticket_share_target']); ?>" placeholder="22790000000">
                <small class="tikras-script-help" id="ticketShareTargetHelp">Numéro au format international, ex: 22790000000.</small>
              </td>
            </tr>
            <tr data-script-templates="ticket-automation professional-pack">
              <td class="align-middle">URL TIKRAS IT</td>
              <td>
                <input class="form-control" type="text" name="ticket_notify_url" value="<?= tikras_script_h($params['ticket_notify_url']); ?>" placeholder="http://ip-mikhmon/process/ticketautoshare.php">
                <small class="tikras-script-help">Le routeur appelle cette URL pour créer le PDF petit ticket après génération.</small>
              </td>
            </tr>
            <tr data-script-templates="hotspot-hygiene professional-pack">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-recycle"></i> Maintenance Hotspot et DHCP</td>
            </tr>
            <tr data-script-templates="hotspot-hygiene professional-pack">
              <td class="align-middle">Fréquence nettoyage</td>
              <td>
                <input class="form-control" type="text" name="cleanup_interval" value="<?= tikras_script_h($params['cleanup_interval']); ?>" placeholder="1d">
                <small class="tikras-script-help">Nettoie les cookies Hotspot, les hôtes non autorisés et les baux DHCP dynamiques en attente.</small>
              </td>
            </tr>
            <tr data-script-templates="automation multiwan-failover ticket-automation hotspot-hygiene professional-pack">
              <td class="tikras-script-section" colspan="2"><i class="fa fa-clock-o"></i> Planification</td>
            </tr>
            <tr data-script-templates="automation multiwan-failover ticket-automation hotspot-hygiene professional-pack">
              <td class="align-middle">Heure demarrage</td>
              <td><input class="form-control" type="text" name="scheduler_time" value="<?= tikras_script_h($params['scheduler_time']); ?>" placeholder="03:00:00"></td>
            </tr>
          </table>

          <div class="tikras-script-apply-guard tikras-risk-border-<?= tikras_script_h($selectedRisk); ?>">
            <label>
              <input type="checkbox" name="apply_confirm" value="1" id="tikrasApplyConfirm">
              <span>J ai verifie le scenario, le routeur cible, le risque et le script genere.</span>
            </label>
            <small>Les modeles a risque eleve peuvent modifier routes, firewall, Hotspot ou acces administration. Faites une sauvegarde avant application.</small>
          </div>

          <div class="tikras-script-actions">
            <button class="btn bg-primary" type="submit" name="preview_script" value="1"><i class="fa fa-eye"></i> Previsualiser</button>
            <button class="btn bg-success" type="submit" name="apply_script" value="1" id="tikrasApplyButton" disabled onclick="return confirm('Appliquer ce script au routeur <?= tikras_script_h($session); ?> ?');"><i class="fa fa-bolt"></i> Appliquer au routeur</button>
          </div>
        </form>
      </div>
    </div>

    <div class="tikras-script-note">
      <i class="fa <?= tikras_script_h($templates[$selectedTemplate]['icon']); ?>"></i>
      <div>
        <strong><?= tikras_script_h($templates[$selectedTemplate]['title']); ?></strong><br>
        <?= tikras_script_h($templates[$selectedTemplate]['description']); ?>
      </div>
    </div>
  </div>

  <div class="col-7">
    <div class="card">
      <div class="card-header">
        <h3><i class="fa fa-file-code-o"></i> Script genere</h3>
      </div>
      <div class="card-body">
        <div class="tikras-script-summary">
          <div>
            <span>Scenario</span>
            <strong><?= tikras_script_h($templates[$selectedTemplate]['title']); ?></strong>
          </div>
          <div>
            <span>Impact</span>
            <strong><?= tikras_script_h($selectedImpact); ?></strong>
          </div>
          <div>
            <span>Risque</span>
            <strong class="tikras-risk-text-<?= tikras_script_h($selectedRisk); ?>"><?= tikras_script_h($selectedRiskLabel); ?></strong>
          </div>
          <div>
            <span>Taille</span>
            <strong><?= $scriptLineCount; ?> lignes / <?= $scriptSizeKb; ?> Ko</strong>
          </div>
        </div>
        <div class="tikras-script-toolbar">
          <button class="btn bg-primary" type="button" id="copyGeneratedScript"><i class="fa fa-copy"></i> Copier</button>
          <button class="btn bg-secondary" type="button" id="downloadGeneratedScript"><i class="fa fa-download"></i> Télécharger .rsc</button>
        </div>
        <textarea id="tikrasScriptPreview" class="tikras-script-preview" readonly><?= tikras_script_h($scriptSource); ?></textarea>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var model = document.querySelector('.tikras-script-page select[name="script_model"]');
  var trigger = document.getElementById('ticketTrigger');
  var shareChannel = document.getElementById('ticketShareChannel');
  var shareTarget = document.getElementById('ticketShareTarget');
  var shareHelp = document.getElementById('ticketShareTargetHelp');
  var cards = document.querySelectorAll('.tikras-script-page [data-script-card]');
  var copyButton = document.getElementById('copyGeneratedScript');
  var downloadButton = document.getElementById('downloadGeneratedScript');
  var applyConfirm = document.getElementById('tikrasApplyConfirm');
  var applyButton = document.getElementById('tikrasApplyButton');
  var preview = document.getElementById('tikrasScriptPreview');
  var rows = document.querySelectorAll('.tikras-script-page [data-script-templates]');
  var stockRows = document.querySelectorAll('.tikras-script-page [data-ticket-stock]');

  function setRowVisible(row, visible) {
    row.style.display = visible ? '' : 'none';
    var controls = row.querySelectorAll('input, select, textarea, button');
    for (var i = 0; i < controls.length; i++) {
      controls[i].disabled = !visible;
    }
  }

  function refreshApplyButton() {
    if (!applyButton || !applyConfirm) {
      return;
    }
    applyButton.disabled = !applyConfirm.checked;
  }

  function refreshScriptRows() {
    if (!model) {
      return;
    }
    var selected = ' ' + model.value + ' ';
    for (var i = 0; i < rows.length; i++) {
      var templates = ' ' + rows[i].getAttribute('data-script-templates') + ' ';
      var modelVisible = templates.indexOf(selected) > -1;
      rows[i].setAttribute('data-script-model-visible', modelVisible ? '1' : '0');
      setRowVisible(rows[i], modelVisible);
    }
    for (var j = 0; j < cards.length; j++) {
      cards[j].className = cards[j].className.replace(' active', '');
      if (cards[j].getAttribute('data-script-card') === model.value) {
        cards[j].className += ' active';
      }
    }
    refreshStockRows();
    refreshShareTarget();
  }

  function refreshStockRows() {
    if (!trigger) {
      return;
    }
    var showStock = trigger.value === 'stock';
    for (var i = 0; i < stockRows.length; i++) {
      setRowVisible(stockRows[i], stockRows[i].getAttribute('data-script-model-visible') === '1' && showStock);
    }
  }

  if (model) {
    model.addEventListener('change', refreshScriptRows);
  }
  for (var cardIndex = 0; cardIndex < cards.length; cardIndex++) {
    cards[cardIndex].addEventListener('click', function () {
      if (!model) {
        return;
      }
      model.value = this.getAttribute('data-script-card');
      refreshScriptRows();
      if (model.form) {
        model.form.submit();
      }
    });
  }
  if (trigger) {
    trigger.addEventListener('change', refreshScriptRows);
  }
  if (shareChannel) {
    shareChannel.addEventListener('change', refreshShareTarget);
  }
  if (applyConfirm) {
    applyConfirm.addEventListener('change', refreshApplyButton);
  }
  function refreshShareTarget() {
    if (!shareChannel || !shareTarget || !shareHelp) {
      return;
    }
    if (shareChannel.value === 'email') {
      shareTarget.placeholder = 'client@example.com';
      shareHelp.innerHTML = 'Adresse email qui recevra le PDF si PHP mail est configuré.';
    } else if (shareChannel.value === 'telegram') {
      shareTarget.placeholder = '-1001234567890';
      shareHelp.innerHTML = 'Chat ID Telegram. Laissez vide si un Chat ID par défaut est configuré.';
    } else {
      shareTarget.placeholder = '22790000000';
      shareHelp.innerHTML = 'Numéro au format international. Le PDF sera préparé avec un lien WhatsApp.';
    }
  }
  if (copyButton && preview) {
    copyButton.addEventListener('click', function () {
      preview.focus();
      preview.select();
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(preview.value);
      } else {
        document.execCommand('copy');
      }
      copyButton.innerHTML = '<i class="fa fa-check"></i> Copié';
      setTimeout(function () {
        copyButton.innerHTML = '<i class="fa fa-copy"></i> Copier';
      }, 1600);
    });
  }
  if (downloadButton && preview) {
    downloadButton.addEventListener('click', function () {
      var blob = new Blob([preview.value], { type: 'text/plain;charset=utf-8' });
      var link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'tikras-it-<?= tikras_script_h($selectedTemplate); ?>.rsc';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(link.href);
    });
  }
  refreshScriptRows();
  refreshApplyButton();
})();
</script>
