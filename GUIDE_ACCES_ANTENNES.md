# Accéder à vos antennes à distance

Ce guide explique comment joindre, depuis votre ordinateur ou votre téléphone,
les antennes et points d'accès installés derrière vos routeurs MikroTik —
sans être sur place.

---

## Comment cela fonctionne

Trois éléments doivent être en place. Le panneau s'occupe des deux premiers ;
le troisième dépend de vos équipements.

| | Ce qu'il faut | Qui s'en charge |
|---|---|---|
| 1 | Votre appareil rejoint le concentrateur | **Le panneau** (page Accès de secours) |
| 2 | Le routeur laisse passer vers son réseau | **Le panneau** (colonne « Réseau du site ») |
| 3 | L'antenne sait répondre vers le concentrateur | **Vous**, une fois par antenne |

---

## Étape 1 — Raccorder votre appareil

Page **Accès de secours**, section **Mes appareils**.

1. Saisissez un nom (« Portable Ibrahim »), choisissez **Ordinateur** ou **Téléphone**.
2. Cliquez sur **Préparer la configuration**.
3. **Sur téléphone** : ouvrez l'application WireGuard, touchez **+** puis
   **Scanner depuis un code QR**, et visez l'écran.
   **Sur ordinateur** : cliquez sur **Fichier**, puis dans WireGuard choisissez
   **Importer un tunnel depuis un fichier**.
4. Activez le tunnel.

Vous devez alors pouvoir ouvrir Winbox sur `10.200.1.1`, `10.200.1.3`, etc.

> **Winbox ne montrera jamais vos routeurs dans l'onglet « Neighbors »** à
> travers le tunnel. La découverte automatique de MikroTik utilise une
> diffusion réseau, qui ne traverse aucun tunnel. Connectez-vous **par
> adresse IP**. Ce n'est pas une panne.

---

## Étape 2 — Déclarer le réseau du site

Page **Accès de secours**, tableau **Routeurs**, colonne **Réseau du site**.

### Trouver le bon réseau

C'est le point où l'on se trompe le plus souvent : un routeur a souvent
**plusieurs réseaux**, et vos antennes ne sont que sur l'un d'eux.

Dans Winbox, sur le routeur concerné : **IP → Addresses**. Vous verrez par
exemple :

```
20.20.20.1/24     LAN         <- souvent vide
10.10.10.1/22     HOTSPOT     <- les clients Wi-Fi et souvent les antennes
192.168.88.1/24   ether2      <- parfois le réseau d'administration
```

Pour savoir **où sont réellement vos antennes**, allez dans **IP → ARP** :
la liste montre les équipements présents et sur quelle interface. Repérez
l'adresse d'une antenne Grandstream, et retenez le réseau correspondant.

> Exemple : une antenne en `10.10.9.254` sur l'interface HOTSPOT signifie
> que le réseau à déclarer est `10.10.8.0/22` (celui de l'interface HOTSPOT).

### Le déclarer

Saisissez le réseau au format `10.10.8.0/22` puis validez. Le panneau fait
alors deux choses d'un coup :

- il annonce ce réseau au concentrateur ;
- il ajoute sur le routeur une règle d'autorisation (visible dans Winbox sous
  **IP → Firewall → Filter Rules**, commentée `TIKRAS acces site`).

> **Attention aux réseaux en double.** Si deux sites utilisent le même plan
> d'adressage (deux fois `10.10.8.0/22` par exemple), le concentrateur ne
> saura pas vers lequel router : un seul des deux sera joignable. Dans ce cas,
> changez le plan d'adressage de l'un des sites avant de le déclarer.

---

## Étape 3 — Ce que vous devez faire sur l'antenne

C'est la partie que le panneau **ne peut pas** faire : il n'a aucun accès aux
antennes tant qu'elles ne répondent pas.

Une antenne ne répondra que si elle sait **par où renvoyer** la réponse.
Vérifiez, dans son interface d'administration :

1. **Passerelle par défaut** = l'adresse du MikroTik sur ce réseau
   (dans l'exemple ci-dessus : `10.10.10.1`).
   C'est le réglage le plus souvent en cause. Une antenne sans passerelle
   fonctionne très bien sur son réseau local, mais ne peut répondre à personne
   d'extérieur — donc pas à vous.

2. **Adresse IP fixe** (recommandé). Une antenne en DHCP change d'adresse et
   vous ne la retrouverez plus. Réservez-lui une adresse, ou fixez-la sur
   l'antenne.

3. **Pare-feu de l'antenne** : certains modèles Grandstream n'acceptent
   l'administration que depuis leur propre réseau. Cherchez une option du type
   *Access Control*, *Management VLAN* ou *Remote Management*, et autorisez
   `10.200.0.0/16`.

### Vérifier

Depuis votre ordinateur, tunnel activé :

```bash
ping 10.10.9.254
```

- **Ça répond** → ouvrez son interface web dans le navigateur.
- **Ça ne répond pas** → voir le tableau ci-dessous.

---

## Si cela ne marche pas

| Symptôme | Cause la plus fréquente | Ce qu'il faut faire |
|---|---|---|
| Le routeur répond (`10.200.1.x`) mais aucune antenne | Le réseau du site n'est pas déclaré, ou c'est le mauvais | Étape 2 : vérifiez dans **IP → ARP** sur quel réseau sont réellement les antennes |
| Rien ne répond, même le routeur | Le tunnel n'est pas actif sur votre appareil | Ouvrez WireGuard et activez le tunnel |
| Une antenne répond, une autre non | L'antenne muette n'a pas de passerelle, ou a changé d'adresse | Étape 3, points 1 et 2 |
| Tout marchait, plus rien aujourd'hui | L'antenne était en DHCP et a changé d'adresse | Étape 3, point 2 : fixez son adresse |
| Winbox ne liste pas les routeurs | Normal : la découverte ne traverse pas le tunnel | Connectez-vous par adresse IP |

---

## Pour un nouveau site

1. Raccordez le routeur (page **Accès de secours**, bouton **Raccorder**).
2. Relevez le réseau des antennes (**IP → ARP** dans Winbox).
3. Déclarez-le dans la colonne **Réseau du site**.
4. Sur chaque antenne : passerelle = adresse du MikroTik, adresse fixe.

Rien d'autre n'est à faire côté serveur : le concentrateur et le pare-feu
sont réglés automatiquement.
