# Lanceur MIKHMON PRO ADMIN

## Fichier principal

- `lanceur.exe`
- Version Windows: `3.2.0.0`
- Raccourci bureau cree: `MIKHMON PRO ADMIN`

## Utilisation

1. Double-cliquer sur `lanceur.exe`.
2. Le serveur PHP 8 demarre automatiquement.
3. Le navigateur s'ouvre sur Mikhmon.
4. Garder la fenetre du lanceur ouverte pendant l'utilisation.
5. Cliquer sur `Arreter` ou `Quitter` pour fermer le serveur lance par ce programme.

## Commandes de maintenance

- Recompiler le lanceur: `powershell -ExecutionPolicy Bypass -File tools/build-lanceur.ps1`
- Creer le raccourci bureau: `powershell -ExecutionPolicy Bypass -File tools/create-desktop-shortcut.ps1`
- Lancer le serveur PHP 8 sans interface: `powershell -ExecutionPolicy Bypass -File tools/start-php8-server.ps1 -Port 8081`
- Tester les pages principales: `powershell -ExecutionPolicy Bypass -File tools/test-php8-router-pages.ps1 -Session simnet`

## Notes

- Le lanceur utilise `php8/php.exe` et `php8/php.ini`.
- Le site servi est le dossier `mikhmon`.
- L'URL par defaut est `http://127.0.0.1:8081/admin.php?id=login`.
- Si le port `8081` est occupe, le lanceur essaie automatiquement les ports suivants jusqu'a `8090`.
- Les journaux du lanceur sont ecrits dans `mikhmon/share/logs/lanceur.log`.
- Si Windows bloque l'ecriture dans le dossier du programme, le lanceur utilise `%LOCALAPPDATA%/MIKHMON_PRO_ADMIN/lanceur.log`.
- L'ancien `MikhmonServer.exe` reste conserve, mais le lanceur finalise utilise PHP 8.
- Correction anti-calage: l'ouverture d'un routeur se fait par navigation normale, et les connexions RouterOS echouent rapidement si le routeur ne repond pas.
- Delais RouterOS reglables dans `mikhmon/include/routeros.php`: timeout court, une seule tentative, cooldown apres echec pour eviter les blocages.
- Pour eviter les confusions, utiliser ce `lanceur.exe` final PHP 8 et fermer les anciens `MikhmonServer.exe` si une ancienne version est deja ouverte.
- Base locale SQLite active dans `%LOCALAPPDATA%/MikhmonProAdmin/storage`.
- Tickets roaming, cache rapports, statuts routeurs et journaux RouterOS sont historises localement.
- Sauvegarde/restauration disponible dans `admin.php?id=backup`.
- Journal systeme consultable dans `admin.php?id=audit`.
- Generateur de scripts RouterOS 7+: garde-fou de version, modele par defaut non destructif, champs invisibles non soumis, confirmation obligatoire avant application routeur.
