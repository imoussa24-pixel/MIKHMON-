<?php
/*
 * TIKRAS UI helpers.
 * Small reusable view helpers for the progressive interface refresh.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');

if (!function_exists('tikras_ui_icon')) {
  function tikras_ui_icon($name)
  {
    return '<i class="fa fa-' . tikras_h($name) . '"></i>';
  }
}

if (!function_exists('tikras_ui_alert')) {
  function tikras_ui_alert($type, $message, $icon = '')
  {
    $type = preg_replace('/[^a-z0-9_-]/i', '', (string) $type);
    if ($icon == '') {
      $icon = $type == 'danger' ? 'warning' : ($type == 'success' ? 'check-circle' : 'info-circle');
    }
    return '<div class="tikras-alert tikras-alert-' . $type . '">' . tikras_ui_icon($icon) . '<span>' . tikras_h($message) . '</span></div>';
  }
}

if (!function_exists('tikras_ui_page_header')) {
  function tikras_ui_page_header($icon, $title, $subtitle = '', $actions = '')
  {
    $html = '<div class="tikras-page-header">';
    $html .= '<div class="tikras-page-title">';
    $html .= '<span class="tikras-page-icon">' . tikras_ui_icon($icon) . '</span>';
    $html .= '<div><h2>' . tikras_h($title) . '</h2>';
    if ($subtitle != '') {
      $html .= '<p>' . tikras_h($subtitle) . '</p>';
    }
    $html .= '</div></div>';
    if ($actions != '') {
      $html .= '<div class="tikras-page-actions">' . $actions . '</div>';
    }
    $html .= '</div>';
    return $html;
  }
}

if (!function_exists('tikras_ui_button')) {
  function tikras_ui_button($href, $icon, $label, $variant = 'primary', $extraClass = '')
  {
    $class = trim('tikras-btn tikras-btn-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $variant) . ' ' . $extraClass);
    return '<a class="' . tikras_h($class) . '" href="' . tikras_h($href) . '">' . tikras_ui_icon($icon) . '<span>' . tikras_h($label) . '</span></a>';
  }
}

if (!function_exists('tikras_ui_router_card')) {
  function tikras_ui_router_card($session, $hotspotName, $dnsName, $currency)
  {
    $sessionUrl = rawurlencode($session);
    $safeSession = tikras_h($session);
    $safeHotspot = tikras_h($hotspotName);
    $safeDns = tikras_h($dnsName);
    $safeCurrency = tikras_h($currency);
    $displayName = $safeHotspot != '' ? $safeHotspot : $safeSession;
    $confirm = "if(confirm('Voulez-vous vraiment supprimer ce routeur " . tikras_js_string($session) . " (" . tikras_js_string($hotspotName) . ") ?')){loadpage('./admin.php?id=remove-session&session=" . tikras_js_string($sessionUrl) . "')}else{}";

    $html = '<article class="tikras-router-card tikras-router-row" data-router="' . tikras_h(strtolower($session . ' ' . $hotspotName . ' ' . $dnsName)) . '">';
    $html .= '<div class="tikras-router-main">';
    $html .= '<span class="tikras-router-icon">' . tikras_ui_icon('server') . '</span>';
    $html .= '<div class="tikras-router-copy">';
    $html .= '<h3 title="' . $displayName . '">' . $displayName . '</h3>';
    $html .= '<p title="Session : ' . $safeSession . '"><span>Session</span> ' . $safeSession . '</p>';
    $html .= '<div class="tikras-router-meta">';
    if ($safeDns != '') {
      $html .= '<span title="DNS : ' . $safeDns . '">' . tikras_ui_icon('globe') . ' DNS&nbsp;: ' . $safeDns . '</span>';
    }
    if ($safeCurrency != '') {
      $html .= '<span title="Devise : ' . $safeCurrency . '">' . tikras_ui_icon('money') . ' Devise&nbsp;: ' . $safeCurrency . '</span>';
    }
    $html .= '</div>';
    $html .= '</div></div>';
    $html .= '<div class="tikras-router-actions">';
    $html .= '<a class="tikras-btn tikras-btn-primary connect" id="' . $safeSession . '" href="./admin.php?id=connect&session=' . $sessionUrl . '">' . tikras_ui_icon('external-link') . '<span>Ouvrir</span></a>';
    $html .= '<a class="tikras-btn tikras-btn-muted" href="./admin.php?id=settings&session=' . $sessionUrl . '">' . tikras_ui_icon('edit') . '<span>Éditer</span></a>';
    $html .= '<a class="tikras-btn tikras-btn-muted" href="./?system=script-generator&session=' . $sessionUrl . '">' . tikras_ui_icon('code') . '<span>Scripts</span></a>';
    $html .= '<a class="tikras-btn tikras-btn-danger" href="javascript:void(0)" onclick="' . tikras_h($confirm) . '">' . tikras_ui_icon('trash') . '<span>Supprimer</span></a>';
    $html .= '</div>';
    $html .= '</article>';
    return $html;
  }
}
?>
