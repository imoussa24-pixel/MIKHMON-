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
  # Les routeurs joignent le serveur par le reseau prive qui les relie, pas
  # depuis Internet: on ouvre donc chaque reseau auquel le serveur appartient
  # (tunnel WireGuard et reseaux ZeroTier). N'ouvrir que le tunnel laisserait
  # les routeurs joints par ZeroTier sans reponse, sans message d'erreur.
  OUVERTS=0
  for IFACE in $(ip -o link show | awk -F': ' '{print $2}' | grep -E '^(zt|wg)'); do
    CIDR=$(ip -4 -o addr show dev "$IFACE" 2>/dev/null | awk '{print $4}' | head -1)
    [ -z "$CIDR" ] && continue
    RESEAU=$(python3 -c "import ipaddress,sys; print(ipaddress.ip_network(sys.argv[1], strict=False))" "$CIDR" 2>/dev/null)
    [ -z "$RESEAU" ] && continue
    ufw allow from "$RESEAU" to any port 1812 proto udp > /dev/null 2>&1 || true
    ufw allow from "$RESEAU" to any port 1813 proto udp > /dev/null 2>&1 || true
    OUVERTS=$((OUVERTS + 1))
  done
  echo "   ports 1812 et 1813 ouverts sur ${OUVERTS} reseau(x) prive(s)"
  echo "   (relancez ce script apres avoir rejoint un nouveau reseau)"
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
