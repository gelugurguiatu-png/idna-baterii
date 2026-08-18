<?php
// Schimbare parola proprie. POST JSON: {parola_veche, parola_noua}
// Scrie utilizatori.json (preluand lista curenta, inclusiv din fisierul initial).

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'eroare' => 'metoda invalida'));
    exit;
}

$nume = auth_cere_autentificare();

$date = json_decode(file_get_contents('php://input'), true);
$veche = isset($date['parola_veche']) ? $date['parola_veche'] : '';
$noua = isset($date['parola_noua']) ? $date['parola_noua'] : '';

if (strlen($noua) < 8) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'eroare' => 'parola noua trebuie sa aiba minimum 8 caractere'));
    exit;
}

$utilizatori = auth_incarca_utilizatori();
$gasit = false;
foreach ($utilizatori as $i => $u) {
    if ($u['utilizator'] === $nume) {
        if (!password_verify($veche, $u['hash'])) {
            http_response_code(401);
            echo json_encode(array('ok' => false, 'eroare' => 'parola veche gresita'));
            exit;
        }
        $utilizatori[$i]['hash'] = password_hash($noua, PASSWORD_DEFAULT);
        $utilizatori[$i]['schimba_parola'] = false;
        $gasit = true;
        break;
    }
}

if (!$gasit) {
    http_response_code(404);
    echo json_encode(array('ok' => false, 'eroare' => 'utilizator inexistent'));
    exit;
}

if (!auth_salveaza_utilizatori($utilizatori)) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'eroare' => 'nu s-a putut salva'));
    exit;
}

echo json_encode(array('ok' => true));
