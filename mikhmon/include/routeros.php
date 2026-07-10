<?php
/*
 * RouterOS API connection defaults.
 * Keep attempts low so an unreachable router does not freeze the local PHP server.
 */
$tikras_routeros_port = 8728;
$tikras_routeros_timeout = 2;
$tikras_routeros_attempts = 1;
$tikras_routeros_delay = 0;
$tikras_routeros_cooldown = 15;
?>
