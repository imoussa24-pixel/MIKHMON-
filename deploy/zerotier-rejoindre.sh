#!/bin/bash
#
# Rejoint un ou plusieurs reseaux ZeroTier sur le serveur.
# A executer SUR LE SERVEUR:
#   bash deploy/zerotier-rejoindre.sh 8056c2e21c000001 a1b2c3d4e5f60001 ...
#
# Chaque reseau doit ensuite etre autorise dans my.zerotier.com (section
# Members): cochez Auth pour l'identite affichee en fin de script.
set -uo pipefail

if [ "$#" -lt 1 ]; then
  echo "Usage: bash deploy/zerotier-rejoindre.sh ID_RESEAU [ID_RESEAU...]" >&2
  exit 1
fi

if ! command -v zerotier-cli > /dev/null 2>&1; then
  echo "ZeroTier n'est pas installe. Lancez d'abord deploy/vps-install.sh" >&2
  exit 1
fi

IDENTITE=$(zerotier-cli info 2>/dev/null | awk '{print $3}')

echo "== Adhesion aux reseaux =="
for RESEAU in "$@"; do
  # Un identifiant de reseau ZeroTier fait 16 caracteres hexadecimaux.
  if ! echo "$RESEAU" | grep -qE '^[0-9a-fA-F]{16}$'; then
    echo "  [ignore] '$RESEAU' n'est pas un identifiant valide (16 caracteres hexadecimaux)"
    continue
  fi
  if zerotier-cli join "$RESEAU" > /dev/null 2>&1; then
    echo "  [ok] $RESEAU"
  else
    echo "  [echec] $RESEAU"
  fi
done

echo ""
echo "== Etat des reseaux (peut prendre 30 s a passer en OK) =="
sleep 5
zerotier-cli listnetworks | tail -n +2 | while read -r _ _ RESEAU NOM _ ETAT TYPE _ ADRESSES; do
  printf "  %-18s %-24s %-12s %s\n" "$RESEAU" "${NOM:-sans-nom}" "$ETAT" "${ADRESSES:-aucune adresse}"
done

echo ""
echo "=================================================================="
echo " Identite de ce serveur : ${IDENTITE}"
echo ""
echo " Sur https://my.zerotier.com, pour CHAQUE reseau ci-dessus:"
echo "   1. ouvrez le reseau, section Members"
echo "   2. reperez l'identite ${IDENTITE}"
echo "   3. cochez Auth et nommez-la 'serveur-tikras'"
echo ""
echo " Tant qu'un reseau affiche ACCESS_DENIED, l'autorisation manque."
echo "=================================================================="
