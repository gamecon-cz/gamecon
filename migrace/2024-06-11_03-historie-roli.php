<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE VIEW `historie_roli` AS
SELECT prihlaseni.id_uzivatele, prihlaseni.id_role, prihlaseni.kdy, konce_gc.rok
FROM uzivatele_role_log AS prihlaseni
JOIN (SELECT konec_gc(CONCAT(roky.rok, '-01-01')) AS konec_gc, roky.rok
      FROM (SELECT DISTINCT YEAR(uzivatele_role_log.kdy) AS rok
          FROM uzivatele_role_log
          ORDER BY rok DESC
      ) AS roky
) konce_gc
-- žádný JOIN, abychom měli kombinace všech
WHERE prihlaseni.zmena = 'posazen'
    AND NOT EXISTS(
        SELECT *
        FROM uzivatele_role_log AS odhlaseni
        WHERE odhlaseni.zmena = 'sesazen'
        AND prihlaseni.id_uzivatele = odhlaseni.id_uzivatele
        AND prihlaseni.id_role = odhlaseni.id_role
        AND prihlaseni.kdy <= odhlaseni.kdy
    )
    AND prihlaseni.kdy <= konce_gc.konec_gc
    AND prihlaseni.kdy >= (
        -- předtím nemáme věrohodná data
        SELECT MIN(kdy)
        FROM uzivatele_role_log
        WHERE zmena = 'sesazen'
    )
ORDER BY konce_gc.rok DESC, id_role, id_uzivatele
SQL
);
