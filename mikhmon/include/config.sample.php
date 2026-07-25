<?php
/*
 * Configuration de demarrage (aucun secret).
 * L'image Docker n'embarque jamais le vrai include/config.php: la liste des
 * routeurs vit dans le volume persistant (config.local.php), importee via
 * la page Sauvegarde ou tools/export-config-local.php.
 */
if (isset($_SERVER["REQUEST_URI"]) && substr($_SERVER["REQUEST_URI"], -10) == "config.php") {
  header("Location:./");
}

$data = array();
$data['mikhmon'] = array('1' => 'mikhmon<|<admin', 'mikhmon>|>');

include_once(dirname(__DIR__) . '/lib/tikras_config_store.php');
tikras_config_apply_local($data);
