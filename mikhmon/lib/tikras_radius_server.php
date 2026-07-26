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
    $adresse = trim((string) $adresse);
    if ($session == '' || $adresse == '') {
      return array('ok' => false, 'message' => 'Routeur ou adresse manquante.');
    }
    try {
      $pdo = tikras_rad_pdo();
      $stmt = $pdo->prepare('SELECT id, secret FROM nas WHERE shortname = ?');
      $stmt->execute(array($session));
      $existant = $stmt->fetch();
      $secret = ($existant !== false && $existant['secret'] != '') ? $existant['secret'] : tikras_rad_secret();

      if ($existant !== false) {
        $stmt = $pdo->prepare('UPDATE nas SET nasname = ?, secret = ?, description = ? WHERE id = ?');
        $stmt->execute(array($adresse, $secret, $description, $existant['id']));
      } else {
        $stmt = $pdo->prepare('INSERT INTO nas (nasname, shortname, type, secret, description) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(array($adresse, $session, 'mikrotik', $secret, $description));
      }
      tikras_storage_audit('radius.nas_ajout', 'router', $session, $session,
        'Routeur autorise sur le serveur RADIUS.', array('adresse' => $adresse));
      return array('ok' => true, 'message' => 'Routeur autorisé.', 'secret' => $secret, 'adresse' => $adresse);
    } catch (Exception $e) {
      return array('ok' => false, 'message' => $e->getMessage());
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

if (!function_exists('tikras_rad_script_routeur')) {
  /*
   * Commandes RouterOS pour raccorder un routeur au serveur.
   * Uniquement additif: le profil Hotspot vise passe en RADIUS, sans toucher
   * aux autres reglages ni aux tickets deja presents sur le routeur.
   */
  function tikras_rad_script_routeur($adresseServeur, $secret, $profilHotspot, $avecPpp = false)
  {
    $lignes = array(
      '/radius/add service=hotspot' . ($avecPpp ? ',ppp' : '')
        . ' address=' . $adresseServeur
        . ' secret="' . $secret . '"'
        . ' authentication-port=1812 accounting-port=1813 timeout=3s comment="TIKRAS RADIUS"',
      '/radius/incoming/set accept=yes',
    );
    if ($profilHotspot != '') {
      $lignes[] = '/ip/hotspot/profile/set [find name="' . $profilHotspot . '"] use-radius=yes radius-accounting=yes';
    }
    if ($avecPpp) {
      $lignes[] = '/ppp/aaa/set use-radius=yes accounting=yes';
    }
    return implode("\n", $lignes);
  }
}
?>
