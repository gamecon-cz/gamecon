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
 * Žádost se zapíše dřív, než se spočítají limity, a počítá se do nich i ona.
 * Souběžná žádost tak vidí všechny, které se zapsaly před ní. Kdyby se žádost
 * zapsala až po odeslání mailu, paralelní žádosti by všechny viděly stejný
 * počet a mail by poslala každá (s jedním vyřešením Altchy jich bylo 10 z 10).
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
     * Kolik žádostí přijme jeden rozsah IP za OKNO_MINUT, i těch, po kterých mail neodešel.
     * Velkoryse, za jednou IP může být celá síť (wifi na festivalu).
     */
    public const MAX_ZADOSTI_Z_IP = 20;
    public const OKNO_MINUT = 60;
    /**
     * Na dohledání stížnosti to stačí a IP adresy nezůstanou v databázi napořád.
     */
    public const UCHOVAVAT_DNI = 30;

    /**
     * Počítá žádosti ze stejného rozsahu IP jako $idZadosti, i ji samotnou. Rozsah je
     * generovaný sloupec ip_rozsah: IPv4 adresa sama za sebe, u IPv6 celá síť /64, kterou
     * klient obvykle má a adresu v ní může měnit s každou žádostí. Výraz je v migraci
     * 2026-11-06-110407_obnova-hesla-log-ip-rozsah.php.
     *
     * Za jednou sítí /64 může být víc lidí (wifi na festivalu, hosting, který dává adresy
     * z jedné /64), ti limit sdílí stejně jako lidé za jednou IPv4 přes NAT.
     */
    public function jePrekrocenLimitIp(int $idZadosti): bool
    {
        $pocet = dbOneCol(<<<'SQL'
SELECT COUNT(*)
FROM obnova_hesla_log
WHERE ip_rozsah = (
        SELECT ip_rozsah
        FROM obnova_hesla_log
        WHERE id_log = $0
    )
    AND kdy > NOW() - INTERVAL $1 MINUTE
SQL,
            [
                0 => $idZadosti,
                1 => self::OKNO_MINUT,
            ],
        );

        return (int) $pocet > self::MAX_ZADOSTI_Z_IP;
    }

    /**
     * Počítá i mail, který volající právě zapsal jako odeslaný a teprve ho posílá.
     */
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

        return (int) $pocet > self::MAX_MAILU_NA_UCET;
    }

    /**
     * @param int|null $idUzivatele null, když k zadanému e-mailu účet není
     * @param bool     $odeslano    u žádosti, po které se mail teprve posílá, true: limit účtu
     *                              ho tak započítá i souběžným žádostem; když mail neodejde, {@see oznacNeodeslano()}
     *
     * @return int id záznamu
     */
    public function zaznamenej(
        string $ip,
        ?int $idUzivatele,
        bool $odeslano,
    ): int {
        dbExecute(<<<'SQL'
INSERT INTO obnova_hesla_log(ip, id_uzivatele, odeslano)
VALUES ($0, $1, $2)
SQL,
            [
                0 => $ip,
                1 => $idUzivatele,
                2 => (int) $odeslano,
            ],
        );
        $idLog = (int) dbInsertId();
        dbExecute(<<<'SQL'
DELETE FROM obnova_hesla_log
WHERE kdy < NOW() - INTERVAL $0 DAY
SQL,
            [
                0 => self::UCHOVAVAT_DNI,
            ],
        );

        return $idLog;
    }

    public function oznacNeodeslano(int $idLog): void
    {
        dbExecute(<<<'SQL'
UPDATE obnova_hesla_log
SET odeslano = 0
WHERE id_log = $0
SQL,
            [
                0 => $idLog,
            ],
        );
    }

    public function smaz(int $idLog): void
    {
        dbExecute(<<<'SQL'
DELETE FROM obnova_hesla_log
WHERE id_log = $0
SQL,
            [
                0 => $idLog,
            ],
        );
    }
}
