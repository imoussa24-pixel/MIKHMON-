#!/bin/bash
#
# Verification de sante du serveur TIKRAS IT.
#   bash deploy/verifier.sh
#
# A lancer apres l'installation, puis a tout moment pour controler le serveur.
set -uo pipefail

OK=0
KO=0
AVERTIS=0

titre()  { echo ""; echo "== $1"; }
bon()    { echo "  [OK]    $1"; OK=$((OK+1)); }
mauvais(){ echo "  [ERREUR] $1"; KO=$((KO+1)); }
attention(){ echo "  [ATTENTION] $1"; AVERTIS=$((AVERTIS+1)); }

CONTENEUR="${TIKRAS_CONTAINER:-tikras-app}"

titre "Conteneur"
if docker ps --format '{{.Names}}' | grep -qx "$CONTENEUR"; then
  bon "conteneur $CONTENEUR en cours d'execution"
  ETAT=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}sans healthcheck{{end}}' "$CONTENEUR" 2>/dev/null)
  case "$ETAT" in
    healthy) bon "healthcheck: healthy" ;;
    "sans healthcheck") attention "aucun healthcheck defini" ;;
    *) mauvais "healthcheck: $ETAT" ;;
  esac
else
  mauvais "conteneur $CONTENEUR introuvable (docker compose up -d)"
  echo ""; echo "Resume: $OK ok, $AVERTIS attention, $KO erreurs"; exit 1
fi

titre "Application web"
CODE=$(docker exec "$CONTENEUR" php -r '$c=@file_get_contents("http://localhost/admin.php?id=login"); echo $c===false?"0":"200";' 2>/dev/null)
[ "$CODE" = "200" ] && bon "page de connexion accessible" || mauvais "page de connexion injoignable"

titre "Identifiants administrateur"
if docker exec "$CONTENEUR" printenv TIKRAS_ADMIN_PASS > /dev/null 2>&1; then
  MDP=$(docker exec "$CONTENEUR" printenv TIKRAS_ADMIN_PASS)
  if [ -z "$MDP" ] || [ "$MDP" = "CHANGEZ-MOI" ]; then
    mauvais "TIKRAS_ADMIN_PASS non defini ou laisse par defaut"
  elif [ "${#MDP}" -lt 10 ]; then
    attention "mot de passe admin court (${#MDP} caracteres), 12+ conseille"
  else
    bon "mot de passe admin defini (${#MDP} caracteres)"
  fi
else
  mauvais "TIKRAS_ADMIN_PASS absent de l'environnement"
fi

JETON=$(docker exec "$CONTENEUR" printenv TIKRAS_CRON_TOKEN 2>/dev/null || echo "")
if [ -z "$JETON" ] || [ "$JETON" = "CHANGEZ-MOI" ]; then
  attention "TIKRAS_CRON_TOKEN non defini: l'appel cron externe restera refuse"
else
  bon "jeton cron defini"
fi

titre "Donnees et permissions"
if docker exec "$CONTENEUR" test -f /data/storage/mikhmon-pro-admin.sqlite; then
  bon "base de donnees presente"
  PROP=$(docker exec "$CONTENEUR" stat -c '%U' /data/storage/mikhmon-pro-admin.sqlite)
  [ "$PROP" = "www-data" ] && bon "base appartenant a www-data" \
    || mauvais "base appartenant a '$PROP': l'application ne pourra pas ecrire (chown -R www-data:www-data /data)"
  if docker exec -u www-data "$CONTENEUR" php -r '
    try { $p=new PDO("sqlite:/data/storage/mikhmon-pro-admin.sqlite");
      $p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
      $p->exec("CREATE TABLE IF NOT EXISTS _v(a INT)"); $p->exec("DROP TABLE _v"); exit(0);
    } catch (Exception $e) { exit(1); }' 2>/dev/null; then
    bon "ecriture en base fonctionnelle"
  else
    mauvais "ecriture en base impossible"
  fi
  NB=$(docker exec -u www-data "$CONTENEUR" php -r '
    try { $p=new PDO("sqlite:/data/storage/mikhmon-pro-admin.sqlite"); echo $p->query("SELECT COUNT(*) FROM routers")->fetchColumn(); }
    catch (Exception $e) { echo "0"; }' 2>/dev/null)
  [ "$NB" -gt 0 ] 2>/dev/null && bon "$NB routeur(s) en base" \
    || attention "aucun routeur: importez config.local.php ou restaurez une sauvegarde"
else
  attention "base absente: elle sera creee au premier cycle d'automatisation"
fi

titre "Pages"
# Chaque page est reellement rendue, session ouverte.
#
# Un deploiement partiel - une page envoyee sans la bibliotheque qu'elle
# appelle - produit une erreur fatale et une page blanche, invisible de
# l'exterieur: la requete repond alors 200 avec un corps presque vide, ou
# redirige vers la connexion. Rien ne le signalait jusqu'ici.
SID=$(docker exec -u www-data "$CONTENEUR" php -r '
session_start(); $_SESSION["mikhmon"] = "verificateur"; session_write_close(); echo session_id();
' 2>/dev/null | tr -dc 'a-zA-Z0-9,-')

if [ -n "$SID" ]; then
  PAGES_KO=0
  for PAGE in bord sessions tickets planning radius-serveur wireguard backup audit storage; do
    REP=$(docker exec "$CONTENEUR" sh -c "curl -s -o /tmp/_v.html -w '%{http_code}|%{size_download}' -b 'PHPSESSID=${SID}' 'http://127.0.0.1/admin.php?id=${PAGE}'" 2>/dev/null)
    CODE=$(echo "$REP" | cut -d'|' -f1)
    TAILLE=$(echo "$REP" | cut -d'|' -f2)
    FATALE=$(docker exec "$CONTENEUR" sh -c "grep -ci 'fatal error\|Parse error' /tmp/_v.html 2>/dev/null" | tr -dc '0-9')
    [ -z "$FATALE" ] && FATALE=0
    # Une page valide depasse largement 3 Ko: en deca, c'est un rendu tronque.
    if [ "$CODE" != "200" ] || [ "${TAILLE:-0}" -lt 3000 ] || [ "$FATALE" -gt 0 ]; then
      mauvais "page '${PAGE}': code ${CODE}, ${TAILLE} octets$([ "$FATALE" -gt 0 ] && echo ', erreur fatale')"
      PAGES_KO=$((PAGES_KO + 1))
    fi
  done
  docker exec "$CONTENEUR" sh -c "rm -f /tmp/_v.html" 2>/dev/null || true
  [ "$PAGES_KO" -eq 0 ] && bon "les 9 pages du panneau s'affichent"
else
  attention "impossible d'ouvrir une session de verification"
fi

titre "Automatisations"
# On interroge les marqueurs enregistres par les taches elles-memes, et non la
# date d'un fichier journal: le journal n'est ecrit que par le planificateur
# interne du conteneur, si bien qu'un declenchement par la minuterie du systeme
# passait pour une panne. Ces marqueurs, eux, disent ce qui a reellement tourne.
DERNIER=$(docker exec -u www-data "$CONTENEUR" php -r '
require "/var/www/html/lib/tikras_core.php";
require "/var/www/html/lib/tikras_storage.php";
require "/var/www/html/lib/tikras_automation.php";
$recent = 0;
foreach (array("sync_routers", "health_check", "roaming_retry", "tickets", "sales_sync") as $tache) {
  $marqueur = (int) tikras_automation_meta_get("auto.last." . $tache, "0");
  if ($marqueur > $recent) { $recent = $marqueur; }
}
echo $recent;
' 2>/dev/null | tr -dc '0-9')
[ -z "$DERNIER" ] && DERNIER=0

if [ "$DERNIER" -gt 0 ]; then
  AGE=$(( $(date +%s) - DERNIER ))
  if [ "$AGE" -lt 900 ]; then
    bon "dernier cycle il y a ${AGE}s"
  else
    attention "dernier cycle il y a ${AGE}s (plus de 15 min)"
  fi
else
  attention "aucune tache automatique n'a encore tourne"
fi

# Un cycle laisse en attente sur un routeur muet figeait autrefois tout le
# planificateur: on verifie qu'aucun n'est en cours depuis trop longtemps.
FIGE=$(docker exec "$CONTENEUR" sh -c "ps -o etimes=,cmd= -C php 2>/dev/null | awk '\$1 > 600 && /cron.php/' | wc -l" 2>/dev/null | tr -dc '0-9')
[ -z "$FIGE" ] && FIGE=0
if [ "$FIGE" -eq 0 ]; then
  bon "aucun cycle bloque"
else
  attention "${FIGE} cycle(s) en cours depuis plus de 10 min (bloque ?)"
fi

if docker exec "$CONTENEUR" test -f /data/automations.log; then
  ERR=$(docker exec "$CONTENEUR" sh -c 'grep -c "\"ok\": false" /data/automations.log 2>/dev/null | head -1' | tr -dc '0-9')
  [ -z "$ERR" ] && ERR=0
  [ "$ERR" -eq 0 ] && bon "aucun cycle en erreur" || attention "$ERR cycle(s) en erreur dans le journal"
else
  attention "journal d'automatisation absent (attendez ~5 min apres le demarrage)"
fi

titre "Sauvegardes"
NBS=$(docker exec "$CONTENEUR" sh -c 'ls /data/storage/backups/*.zip 2>/dev/null | wc -l' || echo 0)
if [ "$NBS" -gt 0 ] 2>/dev/null; then
  bon "$NBS sauvegarde(s) disponible(s)"
  if docker exec "$CONTENEUR" php -r '
    $f=glob("/data/storage/backups/*.zip"); if(!$f){exit(1);} sort($f);
    $z=new ZipArchive(); if($z->open(end($f))!==true){exit(1);}
    $d=$z->getFromName("storage/mikhmon-pro-admin.sqlite");
    exit($d !== false && strlen($d) > 20000 ? 0 : 1);' 2>/dev/null; then
    bon "la derniere sauvegarde contient bien la base"
  else
    mauvais "derniere sauvegarde vide ou incomplete"
  fi
else
  attention "aucune sauvegarde encore creee (la premiere arrive sous 24 h)"
fi

titre "ZeroTier (acces aux routeurs)"
if command -v zerotier-cli > /dev/null 2>&1; then
  if zerotier-cli listnetworks 2>/dev/null | grep -q " OK "; then
    bon "reseau ZeroTier connecte"
    IPZT=$(zerotier-cli listnetworks 2>/dev/null | awk '/ OK /{print $NF}' | head -1)
    [ -n "$IPZT" ] && bon "adresse ZeroTier: $IPZT"
  else
    mauvais "ZeroTier non connecte: autorisez ce serveur sur my.zerotier.com"
  fi
  EN_LIGNE=$(docker exec -u www-data "$CONTENEUR" php -r '
    try { $p=new PDO("sqlite:/data/storage/mikhmon-pro-admin.sqlite");
    echo $p->query("SELECT COUNT(*) FROM router_status WHERE last_state=\"online\"")->fetchColumn(); }
    catch (Exception $e) { echo "0"; }' 2>/dev/null)
  [ "$EN_LIGNE" -gt 0 ] 2>/dev/null && bon "$EN_LIGNE routeur(s) joignables depuis le conteneur" \
    || attention "aucun routeur joignable: verifiez ZeroTier et les autorisations"
else
  attention "zerotier-cli absent de cet hote"
fi

titre "Securite"
if command -v ufw > /dev/null 2>&1; then
  ufw status 2>/dev/null | grep -q "Status: active" && bon "pare-feu UFW actif" || attention "pare-feu UFW inactif"
fi
docker exec "$CONTENEUR" test -f /var/www/html/include/config.php && \
  ( docker exec "$CONTENEUR" grep -q "@|@" /var/www/html/include/config.php 2>/dev/null \
    && attention "des identifiants figurent dans l'image (config.php)" \
    || bon "aucun identifiant dans l'image" )

echo ""
echo "=================================================="
echo " Resume: $OK ok, $AVERTIS attention, $KO erreur(s)"
echo "=================================================="
[ "$KO" -eq 0 ] && exit 0 || exit 1
