<?php
/*
 * Encodeur QR minimal, sans dependance externe.
 *
 * Le modele imprimable genere son QR dans le navigateur; un PDF fabrique par
 * le serveur ne peut pas s'appuyer dessus. Cet encodeur produit la matrice en
 * PHP pur, que le PDF dessine ensuite en rectangles vectoriels.
 *
 * Portee volontairement reduite au besoin: mode octet, correction M, versions
 * 1 a 6, soit 106 caracteres. C'est largement suffisant pour une adresse de
 * portail suivie d'un code d'acces, et cela evite le bloc d'information de
 * version qu'exigent les versions 7 et suivantes: chaque version couverte est
 * verifiee par un decodeur reel, plutot que d'etendre la portee sans preuve.
 * Au-dela de 106 caracteres, la fonction renvoie null et l'appelant se
 * contente de ne pas afficher de QR.
 */

if (!function_exists('tikras_qr_capacites')) {
  /* Capacite en octets par version, pour le niveau de correction M. */
  function tikras_qr_capacites()
  {
    return array(1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84, 6 => 106);
  }
}

if (!function_exists('tikras_qr_blocs')) {
  /*
   * Structure des blocs de correction pour le niveau M:
   * total de codets, nombre de blocs du groupe 1, codets de donnees par bloc,
   * nombre de blocs du groupe 2, codets de donnees par bloc.
   */
  function tikras_qr_blocs()
  {
    return array(
      1 => array(26, 1, 16, 0, 0),
      2 => array(44, 1, 28, 0, 0),
      3 => array(70, 1, 44, 0, 0),
      4 => array(100, 2, 32, 0, 0),
      5 => array(134, 2, 43, 0, 0),
      6 => array(172, 4, 27, 0, 0),
      7 => array(196, 4, 31, 0, 0),
      8 => array(242, 2, 38, 2, 39),
      9 => array(292, 3, 36, 2, 37),
      10 => array(346, 4, 43, 1, 44),
    );
  }
}

if (!function_exists('tikras_qr_alignements')) {
  function tikras_qr_alignements()
  {
    return array(
      1 => array(), 2 => array(6, 18), 3 => array(6, 22), 4 => array(6, 26),
      5 => array(6, 30), 6 => array(6, 34), 7 => array(6, 22, 38),
      8 => array(6, 24, 42), 9 => array(6, 26, 46), 10 => array(6, 28, 50),
    );
  }
}

if (!function_exists('tikras_qr_galois')) {
  /* Tables exponentielle et logarithmique du corps de Galois GF(256). */
  function tikras_qr_galois()
  {
    static $tables = null;
    if ($tables !== null) {
      return $tables;
    }
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $valeur = 1;
    for ($i = 0; $i < 255; $i++) {
      $exp[$i] = $valeur;
      $log[$valeur] = $i;
      $valeur <<= 1;
      if ($valeur & 0x100) {
        $valeur ^= 0x11d;
      }
    }
    for ($i = 255; $i < 512; $i++) {
      $exp[$i] = $exp[$i - 255];
    }
    $tables = array($exp, $log);
    return $tables;
  }
}

if (!function_exists('tikras_qr_polynome_generateur')) {
  function tikras_qr_polynome_generateur($degre)
  {
    list($exp, $log) = tikras_qr_galois();
    $poly = array(1);
    for ($i = 0; $i < $degre; $i++) {
      $suivant = array_fill(0, count($poly) + 1, 0);
      foreach ($poly as $index => $coefficient) {
        $suivant[$index] ^= $coefficient;
        if ($coefficient != 0) {
          $suivant[$index + 1] ^= $exp[($log[$coefficient] + $i) % 255];
        }
      }
      $poly = $suivant;
    }
    return $poly;
  }
}

if (!function_exists('tikras_qr_correction')) {
  function tikras_qr_correction($donnees, $nbCodets)
  {
    list($exp, $log) = tikras_qr_galois();
    $generateur = tikras_qr_polynome_generateur($nbCodets);
    $reste = array_merge($donnees, array_fill(0, $nbCodets, 0));

    for ($i = 0; $i < count($donnees); $i++) {
      $tete = $reste[$i];
      if ($tete == 0) {
        continue;
      }
      $facteur = $log[$tete];
      foreach ($generateur as $index => $coefficient) {
        if ($coefficient != 0) {
          $reste[$i + $index] ^= $exp[($log[$coefficient] + $facteur) % 255];
        }
      }
    }
    return array_slice($reste, count($donnees), $nbCodets);
  }
}

if (!function_exists('tikras_qr_motifs')) {
  /* Motifs de detection, d'alignement et de synchronisation. */
  function tikras_qr_motifs(&$matrice, &$reserve, $taille, $version)
  {
    $chercheur = function ($ligne, $colonne) use (&$matrice, &$reserve, $taille) {
      for ($y = -1; $y <= 7; $y++) {
        for ($x = -1; $x <= 7; $x++) {
          $l = $ligne + $y;
          $c = $colonne + $x;
          if ($l < 0 || $l >= $taille || $c < 0 || $c >= $taille) {
            continue;
          }
          $borde = ($y >= 0 && $y <= 6 && ($x == 0 || $x == 6))
            || ($x >= 0 && $x <= 6 && ($y == 0 || $y == 6));
          $centre = ($y >= 2 && $y <= 4 && $x >= 2 && $x <= 4);
          $matrice[$l][$c] = ($borde || $centre) ? 1 : 0;
          $reserve[$l][$c] = 1;
        }
      }
    };
    $chercheur(0, 0);
    $chercheur(0, $taille - 7);
    $chercheur($taille - 7, 0);

    // Synchronisation
    for ($i = 8; $i < $taille - 8; $i++) {
      $bit = ($i % 2 == 0) ? 1 : 0;
      $matrice[6][$i] = $bit;
      $reserve[6][$i] = 1;
      $matrice[$i][6] = $bit;
      $reserve[$i][6] = 1;
    }

    // Alignement
    $positions = tikras_qr_alignements();
    $liste = isset($positions[$version]) ? $positions[$version] : array();
    foreach ($liste as $ligne) {
      foreach ($liste as $colonne) {
        $coinChercheur = ($ligne <= 8 && $colonne <= 8)
          || ($ligne <= 8 && $colonne >= $taille - 9)
          || ($ligne >= $taille - 9 && $colonne <= 8);
        if ($coinChercheur) {
          continue;
        }
        for ($y = -2; $y <= 2; $y++) {
          for ($x = -2; $x <= 2; $x++) {
            $noir = (max(abs($y), abs($x)) != 1) ? 1 : 0;
            $matrice[$ligne + $y][$colonne + $x] = $noir;
            $reserve[$ligne + $y][$colonne + $x] = 1;
          }
        }
      }
    }

    // Module toujours noir
    $matrice[$taille - 8][8] = 1;
    $reserve[$taille - 8][8] = 1;

    // Zones reservees a l'information de format
    for ($i = 0; $i <= 8; $i++) {
      if ($i != 6) {
        $reserve[8][$i] = 1;
        $reserve[$i][8] = 1;
      }
    }
    for ($i = 0; $i < 8; $i++) {
      $reserve[8][$taille - 1 - $i] = 1;
      $reserve[$taille - 1 - $i][8] = 1;
    }
  }
}

if (!function_exists('tikras_qr_penalite')) {
  function tikras_qr_penalite($matrice, $taille)
  {
    $score = 0;

    // Series de cinq modules ou plus de meme couleur
    for ($i = 0; $i < $taille; $i++) {
      for ($orientation = 0; $orientation < 2; $orientation++) {
        $precedent = -1;
        $serie = 0;
        for ($j = 0; $j < $taille; $j++) {
          $valeur = $orientation == 0 ? $matrice[$i][$j] : $matrice[$j][$i];
          if ($valeur === $precedent) {
            $serie++;
          } else {
            if ($serie >= 5) {
              $score += 3 + ($serie - 5);
            }
            $precedent = $valeur;
            $serie = 1;
          }
        }
        if ($serie >= 5) {
          $score += 3 + ($serie - 5);
        }
      }
    }

    // Blocs 2x2 uniformes
    for ($i = 0; $i < $taille - 1; $i++) {
      for ($j = 0; $j < $taille - 1; $j++) {
        $v = $matrice[$i][$j];
        if ($v === $matrice[$i][$j + 1] && $v === $matrice[$i + 1][$j] && $v === $matrice[$i + 1][$j + 1]) {
          $score += 3;
        }
      }
    }

    // Motifs ressemblant aux reperes de detection
    $motifs = array(
      array(1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0),
      array(0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1),
    );
    for ($i = 0; $i < $taille; $i++) {
      for ($j = 0; $j < $taille - 10; $j++) {
        foreach ($motifs as $motif) {
          $ligneOk = true;
          $colonneOk = true;
          for ($k = 0; $k < 11; $k++) {
            if ($matrice[$i][$j + $k] !== $motif[$k]) {
              $ligneOk = false;
            }
            if ($matrice[$j + $k][$i] !== $motif[$k]) {
              $colonneOk = false;
            }
          }
          if ($ligneOk) {
            $score += 40;
          }
          if ($colonneOk) {
            $score += 40;
          }
        }
      }
    }

    // Desequilibre entre modules clairs et sombres
    $noirs = 0;
    for ($i = 0; $i < $taille; $i++) {
      for ($j = 0; $j < $taille; $j++) {
        if ($matrice[$i][$j] === 1) {
          $noirs++;
        }
      }
    }
    $pourcentage = ($noirs * 100) / ($taille * $taille);
    $score += 10 * (int) (abs($pourcentage - 50) / 5);

    return $score;
  }
}

if (!function_exists('tikras_qr_format')) {
  function tikras_qr_format(&$matrice, $taille, $masque)
  {
    // Niveau M => indicateur 00
    $donnees = (0 << 3) | $masque;
    $reste = $donnees << 10;
    for ($i = 4; $i >= 0; $i--) {
      if ($reste & (1 << ($i + 10))) {
        $reste ^= 0x537 << $i;
      }
    }
    $bits = (($donnees << 10) | $reste) ^ 0x5412;

    /*
     * Le bit de poids fort occupe la premiere position de chaque copie:
     * l'inverser rend le code illisible par les lecteurs.
     */
    // Copie 1: ligne 8 de gauche a droite, puis colonne 8 de bas en haut.
    for ($i = 0; $i <= 5; $i++) {
      $matrice[8][$i] = ($bits >> (14 - $i)) & 1;
    }
    $matrice[8][7] = ($bits >> 8) & 1;
    $matrice[8][8] = ($bits >> 7) & 1;
    $matrice[7][8] = ($bits >> 6) & 1;
    for ($i = 0; $i <= 5; $i++) {
      $matrice[$i][8] = ($bits >> $i) & 1;
    }

    /*
     * Copie 2: le bit de poids fort demarre en bas de la colonne 8 et la
     * suite se poursuit sur la ligne 8 jusqu'au bord droit. L'ordre differe
     * de la premiere copie, d'ou deux boucles distinctes.
     */
    for ($i = 0; $i <= 6; $i++) {
      $matrice[$taille - 1 - $i][8] = ($bits >> (14 - $i)) & 1;
    }
    for ($i = 0; $i <= 7; $i++) {
      $matrice[8][$taille - 8 + $i] = ($bits >> (7 - $i)) & 1;
    }
  }
}

if (!function_exists('tikras_qr_matrice')) {
  /*
   * Retourne la matrice du QR (tableau de lignes de 0/1) ou null si le texte
   * depasse la capacite prevue.
   */
  function tikras_qr_matrice($texte, $masqueImpose = -1)
  {
    $texte = (string) $texte;
    $longueur = strlen($texte);
    if ($longueur < 1) {
      return null;
    }

    $version = 0;
    foreach (tikras_qr_capacites() as $numero => $capacite) {
      if ($longueur <= $capacite) {
        $version = $numero;
        break;
      }
    }
    if ($version < 1) {
      return null;
    }

    $structure = tikras_qr_blocs();
    list($totalCodets, $blocs1, $donnees1, $blocs2, $donnees2) = $structure[$version];
    $totalDonnees = ($blocs1 * $donnees1) + ($blocs2 * $donnees2);

    // Sequence binaire: mode octet, longueur, donnees, terminateur.
    $bits = '0100';
    $bits .= str_pad(decbin($longueur), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
    for ($i = 0; $i < $longueur; $i++) {
      $bits .= str_pad(decbin(ord($texte[$i])), 8, '0', STR_PAD_LEFT);
    }
    $capaciteBits = $totalDonnees * 8;
    $bits .= str_repeat('0', min(4, max(0, $capaciteBits - strlen($bits))));
    while (strlen($bits) % 8 !== 0) {
      $bits .= '0';
    }
    $remplissage = array('11101100', '00010001');
    $index = 0;
    while (strlen($bits) < $capaciteBits) {
      $bits .= $remplissage[$index % 2];
      $index++;
    }

    $codets = array();
    for ($i = 0; $i < strlen($bits); $i += 8) {
      $codets[] = bindec(substr($bits, $i, 8));
    }

    // Repartition en blocs puis calcul de la correction d'erreur.
    $groupes = array();
    $position = 0;
    for ($i = 0; $i < $blocs1; $i++) {
      $groupes[] = array_slice($codets, $position, $donnees1);
      $position += $donnees1;
    }
    for ($i = 0; $i < $blocs2; $i++) {
      $groupes[] = array_slice($codets, $position, $donnees2);
      $position += $donnees2;
    }
    $nbCorrection = (int) (($totalCodets - $totalDonnees) / ($blocs1 + $blocs2));
    $corrections = array();
    foreach ($groupes as $bloc) {
      $corrections[] = tikras_qr_correction($bloc, $nbCorrection);
    }

    // Entrelacement
    $flux = array();
    $maxDonnees = max($donnees1, $blocs2 > 0 ? $donnees2 : 0);
    for ($i = 0; $i < $maxDonnees; $i++) {
      foreach ($groupes as $bloc) {
        if (isset($bloc[$i])) {
          $flux[] = $bloc[$i];
        }
      }
    }
    for ($i = 0; $i < $nbCorrection; $i++) {
      foreach ($corrections as $bloc) {
        if (isset($bloc[$i])) {
          $flux[] = $bloc[$i];
        }
      }
    }

    $fluxBits = '';
    foreach ($flux as $octet) {
      $fluxBits .= str_pad(decbin($octet), 8, '0', STR_PAD_LEFT);
    }

    $taille = 17 + (4 * $version);
    $matrice = array();
    $reserve = array();
    for ($i = 0; $i < $taille; $i++) {
      $matrice[$i] = array_fill(0, $taille, 0);
      $reserve[$i] = array_fill(0, $taille, 0);
    }
    tikras_qr_motifs($matrice, $reserve, $taille, $version);

    // Remplissage en colonnes doubles, de droite a gauche.
    $indexBit = 0;
    $montant = true;
    for ($colonne = $taille - 1; $colonne > 0; $colonne -= 2) {
      if ($colonne == 6) {
        $colonne--;
      }
      for ($pas = 0; $pas < $taille; $pas++) {
        $ligne = $montant ? ($taille - 1 - $pas) : $pas;
        for ($decalage = 0; $decalage < 2; $decalage++) {
          $c = $colonne - $decalage;
          if ($reserve[$ligne][$c] === 1) {
            continue;
          }
          $bit = $indexBit < strlen($fluxBits) ? (int) $fluxBits[$indexBit] : 0;
          $indexBit++;
          $matrice[$ligne][$c] = $bit;
        }
      }
      $montant = !$montant;
    }

    // Choix du masque le moins penalisant.
    $meilleure = null;
    $meilleurScore = PHP_INT_MAX;
    $premierMasque = $masqueImpose >= 0 ? $masqueImpose : 0;
    $dernierMasque = $masqueImpose >= 0 ? $masqueImpose : 7;
    for ($masque = $premierMasque; $masque <= $dernierMasque; $masque++) {
      $essai = $matrice;
      for ($i = 0; $i < $taille; $i++) {
        for ($j = 0; $j < $taille; $j++) {
          if ($reserve[$i][$j] === 1) {
            continue;
          }
          $applique = false;
          switch ($masque) {
            case 0: $applique = (($i + $j) % 2 == 0); break;
            case 1: $applique = ($i % 2 == 0); break;
            case 2: $applique = ($j % 3 == 0); break;
            case 3: $applique = (($i + $j) % 3 == 0); break;
            case 4: $applique = ((((int) ($i / 2)) + ((int) ($j / 3))) % 2 == 0); break;
            case 5: $applique = ((($i * $j) % 2) + (($i * $j) % 3) == 0); break;
            case 6: $applique = (((($i * $j) % 2) + (($i * $j) % 3)) % 2 == 0); break;
            case 7: $applique = (((($i + $j) % 2) + (($i * $j) % 3)) % 2 == 0); break;
          }
          if ($applique) {
            $essai[$i][$j] ^= 1;
          }
        }
      }
      tikras_qr_format($essai, $taille, $masque);
      $score = tikras_qr_penalite($essai, $taille);
      if ($score < $meilleurScore) {
        $meilleurScore = $score;
        $meilleure = $essai;
      }
    }

    return $meilleure;
  }
}
?>
