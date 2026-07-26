#!/bin/bash
#
# Agent WireGuard du serveur.
#
# L'application web tourne dans un conteneur sans privilege reseau: elle ne
# peut pas declarer un pair ni recharger l'interface. Elle depose donc ses
# demandes dans le volume partage, et cet agent les applique cote systeme.
#
#   bash deploy/wireguard-agent.sh          # traite les demandes en attente
#   bash deploy/wireguard-agent.sh --boucle # surveille en continu
#
# Installe en service par deploy/wireguard-agent-installer.sh
set -uo pipefail

VOLUME="${TIKRAS_VOLUME:-/var/lib/docker/volumes/tikras_tikras-data/_data}"
DOSSIER="${VOLUME}/wireguard"
DEMANDES="${DOSSIER}/demandes"
ETAT="${DOSSIER}/etat"
CONF="/etc/wireguard/wg0.conf"
PORT="${WG_PORT:-51820}"

publier_hub() {
  mkdir -p "$ETAT"
  local pub endpoint
  pub=$(cat /etc/wireguard/serveur.pub 2>/dev/null || echo "")
  endpoint="${WG_ENDPOINT:-}"
  if [ -z "$endpoint" ]; then
    endpoint=$(curl -4 -s --max-time 8 ifconfig.me 2>/dev/null || true)
  fi
  if ! echo "$endpoint" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
    endpoint=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src") print $(i+1)}' | head -1)
  fi
  cat > "${ETAT}/hub.json" <<EOF
{"endpoint":"${endpoint}","port":${PORT},"cle_publique":"${pub}","reseau":"10.200.0.0/16","ip_hub":"10.200.0.1","prefixe":"10.200.1."}
EOF
  chmod 644 "${ETAT}/hub.json"
}

publier_adresses() {
  mkdir -p "$ETAT"
  # Le serveur porte une adresse differente sur chaque reseau (ZeroTier,
  # WireGuard). L'application a besoin de la liste pour indiquer a chaque
  # routeur celle qu'il peut effectivement joindre.
  {
    echo "["
    local premier=1
    while read -r cidr iface; do
      [ -z "$cidr" ] && continue
      [ "$premier" = "0" ] && echo ","
      premier=0
      printf '{"cidr":"%s","interface":"%s"}' "$cidr" "$iface"
    done < <(ip -4 -o addr show scope global 2>/dev/null | awk '{print $4, $2}')
    echo ""
    echo "]"
  } > "${ETAT}/adresses.json"
  chmod 644 "${ETAT}/adresses.json"
}

publier_pairs() {
  mkdir -p "$ETAT"
  # Etat de chaque pair: dernier handshake et volumes echanges.
  {
    echo "{"
    local premier=1
    while read -r cle handshake rx tx; do
      [ -z "$cle" ] && continue
      [ "$premier" = "0" ] && echo ","
      premier=0
      printf '"%s":{"handshake":%s,"rx":%s,"tx":%s}' "$cle" "${handshake:-0}" "${rx:-0}" "${tx:-0}"
    done < <(wg show wg0 dump 2>/dev/null | tail -n +2 | awk '{print $1, $5, $6, $7}')
    echo ""
    echo "}"
  } > "${ETAT}/pairs.json"
  chmod 644 "${ETAT}/pairs.json"
}

traiter_demandes() {
  [ -d "$DEMANDES" ] || return 0
  local fichier
  for fichier in "$DEMANDES"/*.json; do
    [ -e "$fichier" ] || continue
    local champs cle ip session
    # Lecture par un vrai analyseur JSON: les cles WireGuard contiennent des
    # "/" que l'encodage echappe en "\/", et une cle mal decodee rend le
    # fichier de configuration invalide, ce qui ferait tomber tout le tunnel.
    champs=$(python3 - "$fichier" <<'PYEOF' 2>/dev/null
import json, sys
try:
    d = json.load(open(sys.argv[1]))
    print(d.get("public_key", ""))
    print(d.get("tunnel_ip", ""))
    print(d.get("session", ""))
except Exception:
    pass
PYEOF
)
    cle=$(echo "$champs" | sed -n 1p)
    ip=$(echo "$champs" | sed -n 2p)
    session=$(echo "$champs" | sed -n 3p)

    # Une cle WireGuard valide fait 44 caracteres base64 terminés par "=".
    if ! echo "$cle" | grep -qE '^[A-Za-z0-9+/]{43}=$'; then
      echo "$(date '+%F %T') cle invalide, demande ignoree: $(basename "$fichier")"
      rm -f "$fichier"
      continue
    fi
    if ! echo "$ip" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
      echo "$(date '+%F %T') adresse invalide, demande ignoree: $(basename "$fichier")"
      rm -f "$fichier"
      continue
    fi

    if grep -qF "$cle" "$CONF" 2>/dev/null; then
      echo "$(date '+%F %T') pair deja present: ${session}"
    else
      # Une adresse reattribuee ne doit pas rester sur un ancien pair, ni dans
      # le fichier, ni sur l'interface active (sinon un pair fantome subsiste).
      if grep -qE "AllowedIPs *= *${ip}/32" "$CONF" 2>/dev/null; then
        local anciennes
        anciennes=$(python3 - "$CONF" "$ip" <<'PYEOF' 2>/dev/null || true
import re, sys
chemin, ip = sys.argv[1], sys.argv[2]
contenu = open(chemin).read()
blocs = re.split(r'\n(?=\[Peer\])', contenu)
retires, gardes = [], []
for bloc in blocs:
    if f'AllowedIPs = {ip}/32' in bloc:
        trouve = re.search(r'PublicKey\s*=\s*(\S+)', bloc)
        if trouve:
            retires.append(trouve.group(1))
    else:
        gardes.append(bloc)
open(chemin, 'w').write('\n'.join(gardes))
print('\n'.join(retires))
PYEOF
)
        local ancienne
        for ancienne in $anciennes; do
          [ -n "$ancienne" ] && wg set wg0 peer "$ancienne" remove 2>/dev/null || true
        done
        echo "$(date '+%F %T') adresse ${ip} reattribuee, ancien pair retire"
      fi
      {
        echo ""
        echo "[Peer]"
        echo "# ${session}"
        echo "PublicKey = ${cle}"
        echo "AllowedIPs = ${ip}/32"
      } >> "$CONF"
      # Application a chaud: pas de coupure des tunnels deja etablis.
      if wg set wg0 peer "$cle" allowed-ips "${ip}/32" 2>/dev/null; then
        echo "$(date '+%F %T') pair ajoute: ${session} -> ${ip}"
      else
        # Un fichier invalide empecherait l'interface de redemarrer: on verifie
        # avant de toucher au service, et on restaure le cas echeant.
        cp "$CONF" "${CONF}.avant-ajout"
        if wg-quick strip wg0 > /dev/null 2>&1; then
          systemctl restart wg-quick@wg0
          echo "$(date '+%F %T') pair ajoute (rechargement): ${session}"
        else
          mv "${CONF}.avant-ajout" "$CONF"
          echo "$(date '+%F %T') configuration invalide, ajout annule: ${session}"
        fi
        rm -f "${CONF}.avant-ajout"
      fi
    fi
    rm -f "$fichier"
  done
}

if [ ! -f "$CONF" ]; then
  echo "Concentrateur absent: lancez d'abord deploy/wireguard-hub.sh" >&2
  exit 1
fi
mkdir -p "$DOSSIER" "$DEMANDES" "$ETAT"
# L'application tourne en www-data (uid 33) dans le conteneur: elle doit
# pouvoir traverser ces dossiers, ecrire ses demandes et lire l'etat publie.
chmod 755 "$DOSSIER" "$ETAT" 2>/dev/null || true
chmod 775 "$DEMANDES" 2>/dev/null || true
chown 33:33 "$DOSSIER" "$DEMANDES" 2>/dev/null || true

if [ "${1:-}" = "--boucle" ]; then
  echo "Agent WireGuard demarre (surveillance toutes les 10 s)"
  while true; do
    publier_hub
    publier_adresses
    traiter_demandes
    publier_pairs
    sleep 10
  done
else
  publier_hub
  publier_adresses
  traiter_demandes
  publier_pairs
  echo "Traitement termine."
fi
