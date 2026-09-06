<?php
/*
 * Routeurs favoris.
 *
 * Le parc compte plus de cent routeurs, tous presentes au meme rang. Or on
 * n'en exploite qu'une poignee au quotidien: les autres ne sont ouverts que
 * lors d'une intervention. Retrouver les siens demandait de parcourir la
 * liste ou de se souvenir du nom exact a taper dans la recherche.
 *
 * Les favoris remontent en tete de liste et se filtrent d'un clic. La marque
 * est rangee dans la base locale, pas dans le navigateur: elle suit
 * l'utilisateur du poste au telephone.
 */

include_once(dirname(__FILE__) . '/tikras_core.php');
include_once(dirname(__FILE__) . '/tikras_storage.php');

if (!function_exists('tikras_favoris_cle')) {
  function tikras_favoris_cle()
  {
    return 'ui.favoris';
  }
}

if (!function_exists('tikras_favoris_liste')) {
  /* Sessions marquees comme favorites, dans l'ordre d'ajout. */
  function tikras_favoris_liste()
  {
    if (!tikras_storage_available()) {
      return array();
    }
    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare('SELECT meta_value FROM app_meta WHERE meta_key = ? LIMIT 1');
      $stmt->execute(array(tikras_favoris_cle()));
      $valeur = $stmt->fetchColumn();
      if ($valeur === false || $valeur === '') {
        return array();
      }
      $decode = json_decode((string) $valeur, true);
      return is_array($decode) ? array_values(array_unique(array_map('strval', $decode))) : array();
    } catch (Exception $e) {
      return array();
    }
  }
}

if (!function_exists('tikras_favoris_est')) {
  function tikras_favoris_est($session)
  {
    return in_array((string) $session, tikras_favoris_liste(), true);
  }
}

if (!function_exists('tikras_favoris_basculer')) {
  /*
   * Ajoute ou retire un routeur des favoris. Retourne le nouvel etat.
   */
  function tikras_favoris_basculer($session)
  {
    $session = trim((string) $session);
    if ($session == '' || !tikras_storage_available()) {
      return array('ok' => false, 'favori' => false, 'message' => 'Favoris indisponibles.');
    }

    $favoris = tikras_favoris_liste();
    $index = array_search($session, $favoris, true);
    if ($index === false) {
      $favoris[] = $session;
      $etat = true;
    } else {
      array_splice($favoris, $index, 1);
      $etat = false;
    }

    try {
      $pdo = tikras_storage_pdo();
      $stmt = $pdo->prepare('INSERT OR REPLACE INTO app_meta(meta_key, meta_value, updated_at) VALUES(?, ?, ?)');
      $stmt->execute(array(tikras_favoris_cle(), json_encode(array_values($favoris)), tikras_storage_now()));
      return array(
        'ok' => true,
        'favori' => $etat,
        'message' => $etat ? 'Routeur ajouté aux favoris.' : 'Routeur retiré des favoris.',
      );
    } catch (Exception $e) {
      return array('ok' => false, 'favori' => false, 'message' => $e->getMessage());
    }
  }
}

if (!function_exists('tikras_favoris_trier')) {
  /*
   * Remonte les favoris en tete d'une liste de sessions, sans toucher a
   * l'ordre relatif du reste: une liste qui se reorganiserait entierement
   * deroute plus qu'elle n'aide.
   */
  function tikras_favoris_trier($sessions)
  {
    $favoris = tikras_favoris_liste();
    if (count($favoris) < 1 || !is_array($sessions)) {
      return $sessions;
    }
    $enTete = array();
    $reste = array();
    foreach ($sessions as $cle => $valeur) {
      $nom = is_int($cle) ? (string) $valeur : (string) $cle;
      if (in_array($nom, $favoris, true)) {
        $enTete[$cle] = $valeur;
      } else {
        $reste[$cle] = $valeur;
      }
    }
    return $enTete + $reste;
  }
}
