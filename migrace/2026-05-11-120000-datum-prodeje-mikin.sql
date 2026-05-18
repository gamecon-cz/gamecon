UPDATE systemove_nastaveni
SET poradi = poradi + 1
WHERE skupina = 'Časy'
  AND poradi >= 16;

INSERT INTO systemove_nastaveni
(klic, hodnota, vlastni, datovy_typ, nazev, popis, zmena_kdy, skupina, poradi, pouze_pro_cteni, rocnik_nastaveni)
VALUES ('MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE', '', 0, 'date', 'Ukončení prodeje mikin na konci dne',
        'Datum, do kdy ještě (včetně) lze v přihlášce měnit mikiny, než se zamknou', NOW(), 'Časy', 16, 0, -1);
