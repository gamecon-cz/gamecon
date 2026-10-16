<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Kanaly\EmailovaSablona\EmailovaPromenna;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaDefinice;
use Gamecon\Kanaly\GcMail;
use Gamecon\Uzivatel\Dto\VlastniZneniUpominky;
use Gamecon\Uzivatel\SqlStruktura\UpominkaVlastniZneniSqlStruktura as Sql;

class UpominkaVlastniZneni
{
    /**
     * The same editor and substitution as for the other e-mails. The subject has no variables, only system values.
     *
     * @see \Gamecon\Kanaly\EmailovaSablona\EmailoveSablonyKatalog
     */
    public static function definice(): EmailovaSablonaDefinice
    {
        return new EmailovaSablonaDefinice(
            kod: 'upominka_vlastni',
            skupina: 'Upomínky dlužníkům',
            nazev: 'Upomínka dlužníkům vlastním zněním',
            kdyOdchazi: 'Když CFO rozesílá upomínky ručně s volbou „vlastní znění“.',
            prijemce: 'vybraní dlužníci',
            format: GcMail::FORMAT_TEXT,
            promenne: [
                new EmailovaPromenna(
                    nazev: 'jmeno',
                    popis: 'jméno a přezdívka příjemce',
                    ukazka: 'Jana „Jája“ Nováková',
                ),
                new EmailovaPromenna(
                    nazev: 'vs',
                    popis: 'variabilní symbol platby (ID účastníka)',
                    ukazka: '1234',
                ),
                new EmailovaPromenna(
                    nazev: 'dluh',
                    popis: 'dlužná částka v korunách',
                    ukazka: '350',
                ),
                new EmailovaPromenna(
                    nazev: 'a',
                    popis: 'koncovka podle pohlaví příjemce: u ženy „a“, jinak nic (např. „dostal%a%“)',
                    ukazka: 'a',
                ),
            ],
        );
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
}
