<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Uzivatel\Dto\VlastniZneniUpominky;
use Gamecon\Uzivatel\SqlStruktura\UpominkaVlastniZneniSqlStruktura as Sql;

class UpominkaVlastniZneni
{
    /**
     * Konstanty, které smí vlastní znění dosadit. Výčet je tu proto, že
     * nahradNazvyKonstantZaHodnoty() dosadí LIBOVOLNOU definovanou konstantu,
     * tedy i FIO_TOKEN nebo DB_PASS - a text upomínky odchází stovkám lidí mimo
     * organizaci.
     *
     * @var list<string>
     */
    public const POVOLENE_KONSTANTY = ['UCET_CZ', 'IBAN', 'ROCNIK'];

    /**
     * Zástupné symboly, které se liší příjemce od příjemce. Tvar {…} sem patří
     * proto, že se stejně zapisují koncovky v model/pomoc.php.
     *
     * @return array<string, string> symbol => co doplní
     */
    public static function dejPopisSymbolu(): array
    {
        return [
            '{jmeno}' => 'jméno a přezdívka příjemce',
            '{vs}'    => 'variabilní symbol platby',
            '{dluh}'  => 'dlužná částka v korunách',
            '{a}'     => 'koncovka podle pohlaví (např. „dostal{a}“)',
        ];
    }

    public static function dosadPovoleneKonstanty(string $text): string
    {
        $nahrady = [];
        foreach (self::POVOLENE_KONSTANTY as $nazevKonstanty) {
            if (defined($nazevKonstanty)) {
                $nahrady["%$nazevKonstanty%"] = (string) constant($nazevKonstanty);
            }
        }

        return strtr($text, $nahrady);
    }

    /** @var array<int, ?VlastniZneniUpominky> */
    private array $zneniPodleRocniku = [];

    /**
     * Cachuje se kvůli dvěma věcem: seznam dlužníků se ptá dvakrát na každého
     * z ~200 lidí, a předmět s textem se musí rozhodnout ze stejného znění -
     * jinak by uprostřed rozesílání mohl vyjít vlastní předmět se standardním
     * textem.
     */
    public function dejZneni(int $rocnik): ?VlastniZneniUpominky
    {
        if (array_key_exists($rocnik, $this->zneniPodleRocniku)) {
            return $this->zneniPodleRocniku[$rocnik];
        }

        return $this->zneniPodleRocniku[$rocnik] = $this->prectiZneni($rocnik);
    }

    private function prectiZneni(int $rocnik): ?VlastniZneniUpominky
    {
        $radek = dbOneLine(<<<SQL
SELECT predmet, text, zmenil, zmeneno_kdy
FROM upominka_vlastni_zneni
WHERE rocnik = $0
SQL,
            [
                0 => $rocnik,
            ],
        );

        if (!$radek) {
            return null;
        }

        return new VlastniZneniUpominky(
            predmet: (string) $radek[Sql::PREDMET],
            text: (string) $radek[Sql::TEXT],
            zmenil: $radek[Sql::ZMENIL] !== null
                ? (int) $radek[Sql::ZMENIL]
                : null,
            zmenenoKdy: $radek[Sql::ZMENENO_KDY] !== null
                ? new \DateTimeImmutable($radek[Sql::ZMENENO_KDY])
                : null,
        );
    }

    public function uloz(
        int    $rocnik,
        string $predmet,
        string $text,
        int    $zmenil,
    ): void {
        dbQuery(<<<SQL
INSERT INTO upominka_vlastni_zneni(rocnik, predmet, text, zmenil)
VALUES ($0, $1, $2, $3)
ON DUPLICATE KEY UPDATE predmet = VALUES(predmet), text = VALUES(text), zmenil = VALUES(zmenil)
SQL,
            [
                0 => $rocnik,
                1 => $predmet,
                2 => $text,
                3 => $zmenil,
            ],
        );

        unset($this->zneniPodleRocniku[$rocnik]);
    }

    public function dosadSymboly(
        string $text,
        string $jmenoNick,
        int    $variabilniSymbol,
        int    $dluh,
        string $koncovkaDlePohlavi,
    ): string {
        return self::dosadPovoleneKonstanty(strtr($text, [
            '{jmeno}' => $jmenoNick,
            '{vs}'    => (string) $variabilniSymbol,
            '{dluh}'  => (string) $dluh,
            '{a}'     => $koncovkaDlePohlavi,
        ]));
    }
}
