# Configuration mail, WhatsApp et Telegram pour les tickets PDF

Ce module permet d'envoyer les tickets generes par TIKRAS IT au format PDF petit style impression. La configuration est enregistree par session/routeur, donc chaque routeur peut avoir son expediteur mail et son API WhatsApp.

## 1. Configuration dans TIKRAS IT

1. Ouvrir TIKRAS IT.
2. Aller dans `Settings` du routeur concerne.
3. Remplir le bloc `Notifications tickets PDF`.
4. Cliquer sur `Save`.

Les valeurs sont stockees dans `mikhmon/include/notify_config.php`.

## 2. Configuration PHP mail

Champs disponibles:

- `Email expediteur`: adresse utilisee dans l'en-tete `From`.
- `Nom expediteur`: nom visible par le destinataire.
- `SMTP PHP`: serveur SMTP utilise par la fonction PHP `mail()` quand l'environnement PHP le supporte.
- `Port SMTP`: port SMTP, souvent `25`, `587` ou celui donne par l'hebergeur.

Important:

- L'envoi mail utilise la fonction PHP `mail()`.
- Sur beaucoup d'hebergements Linux, `mail()` utilise le service mail local du serveur.
- Sur Windows/portable PHP, `SMTP`, `smtp_port` et `sendmail_from` peuvent etre appliques au runtime, mais PHP `mail()` ne gere pas toujours l'authentification SMTP.
- Si votre SMTP demande un login/mot de passe, configurez un relais local ou l'hebergeur pour autoriser l'envoi depuis TIKRAS IT.

Exemple simple:

```text
Email expediteur: tickets@mon-domaine.com
Nom expediteur: TIKRAS IT
SMTP PHP: smtp.mon-domaine.com
Port SMTP: 25
```

## 3. Ajout de l'API WhatsApp

TIKRAS IT ne peut pas envoyer directement via WhatsApp officiel sans passer par une API/gateway. Renseignez donc les informations fournies par votre fournisseur WhatsApp.

Champs disponibles:

- `API WhatsApp`: URL d'envoi du fournisseur.
- `Token API`: cle/token d'authentification.
- `Methode API`: `POST` ou `GET`.
- `Payload API`: donnees envoyees a l'API.
- `Bot Telegram`: token du bot cree avec BotFather.
- `Chat ID`: identifiant du chat, groupe ou canal destinataire.
- `Parse mode`: option Telegram `HTML`, `Markdown` ou vide.

Placeholders disponibles:

- `{token}`: token API configure.
- `{phone}`: numero WhatsApp saisi dans le generateur.
- `{message}`: message du ticket.
- `{pdf_url}`: lien public du PDF genere.

Payload par defaut:

```text
token={token}&to={phone}&message={message}&pdf={pdf_url}
```

Exemple POST generique:

```text
API WhatsApp: https://api.mon-gateway.com/send
Token API: xxxxx
Methode API: POST
Payload API: token={token}&to={phone}&message={message}&pdf={pdf_url}
```

Exemple GET generique:

```text
API WhatsApp: https://api.mon-gateway.com/send
Token API: xxxxx
Methode API: GET
Payload API: token={token}&phone={phone}&text={message}&file={pdf_url}
```

## 4. Envoi depuis le generateur manuel

Dans `Hotspot > Generate User`, le bloc `Envoi apres generation` permet de choisir `WhatsApp`, `Telegram` ou `Email` et de saisir le destinataire.

- Email: TIKRAS IT envoie le PDF en piece jointe avec la configuration mail du routeur.
- WhatsApp: TIKRAS IT appelle l'API configuree et transmet le message avec le lien PDF.
- Telegram: TIKRAS IT appelle le Bot API Telegram et transmet le message avec le lien PDF.
- Si l'API WhatsApp n'est pas configuree, le bouton/lien WhatsApp reste disponible comme secours.
- Si le bot Telegram n'est pas configure, le bouton de partage Telegram reste disponible comme secours.
- Le statut d'envoi est garde dans `voucher/temp.php` pour eviter un renvoi a chaque actualisation de page.

## 5. Envoi automatique depuis le generateur de script

Pour les tickets automatiques:

1. Aller dans le generateur de script.
2. Activer/configurer la partie `Automatisation tickets`.
3. Dans `Envoi automatique PDF`, choisir `WhatsApp` ou `Email`.
4. Renseigner la destination:
   - WhatsApp: numero au format international, par exemple `22790000000`.
   - Telegram: chat id, par exemple `-1001234567890`; si vide, TIKRAS IT utilise le Chat ID configure dans les settings.
   - Email: adresse email du destinataire.
5. Verifier que l'URL callback pointe vers `process/ticketautoshare.php`.

Le routeur MikroTik appelle cette URL apres generation. TIKRAS IT recupere les tickets par commentaire, cree le PDF, puis envoie selon le canal choisi.

## 6. Conditions a respecter

- Le routeur MikroTik doit pouvoir joindre l'URL TIKRAS IT indiquee dans le script.
- Le lien PDF doit etre accessible par le destinataire si l'API WhatsApp envoie seulement un lien.
- Pour l'email, le serveur qui execute TIKRAS IT doit etre autorise a envoyer des mails.
- Le token de callback est genere par session et verifie par `process/ticketautoshare.php`.

## 7. Fichiers principaux

- `mikhmon/settings/settings.php`: formulaire de configuration par routeur.
- `mikhmon/include/notify_config.php`: stockage des reglages.
- `mikhmon/lib/tikras_notify.php`: application des reglages mail et appel API WhatsApp/Telegram.
- `mikhmon/process/ticketautoshare.php`: callback RouterOS, generation PDF et envoi automatique.
- `mikhmon/lib/tikras_ticket_pdf.php`: generation PDF et envoi mail avec piece jointe.
