# Roaming RADIUS central pour tickets Hotspot

Ce mode sert a faire accepter les memes tickets Hotspot sur plusieurs routeurs MikroTik sans devoir copier chaque ticket sur chaque routeur. Les routeurs deviennent des clients RADIUS et demandent au serveur central si un ticket est valide.

## Architecture conseillee

- Serveur central: FreeRADIUS ou MikroTik User Manager.
- Transport entre sites: WireGuard `10.252.0.0/24`.
- Routeur central/hub: serveur RADIUS joignable par les sites, souvent `10.252.0.1`.
- Routeurs distants/spokes: clients RADIUS qui pointent vers le serveur central.
- Secret RADIUS: identique entre chaque routeur client et le serveur RADIUS.

## Ce que Mikhmon configure

Dans le generateur de scripts, choisir le modele `Roaming RADIUS Hotspot`.

Le script RouterOS genere:

- cree ou met a jour l'entree `/radius` avec service `hotspot` ou `hotspot,ppp`;
- active `use-radius=yes` sur tous les profils Hotspot ou sur un profil choisi;
- active l'accounting Hotspot si demande;
- peut activer PPP AAA RADIUS;
- active `/radius incoming` pour permettre les deconnexions envoyees par le serveur RADIUS.

## Procedure par routeur

1. Mettre en place l'interconnexion WireGuard entre les sites.
2. Sur le serveur RADIUS, declarer chaque MikroTik comme NAS/client avec son IP WireGuard et le meme secret.
3. Dans Mikhmon, ouvrir le generateur de scripts.
4. Selectionner `Roaming RADIUS Hotspot`.
5. Renseigner l'adresse du serveur RADIUS, le secret, les ports `1812/1813`, puis le profil Hotspot a modifier.
6. Copier le script dans le terminal MikroTik du routeur concerne.
7. Tester un ticket central depuis ce routeur.
8. Repeter sur chaque routeur distant.

## Creation des tickets

Le RADIUS central demande que les tickets soient crees dans le serveur RADIUS, pas seulement dans `/ip hotspot user` d'un routeur.

Deux modes restent donc disponibles:

- Roaming par synchronisation API: Mikhmon cree le ticket local puis le copie sur les routeurs choisis. C'est deja integre dans la page de generation des tickets.
- Roaming RADIUS central: les routeurs interrogent un serveur unique. Il faut ensuite connecter Mikhmon a User Manager ou FreeRADIUS pour que les tickets soient crees directement dans cette base centrale.

## Verification rapide cote MikroTik

```routeros
/radius print detail
/ip hotspot profile print detail where use-radius=yes
/log print where message~"radius"
```

Si le client voit `RADIUS server is not responding`, verifier:

- ping vers l'IP WireGuard du serveur RADIUS;
- ports UDP `1812` et `1813` ouverts;
- secret identique cote MikroTik et cote serveur;
- IP NAS declaree dans le serveur RADIUS;
- profil Hotspot bien passe en `use-radius=yes`.
