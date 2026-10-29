<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

/**
 * Žádosti o obnovu zapomenutého hesla (kdy, z jaké IP, pro jaký účet, jestli
 * odešel mail) a limity, které se z nich počítají.
 *
 * Altcha na formuláři sama zahlcení cizí schránky nezabrání: vyřešenou výzvu
 * přijme opakovaně až do její expirace a bot ji spočítá za zlomek sekundy.
 *
 * Čas měří databáze (NOW()), zápis i limity se tak řídí stejnými hodinami.
 */
class ObnovaHeslaLog
{
    /**
     * Kolik mailů s odkazem dostane jeden účet za OKNO_MINUT. Odkaz platí hodinu
     * (ResetHeslaToken::TTL_SEKUND), takže kdo narazí na limit, má ve schránce
     * ještě platný odkaz z dřívějška.
     */
    public const MAX_MAILU_NA_UCET = 3;
    /**
     * Kolik žádostí přijme jedna IP za OKNO_MINUT, i těch, po kterých mail
     * neodešel. Velkoryse, za jednou IP může být celá síť (wifi na festivalu).
     */
    public const MAX_ZADOSTI_Z_IP = 20;
    public const OKNO_MINUT = 60;
    /**
     * Na dohledání stížnosti to stačí a IP adresy nezůstanou v databázi napořád.
     */
    public const UCHOVAVAT_DNI = 30;

    public function jePrekrocenLimitIp(string $ip): bool
    {
        $pocet = dbOneCol(<<<'SQL'
SELECT COUNT(*)
FROM obnova_hesla_log
WHERE ip = $0
    AND kdy > NOW() - INTERVAL $1 MINUTE
SQL,
            [
                0 => $ip,
                1 => self::OKNO_MINUT,
            ],
        );

        return (int) $pocet >= self::MAX_ZADOSTI_Z_IP;
    }

    public function jePrekrocenLimitUctu(int $idUzivatele): bool
    {
        $pocet = dbOneCol(<<<'SQL'
SELECT COUNT(*)
FROM obnova_hesla_log
WHERE id_uzivatele = $0
    AND odeslano = 1
    AND kdy > NOW() - INTERVAL $1 MINUTE
SQL,
            [
                0 => $idUzivatele,
                1 => self::OKNO_MINUT,
            ],
        );

        return (int) $pocet >= self::MAX_MAILU_NA_UCET;
    }

    /**
     * @param int|null $idUzivatele null, když k zadanému e-mailu účet není
     */
    public function zaznamenej(
        string $ip,
        ?int $idUzivatele,
        bool $odeslano,
    ): void {
        dbQuery(<<<'SQL'
INSERT INTO obnova_hesla_log(ip, id_uzivatele, odeslano)
VALUES ($0, $1, $2)
SQL,
            [
                0 => $ip,
                1 => $idUzivatele,
                2 => (int) $odeslano,
            ],
        );
        dbQuery(<<<'SQL'
DELETE FROM obnova_hesla_log
WHERE kdy < NOW() - INTERVAL $0 DAY
SQL,
            [
                0 => self::UCHOVAVAT_DNI,
            ],
        );
    }
}
