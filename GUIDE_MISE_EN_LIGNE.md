# Guide de mise en ligne — TIKRAS IT

Marche a suivre complete, de la commande du serveur au panneau accessible
depuis n'importe ou. Comptez 30 a 45 minutes.

---

## Etape 0 — Quel serveur choisir

### Ce que consomme reellement l'application (mesure, pas estime)

| Ressource | Mesure sous charge (106 routeurs) |
|---|---|
| Memoire vive | **35 Mo** |
| Processeur | **0,3 %** |
| Disque (donnees) | 208 Ko (+ 12 Mo d'application) |

L'application est tres legere : **la puissance n'est pas le critere de choix**.
Ce qui compte vraiment, c'est la fiabilite, la facilite de paiement depuis le
Niger, et le prix. Comptez surtout la marge pour le systeme, Docker et ZeroTier
(environ 700 Mo) : **2 Go de memoire suffisent tres largement**.

### Recommandation

**Contabo — VPS S (Allemagne)**, environ 7 $/mois.

- Souscription simple depuis l'Afrique de l'Ouest, PayPal accepte : c'est le
  point decisif, Hetzner et OVH refusent ou bloquent souvent les nouveaux
  comptes africains en controle anti-fraude.
- Ressources tres superieures au besoin (4 vCPU, 8 Go, 200 Go) : de la marge
  pour ajouter plus tard un serveur RADIUS central ou un portail captif.
- Son defaut connu (processeur partage, performances par cœur moyennes) est
  **sans consequence ici** puisque l'application utilise 0,3 % de processeur.
- Choisissez le datacenter **Allemagne (Nuremberg)** : c'est le mieux raccorde
  a l'Afrique de l'Ouest parmi leurs sites.

**Alternative si vous avez deja une carte acceptee a l'international :**
Hetzner CX22/CX23 (environ 5,50 €/mois) — meilleur reseau et meilleure
reputation de fiabilite, mais verification d'identite plus stricte.

**A savoir sur la latence :** vous etes au Niger et vos routeurs aussi ; avec un
serveur en Europe, chaque action fait un aller-retour Niger → Europe → Niger,
soit environ 150 a 250 ms de delai supplementaire. C'est perceptible mais sans
gravite pour un panneau d'administration. Aucun hebergeur serieux et abordable
n'est actuellement present au Niger meme.

### A la commande

- Systeme : **Ubuntu 24.04 LTS**
- Notez l'adresse IP et le mot de passe root envoyes par courriel.

---

## Etape 1 — Preparer le reseau ZeroTier

1. Connectez-vous sur <https://my.zerotier.com>.
2. Ouvrez le reseau que vos routeurs utilisent deja.
3. Copiez son **Network ID** (16 caracteres).

---

## Etape 2 — Exporter vos routeurs (sur votre PC Windows)

```powershell
cd C:\Users\USER\Documents\MIKHMON_PRO_ADMIN_FINALISE\nouveau_php8_lanceur
php8\php.exe tools\export-config-local.php
```

Un fichier `config.local.php` est cree (vos 106 routeurs). **Il contient des
mots de passe : ne le mettez jamais sur git ni par courriel.**

---

## Etape 3 — Envoyer le projet sur le serveur

### Option recommandee : depot git prive

```powershell
git remote add origin https://github.com/VOTRE_COMPTE/tikras-mikhmon.git
git push -u origin chore/php8-hardening-2026-07-10
```

Le depot **doit etre prive**. Les fichiers sensibles (`config.php`, `.env`,
`config.local.php`) sont deja exclus automatiquement.

Puis, sur le serveur :

```bash
ssh root@VOTRE_IP
apt update && apt install -y git
git clone https://github.com/VOTRE_COMPTE/tikras-mikhmon.git tikras
```

### Option sans git

```powershell
scp -r C:\Users\USER\Documents\MIKHMON_PRO_ADMIN_FINALISE\nouveau_php8_lanceur root@VOTRE_IP:/root/tikras
```

### Dans les deux cas, envoyez la liste des routeurs

```powershell
scp config.local.php root@VOTRE_IP:/root/config.local.php
```

Le script d'installation la trouvera et l'importera tout seul, puis la
supprimera du serveur.

---

## Etape 4 — Installer

```bash
ssh root@VOTRE_IP
cd tikras
ZT_NETWORK=VOTRE_NETWORK_ID bash deploy/vps-install.sh
```

Le script installe Docker et ZeroTier, rejoint le reseau, configure le
pare-feu, cree le fichier `.env` et demarre l'application.

**Il s'arretera une fois pour vous faire editer `.env`** : remplacez
`TIKRAS_ADMIN_PASS=CHANGEZ-MOI` par un vrai mot de passe (12 caracteres ou
plus), enregistrez avec `Ctrl+O` puis `Ctrl+X`.

---

## Etape 5 — Autoriser le serveur dans ZeroTier

**Indispensable, sinon aucun routeur ne sera joignable.**

1. Retournez sur <https://my.zerotier.com>, ouvrez votre reseau.
2. Section **Members** : une nouvelle machine apparait (celle du serveur).
3. Cochez **Auth** et donnez-lui un nom, par exemple `serveur-tikras`.

Verifiez ensuite sur le serveur :

```bash
zerotier-cli listnetworks     # doit afficher OK
bash deploy/verifier.sh       # controle complet
```

Le controle doit afficher **0 erreur** et vos routeurs joignables.

---

## Etape 6 — Se connecter

Ouvrez `http://VOTRE_IP/` depuis n'importe quel appareil, connectez-vous avec
les identifiants du `.env`. Vos 106 routeurs doivent apparaitre avec leurs
badges d'etat, et vous pouvez generer vos tickets.

---

## Ajouter HTTPS (recommande)

Avec un nom de domaine pointant vers l'IP du serveur :

```bash
nano .env      # TIKRAS_DOMAIN=panel.votredomaine.com  et  TIKRAS_HTTP_PORT=8080
docker compose --profile https up -d
```

Le certificat est obtenu et renouvele automatiquement.

---

## Au quotidien

```bash
bash deploy/verifier.sh                     # controle de sante
docker compose logs -f app                  # journaux
docker exec tikras-app cat /data/automations.log   # automatisations
git pull && docker compose up -d --build    # mise a jour
```

Les sauvegardes automatiques sont quotidiennes (10 conservees). Pour en
recuperer une sur votre PC :

```powershell
scp root@VOTRE_IP:/var/lib/docker/volumes/tikras_tikras-data/_data/storage/backups/auto-backup-*.zip .
```

---

## En cas de probleme

| Symptome | Cause probable | Solution |
|---|---|---|
| Routeurs tous hors ligne | Serveur non autorise dans ZeroTier | Cochez Auth dans Members |
| « readonly database » | Un cron lance en root | `docker exec tikras-app chown -R www-data:www-data /data` |
| Panneau inaccessible | Conteneur arrete | `docker compose up -d` puis `bash deploy/verifier.sh` |
| Connexion refusee | Mot de passe non defini | Verifiez `TIKRAS_ADMIN_PASS` dans `.env`, puis `docker compose up -d` |

---

## Securite : a faire des le premier jour

1. Mot de passe admin long et unique.
2. HTTPS des que vous avez un domaine (sinon le mot de passe circule en clair).
3. Sauvegarde reguliere recuperee hors du serveur.
4. Idealement, n'exposez le panneau qu'aux membres de votre reseau ZeroTier
   plutot qu'a tout Internet.
