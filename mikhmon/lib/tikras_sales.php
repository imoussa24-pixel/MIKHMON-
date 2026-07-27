<?php
/*
 * Releve des ventes du parc.
 *
 * Chaque routeur tient lui-meme le journal de ses ventes: un script RouterOS
 * par ticket vendu, dont le nom porte les donnees separees par "-|-". Jusqu'ici
 * ce journal n'etait lu qu'a l'ouverture manuelle de la page Rapport d'un
 * routeur, si bien que la base ne connaissait les recettes que des quelques
 * routeurs visites - deux sur cent six en pratique. Impossible, dans ces
 * conditions, de savoir ce que le parc a rapporte.
 *
 * Ce module fait le meme relevé sans intervention, routeur par routeur, et le
 * range dans la meme table que la page Rapport (sales_cache): les deux
 * chemins produisent des lignes identiques, celui qui passe en dernier
 * remplace simplement les valeurs du meme mois.
 */

include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');
include_once(dirname(__FILE__) . '/tikras_config_store.php');
include_once(dirname(__FILE__) . '/routeros_api.class.php');
include_once(dirname(__FILE__) . '/tikras_routeros.php');

if (!function_exists('tikras_sales_periode_mois')) {
  /*
   * Cle du mois telle que le routeur l'ecrit dans le champ "owner" de ses
   * scripts de vente: mois sur deux chiffres colle a l'annee, "072026".
   * Verifie sur un routeur en service, qui portait 953 enregistrements sous
   * cette cle pour le mois courant.
   *
   * Le decalage permet de relire le mois precedent en debut de mois, quand
   * des ventes de fin de mois arrivent encore.
   */
  function tikras_sales_periode_mois($decalage = 0)
  {
    $ts = mktime(0, 0, 0, (int) date('n') + (int) $decalage, 1, (int) date('Y'));
    return date('mY', $ts);
  }
}

if (!function_exists('tikras_sales_lire_routeur')) {
  /*
   * Releve les ventes d'un routeur pour un mois donne.
   *
   * Retourne array('ok' => bool, 'lignes' => int, 'total' => float, 'erreur' => string).
   * La requete ne demande que le nom des scripts: sans ".proplist", RouterOS
   * renvoie aussi le code source de chaque enregistrement, ce qui represente
   * plusieurs mega-octets sur un routeur charge.
   */
  function tikras_sales_lire_routeur($data, $session, $mois = '')
  {
    $resultat = array('ok' => false, 'lignes' => 0, 'total' => 0, 'erreur' => '');
    if ($mois == '') {
      $mois = tikras_sales_periode_mois();
    }

    $ip = tikras_cfg_value($data, $session, 1, '!', '');
    $utilisateur = tikras_cfg_value($data, $session, 2, '@|@', '');
    $motDePasse = decrypt(tikras_cfg_value($data, $session, 3, '#|#', ''));
    $devise = tikras_cfg_value($data, $session, 6, '&', '');
    if ($ip == '') {
      $resultat['erreur'] = 'adresse absente';
      return $resultat;
    }

    $api = tikras_routeros_create();
    $api->attempts = 1;
    $api->timeout = 6;
    if (!tikras_routeros_connect($api, $ip, $utilisateur, $motDePasse, $session, array('timeout' => 6))) {
      $resultat['erreur'] = 'injoignable';
      return $resultat;
    }

    $scripts = $api->comm('/system/script/print', array(
      '?owner' => $mois,
      '.proplist' => 'name',
    ));
    tikras_routeros_disconnect($api);

    if (!is_array($scripts)) {
      $resultat['erreur'] = 'reponse illisible';
      return $resultat;
    }

    $lignes = array();
    $total = 0;
    foreach ($scripts as $script) {
      $nom = tikras_array_get($script, 'name', '');
      if ($nom == '') {
        continue;
      }
      $champs = explode('-|-', $nom);
      // Un enregistrement de vente compte au moins date, heure, identifiant
      // et prix; les scripts d'exploitation du routeur n'ont pas cette forme.
      if (count($champs) < 4) {
        continue;
      }
      $prix = (float) str_replace(',', '', $champs[3]);
      $lignes[] = array(
        'date' => $champs[0],
        'time' => $champs[1],
        'username' => $champs[2],
        'profile' => isset($champs[7]) ? $champs[7] : '-',
        'comment' => isset($champs[8]) ? $champs[8] : '',
        'amount' => $prix,
      );
      $total += $prix;
    }

    $ecriture = tikras_storage_replace_sales_cache($session, 'month:' . $mois, $lignes, $devise);
    if (!isset($ecriture['ok']) || !$ecriture['ok']) {
      $resultat['erreur'] = isset($ecriture['error']) ? (string) $ecriture['error'] : 'enregistrement refuse';
      return $resultat;
    }

    $resultat['ok'] = true;
    $resultat['lignes'] = count($lignes);
    $resultat['total'] = $total;
    return $resultat;
  }
}

if (!function_exists('tikras_sales_releve_parc')) {
  /*
   * Releve un lot de routeurs, les moins recemment releves d'abord.
   *
   * Le parc compte plus de cent routeurs, dont la moitie est hors ligne a un
   * instant donne: tout interroger a chaque passage allongerait le cycle sans
   * profit. Le lot tournant garantit que chaque routeur est vu a intervalle
   * regulier tout en gardant un passage court.
   */
  function tikras_sales_releve_parc($data, $taille = 12, $mois = '')
  {
    $rapport = array('releves' => 0, 'ok' => 0, 'injoignables' => 0, 'ventes' => 0, 'total' => 0);
    if (!is_array($data) || !tikras_storage_available()) {
      return $rapport;
    }
    if ($mois == '') {
      $mois = tikras_sales_periode_mois();
    }

    $etats = function_exists('tikras_storage_all_router_statuses') ? tikras_storage_all_router_statuses() : array();
    $vus = tikras_sales_derniers_releves();

    $candidats = array();
    foreach ($data as $session => $config) {
      if ($session == '' || $session == 'mikhmon' || !is_array($config)) {
        continue;
      }
      if (tikras_cfg_value($data, $session, 1, '!', '') == '') {
        continue;
      }
      /*
       * Un routeur vu hors ligne au dernier controle est ecarte: la tentative
       * de connexion couterait le delai d'attente complet pour rien. Il
       * reviendra des que le controle de sante le retrouvera.
       */
      $etat = isset($etats[$session]['last_state']) ? $etats[$session]['last_state'] : 'unknown';
      if ($etat === 'offline') {
        continue;
      }
      $candidats[] = array(
        'session' => $session,
        'vu' => isset($vus[$session]) ? (string) $vus[$session] : '',
      );
    }

    usort($candidats, function ($a, $b) {
      return strcmp($a['vu'], $b['vu']);
    });
    $candidats = array_slice($candidats, 0, max(1, (int) $taille));

    foreach ($candidats as $candidat) {
      $session = $candidat['session'];
      $lecture = tikras_sales_lire_routeur($data, $session, $mois);
      $rapport['releves']++;
      if ($lecture['ok']) {
        $rapport['ok']++;
        $rapport['ventes'] += $lecture['lignes'];
        $rapport['total'] += $lecture['total'];
      } else {
        $rapport['injoignables']++;
      }
      // Marque meme en cas d'echec: sans cela, un routeur durablement muet
      // serait retente en boucle et bloquerait le tour des autres.
      tikras_sales_marquer_releve($session);
    }

    return $rapport;
  }
}

if (!function_exists('tikras_sales_derniers_releves')) {
  function tikras_sales_derniers_releves()
  {
    $valeur = tikras_automation_meta_get('sales.vus', '');
    if ($valeur == '') {
      return array();
    }
    $decode = json_decode($valeur, true);
    return is_array($decode) ? $decode : array();
  }
}

if (!function_exists('tikras_sales_marquer_releve')) {
  function tikras_sales_marquer_releve($session)
  {
    $vus = tikras_sales_derniers_releves();
    $vus[(string) $session] = date('Y-m-d H:i:s');
    // Le parc evolue: on ne garde que les entrees utiles pour que la valeur
    // reste courte.
    if (count($vus) > 400) {
      asort($vus);
      $vus = array_slice($vus, -300, null, true);
    }
    tikras_automation_meta_set('sales.vus', json_encode($vus));
  }
}

if (!function_exists('tikras_sales_source')) {
  /*
   * Source dedoublonnee des ventes.
   *
   * Une meme vente peut etre enregistree deux fois: la page Rapport ecrit
   * sous la cle du mois ("month:072026") quand on consulte le mois, et sous
   * celle du jour ("day:2026-07-26") quand on consulte la journee. Les deux
   * jeux coexistent alors pour les memes tickets.
   *
   * Mesure sur les donnees reelles du 26 juillet: 53 ventes et 9 600 CFA en
   * comptage direct, contre 29 ventes et 5 100 CFA en realite - le chiffre
   * d'affaires affiche aurait ete presque double. Toute lecture passe donc
   * par ce regroupement, qui ne retient qu'une ligne par vente.
   */
  function tikras_sales_source()
  {
    /*
     * La devise est ramenee en majuscules: le parc contient a la fois "CFA"
     * et "Cfa" pour la meme monnaie, ce qui les faisait compter comme deux
     * devises distinctes.
     */
    return '(SELECT session, ticket_code, profile, UPPER(TRIM(currency)) AS currency,
                    sold_at, MAX(price) AS price
             FROM sales_cache GROUP BY session, ticket_code, sold_at)';
  }
}

if (!function_exists('tikras_sales_synthese')) {
  /*
   * Chiffres consolides du parc, lus dans la base et non sur les routeurs:
   * l'affichage d'un tableau de bord ne doit jamais dependre de la
   * disponibilite de cent routeurs.
   */
  function tikras_sales_synthese()
  {
    $vide = array(
      'jour' => array('total' => 0, 'nombre' => 0),
      'mois' => array('total' => 0, 'nombre' => 0),
      'hier' => array('total' => 0, 'nombre' => 0),
      'devise' => '',
      'devises' => array(),
      'par_routeur' => array(),
      'par_profil' => array(),
      'jours' => array(),
      'disponible' => false,
    );
    if (!tikras_storage_available()) {
      return $vide;
    }

    try {
      $pdo = tikras_storage_pdo();
      $aujourdhui = date('Y-m-d');
      $hier = date('Y-m-d', strtotime('-1 day'));
      $debutMois = date('Y-m-01');

      $synthese = $vide;
      $synthese['disponible'] = true;
      $source = tikras_sales_source();

      /*
       * Le parc n'encaisse pas dans une seule monnaie: des routeurs sont au
       * Niger (CFA) et d'autres au Nigeria (NGN). Additionner les deux
       * donnerait un nombre qui ne veut rien dire. La monnaie principale est
       * celle qui porte le plus gros volume; les autres sont totalisees a
       * part et affichees en complement.
       */
      $req = $pdo->prepare('SELECT currency, COUNT(*) n, COALESCE(SUM(price), 0) t FROM ' . $source . '
        WHERE substr(sold_at, 1, 10) >= ? GROUP BY currency ORDER BY t DESC');
      $req->execute(array($debutMois));
      $parDevise = $req->fetchAll();
      $synthese['devises'] = array();
      foreach ($parDevise as $ligne) {
        $synthese['devises'][] = array(
          'devise' => (string) $ligne['currency'],
          'total' => (float) $ligne['t'],
          'nombre' => (int) $ligne['n'],
        );
      }
      $synthese['devise'] = count($parDevise) > 0 ? (string) $parDevise[0]['currency'] : '';
      $principale = $synthese['devise'];

      $req = $pdo->prepare('SELECT COUNT(*) n, COALESCE(SUM(price), 0) t FROM ' . $source . '
        WHERE substr(sold_at, 1, 10) = ? AND currency = ?');
      foreach (array('jour' => $aujourdhui, 'hier' => $hier) as $cle => $jour) {
        $req->execute(array($jour, $principale));
        $ligne = $req->fetch();
        $synthese[$cle] = array('total' => (float) $ligne['t'], 'nombre' => (int) $ligne['n']);
      }

      $req = $pdo->prepare('SELECT COUNT(*) n, COALESCE(SUM(price), 0) t FROM ' . $source . '
        WHERE substr(sold_at, 1, 10) >= ? AND currency = ?');
      $req->execute(array($debutMois, $principale));
      $ligne = $req->fetch();
      $synthese['mois'] = array('total' => (float) $ligne['t'], 'nombre' => (int) $ligne['n']);

      /* Classements et courbe restent dans la monnaie principale, pour que
         les montants affiches soient comparables entre eux. */
      $req = $pdo->prepare('SELECT session, COUNT(*) n, COALESCE(SUM(price), 0) t FROM ' . $source . '
        WHERE substr(sold_at, 1, 10) >= ? AND currency = ? GROUP BY session ORDER BY t DESC LIMIT 6');
      $req->execute(array($debutMois, $principale));
      $synthese['par_routeur'] = $req->fetchAll();

      $req = $pdo->prepare('SELECT profile, COUNT(*) n, COALESCE(SUM(price), 0) t FROM ' . $source . '
        WHERE substr(sold_at, 1, 10) >= ? AND currency = ? GROUP BY profile ORDER BY t DESC LIMIT 6');
      $req->execute(array($debutMois, $principale));
      $synthese['par_profil'] = $req->fetchAll();

      // Quatorze jours: assez pour lire une tendance, assez court pour rester
      // lisible sur un telephone.
      $depuis = date('Y-m-d', strtotime('-13 days'));
      $req = $pdo->prepare('SELECT substr(sold_at, 1, 10) j, COUNT(*) n, COALESCE(SUM(price), 0) t
        FROM ' . $source . ' WHERE substr(sold_at, 1, 10) >= ? AND currency = ? GROUP BY j ORDER BY j');
      $req->execute(array($depuis, $principale));
      $parJour = array();
      foreach ($req->fetchAll() as $ligne) {
        $parJour[(string) $ligne['j']] = array('nombre' => (int) $ligne['n'], 'total' => (float) $ligne['t']);
      }
      $jours = array();
      for ($i = 13; $i >= 0; $i--) {
        $jour = date('Y-m-d', strtotime('-' . $i . ' days'));
        $jours[] = array(
          'jour' => $jour,
          'nombre' => isset($parJour[$jour]) ? $parJour[$jour]['nombre'] : 0,
          'total' => isset($parJour[$jour]) ? $parJour[$jour]['total'] : 0,
        );
      }
      $synthese['jours'] = $jours;

      return $synthese;
    } catch (Exception $e) {
      return $vide;
    }
  }
}

if (!function_exists('tikras_sales_couverture')) {
  /*
   * Part du parc dont les recettes sont effectivement connues.
   *
   * Un total consolide qui ne couvrirait qu'une poignee de routeurs induirait
   * en erreur plus surement qu'une absence de chiffre: le tableau de bord
   * affiche donc toujours combien de routeurs il represente.
   */
  function tikras_sales_couverture($data)
  {
    $rapport = array('connus' => 0, 'total' => 0, 'jamais_releves' => array());
    if (!is_array($data) || !tikras_storage_available()) {
      return $rapport;
    }
    $vus = tikras_sales_derniers_releves();
    try {
      $pdo = tikras_storage_pdo();
      $avecVentes = array();
      $req = $pdo->prepare('SELECT DISTINCT session FROM sales_cache WHERE substr(sold_at, 1, 10) >= ?');
      $req->execute(array(date('Y-m-01')));
      foreach ($req->fetchAll() as $ligne) {
        $avecVentes[(string) $ligne['session']] = true;
      }
    } catch (Exception $e) {
      $avecVentes = array();
    }

    foreach ($data as $session => $config) {
      if ($session == '' || $session == 'mikhmon' || !is_array($config)) {
        continue;
      }
      $rapport['total']++;
      if (isset($vus[$session]) || isset($avecVentes[$session])) {
        $rapport['connus']++;
      } else {
        $rapport['jamais_releves'][] = $session;
      }
    }
    return $rapport;
  }
}
