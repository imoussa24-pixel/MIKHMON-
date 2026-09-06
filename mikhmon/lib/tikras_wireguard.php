<?php
/*
 * Raccordement des routeurs au concentrateur WireGuard.
 *
 * Le conteneur applicatif est volontairement isole du systeme: il ne peut ni
 * lire /etc/wireguard ni piloter l'interface reseau. Le travail est donc
 * partage:
 *   - ici (application): generation des cles, ecriture d'une demande dans le
 *     volume partage, puis configuration du routeur par l'API MikroTik;
 *   - agent de l'hote (deploy/wireguard-agent.sh): declaration du pair cote
 *     serveur et rechargement de l'interface.
 * Aucun privilege supplementaire n'est ainsi accorde a l'application web.
 */
include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');
include_once(dirname(__FILE__) . '/tikras_config_store.php');
include_once(dirname(__FILE__) . '/routeros_api.class.php');

if (!function_exists('tikras_wg_dir')) {
  function tikras_wg_dir()
  {
    $base = getenv('TIKRAS_DATA_DIR');
    if ($base === false || trim((string) $base) == '') {
      $base = tikras_config_local_dir();
    }
    $dir = rtrim((string) $base, "\\/") . DIRECTORY_SEPARATOR . 'wireguard';
    foreach (array($dir, $dir . DIRECTORY_SEPARATOR . 'demandes', $dir . DIRECTORY_SEPARATOR . 'etat') as $chemin) {
      if (!is_dir($chemin)) {
        @mkdir($chemin, 0770, true);
      }
    }
    return $dir;
  }
}

if (!function_exists('tikras_wg_config')) {
  function tikras_wg_config()
  {
    $etat = tikras_wg_dir() . DIRECTORY_SEPARATOR . 'etat' . DIRECTORY_SEPARATOR . 'hub.json';
    $defaut = array(
      'endpoint' => (string) getenv('TIKRAS_WG_ENDPOINT'),
      'port' => 51820,
      'cle_publique' => '',
      'reseau' => '10.200.0.0/16',
      'ip_hub' => '10.200.0.1',
      'prefixe' => '10.200.1.',
      'disponible' => false,
    );
    if (is_file($etat)) {
      $lu = json_decode((string) @file_get_contents($etat), true);
      if (is_array($lu)) {
        $defaut = array_merge($defaut, $lu);
        $defaut['disponible'] = isset($lu['cle_publique']) && $lu['cle_publique'] != '';
      }
    }
    return $defaut;
  }
}

if (!function_exists('tikras_wg_generer_cles')) {
  /* Paire X25519 au format WireGuard (clamping puis derivation). */
  function tikras_wg_generer_cles()
  {
    if (!extension_loaded('sodium')) {
      return null;
    }
    $privee = random_bytes(32);
    $privee[0] = chr(ord($privee[0]) & 248);
    $privee[31] = chr((ord($privee[31]) & 127) | 64);
    $publique = sodium_crypto_scalarmult_base($privee);
    return array(
      'privee' => base64_encode($privee),
      'publique' => base64_encode($publique),
    );
  }
}

if (!function_exists('tikras_wg_table')) {
  function tikras_wg_table()
  {
    if (!tikras_storage_available()) {
      return false;
    }
    $pdo = tikras_storage_pdo();
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS wireguard_peers (
        session TEXT PRIMARY KEY,
        tunnel_ip TEXT NOT NULL,
        public_key TEXT NOT NULL,
        private_key TEXT NOT NULL,
        state TEXT NOT NULL DEFAULT 'en_attente',
        last_error TEXT NOT NULL DEFAULT '',
        router_applied INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
      )
    ");

    /*
     * Colonnes ajoutees apres coup: une installation existante conserve sa
     * table, on complete donc au lieu de la recreer.
     *
     *   peer_type   'routeur' ou 'appareil'. Un ordinateur ou un telephone
     *               rejoint le meme tunnel qu'un routeur, mais on ne lui
     *               applique aucune configuration RouterOS.
     *   lan_subnet  reseau local situe derriere un routeur (ses antennes,
     *               ses points d'acces). Sans lui, le tunnel ne mene qu'au
     *               routeur lui-meme et rien de ce qu'il dessert n'est
     *               joignable.
     *   label       nom lisible, l'identifiant technique etant impose par
     *               la session du routeur.
     */
    $colonnes = array();
    foreach ($pdo->query('PRAGMA table_info(wireguard_peers)') as $colonne) {
      $colonnes[(string) $colonne['name']] = true;
    }
    $ajouts = array(
      'peer_type' => "ALTER TABLE wireguard_peers ADD COLUMN peer_type TEXT NOT NULL DEFAULT 'routeur'",
      'lan_subnet' => "ALTER TABLE wireguard_peers ADD COLUMN lan_subnet TEXT NOT NULL DEFAULT ''",
      'label' => "ALTER TABLE wireguard_peers ADD COLUMN label TEXT NOT NULL DEFAULT ''",
    );
    foreach ($ajouts as $nom => $sql) {
      if (!isset($colonnes[$nom])) {
        try {
          $pdo->exec($sql);
        } catch (Exception $e) {
          // Une colonne deja presente ne doit pas empecher l'application de
          // demarrer: on poursuit avec le schema en place.
        }
      }
    }
    return true;
  }
}

if (!function_exists('tikras_wg_liste')) {
  function tikras_wg_liste()
  {
    if (!tikras_wg_table()) {
      return array();
    }
    try {
      $rows = tikras_storage_pdo()->query('SELECT * FROM wireguard_peers')->fetchAll();
      $map = array();
      foreach ($rows as $row) {
        $map[(string) $row['session']] = $row;
      }
      return $map;
    } catch (Exception $e) {
      return array();
    }
  }
}

if (!function_exists('tikras_wg_prochaine_ip')) {
  function tikras_wg_prochaine_ip()
  {
    $config = tikras_wg_config();
    $prefixe = $config['prefixe'];
    $utilisees = array();
    foreach (tikras_wg_liste() as $row) {
      $ip = (string) $row['tunnel_ip'];
      if (strpos($ip, $prefixe) === 0) {
        $utilisees[(int) substr($ip, strlen($prefixe))] = true;
      }
    }
    for ($i = 1; $i <= 254; $i++) {
      if (!isset($utilisees[$i])) {
        return $prefixe . $i;
      }
    }
    return '';
  }
}

if (!function_exists('tikras_wg_deposer_demande')) {
  /* Depose la demande lue par l'agent de l'hote. */
  function tikras_wg_deposer_demande($session, $publique, $ip)
  {
    $fichier = tikras_wg_dir() . DIRECTORY_SEPARATOR . 'demandes' . DIRECTORY_SEPARATOR
      . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $session) . '.json';
    // Les cles WireGuard contiennent des "/": on evite leur echappement pour
    // qu'une lecture simple du fichier ne produise pas une cle invalide.
    $contenu = json_encode(array(
      'session' => (string) $session,
      'public_key' => (string) $publique,
      'tunnel_ip' => (string) $ip,
      'demande_at' => date('c'),
    ), JSON_UNESCAPED_SLASHES);
    return @file_put_contents($fichier, $contenu) !== false;
  }
}

if (!function_exists('tikras_wg_etat_pair')) {
  /* Etat publie par l'agent (handshake, volumes). */
  function tikras_wg_etat_pair($session)
  {
    $fichier = tikras_wg_dir() . DIRECTORY_SEPARATOR . 'etat' . DIRECTORY_SEPARATOR . 'pairs.json';
    if (!is_file($fichier)) {
      return array();
    }
    $lu = json_decode((string) @file_get_contents($fichier), true);
    if (!is_array($lu)) {
      return array();
    }
    foreach (tikras_wg_liste() as $nom => $row) {
      if ($nom === $session && isset($lu[(string) $row['public_key']])) {
        return $lu[(string) $row['public_key']];
      }
    }
    return array();
  }
}

if (!function_exists('tikras_wg_configurer_routeur')) {
  /*
   * Applique la configuration sur le routeur par l'API MikroTik.
   * Uniquement additif: aucune regle ni service existant n'est modifie.
   */
  function tikras_wg_configurer_routeur($api, $clePrivee, $ipTunnel, $config)
  {
    $rapport = array('ok' => false, 'etapes' => array(), 'erreur' => '');

    $existantes = $api->comm('/interface/wireguard/print', array('.proplist' => '.id,name', '?name' => 'wg-tikras'));
    if (is_array($existantes) && isset($existantes[0]['.id'])) {
      $idInterface = $existantes[0]['.id'];
      $api->comm('/interface/wireguard/set', array('.id' => $idInterface, 'private-key' => $clePrivee));
      $rapport['etapes'][] = 'Interface existante mise a jour';
    } else {
      $r = $api->comm('/interface/wireguard/add', array(
        'name' => 'wg-tikras',
        'listen-port' => '13231',
        'private-key' => $clePrivee,
        'comment' => 'TIKRAS IT',
      ));
      if (is_array($r) && isset($r[0]['message'])) {
        $rapport['erreur'] = 'Interface: ' . $r[0]['message'];
        return $rapport;
      }
      $rapport['etapes'][] = 'Interface wg-tikras creee';
    }

    $adresses = $api->comm('/ip/address/print', array('.proplist' => '.id,address', '?interface' => 'wg-tikras'));
    $adresseVoulue = $ipTunnel . '/16';
    $adressePresente = false;
    if (is_array($adresses)) {
      foreach ($adresses as $adresse) {
        if (isset($adresse['address']) && $adresse['address'] == $adresseVoulue) {
          $adressePresente = true;
        }
      }
    }
    if (!$adressePresente) {
      $r = $api->comm('/ip/address/add', array(
        'address' => $adresseVoulue,
        'interface' => 'wg-tikras',
        'comment' => 'TIKRAS IT',
      ));
      if (is_array($r) && isset($r[0]['message'])) {
        $rapport['erreur'] = 'Adresse: ' . $r[0]['message'];
        return $rapport;
      }
      $rapport['etapes'][] = 'Adresse ' . $adresseVoulue . ' attribuee';
    }

    // Pair vers le serveur: on repart de zero pour eviter les doublons.
    $pairs = $api->comm('/interface/wireguard/peers/print', array('.proplist' => '.id,interface'));
    if (is_array($pairs)) {
      foreach ($pairs as $pair) {
        if (isset($pair['interface']) && $pair['interface'] == 'wg-tikras' && isset($pair['.id'])) {
          $api->comm('/interface/wireguard/peers/remove', array('.id' => $pair['.id']));
        }
      }
    }
    $r = $api->comm('/interface/wireguard/peers/add', array(
      'interface' => 'wg-tikras',
      'public-key' => $config['cle_publique'],
      'endpoint-address' => $config['endpoint'],
      'endpoint-port' => (string) $config['port'],
      'allowed-address' => $config['reseau'],
      'persistent-keepalive' => '25s',
      'comment' => 'Serveur TIKRAS',
    ));
    if (is_array($r) && isset($r[0]['message'])) {
      $rapport['erreur'] = 'Pair: ' . $r[0]['message'];
      return $rapport;
    }
    // La creation echoue silencieusement si une cle est vide: on verifie.
    $verif = $api->comm('/interface/wireguard/peers/print', array('.proplist' => '.id,interface'));
    $pairPresent = false;
    if (is_array($verif)) {
      foreach ($verif as $pair) {
        if (isset($pair['interface']) && $pair['interface'] == 'wg-tikras') {
          $pairPresent = true;
        }
      }
    }
    if (!$pairPresent) {
      $rapport['erreur'] = "Le pair n'a pas ete cree: verifiez la cle publique du serveur.";
      return $rapport;
    }
    $rapport['etapes'][] = 'Serveur declare comme pair';

    /*
     * La regle doit passer avant le premier "drop" de la chaine input, sinon
     * le trafic du tunnel serait rejete.
     */
    $regles = $api->comm('/ip/firewall/filter/print', array('.proplist' => '.id,comment,action,in-interface', '?chain' => 'input'));
    $regleExistante = false;
    $premiereRegle = '';
    if (is_array($regles)) {
      foreach ($regles as $index => $regle) {
        if ($index === 0 && isset($regle['.id'])) {
          $premiereRegle = $regle['.id'];
        }
        if (isset($regle['in-interface']) && $regle['in-interface'] == 'wg-tikras') {
          $regleExistante = true;
        }
      }
    }
    if (!$regleExistante) {
      $args = array(
        'chain' => 'input',
        'in-interface' => 'wg-tikras',
        'action' => 'accept',
        'comment' => 'TIKRAS IT: acces par le tunnel',
      );
      if ($premiereRegle != '') {
        $args['place-before'] = $premiereRegle;
      }
      $r = $api->comm('/ip/firewall/filter/add', $args);
      if (is_array($r) && isset($r[0]['message'])) {
        $rapport['erreur'] = 'Pare-feu: ' . $r[0]['message'];
        return $rapport;
      }
      $rapport['etapes'][] = 'Acces autorise dans le pare-feu';
    }

    $rapport['ok'] = true;
    return $rapport;
  }
}

if (!function_exists('tikras_wg_enregistrer')) {
  function tikras_wg_enregistrer($session, $ip, $cles, $etat, $erreur, $applique)
  {
    if (!tikras_wg_table()) {
      return false;
    }
    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare('INSERT OR REPLACE INTO wireguard_peers
        (session, tunnel_ip, public_key, private_key, state, last_error, router_applied, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, COALESCE((SELECT created_at FROM wireguard_peers WHERE session = ?), ?), ?)');
      $now = tikras_storage_now();
      $stmt->execute(array(
        $session, $ip, $cles['publique'], $cles['privee'], $etat, $erreur,
        $applique ? 1 : 0, $session, $now, $now,
      ));
      return true;
    } catch (Exception $e) {
      return false;
    }
  }
}

if (!function_exists('tikras_wg_preparer')) {
  /*
   * Prepare un routeur qui n'est pas encore joignable depuis le serveur:
   * typiquement un routeur neuf, configure sur le reseau local.
   * On reserve son adresse et on produit le script a coller sur place; des
   * que le tunnel monte, le routeur devient joignable et peut etre ajoute.
   */
  function tikras_wg_preparer($session)
  {
    $rapport = array('ok' => false, 'message' => '', 'ip' => '', 'script' => '');
    $session = trim((string) $session);
    $config = tikras_wg_config();

    if ($session == '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $session)) {
      $rapport['message'] = 'Nom invalide : lettres, chiffres, point, tiret et souligne uniquement.';
      return $rapport;
    }
    if (!$config['disponible'] || $config['endpoint'] == '') {
      $rapport['message'] = "Le concentrateur n'est pas encore pret sur le serveur.";
      return $rapport;
    }

    $connus = tikras_wg_liste();
    if (isset($connus[$session]) && $connus[$session]['private_key'] != '') {
      $ip = (string) $connus[$session]['tunnel_ip'];
      $cles = array(
        'privee' => (string) $connus[$session]['private_key'],
        'publique' => (string) $connus[$session]['public_key'],
      );
    } else {
      $cles = tikras_wg_generer_cles();
      if ($cles === null) {
        $rapport['message'] = 'Generation de cles indisponible sur ce serveur.';
        return $rapport;
      }
      $ip = tikras_wg_prochaine_ip();
      if ($ip == '') {
        $rapport['message'] = 'Plus aucune adresse libre dans la plage du tunnel.';
        return $rapport;
      }
    }

    if (!tikras_wg_deposer_demande($session, $cles['publique'], $ip)) {
      $rapport['message'] = "Impossible d'ecrire la demande pour le serveur.";
      return $rapport;
    }
    tikras_wg_enregistrer($session, $ip, $cles, 'prepare', '', false);
    tikras_storage_audit('wireguard.preparation', 'router', $session, $session,
      'Adresse de tunnel reservee pour un nouveau routeur.', array('tunnel_ip' => $ip));

    $rapport['ok'] = true;
    $rapport['ip'] = $ip;
    $rapport['script'] = tikras_wg_script_routeur($cles['privee'], $ip, $config);
    $rapport['message'] = 'Adresse ' . $ip . ' reservee. Collez le script ci-dessous dans le routeur.';
    return $rapport;
  }
}

if (!function_exists('tikras_wg_script_routeur')) {
  /* Commandes RouterOS 7 a coller sur un routeur non encore joignable. */
  function tikras_wg_script_routeur($clePrivee, $ipTunnel, $config)
  {
    $lignes = array(
      '/interface/wireguard/add name=wg-tikras listen-port=13231 private-key="' . $clePrivee . '" comment="TIKRAS IT"',
      '/ip/address/add address=' . $ipTunnel . '/16 interface=wg-tikras comment="TIKRAS IT"',
      '/interface/wireguard/peers/add interface=wg-tikras public-key="' . $config['cle_publique'] . '"'
        . ' endpoint-address=' . $config['endpoint'] . ' endpoint-port=' . $config['port']
        . ' allowed-address=' . $config['reseau'] . ' persistent-keepalive=25s comment="Serveur TIKRAS"',
      '/ip/firewall/filter/add chain=input in-interface=wg-tikras action=accept comment="TIKRAS IT: acces par le tunnel" place-before=0',
    );
    return implode("\n", $lignes);
  }
}

if (!function_exists('tikras_wg_ajouter_routeur_mikhmon')) {
  /*
   * Enregistre le routeur dans TIKRAS IT en utilisant son adresse de tunnel.
   * Utilise apres qu'un routeur prepare a joint le concentrateur.
   */
  function tikras_wg_ajouter_routeur_mikhmon(&$data, $session, $ip, $utilisateur, $motDePasse, $hotspot, $dns, $devise)
  {
    if (!is_array($data)) {
      $data = array();
    }
    $session = trim((string) $session);
    if ($session == '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $session)) {
      return array('ok' => false, 'message' => 'Nom de routeur invalide.');
    }
    if ($utilisateur == '') {
      return array('ok' => false, 'message' => "L'identifiant du routeur est obligatoire.");
    }

    $hotspot = $hotspot != '' ? $hotspot : $session;
    $data[$session] = array(
      '1' => $session . '!' . $ip,
      $session . '@|@' . $utilisateur,
      $session . '#|#' . encrypt($motDePasse),
      $session . '%' . $hotspot,
      $session . '^' . $dns,
      $session . '&' . ($devise != '' ? $devise : 'CFA'),
      $session . '*10',
      $session . '(1',
      $session . ')',
      $session . '=10',
      $session . '@!@disable',
    );

    if (!tikras_config_write_all($data)) {
      return array('ok' => false, 'message' => "Enregistrement impossible : verifiez les droits d'ecriture.");
    }
    tikras_storage_audit('wireguard.ajout_routeur', 'router', $session, $session,
      'Routeur ajoute a TIKRAS IT via son adresse de tunnel.', array('tunnel_ip' => $ip));
    return array('ok' => true, 'message' => 'Routeur ' . $session . ' ajoute avec l\'adresse ' . $ip . '.');
  }
}

if (!function_exists('tikras_wg_raccorder')) {
  /*
   * Raccorde un routeur de bout en bout: cles, demande a l'agent, puis
   * configuration du routeur. Retourne un rapport lisible par l'interface.
   */
  function tikras_wg_raccorder($session, $api)
  {
    $rapport = array('ok' => false, 'message' => '', 'etapes' => array(), 'ip' => '');
    $config = tikras_wg_config();

    if (!$config['disponible'] || $config['endpoint'] == '') {
      $rapport['message'] = "Le concentrateur n'est pas encore pret sur le serveur.";
      return $rapport;
    }

    $connus = tikras_wg_liste();
    if (isset($connus[$session]) && $connus[$session]['private_key'] != '') {
      $ip = (string) $connus[$session]['tunnel_ip'];
      $cles = array(
        'privee' => (string) $connus[$session]['private_key'],
        'publique' => (string) $connus[$session]['public_key'],
      );
      $rapport['etapes'][] = 'Cles existantes reutilisees';
    } else {
      $cles = tikras_wg_generer_cles();
      if ($cles === null) {
        $rapport['message'] = 'Generation de cles indisponible sur ce serveur.';
        return $rapport;
      }
      $ip = tikras_wg_prochaine_ip();
      if ($ip == '') {
        $rapport['message'] = 'Plus aucune adresse libre dans la plage du tunnel.';
        return $rapport;
      }
      $rapport['etapes'][] = 'Adresse ' . $ip . ' reservee';
    }
    $rapport['ip'] = $ip;

    if (!tikras_wg_deposer_demande($session, $cles['publique'], $ip)) {
      $rapport['message'] = "Impossible d'ecrire la demande pour le serveur.";
      return $rapport;
    }
    $rapport['etapes'][] = 'Demande transmise au serveur';

    $resultat = tikras_wg_configurer_routeur($api, $cles['privee'], $ip, $config);
    foreach ($resultat['etapes'] as $etape) {
      $rapport['etapes'][] = $etape;
    }

    if (!$resultat['ok']) {
      tikras_wg_enregistrer($session, $ip, $cles, 'erreur', $resultat['erreur'], false);
      $rapport['message'] = $resultat['erreur'];
      return $rapport;
    }

    tikras_wg_enregistrer($session, $ip, $cles, 'actif', '', true);
    tikras_storage_audit('wireguard.raccord', 'router', $session, $session,
      'Routeur raccorde au tunnel WireGuard.', array('tunnel_ip' => $ip));

    $rapport['ok'] = true;
    $rapport['message'] = 'Routeur raccorde. Adresse dans le tunnel : ' . $ip;
    return $rapport;
  }
}
?>
