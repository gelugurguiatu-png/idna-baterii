<?php
// Helper autentificare operatori pentru administrare catalog baterii.
// Utilizatorii stau in api/utilizatori.json (NU e in git, protejat de .htaccess).
// La prima instalare se foloseste api/utilizatori-initial.json (din git),
// cu parola initiala care TREBUIE schimbata la primul login.

function auth_porneste_sesiune() {
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    session_name('BATERIIADMIN');
    // calea cookie = folderul aplicatiei (parintele lui /api/), calculat dinamic:
    // pe server -> /baterii/ ; local (php -S din radacina proiectului) -> /
    $scriptDir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
    $caleApp = rtrim($scriptDir, '/') . '/';
    $params = array(
        'lifetime' => 0,
        'path' => $caleApp,
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']),
    );
    // samesite exista doar din PHP 7.3; fallback silentios pe versiuni vechi
    if (PHP_VERSION_ID >= 70300) {
        $params['samesite'] = 'Lax';
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params($params['lifetime'], $params['path'], '', $params['secure'], true);
    }
    session_start();
}

function auth_fisier_utilizatori() {
    return __DIR__ . '/utilizatori.json';
}

function auth_fisier_utilizatori_initial() {
    return __DIR__ . '/utilizatori-initial.json';
}

// Incarca lista de utilizatori. Daca fisierul real nu exista inca,
// foloseste fisierul initial (cont bootstrap cu schimbare parola fortata).
function auth_incarca_utilizatori() {
    $fisier = auth_fisier_utilizatori();
    if (!file_exists($fisier)) {
        $fisier = auth_fisier_utilizatori_initial();
    }
    if (!file_exists($fisier)) {
        return array();
    }
    $date = json_decode(file_get_contents($fisier), true);
    if (!is_array($date) || !isset($date['utilizatori']) || !is_array($date['utilizatori'])) {
        return array();
    }
    return $date['utilizatori'];
}

// Salveaza lista in fisierul REAL (din acest moment cel initial e ignorat).
function auth_salveaza_utilizatori($utilizatori) {
    $ok = file_put_contents(
        auth_fisier_utilizatori(),
        json_encode(array('utilizatori' => array_values($utilizatori)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
    return $ok !== false;
}

function auth_gaseste_utilizator($nume) {
    foreach (auth_incarca_utilizatori() as $u) {
        if (isset($u['utilizator']) && $u['utilizator'] === $nume) {
            return $u;
        }
    }
    return null;
}

// Raspunde 401 si opreste executia daca nu exista sesiune valida.
function auth_cere_autentificare() {
    auth_porneste_sesiune();
    if (empty($_SESSION['utilizator'])) {
        http_response_code(401);
        echo json_encode(array('ok' => false, 'eroare' => 'neautentificat'));
        exit;
    }
    return $_SESSION['utilizator'];
}

// Ca mai sus, dar cere si rol admin.
function auth_cere_admin() {
    $nume = auth_cere_autentificare();
    $u = auth_gaseste_utilizator($nume);
    if (!$u || !isset($u['rol']) || $u['rol'] !== 'admin') {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'eroare' => 'necesita rol admin'));
        exit;
    }
    return $nume;
}

// Rate limit simplu pe IP pentru login (10 incercari / 15 minute).
function auth_verifica_rate_limit_login() {
    $fisier = __DIR__ . '/rate-limit-login.json';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'necunoscut';
    $acum = time();
    $rate = array();
    if (file_exists($fisier)) {
        $rate = json_decode(file_get_contents($fisier), true);
        if (!is_array($rate)) { $rate = array(); }
    }
    $recente = array();
    $nr_ip = 0;
    foreach ($rate as $intrare) {
        if ($intrare['t'] > $acum - 900) {
            $recente[] = $intrare;
            if ($intrare['ip'] === $ip) { $nr_ip++; }
        }
    }
    if ($nr_ip >= 10) {
        http_response_code(429);
        echo json_encode(array('ok' => false, 'eroare' => 'prea multe incercari, asteapta 15 minute'));
        exit;
    }
    $recente[] = array('ip' => $ip, 't' => $acum);
    file_put_contents($fisier, json_encode($recente), LOCK_EX);
}
