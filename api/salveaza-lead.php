<?php
// Salveaza cererea de oferta (lead) din calculatorul de baterii AFM.
// Scrie in leads.csv (protejat prin .htaccess) si trimite email de notificare.

header('Content-Type: application/json; charset=utf-8');

// emailul pe care primesti notificarile de lead nou
$EMAIL_NOTIFICARE = 'afm.baterii@idnapower.ro';

// accepta doar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'eroare' => 'metoda invalida'));
    exit;
}

$corp = file_get_contents('php://input');
$date = json_decode($corp, true);

if (!is_array($date)) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'eroare' => 'date invalide'));
    exit;
}

// validare minima server-side
$nume = isset($date['nume']) ? trim($date['nume']) : '';
$telefon = isset($date['telefon']) ? trim($date['telefon']) : '';
$email = isset($date['email']) ? trim($date['email']) : '';
if (strlen($nume) < 3 || strlen($telefon) < 10 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(array('ok' => false, 'eroare' => 'nume, telefon sau email invalid'));
    exit;
}

// anti-spam simplu: max 5 cereri pe ora de la acelasi IP
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'necunoscut';
$fisier_rate = __DIR__ . '/rate-limit.json';
$acum = time();
$rate = array();
if (file_exists($fisier_rate)) {
    $rate = json_decode(file_get_contents($fisier_rate), true);
    if (!is_array($rate)) { $rate = array(); }
}
// pastreaza doar intrarile din ultima ora
$recente = array();
foreach ($rate as $intrare) {
    if ($intrare['t'] > $acum - 3600) { $recente[] = $intrare; }
}
$nr_ip = 0;
foreach ($recente as $intrare) {
    if ($intrare['ip'] === $ip) { $nr_ip++; }
}
if ($nr_ip >= 5) {
    http_response_code(429);
    echo json_encode(array('ok' => false, 'eroare' => 'prea multe cereri, incearca mai tarziu'));
    exit;
}
$recente[] = array('ip' => $ip, 't' => $acum);
file_put_contents($fisier_rate, json_encode($recente), LOCK_EX);

// campurile salvate, in ordine fixa
$campuri = array(
    'nume', 'telefon', 'email', 'judet', 'localitate',
    'putere_pv', 'invertor_model', 'invertor_tip', 'retea', 'consum_lunar',
    'baterie', 'valoare_totala', 'finantare_afm', 'contributie_client', 'punctaj',
    'contributie_extra', 'prosumator', 'are_baterie', 'activitati', 'datorii'
);

$rand = array(date('Y-m-d H:i:s'), $ip);
foreach ($campuri as $c) {
    $val = isset($date[$c]) ? $date[$c] : '';
    if (is_bool($val)) { $val = $val ? 'da' : 'nu'; }
    $rand[] = is_scalar($val) ? (string)$val : json_encode($val);
}

// scrie in CSV (cu antet la prima scriere)
$fisier_csv = __DIR__ . '/leads.csv';
$exista = file_exists($fisier_csv);
$fh = fopen($fisier_csv, 'a');
if ($fh === false) {
    http_response_code(500);
    echo json_encode(array('ok' => false, 'eroare' => 'nu s-a putut salva'));
    exit;
}
if (!$exista) {
    fputcsv($fh, array_merge(array('data', 'ip'), $campuri));
}
fputcsv($fh, $rand);
fclose($fh);

// trimite email de notificare (daca esueaza, lead-ul ramane oricum in CSV)
$subiect = 'Lead nou baterii AFM: ' . $nume . ' - ' . (isset($date['baterie']) ? $date['baterie'] : '');
$mesaj = "Cerere noua din calculatorul de baterii AFM:\n\n";
foreach ($campuri as $c) {
    $val = isset($date[$c]) ? $date[$c] : '';
    if (is_bool($val)) { $val = $val ? 'da' : 'nu'; }
    if (is_scalar($val)) {
        $mesaj .= str_pad($c, 20) . ': ' . $val . "\n";
    }
}
$antete = 'From: calculator@idnapower.ro' . "\r\n" .
          'Reply-To: ' . $email . "\r\n" .
          'Content-Type: text/plain; charset=utf-8';
@mail($EMAIL_NOTIFICARE, $subiect, $mesaj, $antete);

echo json_encode(array('ok' => true));
