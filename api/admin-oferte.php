<?php
// Registrul ofertelor generate, pentru tabul "Oferte generate" din admin.
// GET  (autentificat)   -> {ok, oferte: [...]} - fara cele sterse, cele mai noi primele
// POST (doar rol admin) -> {actiune:"sterge", nr} - soft-delete: marcheaza oferta
//   ca stearsa (numerotarea ramane intacta - nr urmator = total intrari + 1)
//   si sterge fisierul PDF de pe disc.

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/auth.php';

auth_cere_autentificare();

$fisReg = __DIR__ . '/oferte/registru.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $registru = array();
    if (file_exists($fisReg)) {
        $registru = json_decode(file_get_contents($fisReg), true);
        if (!is_array($registru)) { $registru = array(); }
    }
    $vizibile = array();
    foreach ($registru as $o) {
        if (empty($o['sters'])) { $vizibile[] = $o; }
    }
    echo json_encode(array('ok' => true, 'oferte' => array_reverse($vizibile)), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'eroare' => 'metoda invalida'));
    exit;
}

// stergerea e permisa doar adminilor
auth_cere_admin();

$date = json_decode(file_get_contents('php://input'), true);
$actiune = isset($date['actiune']) ? $date['actiune'] : '';
$nr = isset($date['nr']) ? intval($date['nr']) : 0;

if ($actiune !== 'sterge' || $nr <= 0) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'eroare' => 'actiune necunoscuta'));
    exit;
}

$fh = fopen($fisReg, 'c+');
if ($fh === false || !flock($fh, LOCK_EX)) {
    if ($fh !== false) { fclose($fh); }
    http_response_code(500);
    echo json_encode(array('ok' => false, 'eroare' => 'nu s-a putut accesa registrul'));
    exit;
}

$registru = json_decode(stream_get_contents($fh), true);
if (!is_array($registru)) { $registru = array(); }

$gasita = false;
foreach ($registru as $i => $o) {
    if (isset($o['nr']) && intval($o['nr']) === $nr && empty($o['sters'])) {
        $registru[$i]['sters'] = true;
        $registru[$i]['sters_la'] = date('Y-m-d H:i:s');
        $registru[$i]['sters_de'] = $_SESSION['utilizator'];
        // sterge si fisierul PDF de pe disc
        if (!empty($o['fisier'])) {
            @unlink(__DIR__ . '/oferte/' . basename($o['fisier']));
        }
        $gasita = true;
        break;
    }
}

if ($gasita) {
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($registru, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fh);
}
flock($fh, LOCK_UN);
fclose($fh);

if (!$gasita) {
    http_response_code(404);
    echo json_encode(array('ok' => false, 'eroare' => 'oferta inexistenta sau deja stearsa'));
    exit;
}

echo json_encode(array('ok' => true));
