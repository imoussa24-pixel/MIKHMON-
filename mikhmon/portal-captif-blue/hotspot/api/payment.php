<?php
header('Content-Type: application/json; charset=utf-8');
http_response_code(501);
echo json_encode(array(
    'status' => 'not_configured',
    'message' => 'Ajoutez ici les identifiants de votre passerelle Mobile Money avant la mise en production.'
));
