# Second chemin d'acces aux routeurs — WireGuard

Objectif : ne plus dependre d'un seul service pour joindre les routeurs.
ZeroTier reste en place ; WireGuard s'ajoute comme chemin independant.

## Pourquoi ce choix

- **87 routeurs sur 106** n'etaient joignables que par ZeroTier.
- Le parc est **integralement en RouterOS 7** : WireGuard y est natif, aucun
  logiciel tiers a installer sur les routeurs.
- Le routeur **appelle** le serveur : le tunnel traverse le NAT des operateurs,
  donc aucune adresse publique n'est necessaire cote site.
- Les deux systemes coexistent : si l'un tombe, l'autre reste disponible.

## Architecture

```
   Routeur MikroTik                     Serveur TIKRAS (169.58.74.46)
   wg-tikras 10.200.1.x  ──── UDP 51820 ────▶  wg0 10.200.0.1
   (connexion sortante, keepalive 25 s)        concentrateur
```

Plage du tunnel : `10.200.0.0/16`, choisie hors des plages ZeroTier
(10.12, 10.51, 10.82, 10.92, 10.147, 10.241, 10.242, 172.27, 172.30) et LAN
(192.168.x, 10.0.x, 10.10.x) deja utilisees.

## Ajouter un routeur (y compris les futurs)

```bash
ssh -i ~/.ssh/tikras_deploy root@169.58.74.46 \
  "cd /root/tikras && bash deploy/wireguard-routeur.sh NOM_DE_SESSION"
```

Le script attribue une adresse libre, enregistre le pair cote serveur et
affiche quatre commandes a coller dans le terminal du routeur. Relancer la
commande pour un routeur deja connu reaffiche sa configuration sans doublon.

Ensuite, dans TIKRAS IT, l'adresse du routeur peut etre remplacee par son
adresse `10.200.1.x` pour passer par WireGuard.

## Verifications

Cote serveur :

```bash
wg show wg0                    # handshake et volumes echanges
ping -c3 10.200.1.1            # joignabilite dans le tunnel
cat /etc/wireguard/routeurs.txt   # registre des routeurs (contient les cles)
```

Cote routeur :

```routeros
/interface/wireguard/peers/print    # rx/tx doivent augmenter
/ping 10.200.0.1
```

## Resultat mesure sur le premier routeur (simnet)

| Chemin | Adresse | Connexion API |
|---|---|---|
| ZeroTier | 10.241.25.191 | joignable, 585 ms |
| WireGuard | 10.200.1.1 | joignable, 504 ms |

Latence dans le tunnel : 150 ms, 0 % de perte. Les 15 clients hotspot
connectes n'ont pas ete affectes par l'operation.

## Points de prudence

- Les commandes generees sont **additives** : elles ne modifient ni ZeroTier,
  ni le pare-feu existant, ni les services. L'acces en place reste intact.
- La regle de pare-feu est inseree **avant** la regle `drop` de la chaine
  `input`, sinon le trafic du tunnel serait rejete.
- Si l'API d'un routeur restreint les adresses autorisees, **ajouter** la plage
  du tunnel sans retirer les valeurs existantes :
  ```routeros
  /ip/service/print detail where name=api
  /ip/service/set api address=LISTE_ACTUELLE,10.200.0.0/16
  ```
- `/etc/wireguard/routeurs.txt` contient les cles privees des routeurs :
  il est en droits 600 et doit rester sur le serveur.

## Retirer un routeur du tunnel

Sur le routeur :

```routeros
/interface/wireguard/remove [find name=wg-tikras]
/ip/firewall/filter/remove [find comment~"TIKRAS IT: acces par le tunnel"]
```

Sur le serveur : retirer le bloc `[Peer]` correspondant dans
`/etc/wireguard/wg0.conf`, sa ligne dans `/etc/wireguard/routeurs.txt`, puis
`systemctl restart wg-quick@wg0`.
