#!/bin/bash
#
# Installe le serveur RADIUS TIKRAS sur le serveur.
#
#   sudo bash deploy/radius-installer.sh
#
# Les tickets partages entre routeurs sont alors authentifies ici, a partir de
# la base commune avec le panneau, en remplacement de User Manager.
set -euo pipefail

if [ "$(id -u)" != "0" ]; then
  echo "Lancez ce script avec sudo." >&2
  exit 1
fi

PROJET="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJET"

echo "== [1/4] Construction de l'image =="
docker compose --profile radius build radius 2>&1 | tail -3

echo "== [2/4] Demarrage du service =="
docker compose --profile radius up -d radius
sleep 12

if ! docker ps --format '{{.Names}}' | grep -qx tikras-radius; then
  echo "Le service n'a pas demarre. Journal :" >&2
  docker logs tikras-radius 2>&1 | tail -20 >&2
  exit 1
fi

echo "== [3/4] Pare-feu =="
if command -v ufw > /dev/null 2>&1; then
  # Les routeurs interrogent le serveur par le tunnel, pas depuis Internet.
  ufw allow from 10.200.0.0/16 to any port 1812 proto udp > /dev/null 2>&1 || true
  ufw allow from 10.200.0.0/16 to any port 1813 proto udp > /dev/null 2>&1 || true
  echo "   ports 1812 et 1813 ouverts pour le tunnel WireGuard"
  echo "   (pour un routeur joint par ZeroTier, ouvrez aussi sa plage)"
fi

echo "== [4/4] Verification =="
# On ne retient que les erreurs anterieures a la mise en service: au-dela,
# FreeRADIUS journalise en "Error" de simples avertissements BlastRADIUS
# declenches par les sondes de verification elles-memes.
ERREURS=$(docker logs tikras-radius 2>&1 | sed '/Ready to process requests/q' | grep -icE '^.*: Error' || true)
if [ "${ERREURS:-0}" -gt 0 ]; then
  echo "   ${ERREURS} erreur(s) au demarrage :" >&2
  docker logs tikras-radius 2>&1 | sed '/Ready to process requests/q' | grep -iE '^.*: Error' | head -5 >&2
  exit 1
fi
if ! docker logs tikras-radius 2>&1 | grep -q 'Ready to process requests'; then
  echo "   le serveur n'a pas atteint l'etat operationnel" >&2
  exit 1
fi

if docker exec tikras-radius sh -c 'radtest sonde sonde 127.0.0.1 0 tikras-diagnostic 2>&1 | grep -q "Access-"'; then
  echo "   le serveur repond aux demandes d'authentification"
else
  echo "   le serveur ne repond pas" >&2
  exit 1
fi

BASE="/var/lib/docker/volumes/tikras_tikras-data/_data/radius/radius.sqlite"
echo ""
echo "=================================================================="
echo " Serveur RADIUS actif."
echo ""
echo "   Base       : ${BASE}"
echo "   Ports      : 1812 (authentification), 1813 (comptabilite)"
echo "   Adresse vue par les routeurs : 10.200.0.1 (tunnel WireGuard)"
echo ""
echo " Dans le panneau, menu RADIUS central :"
echo "   1. autorisez les routeurs concernes, un par un;"
echo "   2. collez le script affiche dans chaque routeur;"
echo "   3. generez des tickets en choisissant le partage RADIUS."
echo ""
echo " Il n'est ni utile ni souhaitable d'y raccorder tout le parc:"
echo " seuls les routeurs devant partager les memes tickets sont concernes."
echo "=================================================================="
