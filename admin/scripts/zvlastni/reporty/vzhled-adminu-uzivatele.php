<?php

declare(strict_types=1);

use Gamecon\Admin\VzhledAdminuEnum;

require __DIR__ . '/sdilene-hlavicky.php';

// Kdo jaký vzhled administrace používá a jak dlouho. Záznamy začínají nasazením moderního vzhledu,
// „bez volby“ jsou dny v klasickém vzhledu, kdy si uživatel ještě nevybral.
Report::zSql(<<<'SQL'
SELECT
    uzivatele_hodnoty.id_uzivatele AS "ID",
    uzivatele_hodnoty.login_uzivatele AS "Přezdívka",
    TRIM(CONCAT(uzivatele_hodnoty.jmeno_uzivatele, ' ', uzivatele_hodnoty.prijmeni_uzivatele)) AS "Jméno",
    CASE uzivatele_vzhled_adminu.vzhled
        WHEN $0 THEN 'moderní'
        WHEN $1 THEN 'klasický'
        ELSE 'nevybral(a)'
    END AS "Zvolený vzhled",
    uzivatele_vzhled_adminu.zmeneno_kdy AS "Zvoleno kdy",
    SUM(uzivatele_vzhled_adminu_pouziti.vzhled = $0) AS "Dní v moderním",
    SUM(uzivatele_vzhled_adminu_pouziti.vzhled = $1 AND uzivatele_vzhled_adminu_pouziti.zvoleny) AS "Dní v klasickém",
    SUM(uzivatele_vzhled_adminu_pouziti.vzhled = $1 AND NOT uzivatele_vzhled_adminu_pouziti.zvoleny) AS "Dní v klasickém bez volby",
    SUM(IF(uzivatele_vzhled_adminu_pouziti.vzhled = $0, uzivatele_vzhled_adminu_pouziti.pocet_zobrazeni, 0)) AS "Stránek v moderním",
    SUM(IF(uzivatele_vzhled_adminu_pouziti.vzhled = $1, uzivatele_vzhled_adminu_pouziti.pocet_zobrazeni, 0)) AS "Stránek v klasickém",
    MIN(uzivatele_vzhled_adminu_pouziti.den) AS "První den",
    MAX(uzivatele_vzhled_adminu_pouziti.den) AS "Poslední den"
FROM uzivatele_vzhled_adminu_pouziti
JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = uzivatele_vzhled_adminu_pouziti.id_uzivatele
LEFT JOIN uzivatele_vzhled_adminu ON uzivatele_vzhled_adminu.id_uzivatele = uzivatele_vzhled_adminu_pouziti.id_uzivatele
GROUP BY uzivatele_hodnoty.id_uzivatele
ORDER BY MAX(uzivatele_vzhled_adminu_pouziti.den) DESC, uzivatele_hodnoty.login_uzivatele
SQL,
    [
        0 => VzhledAdminuEnum::MODERNI->value,
        1 => VzhledAdminuEnum::KLASICKY->value,
    ],
)->tFormat(get('format'));
