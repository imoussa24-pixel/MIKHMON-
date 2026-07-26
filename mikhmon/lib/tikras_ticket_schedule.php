<?php
/*
 * Generation planifiee de tickets et envoi automatique du PDF.
 *
 * Une planification decrit ce qu'il faut produire (routeur, profil, quantite,
 * format des codes) et a qui l'envoyer (courriel, WhatsApp ou Telegram), a la
 * periodicite choisie. L'execution est portee par le moteur d'automatisation
 * deja en place, donc sans dependance a un ordonnanceur externe.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');
include_once(dirname(__FILE__) . '/tikras_config_store.php');
include_once(dirname(__FILE__) . '/routeros_api.class.php');
include_once(dirname(__FILE__) . '/tikras_routeros.php');
include_once(dirname(__FILE__) . '/tikras_ticket_pdf.php');
include_once(dirname(__FILE__) . '/tikras_notify.php');

if (!function_exists('tikras_sched_table')) {
  function tikras_sched_table()
  {
    if (!tikras_storage_available()) {
      return false;
    }
    $pdo = tikras_storage_pdo();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS ticket_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NOT NULL DEFAULT '',
        session TEXT NOT NULL,
        profile TEXT NOT NULL,
        server TEXT NOT NULL DEFAULT 'all',
        quantity INTEGER NOT NULL DEFAULT 10,
        user_mode TEXT NOT NULL DEFAULT 'vc',
        code_length INTEGER NOT NULL DEFAULT 6,
        code_chars TEXT NOT NULL DEFAULT 'mix',
        prefix TEXT NOT NULL DEFAULT '',
        time_limit TEXT NOT NULL DEFAULT '',
        data_limit TEXT NOT NULL DEFAULT '0',
        comment TEXT NOT NULL DEFAULT '',
        share_radius INTEGER NOT NULL DEFAULT 0,
        with_qr INTEGER NOT NULL DEFAULT 1,
        logo_file TEXT NOT NULL DEFAULT '',
        channel TEXT NOT NULL DEFAULT 'email',
        target TEXT NOT NULL DEFAULT '',
        frequency TEXT NOT NULL DEFAULT 'daily',
        hour INTEGER NOT NULL DEFAULT 8,
        weekday INTEGER NOT NULL DEFAULT 1,
        monthday INTEGER NOT NULL DEFAULT 1,
        enabled INTEGER NOT NULL DEFAULT 1,
        last_run_at TEXT NOT NULL DEFAULT '',
        last_status TEXT NOT NULL DEFAULT '',
        last_error TEXT NOT NULL DEFAULT '',
        total_runs INTEGER NOT NULL DEFAULT 0,
        total_tickets INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
      )
    ");
    /*
     * Colonnes ajoutees apres coup: on les cree si la table existe deja,
     * afin de ne pas perdre les planifications enregistrees.
     */
    $colonnes = array();
    foreach ($pdo->query('PRAGMA table_info(ticket_schedules)')->fetchAll() as $colonne) {
      $colonnes[] = isset($colonne['name']) ? $colonne['name'] : '';
    }
    if (!in_array('with_qr', $colonnes)) {
      $pdo->exec("ALTER TABLE ticket_schedules ADD COLUMN with_qr INTEGER NOT NULL DEFAULT 1");
    }
    if (!in_array('logo_file', $colonnes)) {
      $pdo->exec("ALTER TABLE ticket_schedules ADD COLUMN logo_file TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('share_radius', $colonnes)) {
      $pdo->exec("ALTER TABLE ticket_schedules ADD COLUMN share_radius INTEGER NOT NULL DEFAULT 0");
    }
    return true;
  }
}

if (!function_exists('tikras_sched_logos')) {
  /* Images deposees par l'utilisateur, proposees comme logo du ticket. */
  function tikras_sched_logos()
  {
    $dossier = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img';
    $fichiers = array();
    if (is_dir($dossier)) {
      foreach (scandir($dossier) as $fichier) {
        if (preg_match('/\.(png|jpe?g|gif)$/i', $fichier)) {
          $fichiers[] = $fichier;
        }
      }
    }
    sort($fichiers);
    return $fichiers;
  }
}

if (!function_exists('tikras_sched_chemin_logo')) {
  function tikras_sched_chemin_logo($fichier)
  {
    $fichier = basename((string) $fichier);
    if ($fichier == '' || !preg_match('/\.(png|jpe?g|gif)$/i', $fichier)) {
      return '';
    }
    $chemin = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $fichier;
    return is_file($chemin) ? $chemin : '';
  }
}

if (!function_exists('tikras_sched_frequences')) {
  function tikras_sched_frequences()
  {
    return array(
      'hourly' => 'Toutes les heures',
      'daily' => 'Chaque jour',
      'weekly' => 'Chaque semaine',
      'monthly' => 'Chaque mois',
    );
  }
}

if (!function_exists('tikras_sched_jours')) {
  function tikras_sched_jours()
  {
    return array(1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi',
      5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche');
  }
}

if (!function_exists('tikras_sched_canaux')) {
  function tikras_sched_canaux()
  {
    return array(
      'email' => 'Courriel',
      'whatsapp' => 'WhatsApp',
      'telegram' => 'Telegram',
      'none' => 'Aucun envoi (PDF conserve)',
    );
  }
}

if (!function_exists('tikras_sched_liste')) {
  function tikras_sched_liste()
  {
    if (!tikras_sched_table()) {
      return array();
    }
    try {
      return tikras_storage_pdo()->query('SELECT * FROM ticket_schedules ORDER BY enabled DESC, label')->fetchAll();
    } catch (Exception $e) {
      return array();
    }
  }
}

if (!function_exists('tikras_sched_get')) {
  function tikras_sched_get($id)
  {
    if (!tikras_sched_table()) {
      return null;
    }
    try {
      $stmt = tikras_storage_pdo()->prepare('SELECT * FROM ticket_schedules WHERE id = ?');
      $stmt->execute(array((int) $id));
      $row = $stmt->fetch();
      return $row === false ? null : $row;
    } catch (Exception $e) {
      return null;
    }
  }
}

if (!function_exists('tikras_sched_enregistrer')) {
  function tikras_sched_enregistrer($champs)
  {
    if (!tikras_sched_table()) {
      return array('ok' => false, 'message' => 'Base locale indisponible.');
    }

    $session = (string) tikras_array_get($champs, 'session', '');
    $profile = (string) tikras_array_get($champs, 'profile', '');
    $canal = (string) tikras_array_get($champs, 'channel', 'email');
    $cible = trim((string) tikras_array_get($champs, 'target', ''));

    if ($session == '' || $profile == '') {
      return array('ok' => false, 'message' => 'Routeur et profil sont obligatoires.');
    }
    if ($canal == 'email' && !filter_var($cible, FILTER_VALIDATE_EMAIL)) {
      return array('ok' => false, 'message' => 'Adresse de courriel invalide.');
    }
    if ($canal == 'whatsapp' && !preg_match('/^[0-9]{8,15}$/', $cible)) {
      return array('ok' => false, 'message' => 'Numero WhatsApp invalide : chiffres uniquement, avec indicatif.');
    }
    $quantite = max(1, min(1000, (int) tikras_array_get($champs, 'quantity', 10)));

    $donnees = array(
      'label' => (string) tikras_array_get($champs, 'label', ''),
      'session' => $session,
      'profile' => $profile,
      'server' => (string) tikras_array_get($champs, 'server', 'all'),
      'quantity' => $quantite,
      'user_mode' => tikras_array_get($champs, 'user_mode', 'vc') == 'up' ? 'up' : 'vc',
      'code_length' => max(3, min(8, (int) tikras_array_get($champs, 'code_length', 6))),
      'code_chars' => (string) tikras_array_get($champs, 'code_chars', 'mix'),
      'prefix' => substr((string) tikras_array_get($champs, 'prefix', ''), 0, 6),
      'time_limit' => (string) tikras_array_get($champs, 'time_limit', ''),
      'data_limit' => (string) tikras_array_get($champs, 'data_limit', '0'),
      'comment' => (string) tikras_array_get($champs, 'comment', ''),
      'share_radius' => tikras_array_get($champs, 'share_radius', '0') == '1' ? 1 : 0,
      'with_qr' => tikras_array_get($champs, 'with_qr', '1') == '1' ? 1 : 0,
      'logo_file' => basename((string) tikras_array_get($champs, 'logo_file', '')),
      'channel' => $canal,
      'target' => $cible,
      'frequency' => (string) tikras_array_get($champs, 'frequency', 'daily'),
      'hour' => max(0, min(23, (int) tikras_array_get($champs, 'hour', 8))),
      'weekday' => max(1, min(7, (int) tikras_array_get($champs, 'weekday', 1))),
      'monthday' => max(1, min(28, (int) tikras_array_get($champs, 'monthday', 1))),
      'enabled' => tikras_array_get($champs, 'enabled', '1') == '1' ? 1 : 0,
    );

    try {
      $pdo = tikras_storage_pdo();
      $now = tikras_storage_now();
      $id = (int) tikras_array_get($champs, 'id', 0);
      if ($id > 0) {
        $sql = 'UPDATE ticket_schedules SET label=?, session=?, profile=?, server=?, quantity=?,
          user_mode=?, code_length=?, code_chars=?, prefix=?, time_limit=?, data_limit=?, comment=?,
          share_radius=?, with_qr=?, logo_file=?, channel=?, target=?, frequency=?, hour=?, weekday=?,
          monthday=?, enabled=?, updated_at=? WHERE id=?';
        $valeurs = array_values($donnees);
        $valeurs[] = $now;
        $valeurs[] = $id;
        $pdo->prepare($sql)->execute($valeurs);
      } else {
        $sql = 'INSERT INTO ticket_schedules (label, session, profile, server, quantity, user_mode,
          code_length, code_chars, prefix, time_limit, data_limit, comment, share_radius, with_qr,
          logo_file, channel, target, frequency, hour, weekday, monthday, enabled, created_at, updated_at)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
        $valeurs = array_values($donnees);
        $valeurs[] = $now;
        $valeurs[] = $now;
        $pdo->prepare($sql)->execute($valeurs);
        $id = (int) $pdo->lastInsertId();
      }
      tikras_storage_audit('schedule.save', 'schedule', (string) $id, $session,
        'Planification de tickets enregistree.', $donnees);
      return array('ok' => true, 'message' => 'Planification enregistree.', 'id' => $id);
    } catch (Exception $e) {
      return array('ok' => false, 'message' => 'Enregistrement impossible : ' . $e->getMessage());
    }
  }
}

if (!function_exists('tikras_sched_supprimer')) {
  function tikras_sched_supprimer($id)
  {
    if (!tikras_sched_table()) {
      return false;
    }
    try {
      $stmt = tikras_storage_pdo()->prepare('DELETE FROM ticket_schedules WHERE id = ?');
      $stmt->execute(array((int) $id));
      tikras_storage_audit('schedule.delete', 'schedule', (string) $id, '', 'Planification supprimee.');
      return true;
    } catch (Exception $e) {
      return false;
    }
  }
}

if (!function_exists('tikras_sched_est_due')) {
  /*
   * Une planification est due quand son creneau est atteint et qu'elle n'a pas
   * deja tourne dans la periode courante. On compare des periodes plutot que
   * des delais pour rester juste meme si le serveur a ete arrete un moment.
   */
  function tikras_sched_est_due($plan, $maintenant = null)
  {
    if ((int) $plan['enabled'] !== 1) {
      return false;
    }
    $maintenant = $maintenant === null ? time() : (int) $maintenant;
    $heure = (int) $plan['hour'];
    $frequence = (string) $plan['frequency'];
    $dernier = (string) $plan['last_run_at'];
    $dernierTs = $dernier != '' ? strtotime($dernier) : 0;

    if ($frequence == 'hourly') {
      return $dernierTs < strtotime(date('Y-m-d H:00:00', $maintenant));
    }

    if ((int) date('G', $maintenant) < $heure) {
      return false;
    }

    $debutJour = strtotime(date('Y-m-d', $maintenant) . ' ' . sprintf('%02d:00:00', $heure));
    if ($frequence == 'daily') {
      return $dernierTs < $debutJour;
    }
    if ($frequence == 'weekly') {
      if ((int) date('N', $maintenant) !== (int) $plan['weekday']) {
        return false;
      }
      return $dernierTs < $debutJour;
    }
    if ($frequence == 'monthly') {
      if ((int) date('j', $maintenant) !== (int) $plan['monthday']) {
        return false;
      }
      return $dernierTs < $debutJour;
    }
    return false;
  }
}

if (!function_exists('tikras_sched_marquer')) {
  function tikras_sched_marquer($id, $statut, $erreur, $tickets)
  {
    if (!tikras_sched_table()) {
      return;
    }
    try {
      $stmt = tikras_storage_pdo()->prepare('UPDATE ticket_schedules SET last_run_at=?, last_status=?,
        last_error=?, total_runs=total_runs+1, total_tickets=total_tickets+?, updated_at=? WHERE id=?');
      $now = tikras_storage_now();
      $stmt->execute(array($now, $statut, $erreur, (int) $tickets, $now, (int) $id));
    } catch (Exception $e) {
      // L'echec du marquage ne doit pas interrompre le cycle d'automatisation.
    }
  }
}

if (!function_exists('tikras_sched_codes')) {
  function tikras_sched_codes($jeu)
  {
    $jeux = array(
      'lower' => 'abcdefghijkmnprstuvwxyz',
      'upper' => 'ABCDEFGHJKLMNPRSTUVWXYZ',
      'upplow' => 'ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz',
      'mix' => '23456789abcdefghijkmnprstuvwxyz',
      'mix1' => '23456789ABCDEFGHJKLMNPRSTUVWXYZ',
      'mix2' => '23456789ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz',
      'num' => '23456789',
    );
    return isset($jeux[$jeu]) ? $jeux[$jeu] : $jeux['mix'];
  }
}

if (!function_exists('tikras_sched_aleatoire')) {
  function tikras_sched_aleatoire($longueur, $jeu)
  {
    $total = strlen($jeu);
    $valeur = '';
    for ($i = 0; $i < $longueur; $i++) {
      if (function_exists('random_int')) {
        try {
          $index = random_int(0, $total - 1);
        } catch (Exception $e) {
          $index = rand(0, $total - 1);
        }
      } else {
        $index = rand(0, $total - 1);
      }
      $valeur .= $jeu[$index];
    }
    return $valeur;
  }
}

if (!function_exists('tikras_sched_executer')) {
  /*
   * Produit les tickets sur le routeur, fabrique le PDF et l'envoie.
   * Retourne un rapport utilisable par le journal comme par l'interface.
   */
  function tikras_sched_executer($plan, $data)
  {
    $rapport = array('ok' => false, 'crees' => 0, 'statut' => '', 'erreur' => '', 'pdf' => '');
    $session = (string) $plan['session'];

    if (!isset($data[$session])) {
      $rapport['erreur'] = 'Routeur absent de la configuration.';
      $rapport['statut'] = 'routeur_inconnu';
      return $rapport;
    }

    $hote = tikras_cfg_value($data, $session, 1, '!', '');
    $utilisateur = tikras_cfg_value($data, $session, 2, '@|@', '');
    $motDePasse = decrypt(tikras_cfg_value($data, $session, 3, '#|#', ''));
    $hotspot = tikras_cfg_value($data, $session, 4, '%', $session);
    $dns = tikras_cfg_value($data, $session, 5, '^', '');
    $devise = tikras_cfg_value($data, $session, 6, '&', '');

    $api = tikras_routeros_create();
    $api->attempts = 1;
    $api->timeout = 8;
    if (!tikras_routeros_connect($api, $hote, $utilisateur, $motDePasse, $session, array('timeout' => 8, 'force' => true))) {
      $rapport['erreur'] = 'Routeur injoignable (' . $hote . ').';
      $rapport['statut'] = 'routeur_injoignable';
      return $rapport;
    }

    /*
     * Prix et validite sont portes par le script "on-login" du profil, comme
     * pour la generation manuelle: le ticket imprime affiche ainsi les memes
     * informations commerciales.
     */
    $prixProfil = '';
    $validiteProfil = '';
    $lignesProfil = tikras_routeros_comm($api, '/ip/hotspot/user/profile/print',
      array('?name' => (string) $plan['profile'], '.proplist' => 'name,on-login'), array());
    if (is_array($lignesProfil) && isset($lignesProfil[0]['on-login'])) {
      $partsProfil = explode(',', (string) $lignesProfil[0]['on-login']);
      $prixProfil = isset($partsProfil[2]) ? trim($partsProfil[2]) : '';
      $validiteProfil = isset($partsProfil[3]) ? trim($partsProfil[3]) : '';
    }

    // Codes existants: on evite de recreer un identifiant deja attribue.
    $existants = array();
    $lignes = tikras_routeros_comm($api, '/ip/hotspot/user/print', array('.proplist' => 'name'), array());
    if (is_array($lignes)) {
      foreach ($lignes as $ligne) {
        if (isset($ligne['name'])) {
          $existants[(string) $ligne['name']] = true;
        }
      }
    }

    $jeu = tikras_sched_codes((string) $plan['code_chars']);
    $longueur = (int) $plan['code_length'];
    $prefixe = (string) $plan['prefix'];
    $quantite = (int) $plan['quantity'];
    $commentaire = $plan['comment'] != ''
      ? (string) $plan['comment']
      : 'auto-' . date('d.m.y') . '-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $plan['label']);

    $tickets = array();
    $tentatives = 0;
    while (count($tickets) < $quantite && $tentatives < ($quantite * 40 + 200)) {
      $tentatives++;
      $identifiant = $prefixe . tikras_sched_aleatoire($longueur, $jeu);
      if (isset($existants[$identifiant])) {
        continue;
      }
      $existants[$identifiant] = true;
      $motDePasseTicket = $plan['user_mode'] == 'up'
        ? tikras_sched_aleatoire($longueur, '23456789')
        : $identifiant;
      $tickets[] = array('username' => $identifiant, 'password' => $motDePasseTicket);
    }

    $crees = 0;
    $premiereErreur = '';
    // Le PDF ne doit contenir que les tickets reellement acceptes par le
    // routeur, sinon il distribuerait des codes inexistants.
    $ticketsCrees = array();
    foreach ($tickets as $ticket) {
      $arguments = array(
        'server' => (string) $plan['server'],
        'name' => $ticket['username'],
        'password' => $ticket['password'],
        'profile' => (string) $plan['profile'],
        'comment' => $commentaire,
      );
      /*
       * RouterOS refuse une limite vide ("invalid time value"): on n'envoie ces
       * arguments que s'ils portent une valeur, sans quoi aucun ticket n'est cree.
       */
      $limiteTemps = trim((string) $plan['time_limit']);
      if ($limiteTemps != '' && $limiteTemps != '0') {
        $arguments['limit-uptime'] = $limiteTemps;
      }
      $limiteDonnees = trim((string) $plan['data_limit']);
      if ($limiteDonnees != '' && $limiteDonnees != '0') {
        $arguments['limit-bytes-total'] = $limiteDonnees;
      }

      $reponse = $api->comm('/ip/hotspot/user/add', $arguments);
      // Une erreur RouterOS arrive sous la cle "!trap", pas en position zero.
      if (is_array($reponse) && isset($reponse['!trap'])) {
        if ($premiereErreur == '' && isset($reponse['!trap'][0]['message'])) {
          $premiereErreur = (string) $reponse['!trap'][0]['message'];
        }
        continue;
      }
      $crees++;
      $ticketsCrees[] = $ticket;
    }
    tikras_routeros_disconnect($api);

    if ($crees < 1) {
      $rapport['erreur'] = "Aucun ticket cree sur le routeur"
        . ($premiereErreur != '' ? ' : ' . $premiereErreur : '.');
      $rapport['statut'] = 'creation_echouee';
      return $rapport;
    }

    /*
     * Partage RADIUS: les memes codes sont enregistres sur le serveur, ce qui
     * les rend valables sur tous les routeurs raccordes. Un echec ici n'annule
     * pas les tickets deja crees sur le routeur, il est seulement signale.
     */
    if ((int) tikras_array_get($plan, 'share_radius', 0) === 1) {
      if (!function_exists('tikras_rad_enregistrer_tickets')) {
        include_once(dirname(__FILE__) . '/tikras_radius_server.php');
      }
      if (function_exists('tikras_rad_enregistrer_tickets')) {
        $partage = tikras_rad_enregistrer_tickets($ticketsCrees, array(
          'profile' => (string) $plan['profile'],
          'time_limit' => (string) $plan['time_limit'],
          'data_limit' => (string) $plan['data_limit'],
        ));
        $rapport['radius'] = $partage;
        if (!$partage['ok']) {
          $rapport['erreur'] = trim($rapport['erreur'] . ' Partage RADIUS : ' . $partage['message']);
        }
      }
    }
    if ($premiereErreur != '') {
      $rapport['erreur'] = 'Certains tickets ont ete refuses : ' . $premiereErreur;
    }
    $rapport['crees'] = $crees;

    // PDF au meme format que la generation manuelle.
    $nomPdf = 'auto-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $session) . '-' . date('Ymd-Hi') . '.pdf';
    $relatif = 'share/tickets/' . $nomPdf;
    $chemin = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'share' . DIRECTORY_SEPARATOR . 'tickets' . DIRECTORY_SEPARATOR . $nomPdf;
    $meta = array(
      'hotspotname' => $hotspot,
      'dnsname' => $dns,
      'profile' => (string) $plan['profile'],
      'validity' => $validiteProfil,
      'timelimit' => (string) $plan['time_limit'],
      'datalimit' => (string) $plan['data_limit'],
      'price' => $prixProfil,
      'currency' => $devise,
      // Options d'impression retenues pour cette planification.
      'qrcode' => (int) tikras_array_get($plan, 'with_qr', 1) === 1,
      'qrtexte' => ($dns != '' ? 'http://' . $dns . ' ' : ''),
      'logo' => tikras_sched_chemin_logo(tikras_array_get($plan, 'logo_file', '')),
    );
    if (!tikras_ticket_pdf_generate($chemin, $ticketsCrees, $meta)) {
      $rapport['erreur'] = 'Tickets crees mais PDF non genere.';
      $rapport['statut'] = 'pdf_echoue';
      return $rapport;
    }
    $rapport['pdf'] = $relatif;

    $canal = (string) $plan['channel'];
    if ($canal == 'none') {
      $rapport['ok'] = true;
      $rapport['statut'] = 'pdf_conserve';
      return $rapport;
    }

    $url = tikras_ticket_public_url($relatif);
    $message = $hotspot . "\r\n" . $crees . " tickets generes automatiquement"
      . "\r\nProfil : " . $plan['profile']
      . ($plan['label'] != '' ? "\r\nPlanification : " . $plan['label'] : '')
      . "\r\nPDF : " . $url;

    if ($canal == 'email') {
      $envoye = tikras_notify_send_ticket_mail($session, (string) $plan['target'],
        'Tickets ' . $hotspot . ' - ' . $plan['profile'], $message, $chemin, $nomPdf);
      $rapport['ok'] = (bool) $envoye;
      $rapport['statut'] = $envoye ? 'courriel_envoye' : 'courriel_non_envoye';
      if (!$envoye) {
        $rapport['erreur'] = 'Tickets et PDF prets, envoi du courriel impossible.';
      }
    } elseif ($canal == 'telegram') {
      $envoye = tikras_notify_send_telegram($session, $message, $url, (string) $plan['target']);
      $rapport['ok'] = (bool) $envoye;
      $rapport['statut'] = $envoye ? 'telegram_envoye' : 'telegram_non_envoye';
      if (!$envoye) {
        $rapport['erreur'] = 'Tickets et PDF prets, envoi Telegram impossible.';
      }
    } else {
      $envoye = tikras_notify_send_whatsapp($session, (string) $plan['target'], $message, $url);
      $rapport['ok'] = (bool) $envoye;
      $rapport['statut'] = $envoye ? 'whatsapp_envoye' : 'whatsapp_non_envoye';
      if (!$envoye) {
        $rapport['erreur'] = 'Tickets et PDF prets, envoi WhatsApp impossible.';
      }
    }
    return $rapport;
  }
}

if (!function_exists('tikras_sched_cycle')) {
  /* Passe en revue les planifications et execute celles qui sont dues. */
  function tikras_sched_cycle($data, $forcerId = 0)
  {
    $bilan = array('examinees' => 0, 'executees' => 0, 'tickets' => 0, 'details' => array());
    foreach (tikras_sched_liste() as $plan) {
      $bilan['examinees']++;
      $forcee = ($forcerId > 0 && (int) $plan['id'] === (int) $forcerId);
      if (!$forcee && !tikras_sched_est_due($plan)) {
        continue;
      }
      $rapport = tikras_sched_executer($plan, $data);
      tikras_sched_marquer($plan['id'], $rapport['statut'], $rapport['erreur'], $rapport['crees']);
      $bilan['executees']++;
      $bilan['tickets'] += $rapport['crees'];
      $bilan['details'][] = array(
        'id' => (int) $plan['id'],
        'label' => (string) $plan['label'],
        'statut' => $rapport['statut'],
        'tickets' => $rapport['crees'],
        'erreur' => $rapport['erreur'],
      );
      tikras_storage_audit('schedule.run', 'schedule', (string) $plan['id'], (string) $plan['session'],
        'Execution planifiee : ' . $rapport['statut'], $rapport);
    }
    return $bilan;
  }
}
?>
