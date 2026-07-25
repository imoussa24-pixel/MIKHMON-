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

if [ -z "${ZT_NETWORK:-}" ] && [ -t 0 ]; then
  read -r -p "ID du reseau ZeroTier a rejoindre (vide pour passer): " ZT_NETWORK || ZT_NETWORK=""
fi

if [ -n "${ZT_NETWORK:-}" ]; then
  zerotier-cli join "$ZT_NETWORK" || true
  echo ">> Reseau ZeroTier rejoint: $ZT_NETWORK"
else
  echo ">> Aucun reseau ZeroTier indique. Les routeurs resteront injoignables"
  echo "   tant que vous n'aurez pas lance: zerotier-cli join VOTRE_NETWORK_ID"
fi
echo ">> IMPORTANT: autorisez ce serveur dans my.zerotier.com (Members)."
echo ">> Identite du serveur: $(zerotier-cli info || true)"

echo "== [4/6] Configuration =="
if [ ! -f .env ]; then
  cp deploy/.env.example .env
  TOKEN=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')
  sed -i "s/^TIKRAS_CRON_TOKEN=.*/TIKRAS_CRON_TOKEN=${TOKEN}/" .env

  # Mot de passe admin: par variable d'environnement (installation automatique),
  # sinon demande a l'ecran. Sans terminal ni variable, on s'arrete avec un
  # message clair plutot que de demarrer un panneau inaccessible.
  MDP="${TIKRAS_ADMIN_PASS:-}"
  if [ -z "$MDP" ] && [ -t 0 ]; then
    while [ -z "$MDP" ]; do
      read -r -s -p "Mot de passe administrateur (12 caracteres minimum): " MDP
      echo ""
      if [ "${#MDP}" -lt 12 ]; then
        echo "   Trop court, recommencez."
        MDP=""
      fi
    done
  fi

  if [ -z "$MDP" ]; then
    echo ""
    echo "ERREUR: aucun mot de passe administrateur fourni."
    echo "Relancez en le passant en variable d'environnement:"
    echo "  ZT_NETWORK=${ZT_NETWORK:-VOTRE_RESEAU} TIKRAS_ADMIN_PASS='votre-mot-de-passe' bash deploy/vps-install.sh"
    echo "ou editez $PROJECT_DIR/.env puis relancez ce script."
    exit 1
  fi

  UTILISATEUR="${TIKRAS_ADMIN_USER:-admin}"
  # Le mot de passe peut contenir des caracteres speciaux: on evite sed.
  python3 - "$UTILISATEUR" "$MDP" <<'PY' 2>/dev/null || {
import sys, io
utilisateur, mdp = sys.argv[1], sys.argv[2]
lignes = []
with io.open('.env', encoding='utf-8') as f:
    for ligne in f:
        if ligne.startswith('TIKRAS_ADMIN_USER='):
            ligne = 'TIKRAS_ADMIN_USER=%s\n' % utilisateur
        elif ligne.startswith('TIKRAS_ADMIN_PASS='):
            ligne = 'TIKRAS_ADMIN_PASS=%s\n' % mdp
        lignes.append(ligne)
with io.open('.env', 'w', encoding='utf-8') as f:
    f.writelines(lignes)
PY
    # Repli sans python3
    grep -v '^TIKRAS_ADMIN_USER=\|^TIKRAS_ADMIN_PASS=' .env > .env.tmp
    printf 'TIKRAS_ADMIN_USER=%s\nTIKRAS_ADMIN_PASS=%s\n' "$UTILISATEUR" "$MDP" >> .env.tmp
    mv .env.tmp .env
  }
  chmod 600 .env
  echo ">> Fichier .env cree (identifiants enregistres, droits 600)."
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

# Import automatique de la liste des routeurs si elle a ete deposee a cote
# du projet ou dans /tmp (voir tools/export-config-local.php).
echo "== Import des routeurs =="
sleep 8
IMPORTE=""
for SOURCE in "./config.local.php" "/tmp/config.local.php" "/root/config.local.php"; do
  if [ -f "$SOURCE" ]; then
    docker cp "$SOURCE" tikras-app:/data/config.local.php
    docker exec tikras-app chown www-data:www-data /data/config.local.php
    docker exec tikras-app chmod 640 /data/config.local.php
    IMPORTE="$SOURCE"
    break
  fi
done

if [ -n "$IMPORTE" ]; then
  echo ">> Routeurs importes depuis $IMPORTE"
  docker exec -u www-data tikras-app php /var/www/html/cron.php --force > /dev/null 2>&1 || true
  rm -f "$IMPORTE"
  echo ">> Fichier source supprime du serveur (il contenait des identifiants)."
else
  echo ">> Aucun config.local.php trouve."
  echo "   Importez vos routeurs depuis le panneau (page Sauvegarde) ou copiez"
  echo "   le fichier genere par tools/export-config-local.php puis relancez ce script."
fi

IP_PUBLIQUE=$(hostname -I | awk '{print $1}')
echo ""
echo "=================================================================="
echo " TIKRAS IT est en ligne."
echo " - Interface : http://${IP_PUBLIQUE}/  (ou votre domaine en HTTPS)"
echo " - Connexion : identifiants TIKRAS_ADMIN_USER / TIKRAS_ADMIN_PASS du .env"
echo " - Automatisations : planificateur interne du conteneur (toutes les 5 min)."
echo "   Journal : docker exec tikras-app cat /data/automations.log"
echo " - Verifier ZeroTier : zerotier-cli listnetworks  (statut OK attendu)"
echo "=================================================================="
echo ""
echo "Controle de sante:"
bash "$PROJECT_DIR/deploy/verifier.sh" || true
