<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Enum\ProductTagCode;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
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
     * Kopíruje sekvenci z web/moduly/prihlaska/prihlaska.php pro POST
     * „prihlasitNeboUpravit“. Po přechodu na košík z ní zbylo jen přihlášení na GC —
     * objednávky formulář nezapisuje, obsah `$formular` proto ovlivní jen vykreslení.
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
            $pomoc = new \Pomoc($uzivatel);

            dbBegin();
            try {
                if (! $uzivatel->gcPrihlasen()) {
                    $uzivatel->gcPrihlas($uzivatel);
                }
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

    protected function pocetRoliUzivatele(
        \Uzivatel $uzivatel,
        int $idRole,
    ): int {
        return (int) dbOneCol(
            'SELECT COUNT(*) FROM uzivatele_role WHERE id_uzivatele = $0 AND id_role = $1',
            [
                0 => $uzivatel->id(),
                1 => $idRole,
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
    nabizet_do = $5,
    archived_at = $6
SQL,
            [
                0 => $nazev,
                1 => strtoupper(preg_replace('~[^A-Za-z0-9]+~', '_', $nazev)) . '_' . strtoupper(uniqid()),
                2 => $cena,
                3 => $stav,
                4 => $kusuVyrobeno,
                5 => $nabizetDo,
                6 => $archivovanoV,
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
