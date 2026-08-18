<?php
// Salveaza cererea de oferta (lead) din calculatorul de baterii AFM.
// Scrie in leads.csv, genereaza oferta PDF cu numar de inregistrare,
// o arhiveaza in api/oferte/, o trimite clientului pe email cu atasament
// si notifica firma. Registrul ofertelor apare in admin (tab Oferte generate).

header('Content-Type: application/json; charset=utf-8');

// ora Romaniei - serverul e pe alt fus orar, altfel ofertele generate seara
// ar purta data zilei precedente
date_default_timezone_set('Europe/Bucharest');

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

// =====================================================================
// Genereaza oferta PDF cu numar de inregistrare, o arhiveaza si o trimite
// clientului pe email. Daca oricare pas esueaza, lead-ul ramane salvat.
// =====================================================================
$oferta_info = null;
$email_client_trimis = false;
$baterie_id = isset($date['baterie_id']) ? trim($date['baterie_id']) : '';

if ($baterie_id !== '' && $baterie_id !== 'personalizat') {
    require_once __DIR__ . '/oferta-pdf.php';

    // catalogul curent (aceeasi logica precum catalog.php)
    $catalog = require __DIR__ . '/catalog-default.php';
    $fisCat = __DIR__ . '/catalog.json';
    if (file_exists($fisCat)) {
        $salvat = json_decode(file_get_contents($fisCat), true);
        if (is_array($salvat) && isset($salvat['baterii']) && is_array($salvat['baterii'])) {
            $catalog['baterii'] = $salvat['baterii'];
        }
    }

    // folderul arhivei de oferte (protejat de acces direct)
    $dirOferte = __DIR__ . '/oferte';
    if (!is_dir($dirOferte)) {
        @mkdir($dirOferte, 0755, true);
        @file_put_contents($dirOferte . '/.htaccess', "Require all denied\n");
    }

    // registru cu blocare: numarul urmator = cate oferte exista + 1
    $fisReg = $dirOferte . '/registru.json';
    $fh = fopen($fisReg, 'c+');
    if ($fh !== false && flock($fh, LOCK_EX)) {
        $continutReg = stream_get_contents($fh);
        $registru = json_decode($continutReg, true);
        if (!is_array($registru)) { $registru = array(); }
        $nrOferta = count($registru) + 1;
        $nrText = $nrOferta . '/' . date('d.m.Y');

        $rezultat = oferta_pdf_genereaza($date, $catalog, $nrText);
        if (!isset($rezultat['eroare'])) {
            $numeFisier = sprintf('oferta-%04d.pdf', $nrOferta);
            if (file_put_contents($dirOferte . '/' . $numeFisier, $rezultat['pdf']) !== false) {
                $token = bin2hex(random_bytes(16));
                $registru[] = array(
                    'nr' => $nrOferta,
                    'nr_text' => $nrText,
                    'data_ora' => date('Y-m-d H:i:s'),
                    'nume' => $nume,
                    'telefon' => $telefon,
                    'email' => $email,
                    'judet' => isset($date['judet']) ? $date['judet'] : '',
                    'localitate' => isset($date['localitate']) ? $date['localitate'] : '',
                    'baterie_id' => $baterie_id,
                    'baterie' => $rezultat['meta']['baterie'],
                    'capacitate_kwh' => $rezultat['meta']['capacitate_kwh'],
                    'valoare_totala' => $rezultat['meta']['valoare_totala'],
                    'finantare_afm' => $rezultat['meta']['finantare_afm'],
                    'contributie_client' => $rezultat['meta']['contributie_client'],
                    'punctaj' => $rezultat['meta']['punctaj'],
                    'fisier' => $numeFisier,
                    'token' => $token,
                );
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, json_encode($registru, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                fflush($fh);

                $oferta_info = array(
                    'nr' => $nrOferta,
                    'nr_text' => $nrText,
                    'url' => 'api/descarca-oferta.php?t=' . $token,
                );

                // email catre CLIENT cu oferta atasata (multipart MIME)
                $numePdfClient = 'Oferta-iDNA-Power-nr-' . $nrOferta . '.pdf';
                $granita = 'b' . md5(uniqid('', true));
                $subiectClient = '=?UTF-8?B?' . base64_encode('Oferta dvs. iDNA Power nr. ' . $nrText . ' - sistem de stocare a energiei') . '?=';
                $corpText = "Buna ziua, " . $nume . ",\n\n"
                    . "Va multumim pentru interesul acordat! Atasat gasiti oferta dvs. nr. " . $nrText
                    . " pentru " . $rezultat['meta']['baterie'] . ".\n\n"
                    . "Pe scurt:\n"
                    . "  - Valoare totala proiect: " . nr_ro($rezultat['meta']['valoare_totala'], 0) . " lei (TVA inclusa)\n"
                    . "  - Finantare AFM: " . nr_ro($rezultat['meta']['finantare_afm'], 0) . " lei\n"
                    . "  - Contributia dvs.: " . nr_ro($rezultat['meta']['contributie_client'], 0) . " lei\n"
                    . "  - Punctaj estimat: " . nr_ro($rezultat['meta']['punctaj'], 1) . " / 100\n\n"
                    . "In paginile ofertei aveti si datele exacte pentru inscrierea in programul AFM, documentele necesare si pasii urmatori.\n\n"
                    . "Va contactam in cel mai scurt timp. Pentru orice intrebare: " . $EMAIL_NOTIFICARE . "\n\n"
                    . "Cu stima,\nEchipa iDNA Power\n";
                $anteteClient = 'From: iDNA Power <' . $EMAIL_NOTIFICARE . '>' . "\r\n"
                    . 'Reply-To: ' . $EMAIL_NOTIFICARE . "\r\n"
                    . 'MIME-Version: 1.0' . "\r\n"
                    . 'Content-Type: multipart/mixed; boundary="' . $granita . '"';
                $corpMime = '--' . $granita . "\r\n"
                    . 'Content-Type: text/plain; charset=utf-8' . "\r\n"
                    . 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n"
                    . $corpText . "\r\n"
                    . '--' . $granita . "\r\n"
                    . 'Content-Type: application/pdf; name="' . $numePdfClient . '"' . "\r\n"
                    . 'Content-Transfer-Encoding: base64' . "\r\n"
                    . 'Content-Disposition: attachment; filename="' . $numePdfClient . '"' . "\r\n\r\n"
                    . chunk_split(base64_encode($rezultat['pdf'])) . "\r\n"
                    . '--' . $granita . '--';
                $email_client_trimis = @mail($email, $subiectClient, $corpMime, $anteteClient);
            }
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    } elseif ($fh !== false) {
        fclose($fh);
    }
}

// email de notificare catre firma (daca esueaza, lead-ul ramane oricum in CSV)
$subiect = 'Lead nou baterii AFM' . ($oferta_info ? ' - oferta nr. ' . $oferta_info['nr_text'] : '') . ': ' . $nume . ' - ' . (isset($date['baterie']) ? $date['baterie'] : '');
$mesaj = "Cerere noua din calculatorul de baterii AFM"
    . ($oferta_info ? " (oferta nr. " . $oferta_info['nr_text'] . ", trimisa clientului: " . ($email_client_trimis ? 'DA' : 'NU') . ")" : '') . ":\n\n";
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

echo json_encode(array('ok' => true, 'oferta' => $oferta_info, 'email_trimis' => $email_client_trimis));
