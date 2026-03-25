<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Recalculates `uzivatele_role_podle_rocniku` table.
 *
 * Port of legacy `\Gamecon\Role\RolePodleRocniku::prepocitejHistoriiRoliProRocnik()`.
 */
class RoleHistoryRecalculator
{
    private const ROLE_TYPE_PERMANENT = 'trvala';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function recalculate(int $year, ?int $userId = null): void
    {
        $gcBeziDo = $this->getGcBeziDo($year);

        if ($gcBeziDo === null) {
            return;
        }

        $this->connection->beginTransaction();
        try {
            $userCondition = $userId !== null ? 'AND id_uzivatele = :userId' : '';

            $this->connection->executeStatement(
                "DELETE FROM uzivatele_role_podle_rocniku
                WHERE rocnik = :year {$userCondition}",
                array_filter([
                    'year'   => $year,
                    'userId' => $userId,
                ]),
            );

            $typTrvala = self::ROLE_TYPE_PERMANENT;
            $userConditionLog = $userId !== null ? 'AND prihlaseni.id_uzivatele = :userId' : '';

            $this->connection->executeStatement(
                "INSERT IGNORE INTO uzivatele_role_podle_rocniku (id_uzivatele, id_role, od_kdy, rocnik)
                SELECT prihlaseni.id_uzivatele, prihlaseni.id_role, prihlaseni.kdy, :year
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
                        AND odhlaseni.kdy <= :gcBeziDo
                    )
                    AND (role_seznam.typ_role = '{$typTrvala}' OR role_seznam.rocnik_role = :year)
                    AND prihlaseni.kdy <= :gcBeziDo
                    AND prihlaseni.kdy >= (
                        SELECT MIN(kdy)
                        FROM uzivatele_role_log
                        WHERE zmena = 'sesazen'
                    )
                    {$userConditionLog}
                UNION
                SELECT uzivatele_role.id_uzivatele, uzivatele_role.id_role, uzivatele_role.posazen, :year
                FROM uzivatele_role
                JOIN role_seznam ON uzivatele_role.id_role = role_seznam.id_role
                WHERE posazen <= :gcBeziDo
                AND role_seznam.typ_role = '{$typTrvala}'
                ORDER BY id_role, id_uzivatele, kdy",
                array_filter([
                    'year'     => $year,
                    'gcBeziDo' => $gcBeziDo,
                    'userId'   => $userId,
                ]),
            );

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    private function getGcBeziDo(int $year): ?string
    {
        $result = $this->connection->fetchOne(
            "SELECT hodnota FROM systemove_nastaveni
            WHERE klic = 'GC_BEZI_DO' AND rocnik_nastaveni = :year",
            [
                'year' => $year,
            ],
        );

        return $result !== false ? (string) $result : null;
    }
}
