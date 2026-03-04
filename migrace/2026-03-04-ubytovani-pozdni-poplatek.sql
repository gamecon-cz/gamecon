ALTER TABLE shop_nakupy
    ADD COLUMN poplatek    DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
    ADD COLUMN puvodni_cena DECIMAL(6, 2) NULL     DEFAULT NULL;

INSERT INTO systemove_nastaveni
    (klic, hodnota, vlastni, datovy_typ, nazev, popis, skupina)
VALUES ('UBYTOVANI_POZDNI_POPLATEK_ZA_NOC', '0', 1, 'number',
        'Pozdní poplatek za ubytování (Kč/noc)',
        'Příplatek za noc ubytování objednaného na místě (po uzavření veřejného prodeje ubytování). Infopult vidí tento poplatek při přidělování ubytování.',
        'Finance');
