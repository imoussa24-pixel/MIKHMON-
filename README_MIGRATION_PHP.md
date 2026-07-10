# Mise a niveau PHP progressive

Le projet embarque encore PHP 5.4.17. La migration doit donc rester progressive pour garder le meme aspect graphique et eviter de casser les pages existantes.

## Couche appliquee

- Ajout de `mikhmon/lib/tikras_core.php`.
- Demarrage de session centralise avec `tikras_start_session()`.
- Gestion des erreurs centralisee avec journalisation dans `mikhmon/share/logs/php-error.log`.
- Lecture securisee des entrees avec `tikras_get()`, `tikras_post()`, `tikras_post_array()`, `tikras_server()`, `tikras_cookie()` et `tikras_file()`.
- Valeurs de session par defaut pour eviter les notices sur PHP recent.
- Migration des entrees principales `admin.php` et `index.php`.
- Migration des zones Hotspot, PPP, settings, process, report, status, traffic, system et dashboard.
- Correction des acces fragiles `explode(...)[n]` dans le code actif.
- Remplacement des anciens `error_reporting(0)` par le bootstrap central.

## Regle de migration

Pour chaque page modifiee:

1. Inclure `lib/tikras_core.php`.
2. Remplacer les lectures directes `$_GET[...]` et `$_POST[...]`.
3. Remplacer les lectures de session non garanties par `tikras_session_get()` ou declarer une valeur par defaut.
4. Garder le HTML/CSS existant pour conserver l'apparence.
5. Lancer le lint PHP sur toute la base avant de livrer.

## Verification actuelle

- `PHP lint errors: 0` avec le runtime embarque `php/m-php.exe`.
- Runtime PHP 8 ajoute dans `php8/`.
- Version PHP 8 installee: `PHP 8.5.6 (cli) (NTS Visual C++ 2022 x64)`.
- Configuration locale ajoutee: `php8/php.ini`.
- `PHP 8 lint errors: 0` avec `php8/php.exe`.
- Correction PHP 8.5 appliquee dans `mikhmon/lib/tikras_notify.php` pour remplacer l'ancien acces direct a `$http_response_header`.
- Service commun RouterOS ajoute: `mikhmon/lib/tikras_routeros.php`.
- Les connexions RouterOS actives passent maintenant par `tikras_routeros_create()`, `tikras_routeros_connect()`, `tikras_routeros_comm()` et `tikras_routeros_disconnect()`.
- Mode debug configurable ajoute dans `mikhmon/include/debug.php` et via la variable d'environnement `TIKRAS_DEBUG`.
- Les erreurs restent journalisees dans `mikhmon/share/logs/php-error.log` et ne sont pas affichees aux clients.
- Fuseau horaire initialise dans le bootstrap commun avec fallback `Africa/Niamey`.
- Helper de redirection ajoute: `tikras_redirect()`, avec redirection HTTP quand les headers sont encore disponibles.
- Les redirections critiques des pages admin, index, settings, hotspot, PPP, process et report ont ete converties quand c'etait possible.
- Page de login testee en HTTP: `http://127.0.0.1:8080/admin.php?id=login` repond `200`.
- Page de login testee en PHP 8: `http://127.0.0.1:8081/admin.php?id=login` repond `200`.
- Aucun nouvel acces direct `$_GET[...]`, `$_POST[...]`, `$_FILES[...]` hors du helper central.
- Aucun `error_reporting(0)` restant dans les fichiers PHP.
- Aucun `new RouterosAPI()` direct restant hors du service commun RouterOS.
- Test RouterOS CLI reussi avec PHP 8 sur une session reelle: connexion, identity, resource, profils Hotspot, utilisateurs Hotspot et profils PPP.
- Test HTTP PHP 8 page par page reussi sur une session reelle: 15 pages principales en `200`, sans erreur runtime visible dans le HTML.

## Commandes utiles PHP 8

- Lint complet: `powershell -ExecutionPolicy Bypass -File tools/lint-php8.ps1`
- Serveur local PHP 8: `powershell -ExecutionPolicy Bypass -File tools/start-php8-server.ps1 -Port 8081`
- Le serveur PHP 8 charge `php8/php.ini`, dont `date.timezone=Africa/Niamey`.
- Test CLI RouterOS avec une session configuree: `php8/php.exe tools/test-routeros-cli.php NOM_SESSION`
- Test HTTP page par page avec PHP 8: `powershell -ExecutionPolicy Bypass -File tools/test-php8-router-pages.ps1 -BaseUrl http://127.0.0.1:8081 -Session NOM_SESSION`
- Le test HTTP lit les identifiants admin depuis `mikhmon/include/config.php` si `-User` et `-Password` ne sont pas fournis.

## Etape README appliquee

- Appels RouterOS API isoles dans `mikhmon/lib/tikras_routeros.php`.
- Debug configurable sans affichage d'erreurs au navigateur.
- Redirections critiques converties vers `tikras_redirect()`.
- Scripts de test routeur reel ajoutes pour verifier la connexion RouterOS et les pages principales sous PHP 8.
- La validation routeur reel doit etre lancee avec une session MikroTik joignable depuis ce poste et un compte Mikhmon valide.

## Prochaines validations conseillees

- Lancer `tools/test-routeros-cli.php` avec chaque routeur important.
- Lancer `tools/test-php8-router-pages.ps1` apres connexion a chaque session MikroTik.
- Lire `mikhmon/share/logs/php-error.log` apres les tests pour corriger les avertissements restants.
