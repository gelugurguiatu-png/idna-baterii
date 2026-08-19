<?php
// Endpoint ADMIN: citire + salvare baterii (necesita login).
// GET  -> toate bateriile (inclusiv inactive)
// POST -> {baterii: [...]} - valideaza si scrie api/catalog.json

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/auth.php';

auth_cere_autentificare();

$fisierJson = __DIR__ . '/catalog.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $catalog = require __DIR__ . '/catalog-default.php';
    $baterii = $catalog['baterii'];
    $din_json = false;
    if (file_exists($fisierJson)) {
        $salvat = json_decode(file_get_contents($fisierJson), true);
        if (is_array($salvat) && isset($salvat['baterii']) && is_array($salvat['baterii'])) {
            $baterii = $salvat['baterii'];
            $din_json = true;
        }
    }
    echo json_encode(array('ok' => true, 'baterii' => $baterii, 'salvat_pe_server' => $din_json), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'eroare' => 'metoda invalida'));
    exit;
}

$date = json_decode(file_get_contents('php://input'), true);
if (!is_array($date) || !isset($date['baterii']) || !is_array($date['baterii'])) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'eroare' => 'date invalide'));
    exit;
}

// validare per baterie
$curate = array();
$erori = array();
$ids = array();
foreach ($date['baterii'] as $idx => $b) {
    $nr = $idx + 1;
    $marca = isset($b['marca']) ? trim($b['marca']) : '';
    $model = isset($b['model']) ? trim($b['model']) : '';
    if ($marca === '' || $model === '') { $erori[] = 'bateria #' . $nr . ': marca si modelul sunt obligatorii'; continue; }

    $cap = isset($b['capacitate_kwh']) ? floatval($b['capacitate_kwh']) : 0;
    if ($cap <= 0) { $erori[] = 'bateria #' . $nr . ': capacitate invalida'; }

    $pret = isset($b['pret_baterie']) ? floatval($b['pret_baterie']) : 0;
    $montaj = isset($b['pret_montaj']) ? floatval($b['pret_montaj']) : 0;
    if ($pret <= 0) { $erori[] = 'bateria #' . $nr . ': pret baterie invalid'; }
    if ($montaj < 0) { $erori[] = 'bateria #' . $nr . ': pret montaj invalid'; }

    $retea = isset($b['retea']) ? $b['retea'] : 'ambele';
    if (!in_array($retea, array('mono', 'tri', 'ambele'), true)) { $retea = 'ambele'; }

    // familia de compatibilitate cu invertorul clientului:
    //   universal = orice invertor (sisteme retrofit cu invertor propriu)
    //   fronius   = doar invertoare Fronius HIBRIDE
    //   huawei    = doar invertoare Huawei
    $compat = isset($b['compatibilitate']) ? $b['compatibilitate'] : 'universal';
    if (!in_array($compat, array('universal', 'fronius', 'huawei'), true)) { $compat = 'universal'; }

    $id = isset($b['id']) && trim($b['id']) !== '' ? trim($b['id']) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $marca . '-' . $model));
    while (in_array($id, $ids, true)) { $id .= '-2'; }
    $ids[] = $id;

    $curate[] = array(
        'id' => $id,
        'marca' => $marca,
        'model' => $model,
        'tehnologie' => isset($b['tehnologie']) ? trim($b['tehnologie']) : 'LiFePO4',
        'capacitate_kwh' => $cap,
        'capacitate_utila_kwh' => isset($b['capacitate_utila_kwh']) ? floatval($b['capacitate_utila_kwh']) : $cap,
        'garantie_ani' => isset($b['garantie_ani']) ? intval($b['garantie_ani']) : 0,
        'cicluri' => isset($b['cicluri']) ? intval($b['cicluri']) : 0,
        'retea' => $retea,
        'compatibilitate' => $compat,
        'invertoare_compatibile' => isset($b['invertoare_compatibile']) ? trim($b['invertoare_compatibile']) : '',
        'pret_baterie' => $pret,
        'pret_montaj' => $montaj,
        'pret_invertor_hibrid' => isset($b['pret_invertor_hibrid']) ? floatval($b['pret_invertor_hibrid']) : 0,
        'stoc' => isset($b['stoc']) ? intval($b['stoc']) : 0,
        'termen_zile' => isset($b['termen_zile']) ? intval($b['termen_zile']) : 0,
        'activ' => !empty($b['activ']),
    );
}

if (count($erori) > 0) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'eroare' => implode('; ', $erori)), JSON_UNESCAPED_UNICODE);
    exit;
}

$ok = file_put_contents($fisierJson, json_encode(array(
    'baterii' => $curate,
    'actualizat_la' => date('Y-m-d H:i:s'),
    'actualizat_de' => $_SESSION['utilizator'],
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'eroare' => 'nu s-a putut scrie catalog.json'));
    exit;
}

echo json_encode(array('ok' => true, 'nr_baterii' => count($curate)));
