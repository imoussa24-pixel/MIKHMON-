#!/bin/bash
#
# Installe l'agent WireGuard en service systeme, pour que les raccordements
# demandes depuis le panneau soient appliques automatiquement.
#
#   sudo bash deploy/wireguard-agent-installer.sh
set -euo pipefail

if [ "$(id -u)" != "0" ]; then
  echo "Lancez ce script avec sudo." >&2
  exit 1
fi

PROJET="$(cd "$(dirname "$0")/.." && pwd)"
AGENT="${PROJET}/deploy/wireguard-agent.sh"

if [ ! -f "$AGENT" ]; then
  echo "Agent introuvable: $AGENT" >&2
  exit 1
fi
chmod +x "$AGENT"

# Le volume Docker peut porter un autre nom selon le repertoire du projet.
VOLUME=$(docker volume ls --format '{{.Name}}' | grep -E 'tikras-data$' | head -1)
if [ -z "$VOLUME" ]; then
  echo "Volume de donnees introuvable." >&2
  exit 1
fi
CHEMIN=$(docker volume inspect "$VOLUME" --format '{{.Mountpoint}}')
echo "Volume de donnees : $CHEMIN"

cat > /etc/systemd/system/tikras-wireguard-agent.service <<EOF
[Unit]
Description=Agent WireGuard TIKRAS (raccordement des routeurs depuis le panneau)
After=network-online.target wg-quick@wg0.service
Wants=wg-quick@wg0.service

[Service]
Type=simple
Environment=TIKRAS_VOLUME=${CHEMIN}
ExecStart=${AGENT} --boucle
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable tikras-wireguard-agent > /dev/null 2>&1 || true
systemctl restart tikras-wireguard-agent
sleep 3

echo ""
if systemctl is-active --quiet tikras-wireguard-agent; then
  echo "Agent actif. Les raccordements demandes depuis le panneau seront"
  echo "appliques automatiquement (verification toutes les 10 secondes)."
else
  echo "L'agent n'a pas demarre. Journal :" >&2
  journalctl -u tikras-wireguard-agent -n 15 --no-pager >&2
  exit 1
fi
echo ""
echo "Journal en direct : journalctl -u tikras-wireguard-agent -f"
