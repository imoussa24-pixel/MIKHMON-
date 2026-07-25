#!/bin/bash
#
# Envoie le projet sur le serveur et lance l'installation ou la mise a jour.
# A executer depuis le PC (Git Bash sous Windows), a la racine du projet.
#
#   bash deploy/pousser-vers-serveur.sh 169.58.74.46
#
# Variables utiles:
#   TIKRAS_ADMIN_PASS   mot de passe admin (premiere installation seulement)
#   ZT_NETWORK          identifiant du reseau ZeroTier a rejoindre
#   SSH_KEY             cle privee a utiliser (defaut: ~/.ssh/tikras_deploy)
#   SANS_CONFIG=1       ne pas renvoyer la liste des routeurs
set -euo pipefail

SERVEUR="${1:-}"
if [ -z "$SERVEUR" ]; then
  echo "Usage: bash deploy/pousser-vers-serveur.sh ADRESSE_DU_SERVEUR" >&2
  exit 1
fi

UTILISATEUR_SSH="${SSH_USER:-root}"
CLE="${SSH_KEY:-$HOME/.ssh/tikras_deploy}"
CIBLE="${UTILISATEUR_SSH}@${SERVEUR}"
DISTANT="/root/tikras"
RACINE="$(cd "$(dirname "$0")/.." && pwd)"
cd "$RACINE"

SSH_OPTS=(-i "$CLE" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20)

echo "== [1/5] Verification de l'acces"
if ! ssh "${SSH_OPTS[@]}" -o BatchMode=yes "$CIBLE" 'echo ok' > /dev/null 2>&1; then
  echo "ERREUR: connexion impossible a $CIBLE avec la cle $CLE." >&2
  echo "" >&2
  echo "Installez d'abord la cle publique sur le serveur en collant cette" >&2
  echo "commande dans votre terminal (votre mot de passe root sera demande):" >&2
  echo "" >&2
  if [ -f "${CLE}.pub" ]; then
    echo "  ssh ${CIBLE} \"mkdir -p ~/.ssh && chmod 700 ~/.ssh && echo '$(cat "${CLE}.pub")' >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && echo INSTALLE\"" >&2
  else
    echo "  (cle publique introuvable: ${CLE}.pub)" >&2
  fi
  echo "" >&2
  exit 1
fi
echo "   acces confirme ($(ssh "${SSH_OPTS[@]}" "$CIBLE" 'hostname; . /etc/os-release 2>/dev/null && echo $PRETTY_NAME' | tr '\n' ' '))"

echo "== [2/5] Preparation de l'archive"
ARCHIVE="$(mktemp -t tikras-XXXXXX).tar.gz"
tar --force-local \
    --exclude=.git --exclude=php8 --exclude=.claude --exclude=lanceur.exe \
    --exclude=.env --exclude='mikhmon/share/logs' --exclude='*.zip' \
    --exclude='mikhmon/include/config.php' --exclude='mikhmon/include/notify_config.php' \
    -czf "$ARCHIVE" .
echo "   archive: $(du -h "$ARCHIVE" | cut -f1)"

echo "== [3/5] Transfert"
ssh "${SSH_OPTS[@]}" "$CIBLE" "mkdir -p $DISTANT"
scp "${SSH_OPTS[@]}" -q "$ARCHIVE" "$CIBLE:/tmp/tikras-projet.tar.gz"
ssh "${SSH_OPTS[@]}" "$CIBLE" "tar -xzf /tmp/tikras-projet.tar.gz -C $DISTANT && rm -f /tmp/tikras-projet.tar.gz && chmod +x $DISTANT/deploy/*.sh"
rm -f "$ARCHIVE"
echo "   projet deploye dans $DISTANT"

if [ "${SANS_CONFIG:-0}" != "1" ]; then
  echo "== [4/5] Envoi de la liste des routeurs"
  CONFIG_TMP="$(mktemp -t tikras-cfg-XXXXXX).php"
  if [ -x "./php8/php.exe" ]; then
    ./php8/php.exe tools/export-config-local.php "$CONFIG_TMP" > /dev/null
  elif command -v php > /dev/null 2>&1; then
    php tools/export-config-local.php "$CONFIG_TMP" > /dev/null
  else
    echo "   PHP introuvable: liste des routeurs non exportee." ; CONFIG_TMP=""
  fi
  if [ -n "$CONFIG_TMP" ] && [ -s "$CONFIG_TMP" ]; then
    scp "${SSH_OPTS[@]}" -q "$CONFIG_TMP" "$CIBLE:/root/config.local.php"
    ssh "${SSH_OPTS[@]}" "$CIBLE" "chmod 600 /root/config.local.php"
    NB=$(grep -c "^  '" "$CONFIG_TMP" 2>/dev/null || echo "?")
    echo "   liste des routeurs envoyee"
    rm -f "$CONFIG_TMP"
  fi
else
  echo "== [4/5] Liste des routeurs ignoree (SANS_CONFIG=1)"
fi

echo "== [5/5] Installation / mise a jour"
if ssh "${SSH_OPTS[@]}" "$CIBLE" "test -f $DISTANT/.env"; then
  echo "   configuration existante: mise a jour de l'application"
  ssh "${SSH_OPTS[@]}" "$CIBLE" "cd $DISTANT && docker compose up -d --build 2>&1 | tail -5"
  # Import de la liste si elle vient d'etre envoyee.
  ssh "${SSH_OPTS[@]}" "$CIBLE" "
    if [ -f /root/config.local.php ]; then
      docker cp /root/config.local.php tikras-app:/data/config.local.php &&
      docker exec tikras-app chown www-data:www-data /data/config.local.php &&
      docker exec tikras-app chmod 640 /data/config.local.php &&
      docker exec -u www-data tikras-app php /var/www/html/cron.php --force > /dev/null 2>&1
      rm -f /root/config.local.php
      echo '   routeurs importes'
    fi" || true
  ssh "${SSH_OPTS[@]}" "$CIBLE" "cd $DISTANT && bash deploy/verifier.sh" || true
else
  echo "   premiere installation"
  ssh "${SSH_OPTS[@]}" "$CIBLE" \
    "cd $DISTANT && ZT_NETWORK='${ZT_NETWORK:-}' TIKRAS_ADMIN_USER='${TIKRAS_ADMIN_USER:-admin}' TIKRAS_ADMIN_PASS='${TIKRAS_ADMIN_PASS:-}' bash deploy/vps-install.sh"
fi

echo ""
echo "Termine. Panneau: http://${SERVEUR}/"
