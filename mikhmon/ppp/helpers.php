<?php
/*
 *  PPP helper functions for Mikhmon.
 */

if (!function_exists('ppp_h')) {
  function ppp_h($value)
  {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('ppp_v')) {
  function ppp_v($array, $key, $fallback = '')
  {
    return isset($array[$key]) ? $array[$key] : $fallback;
  }
}

if (!function_exists('ppp_unit')) {
  function ppp_unit($count)
  {
    return ((int) $count < 2) ? 'item' : 'items';
  }
}

if (!function_exists('ppp_bool_label')) {
  function ppp_bool_label($value)
  {
    return ($value == 'true' || $value == 'yes') ? 'No' : 'Yes';
  }
}

if (!function_exists('ppp_disabled_value')) {
  function ppp_disabled_value($value)
  {
    return ($value == 'true' || $value == 'yes') ? 'yes' : 'no';
  }
}

if (!function_exists('ppp_format_bytes_value')) {
  function ppp_format_bytes_value($value)
  {
    if ($value == '' || $value == '0') {
      return '';
    }

    if (function_exists('formatBytes')) {
      return formatBytes($value, 2);
    }

    return $value;
  }
}

if (!function_exists('ppp_limit_value')) {
  function ppp_limit_value($value)
  {
    if ($value == '' || $value == '0') {
      return '';
    }

    if ($value >= 1073741824 && $value % 1073741824 == 0) {
      return array('value' => ($value / 1073741824), 'unit' => 'GB', 'multiplier' => 1073741824);
    }

    if ($value >= 1048576 && $value % 1048576 == 0) {
      return array('value' => ($value / 1048576), 'unit' => 'MB', 'multiplier' => 1048576);
    }

    return array('value' => $value, 'unit' => 'B', 'multiplier' => 1);
  }
}

if (!function_exists('ppp_service_options')) {
  function ppp_service_options($current)
  {
    $services = array('any', 'async', 'l2tp', 'ovpn', 'pppoe', 'pptp', 'sstp');
    if ($current == '') {
      $current = 'any';
    }

    $html = "<option>" . ppp_h($current) . "</option>";
    for ($i = 0; $i < count($services); $i++) {
      if ($services[$i] != $current) {
        $html .= "<option>" . ppp_h($services[$i]) . "</option>";
      }
    }
    return $html;
  }
}

if (!function_exists('ppp_only_one_options')) {
  function ppp_only_one_options($current)
  {
    $values = array('default', 'yes', 'no');
    if ($current == '') {
      $current = 'default';
    }

    $html = "<option>" . ppp_h($current) . "</option>";
    for ($i = 0; $i < count($values); $i++) {
      if ($values[$i] != $current) {
        $html .= "<option>" . ppp_h($values[$i]) . "</option>";
      }
    }
    return $html;
  }
}
?>
