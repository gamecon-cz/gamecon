<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Dto\OdeslanaUpominka;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\SqlStruktura\UpominkaDluznikaLogSqlStruktura as Sql;

class UpominkaDluznikaLog
{
    public function zaloguj(
        int $idUzivatele,
        TypUpominky $typUpominky,
        int $dluh,
        int $rocnik,
        int $odeslal,
        \DateTimeInterface $kdy,
    ): void {
        dbQuery(<<<SQL
INSERT INTO upominka_dluznika_log(id_uzivatele, typ_upominky, dluh, rocnik, odeslal, kdy)
VALUES ($0, $1, $2, $3, $4, $5)
SQL,
            [
                0 => $idUzivatele,
                1 => $typUpominky->value,
                2 => $dluh,
                3 => $rocnik,
                4 => $odeslal,
                5 => $kdy->format(DateTimeCz::FORMAT_DB),
            ],
        );
    }

    /**
     * Komu už v daném ročníku odešla upomínka daného typu.
     *
     * @return int[] ID uživatelů
     */
    public function idsJizUpomenutych(
        int         $rocnik,
        TypUpominky $typUpominky,
    ): array {
        $idsUzivatelu = dbFetchColumn(<<<SQL
SELECT DISTINCT id_uzivatele
FROM upominka_dluznika_log
WHERE rocnik = $0
    AND typ_upominky = $1
SQL,
            [
                0 => $rocnik,
                1 => $typUpominky->value,
            ],
        );

        return array_map('intval', $idsUzivatelu);
    }

    /**
     * Historie odeslaných upomínek daného ročníku, od nejnovější.
     *
     * @return OdeslanaUpominka[][] ID uživatele => jeho upomínky
     */
    public function historiePodleUzivatelu(int $rocnik): array
    {
        $radky = dbFetchAll(<<<SQL
SELECT id_uzivatele, typ_upominky, dluh, odeslal, kdy
FROM upominka_dluznika_log
WHERE rocnik = $0
ORDER BY kdy DESC
SQL,
            [
                0 => $rocnik,
            ],
        );

        $historie = [];
        foreach ($radky as $radek) {
            $idUzivatele = (int) $radek[Sql::ID_UZIVATELE];
            $historie[$idUzivatele][] = new OdeslanaUpominka(
                typUpominky: TypUpominky::from($radek[Sql::TYP_UPOMINKY]),
                dluh: (int) $radek[Sql::DLUH],
                odeslal: (int) $radek[Sql::ODESLAL],
                kdy: new \DateTimeImmutable($radek[Sql::KDY]),
            );
        }

        return $historie;
    }
}
