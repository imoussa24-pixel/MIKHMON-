# TIKRAS IT — Serveur en ligne + automatisations

Cette version transforme le panneau en vrai serveur web avec automatisations
integrees, deployable sur Render, Railway ou n'importe quel VPS Docker.

## Ce qui est inclus

- `Dockerfile` + `docker/` : image PHP 8.3 + Apache prete a l'emploi.
- `render.yaml` : blueprint Render (service web + disque persistant `/data`).
- `mikhmon/cron.php` : endpoint d'automatisation securise par token.
- `mikhmon/lib/tikras_automation.php` : moteur des taches periodiques.
- Nouveau design `tikras-flux` (clair/sombre) sur toute l'interface.

## Automatisations actives

| Tache | Cadence par defaut | Variable d'environnement |
|---|---|---|
| Surveillance routeurs (ping API + badges en ligne/hors ligne) | 5 min | `TIKRAS_AUTO_HEALTH_INTERVAL` |
| Reprise file roaming (tickets en attente) | 10 min | `TIKRAS_AUTO_ROAMING_INTERVAL` |
| Sauvegarde automatique (rotation, 10 conservees) | 24 h | `TIKRAS_AUTO_BACKUP_INTERVAL`, `TIKRAS_AUTO_BACKUP_KEEP` |
| Sync base locale SQLite | 1 h | `TIKRAS_AUTO_SYNC_INTERVAL` |
| Nettoyage journaux (> 90 jours) | 7 j | `TIKRAS_AUTO_PRUNE_INTERVAL`, `TIKRAS_AUTO_AUDIT_KEEP_DAYS` |

- La surveillance sonde 30 routeurs max par cycle (`TIKRAS_AUTO_HEALTH_BATCH`),
  en commencant par les moins recemment verifies.
- Une alerte Telegram est envoyee quand un routeur tombe ou revient
  (si un bot est configure dans les notifications, `TIKRAS_AUTO_NOTIFY_DOWN=0` pour desactiver).
- Etat visible dans `Parametres admin` (panneau **Automatisations**, bouton
  **Executer maintenant**) et badges d'etat sur chaque routeur.

## Declencheurs

1. **Soft cron** : tant qu'un admin garde le panneau ouvert, le navigateur
   pingue `cron.php?soft=1` toutes les 5 minutes (aucune configuration).
2. **Cron externe (recommande 24h/24)** : programmer un ping HTTP toutes les
   5 minutes sur `https://VOTRE-APP/cron.php?token=TIKRAS_CRON_TOKEN`
   avec cron-job.org, UptimeRobot ou un cron Render.
3. **CLI** : `php mikhmon/cron.php` (ou `--force` pour tout executer).

## Deployer sur Render

1. Depot **prive** GitHub/GitLab, pousser cette branche.
   `mikhmon/include/config.php` (identifiants routeurs) n'est PAS versionne.
2. Render > New > **Blueprint** > choisir le depot (`render.yaml` detecte).
3. Dans l'onglet Environment du service, definir :
   - `TIKRAS_ADMIN_USER` / `TIKRAS_ADMIN_PASS` : login du panneau.
   - `TIKRAS_CRON_TOKEN` est genere automatiquement.
4. Ouvrir l'app > se connecter > ajouter les routeurs (ou restaurer une
   sauvegarde ZIP faite en local via la page **Sauvegarde**). Tout est stocke
   sur le disque persistant `/data`.
5. Programmer le ping cron externe (voir ci-dessus).

### Importer vos routeurs existants

Sur le poste local : `Sauvegarde` > creer une sauvegarde ZIP.
Sur le serveur en ligne : `Sauvegarde` > restaurer ce ZIP.
La liste des routeurs, la config admin et la base SQLite sont importees.

## IMPORTANT — acces aux routeurs (ZeroTier)

Les routeurs sont joints via leurs IP privees ZeroTier/VPN. **Render ne peut
pas rejoindre un reseau ZeroTier** (pas d'acces `/dev/net/tun` dans leurs
conteneurs). Deux options :

- **Option A (complete) : VPS Docker** (Contabo, Hetzner, OVH, ~5 $/mois).
  ZeroTier s'installe sur le VPS, puis :
  ```bash
  docker build -t tikras-mikhmon .
  docker run -d -p 80:80 -v tikras-data:/data \
    -e TIKRAS_ADMIN_USER=... -e TIKRAS_ADMIN_PASS=... -e TIKRAS_CRON_TOKEN=... \
    --network host tikras-mikhmon
  ```
  Le conteneur voit le reseau ZeroTier de l'hote (`--network host`) et tous
  les routeurs restent joignables comme aujourd'hui.
- **Option B (Render)** : l'interface fonctionne partout, mais seuls les
  routeurs joignables publiquement (IP publique + port API, ou hub WireGuard
  avec IP publique) seront accessibles pour la generation de tickets.

## Verification locale

- Lint : `powershell -ExecutionPolicy Bypass -File tools/lint-php8.ps1`
- Tick manuel : `php8\php.exe mikhmon\cron.php --force`
- Serveur local : `powershell -ExecutionPolicy Bypass -File tools/start-php8-server.ps1`
