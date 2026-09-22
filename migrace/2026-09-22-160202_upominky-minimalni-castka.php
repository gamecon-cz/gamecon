<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Práh, pod kterým automatická upomínka nechodí - upomínat haléřové dluhy nemá smysl.
// `vlastni = 1` je nutné: klíč nemá záznam v dejVychoziHodnoty(), takže by se konstanta
// při bootu definovala prázdná a práh by tiše spadl na nulu.
$vychoziCastka = $this->q("
SELECT hodnota
FROM systemove_nastaveni
WHERE klic = 'NEPLATIC_CASTKA_VELKY_DLUH'
    AND rocnik_nastaveni = -1
")->fetch_assoc()['hodnota'] ?? '251'; // fallback je nedosažitelný, klíč zakládá migrace 000

$vychoziCastka = (float)$vychoziCastka;

$this->q("
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, vlastni, datovy_typ, nazev, popis, skupina, poradi,
                                        pouze_pro_cteni, rocnik_nastaveni)
VALUES ('UPOMINKA_MINIMALNI_CASTKA', '$vychoziCastka', 1, 'number', 'Nejmenší dluh, na který se upomíná',
        'Kolik kč musí účastník dlužit, aby mu automaticky odešla upomínka. Ruční rozeslání z adminu práh nehlídá.',
        'Neplatič', NULL, 0, -1)
");
