# Plan de remaniement interface MIKHMON PRO ADMIN

## Diagnostic rapide

L'interface actuelle fonctionne, mais elle reste construite comme une application PHP classique:

- HTML melange directement dans les pages PHP.
- Navigation, cartes, formulaires et tableaux repetes dans beaucoup de fichiers.
- CSS historique `mikhmon-ui.*.min.css` + une couche `mikhmon-custom.css`.
- jQuery, Font Awesome 4 et Highcharts.
- Dashboard deja enrichi, mais encore charge par gros blocs synchrones.
- Tables Hotspot/PPP utiles, mais peu confortables pour gros volumes.
- Bonne base PHP 8 maintenant, donc on peut moderniser sans casser le moteur RouterOS.

## Strategie recommandee

Ne pas tout reecrire en une fois. Il faut passer par une couche moderne progressive:

1. Garder le backend PHP/RouterOS actuel.
2. Ajouter un systeme de composants UI reutilisables.
3. Remplacer progressivement les blocs visuels page par page.
4. Ajouter des endpoints JSON pour les zones dynamiques.
5. En dernier seulement, envisager une interface plus proche d'une application web moderne.

## Phase 1 - Socle visuel moderne

Objectif: donner un aspect moderne sans casser les pages.

- Creer `mikhmon/lib/tikras_ui.php`.
- Ajouter des helpers: page header, toolbar, alert, stat card, action button, table wrapper, empty state.
- Creer une nouvelle feuille `css/tikras-modern.css`.
- Conserver les anciennes classes pour compatibilite.
- Moderniser login, navbar, sidebar, notifications, loader, messages d'erreur.

Resultat attendu:

- Interface plus nette.
- Meilleure lisibilite mobile.
- Moins de HTML duplique.
- Aucun changement du fonctionnement RouterOS.

Etat 2026-05-25:

- `mikhmon/lib/tikras_ui.php` ajoute les premiers composants reutilisables.
- `mikhmon/css/tikras-modern.css` est charge avec cache-buster automatique.
- L'ecran de connexion est remanie.
- La page Sessions/Routeurs est remaniee avec recherche, cartes routeurs et actions plus lisibles.
- Les tests PHP 8 et les tests HTTP principaux passent apres modification.

## Phase 2 - Dashboard professionnel

Objectif: transformer l'accueil en centre de controle.

- Resume routeur: statut API, modele, version RouterOS, uptime, CPU, RAM, disque.
- Resume clients: Hotspot actifs, PPP actifs, total comptes, ratio actifs.
- Resume ventes: aujourd'hui, mois, dernier ticket, meilleur profil.
- Alertes: API lente, routeur non joignable, disque bas, CPU eleve, profils sans prix.
- Graphiques plus utiles: trafic, actifs, ventes, repartition profils.
- Actualisation par blocs plutot que rechargement complet.

Resultat attendu:

- On voit immediatement si le routeur va bien.
- Moins de clics.
- Diagnostic plus rapide.

Etat 2026-05-25:

- Premiere action rapide ajoutee sur le dashboard: generation tickets, utilisateurs, rapport.
- L'en-tete dashboard reprend la session, la version RouterOS et les clients actifs.
- Les blocs RouterOS existants sont conserves pour limiter le risque.

## Phase 3 - Tables Hotspot/PPP modernes

Objectif: rendre les listes exploitables pour de gros reseaux.

- Barre de recherche globale.
- Filtres par profil, statut, commentaire, serveur, expiration.
- Actions groupees: activer, desactiver, supprimer, exporter.
- Colonnes fixes et lisibles.
- Badges statut: actif, expire, desactive, illimite.
- Pagination ou chargement progressif si la liste est tres grande.
- Confirmation claire pour les suppressions.

Pages prioritaires:

- Hotspot users.
- Hotspot active.
- Hotspot profiles.
- PPP secrets.
- PPP active.
- PPP profiles.
- Sessions routeurs.

Etat 2026-05-26:

- `js/tikras-modern.js` ajoute la recherche locale moderne et les compteurs visibles.
- Hotspot users modernise: en-tete, actions rapides, filtres, table claire, compteur dynamique.
- Hotspot active modernise: en-tete, recherche locale, table claire, compteur dynamique.
- PPP secrets modernise: en-tete, actions rapides, filtres, table claire, compteur dynamique.
- La logique RouterOS existante est conservee.

Correctifs 2026-05-26:

- La recherche globale legacy ne touche plus les champs modernes `.tikras-data-search`.
- Le rapport de vente garde sa recherche dediee avec recalcul des totaux, sans script global parasite.
- User Log utilise maintenant la recherche moderne avec compteur dynamique.
- Nouveau `Journal RouterOS` ajoute dans le menu Journal, avec recherche, filtre topic et limite d'affichage.

## Phase 4 - Formulaires plus intelligents

Objectif: reduire les erreurs humaines.

- Creation ticket/utilisateur avec apercu instantane.
- Validation avant envoi: duree, prix, profil, limite data.
- Templates rapides: 1h, 3h, 1 jour, 7 jours, mensuel.
- Generation de mots de passe plus propre.
- Boutons clairs: enregistrer, annuler, imprimer, partager.
- Messages de succes/echec persistants.

Etat 2026-05-26:

- La page de generation tickets a maintenant un apercu instantane: quantite, mode, profil, serveur, code, limites, roaming et partage.
- Des alertes simples signalent les oublis visibles avant generation: profil absent, lot lourd, limites vides, roaming selectionne sans routeur, destinataire de partage incomplet.
- La mise en page tickets passe en deux colonnes sur bureau et en colonne unique mobile, sans debordement horizontal.
- La logique RouterOS de generation n'a pas ete modifiee.

## Phase 5 - Couche API interne

Objectif: preparer une vraie evolution sans tout casser.

- Ajouter `api/*.php` pour retourner du JSON.
- Endpoints: statut routeur, stats dashboard, liste utilisateurs, ventes, tickets, routeurs.
- Garder les pages PHP classiques comme affichage principal.
- Charger certains blocs en AJAX propre, avec timeout et erreur visible.

Resultat attendu:

- Interface plus rapide.
- Moins de blocage quand un routeur est lent.
- Possibilite future d'app mobile ou tableau externe.

## Phase 6 - Qualificatifs a augmenter

### Qualite visuelle

- Interface moderne, sobre, dense, orientee exploitation.
- Meilleure hierarchie: routeur, statut, actions, donnees.
- Theme clair/sombre propre.

### Qualite ergonomique

- Moins de clics pour creer, vendre, imprimer, rechercher.
- Actions importantes toujours visibles.
- Recherche routeur et recherche utilisateur plus rapides.

### Qualite technique

- Composants UI reutilisables.
- Moins de duplication HTML.
- Delais RouterOS centralises.
- Logs plus utiles.
- Tests HTTP automatises.

### Qualite commerciale

- Branding TIKRAS IT plus professionnel.
- Pages tickets et portail captif plus presentables.
- Exports PDF/CSV mieux organises.
- Experience plus rassurante pour clients et vendeurs.

### Qualite reseau

- Detection routeur lent/injoignable.
- Statut API visible.
- Alertes ressources.
- Pre-validation des scripts generes.

## Risques

- Une refonte trop rapide peut casser des pages sensibles.
- Les anciennes pages ont beaucoup de HTML inline.
- Les tableaux volumineux peuvent ralentir si on modernise seulement le CSS.
- Il faut garder PHP 5 en secours, mais construire le nouveau paquet sur PHP 8.

## Ordre conseille

1. Creer la couche UI `tikras_ui.php` et `tikras-modern.css`.
2. Moderniser login + shell global: navbar, sidebar, notifications.
3. Moderniser la liste des routeurs et l'ouverture de sessions.
4. Moderniser le dashboard.
5. Moderniser les tables Hotspot.
6. Moderniser les tables PPP.
7. Ajouter endpoints JSON pour dashboard et listes lourdes.
8. Ajouter tests visuels et tests HTTP sur les pages principales.

## Recommendation finale

La meilleure voie est une refonte progressive, pas une reecriture totale.

On garde le moteur actuel, deja modernise PHP 8, et on remplace l'interface par couches. Cela donnera rapidement un programme plus professionnel tout en evitant de casser la gestion MikroTik, les tickets, le roaming et les rapports.
