#!/bin/bash
#
# Installe le concentrateur WireGuard sur le serveur TIKRAS.
# Les routeurs s'y connectent en sortant, ce qui traverse le NAT des
# operateurs: aucune adresse publique n'est requise cote routeur.
#
#   sudo bash deploy/wireguard-hub.sh
#
# Plage du tunnel: 10.200.0.0/16 (serveur 10.200.0.1, routeurs 10.200.1.x).
# Elle a ete choisie hors des plages ZeroTier et LAN deja utilisees.
set -euo pipefail

if [ "$(id -u)" != "0" ]; then
  echo "Lancez ce script avec sudo." >&2
  exit 1
fi

PORT="${WG_PORT:-51820}"
RESEAU="${WG_NETWORK:-10.200.0.0/16}"
IP_HUB="${WG_HUB_IP:-10.200.0.1}"
CONF="/etc/wireguard/wg0.conf"

echo "== [1/5] Installation de WireGuard =="
if ! command -v wg > /dev/null 2>&1; then
  apt-get update -y > /dev/null
  apt-get install -y wireguard wireguard-tools > /dev/null
fi
echo "   $(wg --version)"

echo "== [2/5] Cles du serveur =="
mkdir -p /etc/wireguard
chmod 700 /etc/wireguard
if [ ! -f /etc/wireguard/serveur.key ]; then
  umask 077
  wg genkey > /etc/wireguard/serveur.key
  wg pubkey < /etc/wireguard/serveur.key > /etc/wireguard/serveur.pub
  echo "   nouvelle paire de cles generee"
else
  echo "   cles existantes conservees"
fi
CLE_PRIVEE=$(cat /etc/wireguard/serveur.key)
CLE_PUBLIQUE=$(cat /etc/wireguard/serveur.pub)

echo "== [3/5] Configuration de l'interface =="
if [ ! -f "$CONF" ]; then
  cat > "$CONF" <<EOF
# Concentrateur WireGuard TIKRAS.
# Les pairs (routeurs) sont ajoutes par deploy/wireguard-routeur.sh.
[Interface]
Address = ${IP_HUB}/16
ListenPort = ${PORT}
PrivateKey = ${CLE_PRIVEE}
# Les routeurs joignent le serveur; le trafic reste dans le tunnel.
EOF
  chmod 600 "$CONF"
  echo "   $CONF cree"
else
  echo "   $CONF existant conserve (pairs preserves)"
fi

echo "== [4/5] Reseau et pare-feu =="
# Routage entre les pairs (un routeur peut ainsi en joindre un autre).
if ! grep -q "^net.ipv4.ip_forward=1" /etc/sysctl.conf 2>/dev/null; then
  echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
fi
sysctl -q -w net.ipv4.ip_forward=1
if command -v ufw > /dev/null 2>&1; then
  ufw allow "${PORT}/udp" > /dev/null 2>&1 || true
  echo "   port ${PORT}/udp ouvert"
  # Activer le routage du noyau ne suffit pas: UFW rejette par defaut tout ce
  # qui traverse la machine. Sans cette regle, chaque pair atteint le serveur
  # mais aucun n'atteint les autres - un ordinateur raccorde ne voit alors
  # aucun routeur, et rien ne l'explique. Le defaut est reste invisible
  # jusqu'a ce que 156 000 paquets aient ete rejetes en silence.
  ufw route allow in on wg0 out on wg0 > /dev/null 2>&1 || true
  echo "   relais entre pairs autorise"
fi

echo "== [5/5] Demarrage =="
systemctl enable wg-quick@wg0 > /dev/null 2>&1 || true
if systemctl is-active --quiet wg-quick@wg0; then
  systemctl restart wg-quick@wg0
else
  systemctl start wg-quick@wg0
fi
sleep 1

# Forcer IPv4: les routeurs distants n'ont generalement pas d'IPv6.
IP_PUBLIQUE=$(curl -4 -s --max-time 8 ifconfig.me 2>/dev/null || true)
if ! echo "${IP_PUBLIQUE}" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
  IP_PUBLIQUE=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1)
fi

echo ""
echo "=================================================================="
echo " Concentrateur WireGuard actif."
echo ""
echo "   Point de rendez-vous : ${IP_PUBLIQUE}:${PORT}"
echo "   Cle publique serveur : ${CLE_PUBLIQUE}"
echo "   Adresse dans le tunnel: ${IP_HUB}"
echo "   Plage des routeurs   : 10.200.1.1 et suivantes"
echo ""
echo " Ajouter un routeur:"
echo "   bash deploy/wireguard-routeur.sh NOM_DE_SESSION"
echo "=================================================================="
wg show wg0 2>/dev/null | head -5 || true
