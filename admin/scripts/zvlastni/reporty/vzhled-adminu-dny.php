<?php

declare(strict_types=1);

use Gamecon\Admin\VzhledAdminuEnum;

require __DIR__ . '/sdilene-hlavicky.php';

// Kolik lidí který den pracovalo v kterém vzhledu administrace. Kdo vzhled ten den přepnul, je v obou.
Report::zSql(<<<'SQL'
SELECT
    uzivatele_vzhled_adminu_pouziti.den AS "Den",
    COUNT(DISTINCT uzivatele_vzhled_adminu_pouziti.id_uzivatele) AS "Uživatelů celkem",
    COUNT(DISTINCT IF(uzivatele_vzhled_adminu_pouziti.vzhled = $0, uzivatele_vzhled_adminu_pouziti.id_uzivatele, NULL)) AS "V moderním",
    COUNT(DISTINCT IF(uzivatele_vzhled_adminu_pouziti.vzhled = $1 AND uzivatele_vzhled_adminu_pouziti.zvoleny, uzivatele_vzhled_adminu_pouziti.id_uzivatele, NULL)) AS "V klasickém",
    COUNT(DISTINCT IF(uzivatele_vzhled_adminu_pouziti.vzhled = $1 AND NOT uzivatele_vzhled_adminu_pouziti.zvoleny, uzivatele_vzhled_adminu_pouziti.id_uzivatele, NULL)) AS "V klasickém bez volby",
    SUM(IF(uzivatele_vzhled_adminu_pouziti.vzhled = $0, uzivatele_vzhled_adminu_pouziti.pocet_zobrazeni, 0)) AS "Stránek v moderním",
    SUM(IF(uzivatele_vzhled_adminu_pouziti.vzhled = $1, uzivatele_vzhled_adminu_pouziti.pocet_zobrazeni, 0)) AS "Stránek v klasickém"
FROM uzivatele_vzhled_adminu_pouziti
GROUP BY uzivatele_vzhled_adminu_pouziti.den
ORDER BY uzivatele_vzhled_adminu_pouziti.den DESC
SQL,
    [
        0 => VzhledAdminuEnum::MODERNI->value,
        1 => VzhledAdminuEnum::KLASICKY->value,
    ],
)->tFormat(get('format'));
