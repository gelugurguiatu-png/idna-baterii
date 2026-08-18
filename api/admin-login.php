<?php
// Login operator. POST JSON: {utilizator, parola}
// Raspuns: {ok, utilizator, rol, schimba_parola}

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'eroare' => 'metoda invalida'));
    exit;
}

auth_verifica_rate_limit_login();

$date = json_decode(file_get_contents('php://input'), true);
$nume = isset($date['utilizator']) ? trim($date['utilizator']) : '';
$parola = isset($date['parola']) ? $date['parola'] : '';

if ($nume === '' || $parola === '') {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'eroare' => 'completeaza utilizator si parola'));
    exit;
}

$u = auth_gaseste_utilizator($nume);
if (!$u || !password_verify($parola, $u['hash'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'eroare' => 'utilizator sau parola gresite'));
    exit;
}

auth_porneste_sesiune();
session_regenerate_id(true);
$_SESSION['utilizator'] = $nume;

echo json_encode(array(
    'ok' => true,
    'utilizator' => $nume,
    'rol' => isset($u['rol']) ? $u['rol'] : 'operator',
    'schimba_parola' => !empty($u['schimba_parola']),
));
