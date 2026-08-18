<?php
// Gestionare operatori (doar rol admin).
// GET  -> lista utilizatori (fara hash-uri)
// POST -> {actiune:"adauga", utilizator, parola, rol} sau {actiune:"sterge", utilizator}
// Utilizatorii noi primesc schimba_parola=true (parola temporara).

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/auth.php';

$numeAdmin = auth_cere_admin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lista = array();
    foreach (auth_incarca_utilizatori() as $u) {
        $lista[] = array(
            'utilizator' => $u['utilizator'],
            'rol' => isset($u['rol']) ? $u['rol'] : 'operator',
            'schimba_parola' => !empty($u['schimba_parola']),
        );
    }
    echo json_encode(array('ok' => true, 'utilizatori' => $lista));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'eroare' => 'metoda invalida'));
    exit;
}

$date = json_decode(file_get_contents('php://input'), true);
$actiune = isset($date['actiune']) ? $date['actiune'] : '';
$utilizatori = auth_incarca_utilizatori();

if ($actiune === 'adauga') {
    $nume = isset($date['utilizator']) ? trim($date['utilizator']) : '';
    $parola = isset($date['parola']) ? $date['parola'] : '';
    $rol = (isset($date['rol']) && $date['rol'] === 'admin') ? 'admin' : 'operator';

    if (!preg_match('/^[a-z0-9._-]{3,30}$/i', $nume)) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'eroare' => 'nume utilizator invalid (3-30 caractere, litere/cifre/._-)'));
        exit;
    }
    if (strlen($parola) < 8) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'eroare' => 'parola trebuie sa aiba minimum 8 caractere'));
        exit;
    }
    foreach ($utilizatori as $u) {
        if (strtolower($u['utilizator']) === strtolower($nume)) {
            http_response_code(409);
            echo json_encode(array('ok' => false, 'eroare' => 'utilizatorul exista deja'));
            exit;
        }
    }
    $utilizatori[] = array(
        'utilizator' => $nume,
        'hash' => password_hash($parola, PASSWORD_DEFAULT),
        'rol' => $rol,
        'schimba_parola' => true,
    );
    if (!auth_salveaza_utilizatori($utilizatori)) {
        http_response_code(500);
        echo json_encode(array('ok' => false, 'eroare' => 'nu s-a putut salva'));
        exit;
    }
    echo json_encode(array('ok' => true));
    exit;
}

if ($actiune === 'sterge') {
    $nume = isset($date['utilizator']) ? trim($date['utilizator']) : '';
    if ($nume === $numeAdmin) {
        http_response_code(400);
        echo json_encode(array('ok' => false, 'eroare' => 'nu iti poti sterge propriul cont'));
        exit;
    }
    $ramas = array();
    foreach ($utilizatori as $u) {
        if ($u['utilizator'] !== $nume) { $ramas[] = $u; }
    }
    if (count($ramas) === count($utilizatori)) {
        http_response_code(404);
        echo json_encode(array('ok' => false, 'eroare' => 'utilizator inexistent'));
        exit;
    }
    if (!auth_salveaza_utilizatori($ramas)) {
        http_response_code(500);
        echo json_encode(array('ok' => false, 'eroare' => 'nu s-a putut salva'));
        exit;
    }
    echo json_encode(array('ok' => true));
    exit;
}

http_response_code(400);
echo json_encode(array('ok' => false, 'eroare' => 'actiune necunoscuta'));
