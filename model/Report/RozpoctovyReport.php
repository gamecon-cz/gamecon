<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class RozpoctovyReport
{
    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function exportuj(
        ?string $format,
        string $doSouboru = null,
    )
    {

    }

    private function poctyUcastniku()
    {
        $prihlasen = Role::VYZNAM_PRIHLASEN;
        $pritomen = Role::VYZNAM_PRITOMEN;
        $ucast = Role::TYP_UCAST;

        return dbFetchAll(<<<SQL
SELECT
    `účastníci (obyč)`,
    `orgové - full`,
    `orgové - ubytko`,
    `orgové - trička`,
    `vypravěči`,
    `dobrovolníci sr`,
    `partneři`,
    `brigádníci`
FROM (
    SELECT
        rocnik_role,
        SUM(IF(registrace, 1, 0)) AS Registrovaných,
        SUM(IF(dorazeni, 1, 0)) AS Dorazilo,
        SUM(
            IF(
                dorazeni AND EXISTS(
                SELECT * FROM uzivatele_role_log AS posazen
                    LEFT JOIN uzivatele_role_log AS sesazen
                        ON sesazen.id_role = posazen.id_role
                               AND sesazen.id_uzivatele =posazen.id_uzivatele
                               AND sesazen.kdy > posazen.kdy AND sesazen.zmena = $4
                WHERE posazen.zmena = $3
                    AND sesazen.id_uzivatele IS NULL /* neexistuje novější záznam */
                    AND posazen.id_uzivatele = podle_roku.id_uzivatele
                    AND posazen.id_role IN (?)
                ),
                1,
                0
            )
        )
    FROM (
        SELECT
            role_seznam.rocnik_role,
            uzivatele_role.id_role,
            role_seznam.vyznam_role = '$prihlasen' AS registrace,
            role_seznam.vyznam_role = '$pritomen' AS dorazeni,
            uzivatele_role.id_uzivatele
            FROM uzivatele_role AS uzivatele_role
            JOIN role_seznam
                ON uzivatele_role.id_role = role_seznam.id_role
            WHERE role_seznam.typ_role = '$ucast'
    ) AS podle_roku
    GROUP BY rocnik_role
) AS pocty
SQL,
        );
    }
}
