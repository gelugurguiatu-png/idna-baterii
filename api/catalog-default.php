<?php
// Catalog IMPLICIT (in git). Bateriile de aici sunt EXEMPLE si se folosesc
// DOAR pana cand un operator salveaza prima data catalogul in admin
// (care creeaza api/catalog.json pe server - fisier ce NU e in git).
//
// firma + program raman mereu aici (le administreaza Gelu prin git);
// bateriile din catalog.json au prioritate fata de cele de mai jos.

return array(
    'firma' => array(
        'nume' => 'iDNA Power',
        'telefon' => '0744 555 445',
        'email' => 'afm.baterii@idnapower.ro',
        'atestat_anre' => 'Atestat ANRE C1A, C2A',
        'judete' => array(
            'Alba', 'Arad', 'Arges', 'Bacau', 'Bihor', 'Bistrita-Nasaud', 'Botosani',
            'Brasov', 'Braila', 'Bucuresti', 'Buzau', 'Calarasi', 'Caras-Severin',
            'Cluj', 'Constanta', 'Covasna', 'Dambovita', 'Dolj', 'Galati', 'Giurgiu',
            'Gorj', 'Harghita', 'Hunedoara', 'Ialomita', 'Iasi', 'Ilfov', 'Maramures',
            'Mehedinti', 'Mures', 'Neamt', 'Olt', 'Prahova', 'Salaj', 'Satu Mare',
            'Sibiu', 'Suceava', 'Teleorman', 'Timis', 'Tulcea', 'Valcea', 'Vaslui', 'Vrancea'
        ),
    ),
    // Parametri conform ghidului FINAL: Ordin 1904/11.09.2026
    'program' => array(
        'procent_finantare' => 0.75,       // max 75% din valoarea totala (Art. 5)
        'plafon_finantare' => 15000,       // max 15.000 lei (Art. 5)
        'standard_cost' => 1500,           // lei/kWh capacitate (Art. 5 alin. 4)
        'capacitate_minima' => 10,         // kWh minim eligibil (Art. 16)
        'cicluri_minime' => 5000,          // cicluri minime (Art. 16 alin. 2 lit. d)
        'contributie_minima' => 0.25,
        // punctaj (Art. 19 alin. 4) - DOAR 2 criterii, maxim 100:
        'punctaj_contrib_coef' => 30,      // punctaj contributie = 30 x contributie / finantare AFM
        'punctaj_contrib_max' => 50,
        'punctaj_capacitate_coef' => 2.5,  // punctaj capacitate = kWh x 2,5
        'punctaj_capacitate_max' => 50,    // maximul se atinge la 20 kWh
    ),
    'baterii' => array(
        array(
            'id' => 'exemplu-1',
            'marca' => 'EXEMPLU Marca',
            'model' => 'PowerBox 15',
            'tehnologie' => 'LiFePO4',
            'capacitate_kwh' => 15,
            'capacitate_utila_kwh' => 14.2,
            'garantie_ani' => 10,
            'cicluri' => 6000,
            'retea' => 'ambele',
            'compatibilitate' => 'universal',
            'invertoare_compatibile' => 'Deye, SolaX, Growatt (hibride)',
            'pret_baterie' => 19500,
            'pret_montaj' => 3500,
            'pret_invertor_hibrid' => 7500,
            'stoc' => 4,
            'termen_zile' => 14,
            'activ' => true,
        ),
        array(
            'id' => 'exemplu-2',
            'marca' => 'EXEMPLU Marca',
            'model' => 'PowerBox 20',
            'tehnologie' => 'LiFePO4',
            'capacitate_kwh' => 20,
            'capacitate_utila_kwh' => 19,
            'garantie_ani' => 10,
            'cicluri' => 6000,
            'retea' => 'tri',
            'compatibilitate' => 'universal',
            'invertoare_compatibile' => 'Deye, SolaX (hibride trifazate)',
            'pret_baterie' => 26000,
            'pret_montaj' => 4000,
            'pret_invertor_hibrid' => 9500,
            'stoc' => 2,
            'termen_zile' => 14,
            'activ' => true,
        ),
        array(
            'id' => 'exemplu-3',
            'marca' => 'EXEMPLU Marca',
            'model' => 'PowerBox 12 mini',
            'tehnologie' => 'LiFePO4',
            'capacitate_kwh' => 12,
            'capacitate_utila_kwh' => 11.4,
            'garantie_ani' => 7,
            'cicluri' => 4500,
            'retea' => 'mono',
            'compatibilitate' => 'universal',
            'invertoare_compatibile' => 'Growatt, Huawei (hibride monofazate)',
            'pret_baterie' => 15500,
            'pret_montaj' => 3000,
            'pret_invertor_hibrid' => 6500,
            'stoc' => 6,
            'termen_zile' => 10,
            'activ' => true,
        ),
    ),
);
