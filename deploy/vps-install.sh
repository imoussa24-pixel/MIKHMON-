#!/bin/bash
#
# Installation TIKRAS IT sur un VPS Ubuntu/Debian neuf (Option A).
#   1. Copier le projet sur le VPS (git clone du depot prive, ou scp).
#   2. cd dans le dossier du projet puis:  sudo bash deploy/vps-install.sh
#
# Le script installe Docker + ZeroTier, rejoint le reseau, configure le
# pare-feu, demarre l'application et programme les automatisations.
set -euo pipefail

if [ "$(id -u)" != "0" ]; then
  echo "Lancez ce script avec sudo." >&2
  exit 1
fi

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

echo "== [1/6] Paquets de base =="
apt-get update -y
apt-get install -y ca-certificates curl gnupg ufw

echo "== [2/6] Docker =="
if ! command -v docker > /dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh
fi

echo "== [3/6] ZeroTier =="
if ! command -v zerotier-cli > /dev/null 2>&1; then
  curl -s https://install.zerotier.com | bash
fi
systemctl enable --now zerotier-one

if [ -n "${ZT_NETWORK:-}" ]; then
  zerotier-cli join "$ZT_NETWORK" || true
  echo ">> Reseau ZeroTier rejoint: $ZT_NETWORK"
else
  read -r -p "ID du reseau ZeroTier a rejoindre (vide pour passer): " ZT_NETWORK
  if [ -n "$ZT_NETWORK" ]; then
    zerotier-cli join "$ZT_NETWORK" || true
  fi
fi
echo ">> IMPORTANT: autorisez ce serveur dans my.zerotier.com (Members)."
echo ">> Identite du serveur: $(zerotier-cli info || true)"

echo "== [4/6] Configuration =="
if [ ! -f .env ]; then
  cp deploy/.env.example .env
  TOKEN=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')
  sed -i "s/^TIKRAS_CRON_TOKEN=.*/TIKRAS_CRON_TOKEN=${TOKEN}/" .env
  echo ">> Fichier .env cree. EDITEZ-LE (mot de passe admin!) : nano $PROJECT_DIR/.env"
  read -r -p "Appuyez sur Entree une fois .env edite..." _
fi

echo "== [5/6] Pare-feu =="
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw allow 9993/udp   # ZeroTier
ufw --force enable

echo "== [6/6] Application =="
if grep -q '^TIKRAS_DOMAIN=..*' .env; then
  docker compose --profile https up -d --build
else
  docker compose up -d --build
fi

# Les automatisations tournent dans le conteneur (planificateur interne de
# l'entrypoint). Rien a programmer sur l'hote.

echo ""
echo "=================================================================="
echo " TIKRAS IT est en ligne."
echo " - Interface : http://$(hostname -I | awk '{print $1}')/  (ou votre domaine en HTTPS)"
echo " - Connexion : identifiants TIKRAS_ADMIN_USER / TIKRAS_ADMIN_PASS du .env"
echo " - Importez vos routeurs : page Sauvegarde > restaurer le ZIP local,"
echo "   ou copiez config.local.php (voir tools/export-config-local.php)."
echo " - Automatisations : planificateur interne du conteneur (toutes les 5 min)."
echo "   Journal : docker exec tikras-app cat /data/automations.log"
echo " - Verifier ZeroTier : zerotier-cli listnetworks  (statut OK attendu)"
echo "=================================================================="
