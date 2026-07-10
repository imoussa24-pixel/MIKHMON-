# Roaming tickets TIKRAS IT

Cette version ajoute un mode de roaming par synchronisation API dans `Hotspot > Generate User`.

## Principe

Quand un lot de tickets est genere, TIKRAS IT cree une seule liste de codes puis pousse les memes utilisateurs Hotspot sur plusieurs routeurs MikroTik.

Le routeur courant est toujours inclus. Les autres routeurs sont pris depuis les sessions deja enregistrees dans `mikhmon/include/config.php`.

## Utilisation

1. Verifier que les routeurs sont interconnectes, idealement via WireGuard.
2. Verifier que l'API MikroTik est accessible entre Mikhmon et chaque routeur.
3. Creer le meme profil Hotspot sur les routeurs concernes, avec le meme nom, ou laisser l'option de creation automatique du profil activee.
4. Aller dans `Hotspot > Generate User`.
5. Dans `Roaming tickets`, choisir:
   - `Routeur actuel seulement` pour le comportement classique.
   - `Routeurs selectionnes` pour pousser les tickets sur certains routeurs.
   - `Tous les routeurs` pour pousser le lot sur toutes les sessions Mikhmon.
6. Cliquer sur `Tester roaming` pour verifier les routeurs avant creation des tickets.
7. Generer les tickets.
8. Lire le tableau `Roaming tickets` apres generation pour voir les ajouts, mises a jour et erreurs par routeur.

## Facilites ajoutees

- Creation automatique du profil Hotspot manquant sur un routeur distant, a partir des principaux reglages du profil local.
- Test `Sante roaming` avant generation: API joignable, profil Hotspot, serveur Hotspot et avertissements par routeur.
- File de reprise pour les routeurs hors ligne ou en erreur.
- Bouton `Retenter maintenant` quand des lots de tickets attendent encore une synchronisation.
- Limite de reprise: les 5 premiers lots en attente sont retentes a chaque clic pour eviter de bloquer l'interface trop longtemps.

## Conditions importantes

- Le profil choisi doit exister sur le routeur courant. Mikhmon peut ensuite tenter de le creer sur les routeurs distants.
- Si le serveur Hotspot choisi n'existe pas sur un routeur cible, TIKRAS IT utilise automatiquement `server=all`.
- Un routeur inaccessible est place en file de reprise et affiche `Connexion impossible`.
- La file de reprise est stockee localement dans `mikhmon/voucher/roaming_queue.php`.
- Cette approche synchronise les tickets sur plusieurs routeurs. Pour une limitation globale stricte en temps reel, il faudra ensuite passer au mode RADIUS central.

## Fichiers ajoutes/modifies

- `mikhmon/lib/tikras_roaming.php`: fonctions de synchronisation multi-routeurs.
- `mikhmon/hotspot/generateuser.php`: interface et appel roaming dans la generation de tickets.
