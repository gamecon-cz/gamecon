<?php

declare(strict_types=1);

namespace Gamecon\Role;

class RolePodleRocniku
{
    public function prepocitejHistoriiRoliProRocnik(int $rocnik, int $idUzivatele = null): void
    {
        dbBegin();
        try {
            dbQuery(<<<SQL
DELETE FROM uzivatele_role_podle_rocniku
WHERE rocnik = $rocnik
    AND IF ($0 IS NOT NULL, id_uzivatele = $0, TRUE);
SQL,
                [0 => $idUzivatele],
            );
            $typTrvala = Role::TYP_TRVALA;
            dbQuery(<<<SQL
INSERT IGNORE INTO uzivatele_role_podle_rocniku (id_uzivatele, id_role, od_kdy, rocnik)
SELECT prihlaseni.id_uzivatele, prihlaseni.id_role, prihlaseni.kdy, {$rocnik}
FROM uzivatele_role_log AS prihlaseni
JOIN role_seznam
    ON prihlaseni.id_role = role_seznam.id_role
JOIN uzivatele_hodnoty
    ON prihlaseni.id_uzivatele = uzivatele_hodnoty.id_uzivatele
WHERE prihlaseni.zmena = 'posazen'
    AND NOT EXISTS(
        SELECT *
        FROM uzivatele_role_log AS odhlaseni
        WHERE odhlaseni.zmena = 'sesazen'
        AND prihlaseni.id_uzivatele = odhlaseni.id_uzivatele
        AND prihlaseni.id_role = odhlaseni.id_role
        AND prihlaseni.kdy <= odhlaseni.kdy
        AND odhlaseni.kdy <= (SELECT konec_gc(CONCAT({$rocnik}, '-01-01')))
    )
    AND (role_seznam.typ_role = '{$typTrvala}' OR role_seznam.rocnik_role = {$rocnik})
    AND prihlaseni.kdy <= (SELECT konec_gc(CONCAT({$rocnik}, '-01-01')))
    AND prihlaseni.kdy >= (
        -- předtím nemáme věrohodná data
        SELECT MIN(kdy)
        FROM uzivatele_role_log
        WHERE zmena = 'sesazen'
    )
    AND IF ($0 IS NOT NULL, prihlaseni.id_uzivatele = $0, TRUE)
UNION
SELECT uzivatele_role.id_uzivatele, uzivatele_role.id_role, uzivatele_role.posazen, {$rocnik}
FROM uzivatele_role
JOIN role_seznam ON uzivatele_role.id_role = role_seznam.id_role
WHERE posazen <= konec_gc({$rocnik})
AND role_seznam.typ_role = '{$typTrvala}'
ORDER BY id_role, id_uzivatele, kdy
SQL,
                [0 => $idUzivatele],
            );
            dbCommit();
        } catch (\Throwable $e) {
            dbRollback();
            throw $e;
        }
    }
}
