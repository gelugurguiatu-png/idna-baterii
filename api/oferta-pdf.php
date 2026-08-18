<?php
// Genereaza oferta PDF (A4, 3 pagini) dupa identitatea vizuala iDNA Power.
// POST field "date" = JSON: {baterie_id, putere_pv, invertor_tip, contributie_extra,
//                            nume, judet, localitate, consum_lunar}
// Calculele se REFAC pe server din catalog (nu se preiau sume din client).

require __DIR__ . '/../lib/tfpdf/tfpdf.php';
require_once __DIR__ . '/../lib/tfpdf/font/unifont/ttfonts.php';

$TVA = 0.21; // cota TVA folosita si in oferta model

// ---------- date intrare ----------
$json = isset($_POST['date']) ? $_POST['date'] : '';
$in = json_decode($json, true);
if (!is_array($in)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Date invalide';
    exit;
}

$nume = isset($in['nume']) ? trim($in['nume']) : '';
$judet = isset($in['judet']) ? trim($in['judet']) : '';
$localitate = isset($in['localitate']) ? trim($in['localitate']) : '';
$putere_pv = isset($in['putere_pv']) ? floatval($in['putere_pv']) : 0;
$invertor_tip = isset($in['invertor_tip']) ? $in['invertor_tip'] : '';
$contributie_extra = isset($in['contributie_extra']) ? max(0, floatval($in['contributie_extra'])) : 0;
$baterie_id = isset($in['baterie_id']) ? $in['baterie_id'] : '';

if ($nume === '' || $baterie_id === '' || $putere_pv <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Lipsesc date obligatorii';
    exit;
}

// ---------- catalog (aceeasi sursa ca site-ul) ----------
$catalog = require __DIR__ . '/catalog-default.php';
$fisierJson = __DIR__ . '/catalog.json';
if (file_exists($fisierJson)) {
    $salvat = json_decode(file_get_contents($fisierJson), true);
    if (is_array($salvat) && isset($salvat['baterii']) && is_array($salvat['baterii'])) {
        $catalog['baterii'] = $salvat['baterii'];
    }
}

$b = null;
foreach ($catalog['baterii'] as $bat) {
    if (isset($bat['id']) && $bat['id'] === $baterie_id && !empty($bat['activ'])) { $b = $bat; break; }
}
if (!$b) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Baterie inexistenta';
    exit;
}

// ---------- calcule (identice cu JS-ul din index.html) ----------
$P = $catalog['program'];
$F = $catalog['firma'];

$eligibile = $b['pret_baterie'] + $b['pret_montaj'];
$plafon_cost = $b['capacitate_kwh'] * $P['standard_cost'];
$eligibile_plafonate = min($eligibile, $plafon_cost);
$invertor_cost = ($invertor_tip === 'hibrid') ? 0 : floatval($b['pret_invertor_hibrid']);
$total = $b['pret_baterie'] + $b['pret_montaj'] + $invertor_cost;
$afm_baza = min($total * $P['procent_finantare'], $P['plafon_finantare'], $eligibile_plafonate);
$prag = ($P['punctaj_contrib_max'] + $P['punctaj_contrib_minus']) / $P['punctaj_contrib_coef'];
$extra_util_max = max(0, round($afm_baza - (1 - $prag) * $total));
$extra_aplicat = min($contributie_extra, $extra_util_max);
$afm = max(0, $afm_baza - $extra_aplicat);
$client = $total - $afm;
$procent = $client / $total;
$p_contrib = min(max($P['punctaj_contrib_coef'] * $procent - $P['punctaj_contrib_minus'], 0), $P['punctaj_contrib_max']);
$p_cap = min($b['capacitate_kwh'], $P['punctaj_capacitate_max']);
$p_pv = min($putere_pv, $P['punctaj_pv_max']);
$punctaj = $p_contrib + $p_cap + $p_pv;

$total_fara_tva = $total / (1 + $TVA);
$val_tva = $total - $total_fara_tva;

$nr_oferta = date('Ymd-Hi');
$data_oferta = date('d.m.Y');

// formatare numere romaneasca: 40.651,62
function nr_ro($v, $dec = 2) {
    return number_format($v, $dec, ',', '.');
}

// ---------- culori identitate ----------
$VERDE = array(22, 140, 60);      // verde iDNA (tabel, accente)
$VERDE_INCHIS = array(13, 96, 41);
$NAVY = array(43, 49, 114);       // titluri coperta
$GRI_DESCHIS = array(237, 237, 237);

class OfertaPDF extends tFPDF
{
    // dreptunghi cu colturi rotunjite (snippet clasic FPDF)
    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F') $op = 'f';
        elseif ($style == 'FD' || $style == 'DF') $op = 'B';
        else $op = 'S';
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r; $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r; $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }
    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k,
            $x3 * $this->k, ($h - $y3) * $this->k));
    }
    // numarul de linii pe care le-ar ocupa un text intr-o celula de latime $w
    function NbLines($w, $txt)
    {
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin);
        $txt = str_replace("\r", '', (string)$txt);
        $linii = explode("\n", $txt);
        $nb = 0;
        foreach ($linii as $linie) {
            if ($linie === '') { $nb++; continue; }
            $cuvinte = explode(' ', $linie);
            $curent = '';
            $nb++;
            foreach ($cuvinte as $cuvant) {
                $proba = ($curent === '') ? $cuvant : $curent . ' ' . $cuvant;
                if ($this->GetStringWidth($proba) <= $wmax) {
                    $curent = $proba;
                } else {
                    $curent = $cuvant;
                    $nb++;
                }
            }
        }
        return $nb;
    }

    // romb rotunjit plin (decor coperta, ca in modelul original)
    function Romb($cx, $cy, $raza, $rgb)
    {
        $this->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $k = $this->k; $hp = $this->h;
        $this->_out(sprintf('%.2F %.2F m', ($cx) * $k, ($hp - ($cy - $raza)) * $k));
        $this->_out(sprintf('%.2F %.2F l', ($cx + $raza) * $k, ($hp - $cy) * $k));
        $this->_out(sprintf('%.2F %.2F l', ($cx) * $k, ($hp - ($cy + $raza)) * $k));
        $this->_out(sprintf('%.2F %.2F l', ($cx - $raza) * $k, ($hp - $cy) * $k));
        $this->_out('f');
    }
}

$pdf = new OfertaPDF('P', 'mm', 'A4');
$pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
$pdf->AddFont('DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
$pdf->SetAutoPageBreak(false);
$pdf->SetTitle('Oferta iDNA Power - sistem stocare ' . $b['capacitate_kwh'] . ' kWh');

$IMG = __DIR__ . '/../img/';

// =====================================================================
// PAGINA 1 - COPERTA
// =====================================================================
$pdf->AddPage();

// decor romburi verzi stanga-sus (ca in model)
$pdf->Romb(8, 15, 16, $VERDE);
$pdf->Romb(38, 6, 9, $VERDE_INCHIS);
$pdf->Romb(78, 12, 7, $VERDE);
$pdf->Romb(12, 52, 6, $VERDE_INCHIS);
// foto panouri (are masca alba tip romb inclusa in imagine)
if (file_exists($IMG . 'coperta-foto.jpg')) {
    $pdf->Image($IMG . 'coperta-foto.jpg', 22, 18, 88);
}
// logo dreapta-sus
if (file_exists($IMG . 'logo-idna.png')) {
    $pdf->Image($IMG . 'logo-idna.png', 138, 22, 52);
}

// titlu
$pdf->SetTextColor($NAVY[0], $NAVY[1], $NAVY[2]);
$pdf->SetFont('DejaVu', 'B', 26);
$pdf->SetXY(15, 150);
$pdf->Cell(180, 12, 'OFERTĂ TEHNICO-ECONOMICĂ', 0, 1, 'C');
$pdf->SetFont('DejaVu', 'B', 14);
$pdf->SetX(15);
$pdf->Cell(180, 9, 'Nr. înregistrare ' . $nr_oferta . ' / ' . $data_oferta, 0, 1, 'C');

// subiect verde
$pdf->Ln(14);
$pdf->SetTextColor($VERDE_INCHIS[0], $VERDE_INCHIS[1], $VERDE_INCHIS[2]);
$pdf->SetFont('DejaVu', 'B', 17);
$pdf->SetX(15);
$pdf->Cell(180, 9, 'SISTEM DE STOCARE A ENERGIEI ' . nr_ro($b['capacitate_kwh'], 1) . ' kWh', 0, 1, 'C');
$pdf->SetX(15);
$pdf->Cell(180, 9, 'Beneficiar: ' . mb_strtoupper($nume, 'UTF-8'), 0, 1, 'C');
$pdf->SetFont('DejaVu', '', 12);
$loc = trim($localitate . ($judet !== '' ? ', jud. ' . $judet : ''), ', ');
if ($loc !== '') {
    $pdf->SetX(15);
    $pdf->Cell(180, 8, $loc, 0, 1, 'C');
}
$pdf->Ln(4);
$pdf->SetTextColor(120, 120, 120);
$pdf->SetFont('DejaVu', '', 10);
$pdf->SetX(15);
$pdf->Cell(180, 6, 'Program AFM 2026 - instalare sisteme de stocare a energiei din surse regenerabile', 0, 1, 'C');

// banda verde jos cu datele firmei
$pdf->SetFillColor($VERDE[0], $VERDE[1], $VERDE[2]);
$pdf->RoundedRect(8, 252, 194, 38, 6, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('DejaVu', 'B', 15);
$pdf->SetXY(8, 256);
$pdf->Cell(194, 8, 'S.C. iDNA POWER S.R.L.', 0, 1, 'C');
$pdf->SetFont('DejaVu', 'B', 9);
$pdf->SetXY(14, 267);  $pdf->Cell(60, 5, 'Tel.', 0, 0, 'C');
$pdf->SetXY(75, 267);  $pdf->Cell(60, 5, 'Adresă', 0, 0, 'C');
$pdf->SetXY(136, 267); $pdf->Cell(60, 5, 'E-mail', 0, 0, 'C');
$pdf->SetFont('DejaVu', '', 9);
$pdf->SetXY(14, 272);  $pdf->Cell(60, 5, $F['telefon'], 0, 0, 'C');
$pdf->SetXY(75, 272);  $pdf->MultiCell(60, 4.5, "Str. Cuza Vodă, Nr. 102B,\nFocșani, Vrancea", 0, 'C');
$pdf->SetXY(136, 272); $pdf->Cell(60, 5, $F['email'], 0, 0, 'C');

// =====================================================================
// PAGINA 2 - OFERTA FINANCIARA
// =====================================================================
$pdf->AddPage();
if (file_exists($IMG . 'logo-idna.png')) {
    $pdf->Image($IMG . 'logo-idna.png', 85, 12, 40);
}

// tabel echipamente (ca in model: antet verde, alb, bold)
$y = 52;
$pdf->SetXY(12, $y);
$pdf->SetFillColor($VERDE[0], $VERDE[1], $VERDE[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('DejaVu', 'B', 10.5);
$pdf->SetDrawColor(180, 180, 180);
$pdf->Cell(16, 12, 'Nr. crt.', 1, 0, 'C', true);
$pdf->Cell(120, 12, 'Denumire', 1, 0, 'C', true);
$pdf->Cell(20, 12, 'UM', 1, 0, 'C', true);
$pdf->Cell(30, 12, 'Cantitate', 1, 1, 'C', true);

$pdf->SetTextColor(30, 30, 30);
$randuri = array();
$randuri[] = array('Sistem de stocare ' . $b['marca'] . ' ' . $b['model'] . ' - ' . nr_ro($b['capacitate_kwh'], 1) . ' kWh (' . $b['tehnologie'] . ', garanție ' . $b['garantie_ani'] . ' ani, ' . nr_ro($b['cicluri'], 0) . ' cicluri)');
if ($invertor_cost > 0) {
    $randuri[] = array('Invertor hibrid compatibil - furnizare și montaj');
}
$randuri[] = array('Montaj și punere în funcțiune sistem de stocare');

$nr = 1;
foreach ($randuri as $r) {
    $pdf->SetFont('DejaVu', '', 10);
    $x0 = 12; $y0 = $pdf->GetY();
    // inaltime variabila dupa numarul de linii ale textului
    $linii = $pdf->NbLines(120, $r[0]);
    $h = max(11, $linii * 5.5 + 4);
    // chenarele
    $pdf->SetXY($x0, $y0);
    $pdf->Cell(16, $h, $nr, 1, 0, 'C');
    $pdf->Cell(120, $h, '', 1, 0);
    $pdf->Cell(20, $h, 'buc.', 1, 0, 'C');
    $pdf->Cell(30, $h, '1', 1, 1, 'C');
    // textul denumirii, centrat vertical in celula
    $pdf->SetXY($x0 + 16, $y0 + ($h - $linii * 5.5) / 2);
    $pdf->MultiCell(120, 5.5, $r[0], 0, 'L');
    $pdf->SetXY($x0, $y0 + $h);
    $nr++;
}

// totaluri generale (ca in model)
$pdf->Ln(6);
$pdf->SetX(12);
$pdf->SetFillColor($GRI_DESCHIS[0], $GRI_DESCHIS[1], $GRI_DESCHIS[2]);
$pdf->SetFont('DejaVu', 'B', 10.5);
$pdf->Cell(136, 11, 'Total general LEI fără TVA', 1, 0, 'L', true);
$pdf->Cell(50, 11, nr_ro($total_fara_tva), 1, 1, 'R', true);
$pdf->SetX(12);
$pdf->Cell(136, 11, 'Valoare TVA ' . nr_ro($TVA * 100) . '% (RON)', 1, 0, 'L', true);
$pdf->Cell(50, 11, nr_ro($val_tva), 1, 1, 'R', true);
$pdf->SetX(12);
$pdf->SetLineWidth(0.5);
$pdf->Cell(136, 11, 'Total general LEI cu TVA inclus', 1, 0, 'L', true);
$pdf->Cell(50, 11, nr_ro($total), 1, 1, 'R', true);
$pdf->SetLineWidth(0.2);

// sectiunea finantare AFM
$pdf->Ln(8);
$yA = $pdf->GetY();
$pdf->SetFillColor($VERDE[0], $VERDE[1], $VERDE[2]);
$pdf->RoundedRect(12, $yA, 186, 46, 4, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('DejaVu', 'B', 12);
$pdf->SetXY(18, $yA + 5);
$pdf->Cell(174, 7, 'Finanțare prin Programul AFM 2026', 0, 1);
$pdf->SetFont('DejaVu', '', 11);
$pdf->SetXY(18, $yA + 14);
$pdf->Cell(120, 7, 'Finanțare acordată de AFM (' . nr_ro((1 - $procent) * 100, 1) . '% din valoarea totală)', 0, 0);
$pdf->SetFont('DejaVu', 'B', 12);
$pdf->Cell(54, 7, '-' . nr_ro($afm, 0) . ' lei', 0, 1, 'R');
$pdf->SetFont('DejaVu', 'B', 13);
$pdf->SetXY(18, $yA + 26);
$pdf->Cell(120, 8, 'CONTRIBUȚIA DUMNEAVOASTRĂ (' . nr_ro($procent * 100, 1) . '%)', 0, 0);
$pdf->SetFont('DejaVu', 'B', 15);
$pdf->Cell(54, 8, nr_ro($client, 0) . ' lei', 0, 1, 'R');
$pdf->SetFont('DejaVu', '', 8.5);
$pdf->SetXY(18, $yA + 37);
$pdf->Cell(174, 5, 'Finanțarea AFM se evidențiază pe factură și se încasează de instalator direct de la AFM - dvs. plătiți doar contribuția proprie.', 0, 1);

// punctaj
$yP = $yA + 52;
$pdf->SetDrawColor($VERDE[0], $VERDE[1], $VERDE[2]);
$pdf->SetLineWidth(0.6);
$pdf->RoundedRect(12, $yP, 186, 34, 4, 'D');
$pdf->SetLineWidth(0.2);
$pdf->SetTextColor($VERDE_INCHIS[0], $VERDE_INCHIS[1], $VERDE_INCHIS[2]);
$pdf->SetFont('DejaVu', 'B', 12);
$pdf->SetXY(18, $yP + 4);
$pdf->Cell(120, 8, 'PUNCTAJ ESTIMAT ÎN PROGRAM', 0, 0);
$pdf->SetFont('DejaVu', 'B', 17);
$pdf->Cell(54, 8, nr_ro($punctaj, 1) . ' / 100', 0, 1, 'R');
$pdf->SetTextColor(70, 70, 70);
$pdf->SetFont('DejaVu', '', 9.5);
$pdf->SetXY(18, $yP + 15);
$pdf->Cell(174, 5.5, 'Contribuție proprie: ' . nr_ro($p_contrib, 1) . ' pct (max 40)   ·   Capacitate stocare: ' . nr_ro($p_cap, 1) . ' pct (max 40)   ·   Putere fotovoltaică: ' . nr_ro($p_pv, 1) . ' pct (max 20)', 0, 1);
$pdf->SetXY(18, $yP + 22);
$pdf->SetFont('DejaVu', '', 8.5);
$pdf->MultiCell(174, 4.5, 'Proiectele se finanțează în ordinea descrescătoare a punctajului, în limita bugetului sesiunii. Punctajul final se calculează de aplicația AFM pe baza datelor declarate la înscriere.', 0, 'L');

// subsol pagina 2
$pdf->SetTextColor(130, 130, 130);
$pdf->SetFont('DejaVu', '', 8);
$pdf->SetXY(12, 280);
$pdf->Cell(186, 5, 'Ofertă informativă valabilă 30 de zile de la emitere. Prețurile includ TVA. Nu reprezintă un angajament de finanțare din partea AFM.', 0, 0, 'C');

// =====================================================================
// PAGINA 3 - DATE INSCRIERE AFM + PASI
// =====================================================================
$pdf->AddPage();
if (file_exists($IMG . 'logo-idna.png')) {
    $pdf->Image($IMG . 'logo-idna.png', 85, 12, 40);
}

$pdf->SetTextColor($NAVY[0], $NAVY[1], $NAVY[2]);
$pdf->SetFont('DejaVu', 'B', 15);
$pdf->SetXY(12, 50);
$pdf->Cell(186, 9, 'Datele dumneavoastră pentru cererea de finanțare AFM', 0, 1, 'C');
$pdf->SetTextColor(90, 90, 90);
$pdf->SetFont('DejaVu', '', 9.5);
$pdf->SetX(12);
$pdf->Cell(186, 6, 'Valorile de mai jos se completează în aplicația AFM la înscriere (secțiunile B și C din cererea de finanțare):', 0, 1, 'C');

$pdf->Ln(4);
$date_afm = array(
    array('Capacitatea sistemului de stocare', nr_ro($b['capacitate_kwh'], 1) . ' kWh'),
    array('Puterea instalată a sistemului fotovoltaic', nr_ro($putere_pv, 1) . ' kW'),
    array('Procentul contribuției proprii', nr_ro($procent * 100, 1) . ' %'),
    array('Valoarea totală a proiectului', nr_ro($total, 0) . ' lei'),
    array('Contribuție proprie', nr_ro($client, 0) . ' lei'),
    array('Finanțare solicitată de la AFM', nr_ro($afm, 0) . ' lei'),
);
$pdf->SetDrawColor(180, 180, 180);
foreach ($date_afm as $i => $r) {
    $pdf->SetX(25);
    $pdf->SetFont('DejaVu', '', 10.5);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->SetFillColor(($i % 2 === 0) ? 247 : 255, ($i % 2 === 0) ? 247 : 255, ($i % 2 === 0) ? 247 : 255);
    $pdf->Cell(110, 10, $r[0], 1, 0, 'L', true);
    $pdf->SetFont('DejaVu', 'B', 10.5);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->Cell(50, 10, $r[1], 1, 1, 'R', true);
}

$pdf->Ln(7);
$pdf->SetTextColor($VERDE_INCHIS[0], $VERDE_INCHIS[1], $VERDE_INCHIS[2]);
$pdf->SetFont('DejaVu', 'B', 12.5);
$pdf->SetX(12);
$pdf->Cell(186, 8, 'Documente necesare la înscriere (le încărcați în aplicația AFM)', 0, 1);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('DejaVu', '', 10);
$documente = array(
    'Actul de identitate, valabil la data înscrierii',
    'Certificat de atestare fiscală ANAF - bugetul de stat (online, din Spațiul Privat Virtual)',
    'Certificat de atestare fiscală de la primăria de domiciliu (taxe locale)',
    'Certificat fiscal de la primăria locului de montaj - doar dacă diferă de domiciliu',
    'Contractul de prosumator (de la furnizorul de energie)',
    'Certificatul de racordare (de la operatorul de rețea)',
    'Factură de energie electrică recentă (max. 3 luni vechime)',
);
foreach ($documente as $doc) {
    $pdf->SetX(16);
    $pdf->MultiCell(180, 6, '• ' . $doc, 0, 'L');
}
$pdf->SetFont('DejaVu', '', 8.5);
$pdf->SetTextColor(150, 100, 20);
$pdf->SetX(16);
$pdf->MultiCell(180, 4.5, 'Important: documentele emise electronic se încarcă exact în formatul primit (nu scanate/fotografiate). Odată trimise, nu mai pot fi modificate.', 0, 'L');

$pdf->Ln(5);
$pdf->SetTextColor($VERDE_INCHIS[0], $VERDE_INCHIS[1], $VERDE_INCHIS[2]);
$pdf->SetFont('DejaVu', 'B', 12.5);
$pdf->SetX(12);
$pdf->Cell(186, 8, 'Pașii următori', 0, 1);
$pdf->SetTextColor(60, 60, 60);
$pdf->SetFont('DejaVu', '', 10);
$pasi = array(
    'Vă contactăm cu oferta fermă și stabilim detaliile tehnice.',
    'Când AFM deschide sesiunea, vă înscrieți în aplicația AFM cu datele din această ofertă - vă ghidăm pas cu pas.',
    'După aprobare, ne selectați ca instalator validat în aplicația AFM (aveți la dispoziție 90 de zile).',
    'Instalăm sistemul de stocare, îl punem în funcțiune și actualizăm certificatul de racordare.',
    'Plătiți doar contribuția proprie - finanțarea AFM o încasăm direct de la stat.',
);
foreach ($pasi as $i => $pas) {
    $pdf->SetX(16);
    $pdf->MultiCell(180, 6, ($i + 1) . '. ' . $pas, 0, 'L');
}

// banda verde jos (contact)
$pdf->SetFillColor($VERDE[0], $VERDE[1], $VERDE[2]);
$pdf->RoundedRect(8, 267, 194, 24, 5, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('DejaVu', 'B', 11);
$pdf->SetXY(8, 271);
$pdf->Cell(194, 7, 'Aveți întrebări? Sunați-ne: ' . $F['telefon'], 0, 1, 'C');
$pdf->SetFont('DejaVu', '', 9);
$pdf->SetX(8);
$pdf->Cell(194, 6, $F['email'] . '  ·  ' . $F['nume'] . '  ·  ' . $F['atestat_anre'], 0, 1, 'C');

// ---------- livrare ----------
$numeFisier = 'Oferta-iDNA-Power-' . preg_replace('/[^A-Za-z0-9]+/', '-', $b['marca'] . '-' . nr_ro($b['capacitate_kwh'], 1) . 'kWh') . '.pdf';
$pdf->Output('D', $numeFisier);
