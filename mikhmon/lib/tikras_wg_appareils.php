<?php
/*
 * Appareils raccordes au hub WireGuard.
 *
 * Le hub ne reliait que des routeurs. Or l'exploitation quotidienne se fait
 * depuis un ordinateur ou un telephone: pour joindre un routeur avec Winbox,
 * il fallait jusqu'ici passer par ZeroTier ou se trouver sur le reseau local
 * du site. Un appareil raccorde au hub atteint directement tous les routeurs
 * et tout ce qu'ils desservent.
 *
 * Un appareil occupe une plage d'adresses distincte de celle des routeurs:
 * on lit ainsi d'un coup d'oeil qui est un equipement du parc et qui est un
 * poste de travail, et une erreur de manipulation sur les appareils ne peut
 * pas empieter sur les adresses des routeurs en service.
 */

include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');
include_once(dirname(__FILE__) . '/tikras_wireguard.php');

if (!function_exists('tikras_wga_prefixe')) {
  /* Les appareils vivent en 10.200.2.x, les routeurs en 10.200.1.x. */
  function tikras_wga_prefixe()
  {
    $config = tikras_wg_config();
    $prefixeRouteurs = isset($config['prefixe']) ? (string) $config['prefixe'] : '10.200.1.';
    // On derive la plage des appareils de celle des routeurs, pour rester
    // coherent si le reseau du hub venait a changer.
    $morceaux = explode('.', rtrim($prefixeRouteurs, '.'));
    if (count($morceaux) === 3) {
      $morceaux[2] = (string) ((int) $morceaux[2] + 1);
      return implode('.', $morceaux) . '.';
    }
    return '10.200.2.';
  }
}

if (!function_exists('tikras_wga_liste')) {
  /* Appareils enregistres, du plus recent au plus ancien. */
  function tikras_wga_liste()
  {
    if (!tikras_wg_table()) {
      return array();
    }
    try {
      $stmt = tikras_storage_pdo()->prepare(
        "SELECT * FROM wireguard_peers WHERE peer_type = 'appareil' ORDER BY created_at DESC"
      );
      $stmt->execute();
      return $stmt->fetchAll();
    } catch (Exception $e) {
      return array();
    }
  }
}

if (!function_exists('tikras_wga_adresses_occupees')) {
  /*
   * Adresses reellement presentes sur le tunnel, publiees par l'agent.
   *
   * La base ne connait que les pairs crees depuis le panneau. Un pair ajoute
   * a la main - c'etait le cas du premier ordinateur raccorde - lui est
   * invisible, et l'application lui aurait repris son adresse, coupant son
   * acces au parc sans prevenir.
   */
  function tikras_wga_adresses_occupees()
  {
    $fichier = tikras_wg_dir() . DIRECTORY_SEPARATOR . 'etat' . DIRECTORY_SEPARATOR . 'adresses_tunnel.json';
    if (!is_file($fichier)) {
      return array();
    }
    $lu = json_decode((string) @file_get_contents($fichier), true);
    if (!is_array($lu)) {
      return array();
    }
    $adresses = array();
    foreach ($lu as $reseau) {
      $reseau = trim((string) $reseau);
      if ($reseau === '') {
        continue;
      }
      $adresse = strpos($reseau, '/') !== false ? substr($reseau, 0, strpos($reseau, '/')) : $reseau;
      if (filter_var($adresse, FILTER_VALIDATE_IP)) {
        $adresses[$adresse] = true;
      }
    }
    return $adresses;
  }
}

if (!function_exists('tikras_wga_prochaine_ip')) {
  function tikras_wga_prochaine_ip()
  {
    $prefixe = tikras_wga_prefixe();
    $utilisees = array();
    foreach (tikras_wga_liste() as $ligne) {
      $ip = (string) $ligne['tunnel_ip'];
      if (strpos($ip, $prefixe) === 0) {
        $utilisees[(int) substr($ip, strlen($prefixe))] = true;
      }
    }
    // Puis celles reellement occupees sur l'interface, connues ou non de la base.
    foreach (tikras_wga_adresses_occupees() as $adresse => $vrai) {
      if (strpos($adresse, $prefixe) === 0) {
        $utilisees[(int) substr($adresse, strlen($prefixe))] = true;
      }
    }
    /*
     * On demarre a 2: le hub occupe la premiere adresse de son reseau, et
     * laisser .1 libre evite toute confusion avec lui.
     */
    for ($i = 2; $i <= 254; $i++) {
      if (!isset($utilisees[$i])) {
        return $prefixe . $i;
      }
    }
    return '';
  }
}

if (!function_exists('tikras_wga_identifiant')) {
  /*
   * Identifiant technique tire du nom donne par l'utilisateur. La table est
   * indexee par cette valeur, elle ne peut donc contenir que des caracteres
   * simples et doit rester unique.
   */
  function tikras_wga_identifiant($nom, $type = 'ordinateur')
  {
    $base = strtolower(trim((string) $nom));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base);
    $base = trim((string) $base, '-');
    if ($base === '') {
      $base = 'appareil';
    }
    // Le prefixe porte le type: la marche a suivre affichee plus tard en
    // depend, et il se lit sans avoir a relire la base.
    $base = ($type === 'telephone' ? 'tel-' : 'app-') . substr($base, 0, 28);

    $existants = tikras_wg_liste();
    if (!isset($existants[$base])) {
      return $base;
    }
    for ($i = 2; $i <= 99; $i++) {
      if (!isset($existants[$base . '-' . $i])) {
        return $base . '-' . $i;
      }
    }
    return $base . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
  }
}

if (!function_exists('tikras_wga_creer')) {
  /*
   * Cree un appareil et depose sa demande de raccordement pour l'agent.
   *
   * La cle privee est generee ici puis remise a l'utilisateur dans le fichier
   * de configuration: c'est le seul moyen de fournir une configuration prete
   * a l'emploi a quelqu'un qui n'a pas WireGuard en ligne de commande. Elle
   * reste dans la base du serveur, qui contient deja les acces des routeurs.
   */
  function tikras_wga_creer($nom, $type = 'ordinateur')
  {
    $nom = trim((string) $nom);
    if ($nom === '') {
      return array('ok' => false, 'message' => "Donnez un nom à l'appareil.");
    }
    $type = ($type === 'telephone') ? 'telephone' : 'ordinateur';
    if (!tikras_wg_table()) {
      return array('ok' => false, 'message' => 'Base locale indisponible.');
    }

    $config = tikras_wg_config();
    if (empty($config['disponible'])) {
      return array('ok' => false, 'message' => "Le concentrateur n'est pas encore actif sur le serveur.");
    }

    $ip = tikras_wga_prochaine_ip();
    if ($ip === '') {
      return array('ok' => false, 'message' => 'Plus aucune adresse disponible pour un appareil.');
    }

    $cles = tikras_wg_generer_cles();
    if (!is_array($cles) || empty($cles['publique']) || empty($cles['privee'])) {
      return array('ok' => false, 'message' => "Impossible de generer les cles (extension sodium absente ?).");
    }

    $identifiant = tikras_wga_identifiant($nom, $type);
    $maintenant = date('Y-m-d H:i:s');

    try {
      $stmt = tikras_storage_pdo()->prepare(
        'INSERT INTO wireguard_peers(session, tunnel_ip, public_key, private_key, state,
           last_error, router_applied, created_at, updated_at, peer_type, lan_subnet, label)
         VALUES(?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)'
      );
      $stmt->execute(array(
        $identifiant, $ip, $cles['publique'], $cles['privee'], 'en_attente',
        '', $maintenant, $maintenant, 'appareil', '', $nom,
      ));
    } catch (Exception $e) {
      return array('ok' => false, 'message' => $e->getMessage());
    }

    if (!tikras_wg_deposer_demande($identifiant, $cles['publique'], $ip)) {
      return array(
        'ok' => false,
        'message' => "Appareil enregistre, mais la demande n'a pas pu etre deposee pour l'agent.",
      );
    }

    tikras_storage_audit('wireguard.appareil_ajout', 'device', $identifiant, $nom,
      'Appareil raccorde au concentrateur.', array('ip' => $ip));

    return array(
      'ok' => true,
      'message' => 'Appareil « ' . $nom . ' » ajouté. Son adresse dans le tunnel est ' . $ip . '.',
      'identifiant' => $identifiant,
      'ip' => $ip,
    );
  }
}

if (!function_exists('tikras_wga_supprimer')) {
  function tikras_wga_supprimer($identifiant)
  {
    $identifiant = trim((string) $identifiant);
    if ($identifiant === '' || !tikras_wg_table()) {
      return array('ok' => false, 'message' => 'Appareil inconnu.');
    }
    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare("SELECT label, tunnel_ip, public_key FROM wireguard_peers WHERE session = ? AND peer_type = 'appareil' LIMIT 1");
      $stmt->execute(array($identifiant));
      $ligne = $stmt->fetch();
      if (!$ligne) {
        return array('ok' => false, 'message' => 'Appareil inconnu.');
      }
      $pdo->prepare('DELETE FROM wireguard_peers WHERE session = ?')->execute(array($identifiant));

      /*
       * L'agent retire le pair de l'interface: sans cela l'appareil garderait
       * son acces au parc alors qu'il a ete revoque dans le panneau.
       */
      $fichier = tikras_wg_dir() . DIRECTORY_SEPARATOR . 'demandes' . DIRECTORY_SEPARATOR
        . preg_replace('/[^A-Za-z0-9_.-]/', '_', $identifiant) . '.retrait.json';
      @file_put_contents($fichier, json_encode(array(
        'session' => $identifiant,
        'public_key' => (string) $ligne['public_key'],
        'action' => 'retrait',
        'demande_at' => date('c'),
      ), JSON_UNESCAPED_SLASHES));

      tikras_storage_audit('wireguard.appareil_retrait', 'device', $identifiant,
        (string) $ligne['label'], 'Appareil retire du concentrateur.', array());
      return array('ok' => true, 'message' => 'Appareil retiré du concentrateur.');
    } catch (Exception $e) {
      return array('ok' => false, 'message' => $e->getMessage());
    }
  }
}

if (!function_exists('tikras_wga_configuration')) {
  /*
   * Fichier de configuration a installer sur l'appareil.
   *
   * "AllowedIPs" ne contient que le reseau du hub et les reseaux locaux
   * declares: l'appareil continue d'utiliser sa connexion habituelle pour
   * tout le reste. Router l'integralite du trafic par le serveur ralentirait
   * la navigation et consommerait la bande passante du VPS sans profit.
   */
  function tikras_wga_configuration($identifiant)
  {
    if (!tikras_wg_table()) {
      return '';
    }
    try {
      $stmt = tikras_storage_pdo()->prepare("SELECT * FROM wireguard_peers WHERE session = ? AND peer_type = 'appareil' LIMIT 1");
      $stmt->execute(array((string) $identifiant));
      $appareil = $stmt->fetch();
    } catch (Exception $e) {
      return '';
    }
    if (!$appareil) {
      return '';
    }

    $config = tikras_wg_config();
    $reseaux = array((string) $config['reseau']);
    foreach (tikras_wg_liste() as $pair) {
      $lan = isset($pair['lan_subnet']) ? trim((string) $pair['lan_subnet']) : '';
      if ($lan !== '' && !in_array($lan, $reseaux, true)) {
        $reseaux[] = $lan;
      }
    }

    $lignes = array();
    $lignes[] = '# Configuration TIKRAS pour « ' . (string) $appareil['label'] . ' »';
    $lignes[] = '# A importer dans l\'application WireGuard de l\'appareil.';
    $lignes[] = '# Ce fichier contient une cle privee: ne le partagez pas.';
    $lignes[] = '';
    $lignes[] = '[Interface]';
    $lignes[] = 'PrivateKey = ' . (string) $appareil['private_key'];
    $lignes[] = 'Address = ' . (string) $appareil['tunnel_ip'] . '/32';
    $lignes[] = '';
    $lignes[] = '[Peer]';
    $lignes[] = 'PublicKey = ' . (string) $config['cle_publique'];
    $lignes[] = 'Endpoint = ' . (string) $config['endpoint'] . ':' . (int) $config['port'];
    $lignes[] = 'AllowedIPs = ' . implode(', ', $reseaux);
    // Le hub est derriere un pare-feu: sans trafic regulier, la traversee se
    // referme et l'appareil devient injoignable depuis le parc.
    $lignes[] = 'PersistentKeepalive = 25';
    $lignes[] = '';

    return implode("\n", $lignes);
  }
}

if (!function_exists('tikras_wga_configuration_compacte')) {
  /*
   * Meme configuration, reduite a l'essentiel pour tenir dans un code QR.
   *
   * L'encodeur couvre 213 caracteres; la version commentee en depasse 400.
   * On retire donc les commentaires, les espaces autour des signes egal et
   * les lignes vides - le format accepte les deux ecritures. Les reseaux
   * locaux des sites sont omis: ils allongent la ligne "AllowedIPs" sans
   * limite connue d'avance, et le reseau du concentrateur suffit a joindre
   * les routeurs. Qui a besoin des sites prend le fichier.
   */
  function tikras_wga_configuration_compacte($identifiant)
  {
    if (!tikras_wg_table()) {
      return '';
    }
    try {
      $stmt = tikras_storage_pdo()->prepare("SELECT * FROM wireguard_peers WHERE session = ? AND peer_type = 'appareil' LIMIT 1");
      $stmt->execute(array((string) $identifiant));
      $appareil = $stmt->fetch();
    } catch (Exception $e) {
      return '';
    }
    if (!$appareil) {
      return '';
    }

    $config = tikras_wg_config();
    $lignes = array(
      '[Interface]',
      'PrivateKey=' . (string) $appareil['private_key'],
      'Address=' . (string) $appareil['tunnel_ip'] . '/32',
      '[Peer]',
      'PublicKey=' . (string) $config['cle_publique'],
      'Endpoint=' . (string) $config['endpoint'] . ':' . (int) $config['port'],
      'AllowedIPs=' . (string) $config['reseau'],
    );
    $texte = implode("\n", $lignes);

    // Au-dela de la capacite, mieux vaut aucun QR qu'un code tronque.
    return strlen($texte) <= 213 ? $texte : '';
  }
}

if (!function_exists('tikras_wga_ouvrir_site')) {
  /*
   * Autorise le trafic du tunnel a traverser le routeur vers son reseau local.
   *
   * Un routeur raccorde repond deja au tunnel: RouterOS accepte ce qui lui est
   * destine ("input"). Mais ce qui le TRAVERSE ("forward") reste bloque par
   * defaut, si bien que les antennes et points d'acces du site restent
   * injoignables alors que le routeur, lui, repond. C'est la difference qui
   * fait croire a un probleme de configuration des antennes.
   *
   * La regle est placee en tete de la chaine: RouterOS evalue dans l'ordre, et
   * ajoutee a la fin elle serait precedee par les regles de rejet du modele
   * par defaut, donc sans effet.
   */
  function tikras_wga_ouvrir_site($api, $interfaceWg = '')
  {
    if (!is_object($api)) {
      return array('ok' => false, 'message' => 'Routeur injoignable.');
    }

    // Nom reel de l'interface WireGuard sur ce routeur.
    if ($interfaceWg === '') {
      $interfaces = $api->comm('/interface/wireguard/print', array('.proplist' => 'name'));
      if (is_array($interfaces) && isset($interfaces[0]['name'])) {
        $interfaceWg = (string) $interfaces[0]['name'];
      }
    }
    if ($interfaceWg === '') {
      return array('ok' => false, 'message' => "Aucune interface WireGuard sur ce routeur : raccordez-le d'abord.");
    }

    $commentaire = 'TIKRAS acces site';

    // Deja fait ? On ne veut pas empiler des regles identiques.
    $existantes = $api->comm('/ip/firewall/filter/print', array(
      '?chain' => 'forward',
      '?comment' => $commentaire,
    ));
    if (is_array($existantes) && count($existantes) > 0) {
      return array('ok' => true, 'message' => "L'accès au réseau du site était déjà ouvert.", 'deja' => true);
    }

    // Position de la premiere regle, pour inserer avant elle.
    $premieres = $api->comm('/ip/firewall/filter/print', array(
      '?chain' => 'forward',
      '.proplist' => '.id',
    ));
    $parametres = array(
      'chain' => 'forward',
      'in-interface' => $interfaceWg,
      'action' => 'accept',
      'comment' => $commentaire,
    );
    if (is_array($premieres) && isset($premieres[0]['.id'])) {
      $parametres['place-before'] = (string) $premieres[0]['.id'];
    }

    $reponse = $api->comm('/ip/firewall/filter/add', $parametres);
    if (is_array($reponse) && isset($reponse['!trap'][0]['message'])) {
      return array('ok' => false, 'message' => 'Refus du routeur : ' . $reponse['!trap'][0]['message']);
    }

    return array(
      'ok' => true,
      'message' => "Accès ouvert : les équipements du site sont joignables depuis vos appareils.",
      'interface' => $interfaceWg,
    );
  }
}

if (!function_exists('tikras_wga_definir_lan')) {
  /*
   * Declare le reseau local situe derriere un routeur.
   *
   * C'est ce qui rend joignables les antennes et points d'acces raccordes a
   * ce routeur: sans cette declaration, le tunnel ne mene qu'au routeur.
   */
  function tikras_wga_definir_lan($session, $sousReseau)
  {
    $session = trim((string) $session);
    $sousReseau = trim((string) $sousReseau);
    if ($session === '' || !tikras_wg_table()) {
      return array('ok' => false, 'message' => 'Routeur inconnu.');
    }

    if ($sousReseau !== '') {
      if (!preg_match('#^(\d{1,3}\.){3}\d{1,3}/\d{1,2}$#', $sousReseau)) {
        return array('ok' => false, 'message' => "Indiquez un réseau au format 192.168.88.0/24.");
      }
      list($adresse, $masque) = explode('/', $sousReseau);
      if (!filter_var($adresse, FILTER_VALIDATE_IP) || (int) $masque < 8 || (int) $masque > 32) {
        return array('ok' => false, 'message' => "Réseau invalide : vérifiez l'adresse et le masque.");
      }
      /*
       * Un reseau qui engloberait celui du hub detournerait vers ce routeur
       * le trafic destine aux autres pairs, coupant l'acces a tout le parc.
       */
      $config = tikras_wg_config();
      $hub = explode('/', (string) $config['reseau']);
      if (isset($hub[0]) && strpos($sousReseau, substr($hub[0], 0, strrpos($hub[0], '.0') !== false ? strrpos($hub[0], '.0') : 6)) === 0
          && (int) $masque <= (int) (isset($hub[1]) ? $hub[1] : 16)) {
        return array('ok' => false, 'message' => "Ce réseau recouvre celui du tunnel : choisissez le réseau local du site.");
      }
    }

    try {
      $stmt = tikras_storage_pdo()->prepare(
        "UPDATE wireguard_peers SET lan_subnet = ?, updated_at = ? WHERE session = ? AND peer_type = 'routeur'"
      );
      $stmt->execute(array($sousReseau, date('Y-m-d H:i:s'), $session));

      // L'agent recalcule les reseaux autorises du pair.
      $ligne = tikras_storage_pdo()->prepare('SELECT public_key, tunnel_ip FROM wireguard_peers WHERE session = ? LIMIT 1');
      $ligne->execute(array($session));
      $pair = $ligne->fetch();
      if ($pair) {
        $fichier = tikras_wg_dir() . DIRECTORY_SEPARATOR . 'demandes' . DIRECTORY_SEPARATOR
          . preg_replace('/[^A-Za-z0-9_.-]/', '_', $session) . '.json';
        @file_put_contents($fichier, json_encode(array(
          'session' => $session,
          'public_key' => (string) $pair['public_key'],
          'tunnel_ip' => (string) $pair['tunnel_ip'],
          'lan_subnet' => $sousReseau,
          'demande_at' => date('c'),
        ), JSON_UNESCAPED_SLASHES));
      }

      tikras_storage_audit('wireguard.lan', 'router', $session, $session,
        $sousReseau === '' ? 'Reseau local retire.' : 'Reseau local declare.', array('lan' => $sousReseau));
      return array(
        'ok' => true,
        'message' => $sousReseau === ''
          ? 'Réseau local retiré.'
          : 'Réseau ' . $sousReseau . ' déclaré : les équipements de ce site deviennent joignables.',
      );
    } catch (Exception $e) {
      return array('ok' => false, 'message' => $e->getMessage());
    }
  }
}
