<?php
/*
 *  Remove PPP profile.
 */
include_once(dirname(__DIR__) . '/lib/tikras_core.php');
tikras_start_session();
tikras_bootstrap_errors(false);

$API->comm("/ppp/profile/remove", array(
  ".id" => "$removepprofile",
));

tikras_redirect('./?ppp=profiles&session=' . $session);
?>
