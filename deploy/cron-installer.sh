#!/bin/bash
#
# Installe l'execution periodique des automatisations.
#
#   sudo bash deploy/cron-installer.sh
#
# Sans cela, les taches de fond ne s'executent que lorsqu'un navigateur
# administrateur est ouvert sur le panneau: c'est lui qui appelle cron.php
# toutes les cinq minutes. Des que la session expire, l'appel est refuse et
# tout s'arrete - tickets planifies, releve des recettes, sauvegardes et
# surveillance des routeurs compris - sans que rien ne le signale.
#
# La minuterie ci-dessous appelle le script en ligne de commande dans le
# conteneur, ce qui ne demande ni session ni jeton.
set -euo pipefail

if [ "$(id -u)" != "0" ]; then
  echo "Lancez ce script avec sudo." >&2
  exit 1
fi

CONTENEUR="${TIKRAS_CONTENEUR:-tikras-app}"
INTERVALLE="${TIKRAS_CRON_INTERVALLE:-5min}"

if ! docker ps --format '{{.Names}}' | grep -qx "$CONTENEUR"; then
  echo "Conteneur ${CONTENEUR} introuvable. Demarrez l'application d'abord." >&2
  exit 1
fi

echo "== Service =="
cat > /etc/systemd/system/tikras-cron.service <<EOF
[Unit]
Description=Automatisations TIKRAS (tickets planifies, releve des recettes, surveillance)
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
# En ligne de commande, le script n'exige ni session ni jeton: il s'execute
# sous www-data pour ecrire dans la base avec les memes droits que l'appli.
# "timeout" borne le cycle a l'interieur du conteneur: un routeur qui accepte
# la connexion sans jamais repondre laisserait sinon le processus en attente
# indefiniment, le "max_execution_time" de PHP ne comptant pas le temps passe
# bloque sur une socket.
ExecStart=/usr/bin/docker exec -u www-data ${CONTENEUR} timeout --kill-after=15s 180 php /var/www/html/cron.php
TimeoutStartSec=240
EOF

echo "== Minuterie (toutes les ${INTERVALLE}) =="
cat > /etc/systemd/system/tikras-cron.timer <<EOF
[Unit]
Description=Declenche les automatisations TIKRAS toutes les ${INTERVALLE}

[Timer]
OnBootSec=2min
OnUnitActiveSec=${INTERVALLE}
# Rattrape le passage manque si le serveur etait eteint a l'heure prevue.
Persistent=true
Unit=tikras-cron.service

[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now tikras-cron.timer > /dev/null 2>&1
systemctl start tikras-cron.service

sleep 8
echo ""
echo "== Verification =="
if systemctl is-active --quiet tikras-cron.timer; then
  echo "   minuterie active"
else
  echo "   la minuterie n'est pas active" >&2
  exit 1
fi

PROCHAIN=$(systemctl list-timers tikras-cron.timer --no-pager 2>/dev/null | awk 'NR==2 {print $1, $2, $3}')
echo "   prochain passage : ${PROCHAIN:-inconnu}"

# Le service doit avoir reellement fait tourner un cycle.
if docker exec -u www-data "$CONTENEUR" php -r '
require "/var/www/html/lib/tikras_core.php";
require "/var/www/html/lib/tikras_storage.php";
require "/var/www/html/lib/tikras_automation.php";
$t = (int) tikras_automation_meta_get("auto.last.health_check", "0");
exit(($t > 0 && (time() - $t) < 600) ? 0 : 1);
' 2>/dev/null; then
  echo "   un cycle vient de s'executer"
else
  echo "   aucun cycle recent detecte : consultez journalctl -u tikras-cron.service" >&2
fi

echo ""
echo "=================================================================="
echo " Les automatisations tournent desormais toutes les ${INTERVALLE},"
echo " que le panneau soit ouvert ou non."
echo ""
echo "   etat    : systemctl status tikras-cron.timer"
echo "   journal : journalctl -u tikras-cron.service -n 30"
echo "=================================================================="
