# Portail captif bleu pour MikroTik + TIKRAS IT

Ce pack applique le plan du document PDF sous forme d'un portail captif moderne, leger et bleu dominant. Les fichiers a envoyer sur MikroTik sont dans `hotspot/`.

## Installation MikroTik

1. Personnaliser `hotspot/login.html`, `hotspot/pay.html` et les liens WhatsApp `https://wa.me/22700000000`.
2. Televerser le contenu du dossier `hotspot/` dans le dossier `hotspot` de MikroTik via Files, Winbox ou FTP temporaire.
3. Verifier le profil hotspot :
   `/ip hotspot profile set hsprof1 html-directory=hotspot`
4. Tester depuis un client non connecte.

## Ce qui est inclus

- `login.html` compatible variables MikroTik : `$(link-login-only)`, `$(link-orig)`, `$(username)`, erreurs et CHAP avec `md5.js`.
- Theme bleu clair dominant, responsive mobile, cartes arrondies, icones/visuels legers.
- Offres dynamiques via `api/offers.php` quand un serveur PHP est disponible, avec fallback statique sinon.
- Page `pay.html` pour preparer Mobile Money et commande WhatsApp.
- Dossiers prevus pour QR, publicites, sponsors, tickets imprimables et APIs OTP/paiement/stats.

## Conseils de securite du document

Sur le routeur, eviter l'administration publique :

```routeros
/ip service disable ftp
/ip service disable telnet
/ip service set winbox port=55555
```

Ajoutez ensuite une regle firewall qui limite Winbox/SSH au LAN ou a vos IP d'administration.

## Conseils de performance

- Garder les images compressees et petites, idealement sous 200 Ko.
- Preferer SVG/PNG leger pour le logo et JPG compresse pour les photos.
- Eviter les videos lourdes en autoplay, les scripts inutiles et les animations permanentes.
- Activer la duree de cookie hotspot si adaptee a votre offre :

```routeros
/ip hotspot profile set hsprof1 http-cookie-lifetime=1d
```

## Points a brancher avant vente automatique

- Remplacer `api/payment.php` par l'integration Mobile Money reelle.
- Remplacer `api/otp.php` par votre fournisseur SMS/OTP.
- Generer les QR/tickets depuis votre processus TIKRAS IT ou votre caisse.
- Remplacer les visuels sponsor et le numero WhatsApp par vos donnees commerciales.
