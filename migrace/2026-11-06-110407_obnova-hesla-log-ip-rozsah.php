<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Limit žádostí o obnovu hesla počítal po jednotlivých IP adresách. Klient s IPv6 má ale
// obvykle celou síť /64 a adresu v ní může měnit s každou žádostí, takže limit obešel.
// Nově se počítá po rozsazích (Gamecon\Uzivatel\ObnovaHeslaLog::jePrekrocenLimitIp()):
// - IPv4 adresa sama za sebe,
// - IPv4 zapsaná jako IPv6 (::ffff:192.0.2.1) jako ta IPv4, ať jeden klient nemá dva rozsahy
//   podle toho, jak adresu podá server,
// - jiná IPv6 celá síť /64 v kanonickém zápisu (2001:db8:1:2::),
// - neplatná nebo prázdná adresa, jak je.
// IS_IPV6() jde první, IS_IPV4_MAPPED() by u IPv4 adresy hlásil varování.
// Sloupec je generovaný, aby rozsah nemohl s adresou nesouhlasit, ať řádek zapíše kdokoli,
// a aby se při přidání spočítal i u stávajících řádků. Plná adresa zůstává v ip kvůli dohledání.
$this->q(<<<'SQL'
ALTER TABLE obnova_hesla_log
    ADD COLUMN ip_rozsah VARCHAR(45) AS (
        CASE
            WHEN NOT IS_IPV6(ip) THEN ip
            WHEN IS_IPV4_MAPPED(INET6_ATON(ip)) THEN INET6_NTOA(SUBSTRING(INET6_ATON(ip), 13))
            ELSE INET6_NTOA(CONCAT(LEFT(INET6_ATON(ip), 8), UNHEX('0000000000000000')))
        END
    ) STORED AFTER ip,
    DROP INDEX ip_kdy,
    ADD INDEX ip_rozsah_kdy (ip_rozsah, kdy)
SQL,
);
