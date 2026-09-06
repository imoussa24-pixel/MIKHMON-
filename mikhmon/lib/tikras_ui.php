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

if (!function_exists('tikras_ui_status_badge')) {
  /* Badge etat routeur alimente par le health check automatique. */
  function tikras_ui_status_badge($status)
  {
    if (!is_array($status) || !isset($status['last_state']) || $status['last_state'] == 'unknown') {
      return '<span class="tikras-status tikras-status-unknown" title="Pas encore verifie">' . tikras_ui_icon('question-circle') . ' Inconnu</span>';
    }
    $latency = isset($status['last_latency_ms']) ? (int) $status['last_latency_ms'] : 0;
    $seen = isset($status['updated_at']) ? (string) $status['updated_at'] : '';
    if ($status['last_state'] == 'online') {
      return '<span class="tikras-status tikras-status-online" title="Verifie: ' . tikras_h($seen) . '">' . tikras_ui_icon('check-circle') . ' En ligne · ' . $latency . ' ms</span>';
    }
    $error = isset($status['last_error']) ? (string) $status['last_error'] : '';
    return '<span class="tikras-status tikras-status-offline" title="' . tikras_h($error . ' — ' . $seen) . '">' . tikras_ui_icon('times-circle') . ' Hors ligne</span>';
  }
}

if (!function_exists('tikras_ui_router_card')) {
  function tikras_ui_router_card($session, $hotspotName, $dnsName, $currency, $status = null, $favori = false)
  {
    $sessionUrl = rawurlencode($session);
    $safeSession = tikras_h($session);
    $safeHotspot = tikras_h($hotspotName);
    $safeDns = tikras_h($dnsName);
    $safeCurrency = tikras_h($currency);
    $displayName = $safeHotspot != '' ? $safeHotspot : $safeSession;
    $confirm = "if(confirm('Voulez-vous vraiment supprimer ce routeur " . tikras_js_string($session) . " (" . tikras_js_string($hotspotName) . ") ?')){loadpage('./admin.php?id=remove-session&session=" . tikras_js_string($sessionUrl) . "')}else{}";

    $html = '<article id="routeur-' . $safeSession . '" class="tikras-router-card tikras-router-row'
      . ($favori ? ' tikras-router-favori' : '')
      . '" data-favori="' . ($favori ? '1' : '0')
      . '" data-router="' . tikras_h(strtolower($session . ' ' . $hotspotName . ' ' . $dnsName)) . '">';
    $html .= '<div class="tikras-router-main">';
    /*
     * L'etoile est un bouton de formulaire et non un lien: marquer un favori
     * modifie un etat, ce qu'une adresse ouverte par erreur ne doit pas faire.
     */
    $html .= '<form method="post" action="./admin.php?id=sessions" class="tikras-router-favori-forme">';
    $html .= '<input type="hidden" name="session" value="' . $safeSession . '">';
    $html .= '<button type="submit" name="favori" value="1" class="tikras-favori-btn'
      . ($favori ? ' est-favori' : '') . '"'
      . ' title="' . ($favori ? 'Retirer des favoris' : 'Ajouter aux favoris') . '"'
      . ' aria-label="' . ($favori ? 'Retirer ' : 'Ajouter ') . $displayName . ' des favoris"'
      . ' aria-pressed="' . ($favori ? 'true' : 'false') . '">'
      . tikras_ui_icon($favori ? 'star' : 'star-o') . '</button>';
    $html .= '</form>';
    $html .= '<span class="tikras-router-icon">' . tikras_ui_icon('server') . '</span>';
    $html .= '<div class="tikras-router-copy">';
    $html .= '<h3 title="' . $displayName . '">' . $displayName . ' ' . tikras_ui_status_badge($status) . '</h3>';
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
    /*
     * Seules les deux actions courantes portent leur libelle; les autres
     * gardent leur icone et passent leur libelle en infobulle et en nom
     * accessible. Sur un parc de cent routeurs, cinq libelles par ligne
     * feraient tenir la liste sur une trentaine d'ecrans.
     *
     * "Tickets" mene directement au formulaire de generation. C'est le geste
     * du quotidien, et il demandait jusqu'ici de traverser quatre ecrans:
     * ouvrir le routeur, deplier le menu Hotspot, entrer dans Utilisateurs,
     * puis choisir Generer.
     */
    $html .= '<div class="tikras-router-actions">';
    $html .= '<a class="tikras-btn tikras-btn-primary" href="./?hotspot-user=generate&session=' . $sessionUrl . '">' . tikras_ui_icon('ticket') . '<span>Tickets</span></a>';
    $html .= '<a class="tikras-btn tikras-btn-muted connect" id="' . $safeSession . '" href="./admin.php?id=connect&session=' . $sessionUrl . '">' . tikras_ui_icon('external-link') . '<span>Ouvrir</span></a>';
    $html .= '<a class="tikras-btn tikras-btn-muted tikras-btn-icon" title="Éditer" aria-label="Éditer ' . $displayName . '" href="./admin.php?id=settings&session=' . $sessionUrl . '">' . tikras_ui_icon('edit') . '<span>Éditer</span></a>';
    $html .= '<a class="tikras-btn tikras-btn-muted tikras-btn-icon" title="Scripts" aria-label="Scripts de ' . $displayName . '" href="./?system=script-generator&session=' . $sessionUrl . '">' . tikras_ui_icon('code') . '<span>Scripts</span></a>';
    $html .= '<a class="tikras-btn tikras-btn-danger tikras-btn-icon" title="Supprimer" aria-label="Supprimer ' . $displayName . '" href="javascript:void(0)" onclick="' . tikras_h($confirm) . '">' . tikras_ui_icon('trash') . '<span>Supprimer</span></a>';
    $html .= '</div>';
    $html .= '</article>';
    return $html;
  }
}
?>
