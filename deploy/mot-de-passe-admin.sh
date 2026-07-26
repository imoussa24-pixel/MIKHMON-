#!/bin/bash
#
# Consulte ou redefinit le compte administrateur du panneau.
# A executer SUR LE SERVEUR:
#   bash deploy/mot-de-passe-admin.sh                      # etat du compte
#   bash deploy/mot-de-passe-admin.sh NOUVEAU_MOT_DE_PASSE # redefinit
#   bash deploy/mot-de-passe-admin.sh NOUVEAU_MDP nouvel_utilisateur
#
# Utile si vous etes bloque dehors: le compte enregistre ici fait toujours foi.
set -uo pipefail

CONTENEUR="${TIKRAS_CONTAINER:-tikras-app}"
NOUVEAU="${1:-}"
UTILISATEUR="${2:-}"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTENEUR"; then
  echo "Conteneur $CONTENEUR introuvable." >&2
  exit 1
fi

if [ -z "$NOUVEAU" ]; then
  docker exec "$CONTENEUR" php -r '
    require "/var/www/html/lib/routeros_api.class.php";
    $f = "/data/config.local.php";
    if (!is_file($f)) { echo "Aucune configuration locale: le compte vient des variables d\047environnement.\n"; exit; }
    $d = include($f);
    if (!isset($d["mikhmon"])) { echo "Aucun compte enregistre.\n"; exit; }
    $u = explode("<|<", $d["mikhmon"][1], 2);
    $p = explode(">|>", $d["mikhmon"][2], 2);
    $mdp = decrypt(isset($p[1]) ? $p[1] : "");
    echo "Utilisateur : ", isset($u[1]) ? $u[1] : "(vide)", "\n";
    echo "Mot de passe: ", $mdp === "" ? "(vide - les variables d\047environnement prennent le relais)" : strlen($mdp) . " caracteres, commence par " . substr($mdp, 0, 3), "\n";
  '
  echo ""
  echo "Pour le redefinir: bash deploy/mot-de-passe-admin.sh 'NouveauMotDePasse'"
  exit 0
fi

if [ "${#NOUVEAU}" -lt 8 ]; then
  echo "Choisissez un mot de passe d'au moins 8 caracteres." >&2
  exit 1
fi

docker exec -u www-data -e NOUVEAU_MDP="$NOUVEAU" -e NOUVEL_UTILISATEUR="$UTILISATEUR" "$CONTENEUR" php -r '
  require "/var/www/html/lib/routeros_api.class.php";
  require "/var/www/html/lib/tikras_core.php";
  require "/var/www/html/lib/tikras_config_store.php";
  $mdp = (string) getenv("NOUVEAU_MDP");
  $data = array();
  tikras_config_apply_local($data);
  $utilisateur = getenv("NOUVEL_UTILISATEUR");
  if ($utilisateur === false || $utilisateur === "") {
    $u = isset($data["mikhmon"][1]) ? explode("<|<", $data["mikhmon"][1], 2) : array();
    $utilisateur = isset($u[1]) && $u[1] !== "" ? $u[1] : "admin";
  }
  $data["mikhmon"] = array(
    "1" => "mikhmon<|<" . $utilisateur,
    "mikhmon>|>" . encrypt($mdp),
  );
  if (!tikras_config_write_all($data)) {
    fwrite(STDERR, "Ecriture impossible dans " . tikras_config_local_path() . "\n");
    exit(1);
  }
  echo "Compte mis a jour. Utilisateur: ", $utilisateur, "\n";
  echo "Le mot de passe enregistre ici a la priorite sur les variables d\047environnement.\n";
'
