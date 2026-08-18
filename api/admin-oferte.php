<?php
// Registrul ofertelor generate, pentru tabul "Oferte generate" din admin.
// GET (autentificat) -> {ok, oferte: [...]} - cele mai noi primele.

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/auth.php';

auth_cere_autentificare();

$fisReg = __DIR__ . '/oferte/registru.json';
$registru = array();
if (file_exists($fisReg)) {
    $registru = json_decode(file_get_contents($fisReg), true);
    if (!is_array($registru)) { $registru = array(); }
}

echo json_encode(array('ok' => true, 'oferte' => array_reverse($registru)), JSON_UNESCAPED_UNICODE);
