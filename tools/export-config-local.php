<?php
/*
 * Exporte la liste des routeurs (include/config.php + surcharges locales)
 * vers un fichier config.local.php pret a etre copie sur le serveur.
 *
 * Usage:  php8\php.exe tools\export-config-local.php [chemin_sortie]
 *
 * Sur le VPS, deposer le fichier dans le volume de donnees:
 *   docker cp config.local.php tikras-app:/data/config.local.php
 *   docker exec tikras-app chown www-data:www-data /data/config.local.php
 */
$root = dirname(__DIR__);
require_once($root . '/mikhmon/lib/tikras_core.php');
require_once($root . '/mikhmon/lib/tikras_config_store.php');

$data = array();
$session = '';
include($root . '/mikhmon/include/config.php');

if (!is_array($data) || count($data) < 1) {
  fwrite(STDERR, "Aucun routeur trouve dans include/config.php." . PHP_EOL);
  exit(1);
}

$target = isset($argv[1]) && $argv[1] != '' ? $argv[1] : ($root . DIRECTORY_SEPARATOR . 'config.local.php');
$content = "<?php\nreturn " . var_export($data, true) . ";\n";

if (file_put_contents($target, $content) === false) {
  fwrite(STDERR, "Ecriture impossible: " . $target . PHP_EOL);
  exit(1);
}

$routers = 0;
foreach ($data as $key => $value) {
  if ($key != '' && $key != 'mikhmon') {
    $routers++;
  }
}

echo "Export termine." . PHP_EOL;
echo "Fichier : " . $target . PHP_EOL;
echo "Routeurs: " . $routers . PHP_EOL;
echo PHP_EOL;
echo "ATTENTION: ce fichier contient les identifiants des routeurs." . PHP_EOL;
echo "Transferez-le uniquement par scp/ssh, jamais par email ni git." . PHP_EOL;
echo PHP_EOL;
echo "Sur le VPS:" . PHP_EOL;
echo "  scp config.local.php root@VOTRE_VPS:/tmp/" . PHP_EOL;
echo "  ssh root@VOTRE_VPS 'docker cp /tmp/config.local.php tikras-app:/data/config.local.php \\" . PHP_EOL;
echo "    && docker exec tikras-app chown www-data:www-data /data/config.local.php \\" . PHP_EOL;
echo "    && rm /tmp/config.local.php'" . PHP_EOL;
exit(0);
?>
