<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(array(
    'online_users' => 0,
    'sold_today' => 0,
    'bandwidth_label' => 'Actif',
    'updated_at' => date('c')
));
