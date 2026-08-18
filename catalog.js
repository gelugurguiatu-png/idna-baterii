// ============================================================
// CATALOG BATERII + SETARI PROGRAM AFM
// Acesta e SINGURUL fisier pe care il editezi cand se schimba
// stocul, preturile sau regulile programului.
// Dupa editare: salveaza si urca fisierul pe server (suprascrie).
// ============================================================

window.CATALOG = {

  // ---------- DATELE FIRMEI ----------
  firma: {
    nume: "iDNA Power",
    telefon: "0740 000 000",            // <-- COMPLETEAZA telefonul real
    email: "office@idnapower.ro",       // <-- COMPLETEAZA emailul real (afisat clientului)
    atestat_anre: "Atestat ANRE tip B", // afisat pentru incredere
    judete: ["Galati", "Braila", "Vrancea", "Vaslui", "Tulcea"] // judete unde faci montaj
  },

  // ---------- PARAMETRII PROGRAMULUI AFM (Ghid 2026) ----------
  // Se schimba DOAR daca AFM modifica ghidul.
  program: {
    procent_finantare: 0.75,     // max 75% din valoarea totala (Art. 5)
    plafon_finantare: 15000,     // max 15.000 lei (Art. 5)
    standard_cost: 1250,         // lei / kWh capacitate (Art. 5 alin. 4)
    capacitate_minima: 12,       // kWh minim eligibil (Art. 16)
    contributie_minima: 0.25,    // 25% minim contributie proprie
    // punctaj (Art. 19 alin. 4)
    punctaj_contrib_coef: 80,    // punctaj = 80 x procent - 10
    punctaj_contrib_minus: 10,
    punctaj_contrib_max: 40,
    punctaj_capacitate_max: 40,  // 1 pct / kWh, max 40
    punctaj_pv_max: 20           // 1 pct / kW, max 20
  },

  // ---------- BATERII DIN STOC ----------
  // ATENTIE: randurile de mai jos sunt EXEMPLE. Inlocuieste-le cu
  // produsele tale reale (marca, model, preturi cu TVA).
  // Campuri:
  //   activ: true/false - apare sau nu in calculator
  //   retea: "mono" / "tri" / "ambele"
  //   pret_baterie, pret_montaj: lei cu TVA (cheltuieli ELIGIBILE)
  //   pret_invertor_hibrid: lei cu TVA, invertor + montaj, daca clientul
  //     NU are invertor hibrid (cost NEeligibil, platit de client).
  //     Pune 0 daca bateria e all-in-one (are invertor inclus).
  baterii: [
    {
      id: "exemplu-1",
      marca: "EXEMPLU Marca",
      model: "PowerBox 15",
      tehnologie: "LiFePO4",
      capacitate_kwh: 15,
      capacitate_utila_kwh: 14.2,
      garantie_ani: 10,
      cicluri: 6000,
      retea: "ambele",
      invertoare_compatibile: "Deye, SolaX, Growatt (hibride)",
      pret_baterie: 19500,
      pret_montaj: 3500,
      pret_invertor_hibrid: 7500,
      stoc: 4,
      termen_zile: 14,
      activ: true
    },
    {
      id: "exemplu-2",
      marca: "EXEMPLU Marca",
      model: "PowerBox 20",
      tehnologie: "LiFePO4",
      capacitate_kwh: 20,
      capacitate_utila_kwh: 19,
      garantie_ani: 10,
      cicluri: 6000,
      retea: "tri",
      invertoare_compatibile: "Deye, SolaX (hibride trifazate)",
      pret_baterie: 26000,
      pret_montaj: 4000,
      pret_invertor_hibrid: 9500,
      stoc: 2,
      termen_zile: 14,
      activ: true
    },
    {
      id: "exemplu-3",
      marca: "EXEMPLU Marca",
      model: "PowerBox 12 mini",
      tehnologie: "LiFePO4",
      capacitate_kwh: 12,
      capacitate_utila_kwh: 11.4,
      garantie_ani: 7,
      cicluri: 4500,
      retea: "mono",
      invertoare_compatibile: "Growatt, Huawei (hibride monofazate)",
      pret_baterie: 15500,
      pret_montaj: 3000,
      pret_invertor_hibrid: 6500,
      stoc: 6,
      termen_zile: 10,
      activ: true
    }
  ]
};
