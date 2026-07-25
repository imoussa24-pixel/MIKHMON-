#!/bin/bash
#
# Etat des reseaux ZeroTier du serveur, et test d'accessibilite des routeurs.
# A executer SUR LE SERVEUR:
#   bash deploy/zerotier-etat.sh            # etat instantane
#   bash deploy/zerotier-etat.sh --suivre   # rafraichit toutes les 15 s
set -uo pipefail

SUIVRE=0
[ "${1:-}" = "--suivre" ] && SUIVRE=1

afficher() {
  IDENTITE=$(zerotier-cli info 2>/dev/null | awk '{print $3}')
  echo "== Reseaux ZeroTier (identite du serveur: ${IDENTITE}) =="

  zerotier-cli -j listnetworks 2>/dev/null | python3 -c '
import json, sys
try:
    reseaux = json.load(sys.stdin)
except Exception:
    print("  lecture impossible"); sys.exit(0)
ok = 0
for n in reseaux:
    etat = n.get("status", "?")
    adresses = ", ".join(n.get("assignedAddresses", [])) or "-"
    nom = n.get("name") or "(sans nom)"
    marque = "OK " if etat == "OK" else "   "
    if etat == "OK":
        ok += 1
    print("  %s%-18s %-14s %-22s %s" % (marque, n["id"], etat, nom[:22], adresses))
print("")
print("  %d reseau(x) actif(s) sur %d" % (ok, len(reseaux)))
if ok < len(reseaux):
    print("  Les reseaux ACCESS_DENIED attendent une autorisation sur my.zerotier.com")
'
}

tester_routeurs() {
  echo ""
  echo "== Routeurs joignables =="
  docker exec -u www-data tikras-app php -r '
    $pdo = new PDO("sqlite:/data/storage/mikhmon-pro-admin.sqlite");
    $lignes = $pdo->query("SELECT last_state, COUNT(*) n FROM router_status GROUP BY last_state")->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($lignes as $l) { $total += $l["n"]; printf("  %-10s %d\n", $l["last_state"], $l["n"]); }
    if ($total == 0) { echo "  aucun controle effectue pour le moment\n"; }
  ' 2>/dev/null || echo "  application indisponible"
}

if [ "$SUIVRE" = "1" ]; then
  while true; do
    clear
    date '+%H:%M:%S'
    afficher
    tester_routeurs
    echo ""
    echo "(Ctrl+C pour quitter, rafraichissement toutes les 15 s)"
    sleep 15
  done
else
  afficher
  tester_routeurs
fi
