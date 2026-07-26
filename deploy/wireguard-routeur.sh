#!/bin/bash
#
# Ajoute un routeur au concentrateur WireGuard et produit le script RouterOS
# a coller dans son terminal.
#
#   sudo bash deploy/wireguard-routeur.sh NOM_DE_SESSION
#   sudo bash deploy/wireguard-routeur.sh NOM_DE_SESSION 10.200.1.42   # adresse imposee
#
# Relancer le script pour un routeur deja connu reaffiche sa configuration
# sans creer de doublon.
set -euo pipefail

if [ "$(id -u)" != "0" ]; then
  echo "Lancez ce script avec sudo." >&2
  exit 1
fi

SESSION="${1:-}"
IP_IMPOSEE="${2:-}"
PORT="${WG_PORT:-51820}"
CONF="/etc/wireguard/wg0.conf"
REGISTRE="/etc/wireguard/routeurs.txt"

if [ -z "$SESSION" ]; then
  echo "Usage: sudo bash deploy/wireguard-routeur.sh NOM_DE_SESSION [ADRESSE]" >&2
  exit 1
fi
if [ ! -f "$CONF" ]; then
  echo "Concentrateur absent: lancez d'abord deploy/wireguard-hub.sh" >&2
  exit 1
fi

touch "$REGISTRE"
chmod 600 "$REGISTRE"

# Un routeur deja enregistre garde sa cle et son adresse.
LIGNE=$(grep -E "^${SESSION}\|" "$REGISTRE" 2>/dev/null | head -1 || true)
if [ -n "$LIGNE" ]; then
  IP_ROUTEUR=$(echo "$LIGNE" | cut -d'|' -f2)
  CLE_PRIVEE=$(echo "$LIGNE" | cut -d'|' -f3)
  CLE_PUBLIQUE=$(echo "$LIGNE" | cut -d'|' -f4)
  echo "== Routeur deja enregistre: reaffichage de sa configuration =="
else
  if [ -n "$IP_IMPOSEE" ]; then
    IP_ROUTEUR="$IP_IMPOSEE"
  else
    # Premiere adresse libre dans 10.200.1.x
    DERNIER=$(cut -d'|' -f2 "$REGISTRE" 2>/dev/null | awk -F. '$1=="10" && $2=="200" && $3=="1"{print $4}' | sort -n | tail -1)
    SUIVANT=$(( ${DERNIER:-0} + 1 ))
    if [ "$SUIVANT" -gt 254 ]; then
      echo "Plage 10.200.1.x saturee: passez a 10.200.2.x avec le second parametre." >&2
      exit 1
    fi
    IP_ROUTEUR="10.200.1.${SUIVANT}"
  fi
  umask 077
  CLE_PRIVEE=$(wg genkey)
  CLE_PUBLIQUE=$(echo "$CLE_PRIVEE" | wg pubkey)
  echo "${SESSION}|${IP_ROUTEUR}|${CLE_PRIVEE}|${CLE_PUBLIQUE}" >> "$REGISTRE"

  # Declaration du pair cote serveur.
  cat >> "$CONF" <<EOF

[Peer]
# ${SESSION}
PublicKey = ${CLE_PUBLIQUE}
AllowedIPs = ${IP_ROUTEUR}/32
EOF
  systemctl restart wg-quick@wg0
  echo "== Routeur ajoute au concentrateur =="
fi

CLE_SERVEUR=$(cat /etc/wireguard/serveur.pub)
# Forcer IPv4: les routeurs distants n'ont generalement pas d'IPv6.
IP_PUBLIQUE="${WG_ENDPOINT:-}"
if [ -z "$IP_PUBLIQUE" ]; then
  IP_PUBLIQUE=$(curl -4 -s --max-time 8 ifconfig.me 2>/dev/null || true)
fi
if ! echo "${IP_PUBLIQUE}" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
  IP_PUBLIQUE=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1)
fi

cat <<EOF

   Session   : ${SESSION}
   Adresse   : ${IP_ROUTEUR}  (a saisir dans TIKRAS IT comme adresse du routeur)
   Serveur   : ${IP_PUBLIQUE}:${PORT}

------------------------------------------------------------------
 A COLLER DANS LE TERMINAL DU ROUTEUR (RouterOS 7)
------------------------------------------------------------------
/interface/wireguard/add name=wg-tikras listen-port=13231 private-key="${CLE_PRIVEE}" comment="TIKRAS IT"
/ip/address/add address=${IP_ROUTEUR}/16 interface=wg-tikras comment="TIKRAS IT"
/interface/wireguard/peers/add interface=wg-tikras public-key="${CLE_SERVEUR}" endpoint-address=${IP_PUBLIQUE} endpoint-port=${PORT} allowed-address=10.200.0.0/16 persistent-keepalive=25s comment="Serveur TIKRAS"
/ip/firewall/filter/add chain=input in-interface=wg-tikras action=accept comment="TIKRAS IT: acces par le tunnel" place-before=0
------------------------------------------------------------------

 Ces commandes AJOUTENT une interface: elles ne modifient ni ZeroTier, ni le
 pare-feu existant, ni les services. L'acces actuel reste donc intact.

 Si l'API du routeur restreint deja les adresses autorisees, ajoutez la plage
 du tunnel SANS retirer les valeurs existantes:
   /ip/service/print detail where name=api      (relever la liste actuelle)
   /ip/service/set api address=LISTE_ACTUELLE,10.200.0.0/16

 Verification depuis le routeur:
   /interface/wireguard/peers/print   (rx/tx doivent augmenter)
   /ping 10.200.0.1

 Verification depuis le serveur:
   wg show wg0
   ping -c2 ${IP_ROUTEUR}
EOF
