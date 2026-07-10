<?php
/*
 *  Enable, disable and remove PPP secrets.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

if ($enablesecr != "") {
  $API->comm("/ppp/secret/set", array(
    ".id" => "$enablesecr",
    "disabled" => "no",
  ));
} elseif ($disablesecr != "") {
  $API->comm("/ppp/secret/set", array(
    ".id" => "$disablesecr",
    "disabled" => "yes",
  ));
} elseif ($removesecr != "") {
  if (substr($removesecr, 0, 8) == "comment:") {
    $comment = substr($removesecr, 8);
    $getsecret = $API->comm("/ppp/secret/print");
    for ($i = 0; $i < count($getsecret); $i++) {
      if ($getsecret[$i]['comment'] == $comment) {
        $API->comm("/ppp/secret/remove", array(
          ".id" => $getsecret[$i]['.id'],
        ));
      }
    }
  } else {
    $API->comm("/ppp/secret/remove", array(
      ".id" => "$removesecr",
    ));
  }
}

tikras_redirect('./?ppp=secrets&profile=all&session=' . $session);
?>
