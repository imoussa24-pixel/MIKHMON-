# TIKRAS IT — Serveur en ligne (VPS + ZeroTier)

Panneau Mikhmon transforme en vrai serveur web accessible partout, avec
automatisations integrees et acces aux routeurs via ZeroTier.

Tout ce qui suit a ete valide reellement en conteneur Docker :
image sans secrets, import des 106 routeurs, automatisations, sauvegardes.

## Pourquoi un VPS et pas Render

Les routeurs sont joints par leurs IP privees ZeroTier. **Render ne peut pas
rejoindre un reseau ZeroTier** (pas d'acces `/dev/net/tun` dans ses conteneurs).
Sur un VPS, ZeroTier tourne sur l'hote et le conteneur emprunte
automatiquement ses routes : aucune option Docker speciale n'est necessaire
(le NAT sortant du reseau bridge suffit — verifie).

## Installation en une commande

Sur un VPS Ubuntu/Debian neuf (Contabo, Hetzner, OVH... ~5 €/mois) :

```bash
git clone VOTRE_DEPOT_PRIVE tikras && cd tikras
sudo bash deploy/vps-install.sh
```

Le script installe Docker et ZeroTier, rejoint le reseau, configure le
pare-feu (UFW), cree le `.env` avec un jeton cron aleatoire et demarre l'app.

**Apres l'installation**, autorisez le serveur dans <https://my.zerotier.com>
(section Members), puis verifiez : `zerotier-cli listnetworks` doit afficher `OK`.

## Importer vos 106 routeurs

L'image Docker **ne contient aucun secret** : la liste des routeurs vit dans le
volume persistant. Deux methodes au choix.

### Methode 1 — fichier de configuration (rapide, recommandee)

Sur le poste Windows :

```powershell
php8\php.exe tools\export-config-local.php
```

Puis transferez (le fichier contient les identifiants : **scp uniquement**) :

```bash
scp config.local.php root@VOTRE_VPS:/tmp/
ssh root@VOTRE_VPS 'docker cp /tmp/config.local.php tikras-app:/data/config.local.php \
  && docker exec tikras-app chown www-data:www-data /data/config.local.php \
  && docker exec tikras-app chmod 640 /data/config.local.php \
  && rm /tmp/config.local.php'
```

Rechargez le panneau : les 106 routeurs apparaissent.

### Methode 2 — sauvegarde ZIP

Page **Sauvegarde** en local → creer une sauvegarde → sur le serveur, page
**Sauvegarde** → restaurer le ZIP. Importe aussi l'historique et les tickets.

## HTTPS avec un nom de domaine

1. Pointez un enregistrement DNS A vers l'IP du VPS.
2. Dans `.env` : `TIKRAS_DOMAIN=panel.votredomaine.com` et `TIKRAS_HTTP_PORT=8080`.
3. `docker compose --profile https up -d`

Caddy obtient et renouvelle le certificat Let's Encrypt automatiquement.

## Automatisations

Un planificateur tourne **dans le conteneur** : rien a configurer, il demarre
avec l'application et survit aux redemarrages.

| Tache | Cadence | Variable |
|---|---|---|
| Surveillance routeurs (+ alertes Telegram) | 5 min | `TIKRAS_AUTO_HEALTH_INTERVAL` |
| Reprise de la file roaming | 10 min | `TIKRAS_AUTO_ROAMING_INTERVAL` |
| Sauvegarde automatique (10 conservees) | 24 h | `TIKRAS_AUTO_BACKUP_INTERVAL` |
| Sync base locale SQLite | 1 h | `TIKRAS_AUTO_SYNC_INTERVAL` |
| Nettoyage journaux (> 90 j) | 7 j | `TIKRAS_AUTO_PRUNE_INTERVAL` |

- La surveillance sonde 30 routeurs par cycle (`TIKRAS_AUTO_HEALTH_BATCH`), les
  moins recemment verifies d'abord : chaque cycle reste court meme a 106 routeurs.
- Etat visible dans **Parametres admin** (panneau Automatisations, bouton
  *Executer maintenant*) et badges *En ligne / Hors ligne* sur chaque routeur.
- Journal : `docker exec tikras-app cat /data/automations.log`

Declencheurs complementaires : soft-cron du navigateur (panneau ouvert),
appel externe `https://VOTRE_SERVEUR/cron.php?token=...`, ou CLI
`docker exec -u www-data tikras-app php /var/www/html/cron.php --force`.

> Si vous programmez un cron sur l'hote, utilisez **toujours** `-u www-data` :
> en root, les fichiers crees appartiendraient a root et l'application web ne
> pourrait plus ecrire dans la base.

## Exploitation

```bash
docker compose ps                      # etat
docker compose logs -f app             # journaux
docker compose pull && docker compose up -d --build   # mise a jour
docker exec tikras-app ls -l /data/storage/backups/   # sauvegardes
```

Recuperer une sauvegarde sur son poste :

```bash
scp root@VOTRE_VPS:/var/lib/docker/volumes/tikras_tikras-data/_data/storage/backups/auto-backup-*.zip .
```

## Securite

- `.env` et `config.local.php` sont exclus de git (contiennent des secrets).
- L'image Docker ne contient aucun identifiant : elle peut etre reconstruite
  ou partagee sans risque.
- Le compte admin vient de `TIKRAS_ADMIN_USER` / `TIKRAS_ADMIN_PASS` ; un mot
  de passe vide est refuse.
- `cron.php` renvoie 403 sans le bon jeton.
- Pare-feu : seuls SSH, 80, 443 et ZeroTier (9993/udp) sont ouverts.
- Limitez l'exposition : idealement, n'ouvrez le panneau qu'aux membres de
  votre reseau ZeroTier, ou ajoutez une authentification supplementaire.

## Verification locale (Windows)

```powershell
php8\php.exe mikhmon\cron.php --force            # tick manuel
powershell -ExecutionPolicy Bypass -File tools/start-php8-server.ps1
```
