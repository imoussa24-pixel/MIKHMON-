<?php
header('Content-Type: application/json; charset=utf-8');
http_response_code(501);
echo json_encode(array(
    'status' => 'not_configured',
    'message' => 'Connectez ce fichier a votre fournisseur SMS/OTP avant activation.'
));
