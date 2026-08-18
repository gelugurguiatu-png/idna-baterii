<?php
// Descarca o oferta PDF arhivata, pe baza token-ului unic din registru.
// GET t=<token> -> fisierul PDF (attachment).

$token = isset($_GET['t']) ? $_GET['t'] : '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Token invalid';
    exit;
}

$fisReg = __DIR__ . '/oferte/registru.json';
if (!file_exists($fisReg)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Oferta inexistenta';
    exit;
}

$registru = json_decode(file_get_contents($fisReg), true);
if (!is_array($registru)) { $registru = array(); }

$gasita = null;
foreach ($registru as $o) {
    if (isset($o['token']) && hash_equals($o['token'], $token) && empty($o['sters'])) { $gasita = $o; break; }
}

if (!$gasita || !file_exists(__DIR__ . '/oferte/' . $gasita['fisier'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Oferta inexistenta';
    exit;
}

$cale = __DIR__ . '/oferte/' . $gasita['fisier'];
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Oferta-iDNA-Power-nr-' . intval($gasita['nr']) . '.pdf"');
header('Content-Length: ' . filesize($cale));
readfile($cale);
