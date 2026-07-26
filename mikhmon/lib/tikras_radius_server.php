<?php
/*
 * Serveur RADIUS TIKRAS.
 *
 * Les tickets partages entre plusieurs routeurs sont enregistres ici, dans la
 * base lue directement par le serveur RADIUS, au lieu d'etre recopies sur
 * chaque routeur ou confies a User Manager. Un ticket vaut alors pour tous les
 * routeurs raccordes, et sa consommation est comptabilisee au meme endroit.
 *
 * Seuls les routeurs explicitement autorises ici peuvent interroger le
 * serveur: il n'est ni utile ni souhaitable d'y raccorder tout le parc.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');
include_once(dirname(__FILE__) . '/tikras_config_store.php');
include_once(dirname(__FILE__) . '/routeros_api.class.php');

if (!function_exists('tikras_rad_chemin')) {
  function tikras_rad_chemin()
  {
    $base = getenv('TIKRAS_DATA_DIR');
    if ($base === false || trim((string) $base) == '') {
      $base = tikras_config_local_dir();
    }
    return rtrim((string) $base, "\\/") . DIRECTORY_SEPARATOR . 'radius' . DIRECTORY_SEPARATOR . 'radius.sqlite';
  }
}

if (!function_exists('tikras_rad_disponible')) {
  function tikras_rad_disponible()
  {
    return is_file(tikras_rad_chemin()) && class_exists('PDO')
      && in_array('sqlite', PDO::getAvailableDrivers());
  }
}

if (!function_exists('tikras_rad_pdo')) {
  function tikras_rad_pdo()
  {
    static $pdo = null;
    if ($pdo instanceof PDO) {
      return $pdo;
    }
    if (!tikras_rad_disponible()) {
      throw new RuntimeException("Le serveur RADIUS n'est pas encore installe.");
    }
    $pdo = new PDO('sqlite:' . tikras_rad_chemin());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
  }
}

if (!function_exists('tikras_rad_etat')) {
  /* Etat du serveur pour l'interface: base, tickets, routeurs, sessions. */
  function tikras_rad_etat()
  {
    $etat = array(
      'disponible' => false,
      'chemin' => tikras_rad_chemin(),
      'tickets' => 0,
      'routeurs' => 0,
      'sessions_actives' => 0,
      'sessions_total' => 0,
      'erreur' => '',
    );
    if (!tikras_rad_disponible()) {
      $etat['erreur'] = "Base RADIUS absente : lancez deploy/radius-installer.sh sur le serveur.";
      return $etat;
    }
    try {
      $pdo = tikras_rad_pdo();
      $etat['disponible'] = true;
      $etat['tickets'] = (int) $pdo->query('SELECT COUNT(DISTINCT username) FROM radcheck')->fetchColumn();
      $etat['routeurs'] = (int) $pdo->query('SELECT COUNT(*) FROM nas')->fetchColumn();
      $etat['sessions_actives'] = (int) $pdo->query('SELECT COUNT(*) FROM radacct WHERE acctstoptime IS NULL')->fetchColumn();
      $etat['sessions_total'] = (int) $pdo->query('SELECT COUNT(*) FROM radacct')->fetchColumn();
    } catch (Exception $e) {
      $etat['erreur'] = $e->getMessage();
    }
    return $etat;
  }
}

if (!function_exists('tikras_rad_routeurs')) {
  /* Routeurs autorises a interroger le serveur, indexes par adresse. */
  function tikras_rad_routeurs()
  {
    if (!tikras_rad_disponible()) {
      return array();
    }
    try {
      $lignes = tikras_rad_pdo()->query('SELECT id, nasname, shortname, secret, description FROM nas')->fetchAll();
      $map = array();
      foreach ($lignes as $ligne) {
        $map[(string) $ligne['shortname']] = $ligne;
      }
      return $map;
    } catch (Exception $e) {
      return array();
    }
  }
}

if (!function_exists('tikras_rad_secret')) {
  /*
   * Secret partage propre a chaque routeur: un secret unique evite qu'un
   * routeur compromis ne permette d'usurper les autres.
   */
  function tikras_rad_secret()
  {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $secret = '';
    for ($i = 0; $i < 24; $i++) {
      $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $secret;
  }
}

if (!function_exists('tikras_rad_autoriser_routeur')) {
  /*
   * Autorise un routeur a interroger le serveur. L'adresse enregistree est
   * celle par laquelle le serveur voit le routeur; le secret est conserve
   * s'il existe deja, pour ne pas invalider une configuration en place.
   */
  function tikras_rad_autoriser_routeur($session, $adresse, $description = '')
  {
    if (!tikras_rad_disponible()) {
      return array('ok' => false, 'message' => "Serveur RADIUS indisponible.");
    }
    $session = trim((string) $session);
    if ($session == '') {
      return array('ok' => false, 'message' => 'Routeur manquant.');
    }

    /*
     * Un routeur peut atteindre le serveur par plusieurs chemins (ZeroTier,
     * tunnel WireGuard), et l'adresse source vue par le serveur change avec
     * le chemin emprunte. Le serveur rejette toute adresse qu'il ne connait
     * pas, en la signalant seulement dans son journal: on enregistre donc
     * chacune des adresses possibles du routeur, sous un secret commun.
     */
    $adresses = is_array($adresse) ? $adresse : array($adresse);
    $adresses[] = tikras_rad_adresse_tunnel($session);
    $propres = array();
    foreach ($adresses as $candidate) {
      $candidate = trim((string) $candidate);
      if (strpos($candidate, ':') !== false) {
        $candidate = substr($candidate, 0, strpos($candidate, ':'));
      }
      if ($candidate != '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
        $propres[$candidate] = true;
      }
    }
    if (count($propres) < 1) {
      return array('ok' => false, 'message' => 'Aucune adresse valide pour ce routeur.');
    }

    try {
      $pdo = tikras_rad_pdo();
      $stmt = $pdo->prepare('SELECT secret FROM nas WHERE shortname = ? LIMIT 1');
      $stmt->execute(array($session));
      $ancien = $stmt->fetchColumn();
      $secret = ($ancien !== false && $ancien != '') ? $ancien : tikras_rad_secret();

      // On repart des adresses actuelles: une adresse retiree ne doit plus
      // etre acceptee.
      $stmt = $pdo->prepare('DELETE FROM nas WHERE shortname = ?');
      $stmt->execute(array($session));

      $ajout = $pdo->prepare('INSERT OR REPLACE INTO nas (nasname, shortname, type, secret, description) VALUES (?, ?, ?, ?, ?)');
      foreach (array_keys($propres) as $uneAdresse) {
        $ajout->execute(array($uneAdresse, $session, 'mikrotik', $secret, $description));
      }

      tikras_storage_audit('radius.nas_ajout', 'router', $session, $session,
        'Routeur autorise sur le serveur RADIUS.', array('adresses' => array_keys($propres)));
      return array(
        'ok' => true,
        'message' => 'Routeur autorisé (' . count($propres) . ' adresse(s) reconnue(s)).',
        'secret' => $secret,
        'adresse' => reset($adresses),
        'adresses' => array_keys($propres),
      );
    } catch (Exception $e) {
      return array('ok' => false, 'message' => $e->getMessage());
    }
  }
}

if (!function_exists('tikras_rad_adresse_tunnel')) {
  /* Adresse du routeur dans le tunnel WireGuard, s'il y est raccorde. */
  function tikras_rad_adresse_tunnel($session)
  {
    if (!tikras_storage_available()) {
      return '';
    }
    try {
      $pdo = tikras_storage_pdo();
      $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='wireguard_peers'")->fetchAll();
      if (count($tables) < 1) {
        return '';
      }
      $stmt = $pdo->prepare('SELECT tunnel_ip FROM wireguard_peers WHERE session = ? LIMIT 1');
      $stmt->execute(array((string) $session));
      $valeur = $stmt->fetchColumn();
      return $valeur === false ? '' : (string) $valeur;
    } catch (Exception $e) {
      return '';
    }
  }
}

if (!function_exists('tikras_rad_retirer_routeur')) {
  function tikras_rad_retirer_routeur($session)
  {
    if (!tikras_rad_disponible()) {
      return false;
    }
    try {
      $stmt = tikras_rad_pdo()->prepare('DELETE FROM nas WHERE shortname = ?');
      $stmt->execute(array((string) $session));
      tikras_storage_audit('radius.nas_retrait', 'router', (string) $session, (string) $session,
        'Routeur retire du serveur RADIUS.');
      return true;
    } catch (Exception $e) {
      return false;
    }
  }
}

if (!function_exists('tikras_rad_enregistrer_tickets')) {
  /*
   * Enregistre des tickets valables sur tous les routeurs autorises.
   * Les limites de temps et de volume sont posees comme attributs de reponse,
   * appliques par le routeur a la connexion.
   */
  function tikras_rad_enregistrer_tickets($tickets, $options = array())
  {
    $rapport = array('ok' => false, 'crees' => 0, 'ignores' => 0, 'message' => '');
    if (!tikras_rad_disponible()) {
      $rapport['message'] = "Serveur RADIUS indisponible.";
      return $rapport;
    }
    if (!is_array($tickets) || count($tickets) < 1) {
      $rapport['message'] = 'Aucun ticket a enregistrer.';
      return $rapport;
    }

    $profil = (string) tikras_array_get($options, 'profile', '');
    $limiteTemps = trim((string) tikras_array_get($options, 'time_limit', ''));
    $limiteVolume = trim((string) tikras_array_get($options, 'data_limit', ''));
    $debitMontant = trim((string) tikras_array_get($options, 'rate_limit', ''));

    try {
      $pdo = tikras_rad_pdo();
      $pdo->beginTransaction();
      $verif = $pdo->prepare('SELECT COUNT(*) FROM radcheck WHERE username = ?');
      $ajoutCheck = $pdo->prepare('INSERT INTO radcheck (username, attribute, op, value) VALUES (?, ?, ?, ?)');
      $ajoutReply = $pdo->prepare('INSERT INTO radreply (username, attribute, op, value) VALUES (?, ?, ?, ?)');
      $ajoutGroupe = $pdo->prepare('INSERT INTO radusergroup (username, groupname, priority) VALUES (?, ?, ?)');

      foreach ($tickets as $ticket) {
        $identifiant = isset($ticket['username']) ? trim((string) $ticket['username']) : '';
        $motDePasse = isset($ticket['password']) ? (string) $ticket['password'] : $identifiant;
        if ($identifiant == '') {
          continue;
        }
        $verif->execute(array($identifiant));
        if ((int) $verif->fetchColumn() > 0) {
          $rapport['ignores']++;
          continue;
        }

        $ajoutCheck->execute(array($identifiant, 'Cleartext-Password', ':=', $motDePasse));

        /*
         * Les limites sont des attributs de reponse, appliques par le routeur.
         * Les poser en condition de verification exigerait un module compteur
         * absent de cette installation, et ferait echouer l'authentification.
         */
        if ($limiteTemps != '' && $limiteTemps != '0') {
          $secondes = tikras_rad_duree_en_secondes($limiteTemps);
          if ($secondes > 0) {
            $ajoutReply->execute(array($identifiant, 'Session-Timeout', ':=', (string) $secondes));
          }
        }
        if ($limiteVolume != '' && $limiteVolume != '0') {
          $ajoutReply->execute(array($identifiant, 'Mikrotik-Total-Limit', ':=', $limiteVolume));
        }
        if ($debitMontant != '') {
          $ajoutReply->execute(array($identifiant, 'Mikrotik-Rate-Limit', ':=', $debitMontant));
        }
        if ($profil != '') {
          $ajoutGroupe->execute(array($identifiant, $profil, 1));
        }
        $rapport['crees']++;
      }
      $pdo->commit();
      $rapport['ok'] = true;
      $rapport['message'] = $rapport['crees'] . ' ticket(s) enregistres sur le serveur RADIUS.'
        . ($rapport['ignores'] > 0 ? ' ' . $rapport['ignores'] . ' deja present(s).' : '');
      tikras_storage_audit('radius.tickets', 'radius', $profil, '',
        'Tickets enregistres sur le serveur RADIUS.', $rapport);
    } catch (Exception $e) {
      if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
      }
      $rapport['message'] = $e->getMessage();
    }
    return $rapport;
  }
}

if (!function_exists('tikras_rad_duree_en_secondes')) {
  /* Convertit une duree RouterOS (1d, 2h30m, 45m) en secondes. */
  function tikras_rad_duree_en_secondes($valeur)
  {
    $valeur = strtolower(trim((string) $valeur));
    if ($valeur === '' || $valeur === '0') {
      return 0;
    }
    if (preg_match('/^\d+$/', $valeur)) {
      return (int) $valeur;
    }
    $total = 0;
    if (preg_match_all('/(\d+)\s*([dhms])/', $valeur, $lots, PREG_SET_ORDER)) {
      foreach ($lots as $lot) {
        $nombre = (int) $lot[1];
        switch ($lot[2]) {
          case 'd': $total += $nombre * 86400; break;
          case 'h': $total += $nombre * 3600; break;
          case 'm': $total += $nombre * 60; break;
          case 's': $total += $nombre; break;
        }
      }
    }
    return $total;
  }
}

if (!function_exists('tikras_rad_lister_tickets')) {
  function tikras_rad_lister_tickets($limite = 100)
  {
    if (!tikras_rad_disponible()) {
      return array();
    }
    try {
      $sql = 'SELECT c.username,
                MAX(CASE WHEN c.attribute = "Cleartext-Password" THEN c.value END) AS motdepasse,
                (SELECT value FROM radreply r WHERE r.username = c.username AND r.attribute = "Session-Timeout" LIMIT 1) AS duree,
                (SELECT groupname FROM radusergroup g WHERE g.username = c.username LIMIT 1) AS profil,
                (SELECT COUNT(*) FROM radacct a WHERE a.username = c.username) AS sessions,
                (SELECT MAX(acctstarttime) FROM radacct a WHERE a.username = c.username) AS derniere
              FROM radcheck c GROUP BY c.username ORDER BY c.id DESC LIMIT ?';
      $stmt = tikras_rad_pdo()->prepare($sql);
      $stmt->bindValue(1, (int) $limite, PDO::PARAM_INT);
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (Exception $e) {
      return array();
    }
  }
}

if (!function_exists('tikras_rad_supprimer_ticket')) {
  function tikras_rad_supprimer_ticket($identifiant)
  {
    if (!tikras_rad_disponible()) {
      return false;
    }
    try {
      $pdo = tikras_rad_pdo();
      foreach (array('radcheck', 'radreply', 'radusergroup') as $table) {
        $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE username = ?');
        $stmt->execute(array((string) $identifiant));
      }
      return true;
    } catch (Exception $e) {
      return false;
    }
  }
}

if (!function_exists('tikras_rad_adresse_pour_routeur')) {
  /*
   * Adresse du serveur telle que ce routeur peut la joindre.
   *
   * Le serveur porte une adresse par reseau (une par reseau ZeroTier, plus le
   * tunnel WireGuard). Indiquer la mauvaise revient a configurer un serveur
   * injoignable, sans message d'erreur cote routeur: on retient donc celle
   * dont le sous-reseau contient l'adresse du routeur.
   */
  function tikras_rad_adresse_pour_routeur($ipRouteur)
  {
    $ipRouteur = trim((string) $ipRouteur);
    if (strpos($ipRouteur, ':') !== false) {
      $ipRouteur = substr($ipRouteur, 0, strpos($ipRouteur, ':'));
    }
    $numRouteur = ip2long($ipRouteur);
    if ($numRouteur === false) {
      return '';
    }

    $base = getenv('TIKRAS_DATA_DIR');
    if ($base === false || trim((string) $base) == '') {
      $base = tikras_config_local_dir();
    }
    $fichier = rtrim((string) $base, "\\/") . DIRECTORY_SEPARATOR . 'wireguard'
      . DIRECTORY_SEPARATOR . 'etat' . DIRECTORY_SEPARATOR . 'adresses.json';
    if (!is_file($fichier)) {
      return '';
    }
    $liste = json_decode((string) @file_get_contents($fichier), true);
    if (!is_array($liste)) {
      return '';
    }

    $meilleure = '';
    $meilleurPrefixe = -1;
    foreach ($liste as $entree) {
      $cidr = isset($entree['cidr']) ? (string) $entree['cidr'] : '';
      if (strpos($cidr, '/') === false) {
        continue;
      }
      list($adresse, $prefixe) = explode('/', $cidr, 2);
      $prefixe = (int) $prefixe;
      $numServeur = ip2long($adresse);
      if ($numServeur === false || $prefixe < 1 || $prefixe > 32) {
        continue;
      }
      $masque = -1 << (32 - $prefixe);
      if (($numRouteur & $masque) === ($numServeur & $masque)) {
        // Le sous-reseau le plus precis l'emporte.
        if ($prefixe > $meilleurPrefixe) {
          $meilleurPrefixe = $prefixe;
          $meilleure = $adresse;
        }
      }
    }
    return $meilleure;
  }
}

if (!function_exists('tikras_rad_profils_actifs')) {
  /*
   * Profils Hotspot reellement utilises par un serveur du routeur.
   *
   * Basculer un profil inutilise laisse le portail authentifier localement,
   * sans que rien ne signale l'erreur: on cible donc les profils rattaches a
   * un serveur Hotspot existant.
   */
  function tikras_rad_profils_actifs($api)
  {
    $profils = array();
    if (!is_object($api)) {
      return $profils;
    }
    $serveurs = $api->comm('/ip/hotspot/print', array('.proplist' => 'name,profile'));
    if (is_array($serveurs)) {
      foreach ($serveurs as $serveur) {
        if (isset($serveur['profile']) && $serveur['profile'] != '') {
          $profils[(string) $serveur['profile']] = true;
        }
      }
    }
    return array_keys($profils);
  }
}

if (!function_exists('tikras_rad_script_routeur')) {
  /*
   * Commandes RouterOS pour raccorder un routeur au serveur.
   * Uniquement additif: le profil Hotspot vise passe en RADIUS, sans toucher
   * aux autres reglages ni aux tickets deja presents sur le routeur.
   */
  function tikras_rad_script_routeur($adresseServeur, $secret, $profilHotspot, $avecPpp = false)
  {
    // Une declaration precedente est retiree pour eviter les doublons lors
    // d'un nouveau raccordement.
    $lignes = array(
      '/radius/remove [find comment="TIKRAS RADIUS"]',
      '/radius/add service=hotspot' . ($avecPpp ? ',ppp' : '')
        . ' address=' . $adresseServeur
        . ' secret="' . $secret . '"'
        . ' authentication-port=1812 accounting-port=1813 timeout=3s comment="TIKRAS RADIUS"',
      '/radius/incoming/set accept=yes',
    );

    // Plusieurs profils peuvent etre en service sur un meme routeur.
    $profils = is_array($profilHotspot) ? $profilHotspot : ($profilHotspot != '' ? array($profilHotspot) : array());
    foreach ($profils as $profil) {
      $profil = trim((string) $profil);
      if ($profil != '') {
        $lignes[] = '/ip/hotspot/profile/set [find name="' . $profil . '"] use-radius=yes radius-accounting=yes';
      }
    }
    if ($avecPpp) {
      $lignes[] = '/ppp/aaa/set use-radius=yes accounting=yes';
    }
    return implode("\n", $lignes);
  }
}

if (!function_exists('tikras_rad_appliquer_routeur')) {
  /*
   * Applique la configuration RADIUS sur le routeur, par l'API.
   *
   * Coller un script a la main laisse passer trois erreurs qui ne se voient
   * pas: une adresse de serveur prise sur un autre reseau, un profil Hotspot
   * qu'aucun serveur n'utilise, et surtout un ancien serveur RADIUS (User
   * Manager local en 127.0.0.1) laisse en place. RouterOS interroge les
   * serveurs dans l'ordre: l'ancien repond avant le notre, ou fait patienter
   * jusqu'a expiration du delai, et le portail refuse le ticket.
   *
   * Retourne un compte-rendu detaille de ce qui a ete change.
   */
  function tikras_rad_appliquer_routeur($api, $adresseServeur, $secret, $avecPpp = false)
  {
    $rapport = array('ok' => false, 'actions' => array(), 'profils' => array(), 'message' => '');
    if (!is_object($api)) {
      $rapport['message'] = 'Routeur injoignable.';
      return $rapport;
    }
    $adresseServeur = trim((string) $adresseServeur);
    if ($adresseServeur == '' || !filter_var($adresseServeur, FILTER_VALIDATE_IP)) {
      $rapport['message'] = "Adresse du serveur inconnue pour ce routeur.";
      return $rapport;
    }

    try {
      /*
       * 1. Les declarations concurrentes. On ne supprime jamais une entree
       * etrangere: on lui retire seulement le service hotspot, en conservant
       * ses autres usages (ppp, login...). Une entree qui n'aurait plus aucun
       * service est desactivee plutot que detruite, pour rester reversible.
       */
      $existants = $api->comm('/radius/print');
      if (is_array($existants)) {
        foreach ($existants as $entree) {
          $id = tikras_array_get($entree, '.id', '');
          if ($id == '') {
            continue;
          }
          if (tikras_array_get($entree, 'comment', '') === 'TIKRAS RADIUS') {
            $api->comm('/radius/remove', array('.id' => $id));
            continue;
          }
          $services = tikras_array_get($entree, 'service', '');
          if (strpos($services, 'hotspot') === false) {
            continue;
          }
          $restants = array();
          foreach (explode(',', $services) as $service) {
            $service = trim($service);
            if ($service != '' && $service !== 'hotspot') {
              $restants[] = $service;
            }
          }
          $adresseAncienne = tikras_array_get($entree, 'address', '?');
          if (count($restants) > 0) {
            $api->comm('/radius/set', array('.id' => $id, 'service' => implode(',', $restants)));
            $rapport['actions'][] = "Ancien serveur RADIUS " . $adresseAncienne
              . " : service hotspot retiré (ses autres usages sont conservés).";
          } else {
            $api->comm('/radius/set', array('.id' => $id, 'disabled' => 'yes'));
            $rapport['actions'][] = "Ancien serveur RADIUS " . $adresseAncienne . " : désactivé.";
          }
        }
      }

      // 2. Notre serveur.
      $ajout = $api->comm('/radius/add', array(
        'service' => 'hotspot' . ($avecPpp ? ',ppp' : ''),
        'address' => $adresseServeur,
        'secret' => (string) $secret,
        'authentication-port' => '1812',
        'accounting-port' => '1813',
        'timeout' => '3s',
        'comment' => 'TIKRAS RADIUS',
      ));
      if (is_array($ajout) && isset($ajout['!trap'][0]['message'])) {
        $rapport['message'] = 'Refus du routeur : ' . $ajout['!trap'][0]['message'];
        return $rapport;
      }
      $rapport['actions'][] = "Serveur " . $adresseServeur . " déclaré sur le routeur.";
      $api->comm('/radius/incoming/set', array('accept' => 'yes'));

      // 3. Les profils reellement rattaches a un serveur Hotspot.
      $profils = tikras_rad_profils_actifs($api);
      foreach ($profils as $profil) {
        // On passe par l'identifiant interne: designer un profil par son nom
        // echoue des qu'il contient un espace, ce qui est frequent.
        $trouve = $api->comm('/ip/hotspot/profile/print', array(
          '?name' => $profil,
          '.proplist' => '.id',
        ));
        $id = (is_array($trouve) && isset($trouve[0]['.id'])) ? $trouve[0]['.id'] : '';
        if ($id == '') {
          continue;
        }
        $api->comm('/ip/hotspot/profile/set', array(
          '.id' => $id,
          'use-radius' => 'yes',
          'radius-accounting' => 'yes',
        ));
        $rapport['profils'][] = $profil;
      }
      $profils = $rapport['profils'];
      if (count($profils) > 0) {
        $rapport['actions'][] = "Profil(s) basculé(s) en RADIUS : " . implode(', ', $profils) . ".";
      } else {
        $rapport['actions'][] = "Aucun profil Hotspot actif trouvé : vérifiez que ce routeur sert bien un Hotspot.";
      }
      if ($avecPpp) {
        $api->comm('/ppp/aaa/set', array('use-radius' => 'yes', 'accounting' => 'yes'));
        $rapport['actions'][] = "PPP basculé en RADIUS.";
      }

      $rapport['ok'] = true;
      $rapport['message'] = 'Routeur configuré automatiquement.';
      return $rapport;
    } catch (Exception $e) {
      $rapport['message'] = $e->getMessage();
      return $rapport;
    }
  }
}
?>
