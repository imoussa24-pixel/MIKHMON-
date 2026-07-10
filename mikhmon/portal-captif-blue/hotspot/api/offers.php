<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(array(
    array('name' => 'Express', 'duration' => '1h', 'price' => '100 FCFA'),
    array('name' => 'Journee', 'duration' => '24h', 'price' => '500 FCFA'),
    array('name' => 'Semaine', 'duration' => '7j', 'price' => '2 500 FCFA')
));
