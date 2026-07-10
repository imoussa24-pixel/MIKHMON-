# Interconnexion WireGuard depuis Mikhmon/TIKRAS IT

Cette integration ajoute un scenario `Interconnexion WireGuard` dans le generateur de scripts MikroTik.

## Objectif

Relier les routeurs MikroTik en Layer 3 via WireGuard pour que Mikhmon, l'API MikroTik et plus tard un serveur RADIUS central puissent joindre les sites sans exposer les ports d'administration sur Internet.

## Ordre conseille

1. Ouvrir le routeur central dans Mikhmon.
2. Aller dans `Generateur de scripts`.
3. Choisir `Interconnexion WireGuard`.
4. Mettre le role `Hub central`.
5. Laisser la cle publique du pair vide pour la premiere execution si elle n'est pas encore connue.
6. Appliquer ou copier le script, puis recuperer la cle publique locale dans les logs RouterOS.
7. Ouvrir le routeur distant et choisir le role `Site distant`.
8. Mettre comme endpoint l'IP publique ou DNS du hub.
9. Mettre la cle publique du hub dans le champ `Cle publique WireGuard du routeur distant`.
10. Appliquer le script distant, recuperer sa cle publique, puis revenir sur le hub pour renseigner la cle publique du site.

## Plan IP simple

- Hub: `10.252.0.1/24`
- Site 1: `10.252.0.2/24`
- Site 2: `10.252.0.3/24`
- Reseau tunnel autorise: `10.252.0.0/24`

Sur le hub, `Allowed address` du site 1 peut etre:

```text
10.252.0.2/32,192.168.20.0/24
```

Sur le site 1, `Allowed address` vers le hub peut etre:

```text
10.252.0.1/32,192.168.10.0/24
```

## Champs importants

- `Endpoint distant`: a remplir surtout sur les sites distants. Le hub peut rester vide si les sites ont des IP dynamiques.
- `Routes distantes`: reseaux LAN a joindre via WireGuard, par exemple `192.168.20.0/24`.
- `Autoriser API/Winbox/SSH depuis le tunnel`: ajoute le reseau WireGuard dans les services d'administration.
- `Exception NAT`: ajoute une regle srcnat accept vers les routes distantes pour eviter de masquerader le trafic site-a-site.

## Verification rapide

Dans RouterOS:

```routeros
/interface wireguard print
/interface wireguard peers print
/ip route print where comment~"WG route"
/ping 10.252.0.1
```

Si le handshake ne monte pas, verifier d'abord le port UDP sur le hub, l'endpoint, les cles publiques croisees et les routes retour.
