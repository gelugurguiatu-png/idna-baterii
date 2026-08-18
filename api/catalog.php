<?php
// Endpoint PUBLIC: catalogul pentru calculator.
// firma + program vin din catalog-default.php (git);
// bateriile vin din catalog.json (salvat de operatori in admin) daca exista,
// altfel din default. Public se livreaza DOAR bateriile active cu stoc.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$catalog = require __DIR__ . '/catalog-default.php';

$fisierJson = __DIR__ . '/catalog.json';
if (file_exists($fisierJson)) {
    $salvat = json_decode(file_get_contents($fisierJson), true);
    if (is_array($salvat) && isset($salvat['baterii']) && is_array($salvat['baterii'])) {
        $catalog['baterii'] = $salvat['baterii'];
    }
}

// public: doar bateriile active
$active = array();
foreach ($catalog['baterii'] as $b) {
    if (!empty($b['activ'])) { $active[] = $b; }
}
$catalog['baterii'] = $active;

echo json_encode($catalog, JSON_UNESCAPED_UNICODE);
