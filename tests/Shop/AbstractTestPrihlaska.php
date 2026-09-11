<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Enum\ProductTagCode;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Společné zázemí pro testy veřejné přihlášky (web/moduly/prihlaska/prihlaska.php).
 *
 * Testy nevolají samotný modul — je to procedurální skript závislý na šabloně
 * a session. Volají stejnou sekvenci metod, jakou modul spouští nad odeslaným
 * formulářem, takže testovaný kód je tentýž.
 */
abstract class AbstractTestPrihlaska extends AbstractTestDb
{
    protected const DEN_CTVRTEK = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;
    protected const DEN_PATEK = DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK;
    protected const DEN_SOBOTA = DateTimeGamecon::PORADI_HERNIHO_DNE_SOBOTA;

    /**
     * Přihláška si uvnitř otevírá vlastní transakci a commituje ji, takže
     * obalující transakce testu by se commitla s ní. Místo toho resetujeme
     * databázi po každé testovací metodě.
     */
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterSingleTestMethod(): bool
    {
        return true;
    }

    /**
     * Odešle přihlášku se zadaným obsahem formuláře.
     *
     * Kopíruje sekvenci z web/moduly/prihlaska/prihlaska.php pro POST
     * „prihlasitNeboUpravit“ — včetně pořadí, které je významné: ubytování
     * se zpracovává před jídlem, protože ruší snídaně v ceně hotelu.
     *
     * @param array<string, mixed> $formular
     */
    protected function odesliPrihlasku(
        \Uzivatel $uzivatel,
        array $formular,
    ): void {
        $puvodniPost = $_POST;
        $_POST = $formular;
        try {
            $uzivatel = \Uzivatel::zIdUrcite($uzivatel->id());
            $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
            $pomoc = new \Pomoc($uzivatel);

            dbBegin();
            try {
                if (! $uzivatel->gcPrihlasen()) {
                    $uzivatel->gcPrihlas($uzivatel);
                }
                $shop->zpracujPredmety();
                $shop->zpracujUbytovani(ulozitNechceUbytovani: true);
                $shop->zpracujJidlo();
                $shop->zpracujVstupne();
                $pomoc->zpracuj();
                $uzivatel->finance()->obnovUdaje();
                dbCommit();
            } catch (\Throwable $throwable) {
                dbRollback();
                throw $throwable;
            }
        } finally {
            $_POST = $puvodniPost;
            \Uzivatel::smazCache();
        }
    }

    /**
     * Odešle přihlášku a vrátí chybu, kterou vyhodila — nebo null, když prošla.
     *
     * @param array<string, mixed> $formular
     */
    protected function odesliPrihlaskuAZachytChybu(
        \Uzivatel $uzivatel,
        array $formular,
    ): ?\Chyba {
        try {
            $this->odesliPrihlasku($uzivatel, $formular);
        } catch (\Chyba $chyba) {
            return $chyba;
        }

        return null;
    }

    protected function vytvorBeznehoUzivatele(): \Uzivatel
    {
        $unikat = uniqid();
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Běžný',
    prijmeni_uzivatele = 'Účastník'
SQL,
            [
                0 => 'test_prihlaska_' . $unikat,
                1 => 'test.prihlaska.' . $unikat . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    protected function vytvorPredmet(
        string $nazev,
        ?int $kusuVyrobeno = 10,
        float $cena = 100.0,
        int $stav = StavPredmetu::VEREJNY,
        ?string $nabizetDo = null,
        int $modelRok = ROCNIK,
    ): int {
        return $this->vlozPredmet($nazev, TypPredmetu::PREDMET, $kusuVyrobeno, $cena, $stav, $nabizetDo, $modelRok);
    }

    protected function vytvorTricko(
        string $nazev,
        ?int $kusuVyrobeno = 10,
        float $cena = 250.0,
        int $stav = StavPredmetu::VEREJNY,
        ?string $nabizetDo = null,
    ): int {
        return $this->vlozPredmet($nazev, TypPredmetu::TRICKO, $kusuVyrobeno, $cena, $stav, $nabizetDo, ROCNIK);
    }

    protected function vytvorJidlo(
        string $nazev,
        int $den,
        ?int $kusuVyrobeno = 10,
        float $cena = 120.0,
        int $stav = StavPredmetu::VEREJNY,
        ?string $nabizetDo = null,
    ): int {
        return $this->vlozPredmet($nazev, TypPredmetu::JIDLO, $kusuVyrobeno, $cena, $stav, $nabizetDo, ROCNIK, $den);
    }

    protected function vytvorUbytovani(
        string $nazev,
        int $den,
        ?int $kusuVyrobeno = 10,
        float $cena = 300.0,
        int $stav = StavPredmetu::VEREJNY,
        int $modelRok = ROCNIK,
    ): int {
        return $this->vlozPredmet($nazev, TypPredmetu::UBYTOVANI, $kusuVyrobeno, $cena, $stav, null, $modelRok, $den);
    }

    /**
     * Ubytování se objednává minimálně na dvě navazující noci, jedna noc projde
     * jen s právem UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC.
     *
     * @return array<int, int> ids předmětů dvou navazujících nocí
     */
    protected function vytvorUbytovaniNaDvouNocich(
        ?int $kusuVyrobeno = 10,
        float $cena = 300.0,
    ): array {
        return [
            $this->vytvorUbytovani('Ubytování čtvrtek', self::DEN_CTVRTEK, $kusuVyrobeno, $cena),
            $this->vytvorUbytovani('Ubytování pátek', self::DEN_PATEK, $kusuVyrobeno, $cena),
        ];
    }

    protected function pocetVsechNakupu(\Uzivatel $uzivatel): int
    {
        return (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = $0 AND rok = $1',
            [
                0 => $uzivatel->id(),
                1 => ROCNIK,
            ],
        );
    }

    private function vlozPredmet(
        string $nazev,
        int $typ,
        ?int $kusuVyrobeno,
        float $cena,
        int $stav,
        ?string $nabizetDo,
        int $modelRok,
        ?int $ubytovaniDen = null,
    ): int {
        // Ročník už není sloupec: pohled shop_predmety_s_typem ho odvozuje z archived_at,
        // kde NULL znamená letošní a jinak rozhoduje rok archivace.
        $archivovanoV = $modelRok === ROCNIK
            ? null
            : $modelRok . '-01-01 00:00:00';

        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    cena_aktualni = $2,
    stav = $3,
    kusu_vyrobeno = $4,
    ubytovani_den = $5,
    nabizet_do = $6,
    archived_at = $7
SQL,
            [
                0 => $nazev,
                1 => strtoupper(preg_replace('~[^A-Za-z0-9]+~', '_', $nazev)) . '_' . strtoupper(uniqid()),
                2 => $cena,
                3 => $stav,
                4 => $kusuVyrobeno,
                5 => $ubytovaniDen,
                6 => $nabizetDo,
                7 => $archivovanoV,
            ],
        );

        $idPredmetu = dbInsertId();
        $this->oznacTypem($idPredmetu, $typ);

        return $idPredmetu;
    }

    /**
     * Typ předmětu je nově tag, ne sloupec.
     */
    private function oznacTypem(
        int $idPredmetu,
        int $typ,
    ): void {
        $kodTagu = ProductTagCode::fromLegacyTyp($typ);
        if ($kodTagu === null) {
            throw new \LogicException('Pro typ předmětu ' . $typ . ' neexistuje tag');
        }

        $vlozeni = dbQuery(
            'INSERT INTO product_product_tag (product_id, tag_id)
             SELECT $0, id FROM product_tag WHERE code = $1',
            [
                0 => $idPredmetu,
                1 => $kodTagu->value,
            ],
        );
        // Bez tagu v databázi vloží INSERT ... SELECT tiše nula řádků a předmět pak
        // z pohledu vyjde s typ = NULL — spadne až vzdálená assertion.
        if (dbAffectedOrNumRows($vlozeni) !== 1) {
            throw new \LogicException('Tag ' . $kodTagu->value . ' v databázi není');
        }
    }
}
