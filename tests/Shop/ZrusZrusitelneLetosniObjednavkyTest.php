<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use App\Kernel;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Prostredi\Prostredi;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class ZrusZrusitelneLetosniObjednavkyTest extends AbstractTestDb
{
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

    private function vytvorUzivatele(): \Uzivatel
    {
        $uniqueId = uniqid();
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Odhlaseni'
SQL,
            [
                0 => 'test_odhlaseni_' . $uniqueId,
                1 => 'test.odhlaseni.' . $uniqueId . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function vytvorPredmet(string $nazev, int $typ, ?int $den = null): int
    {
        $uniqueId = uniqid();
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    model_rok = $2,
    cena_aktualni = 100,
    stav = $3,
    kusu_vyrobeno = NULL,
    typ = $4,
    ubytovani_den = $5
SQL,
            [
                0 => $nazev,
                1 => strtoupper(str_replace(' ', '_', $nazev)) . '_' . $uniqueId,
                2 => ROCNIK,
                3 => StavPredmetu::VEREJNY,
                4 => $typ,
                5 => $den,
            ],
        );

        return dbInsertId();
    }

    private function objednejPredmet(int $idUzivatele, int $idPredmetu): void
    {
        dbQuery(<<<SQL
INSERT INTO shop_nakupy SET
    id_uzivatele = $0,
    id_predmetu = $1,
    rok = $2,
    cena_nakupni = (SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu = $1),
    datum = NOW()
SQL,
            [0 => $idUzivatele, 1 => $idPredmetu, 2 => ROCNIK],
        );
    }

    private function jeObjednan(int $idUzivatele, int $idPredmetu): bool
    {
        return (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = $0 AND id_predmetu = $1 AND rok = $2',
            [0 => $idUzivatele, 1 => $idPredmetu, 2 => ROCNIK],
        ) > 0;
    }

    private function nastaveni(bool $jidloUkonceno, bool $ubytovaniUkonceno): SystemoveNastaveni
    {
        return new class($jidloUkonceno, $ubytovaniUkonceno) extends SystemoveNastaveni {
            public function __construct(
                private readonly bool $jidloUkonceno,
                private readonly bool $ubytovaniUkonceno,
            ) {
                parent::__construct(
                    rocnik: ROCNIK,
                    ted: new DateTimeImmutableStrict(),
                    prostredi: Prostredi::Production,
                    databazoveNastaveni: DatabazoveNastaveni::vytvorZGlobals(),
                    rootAdresarProjektu: '',
                    privateCacheDir: SPEC,
                    kernel: new Kernel('test', false),
                    publicCacheDir: CACHE,
                );
            }

            public function prodejJidlaUkoncen(): bool
            {
                return $this->jidloUkonceno;
            }

            public function prodejUbytovaniUkoncen(): bool
            {
                return $this->ubytovaniUkonceno;
            }
        };
    }

    /**
     * @test
     */
    public function poUzavercePonechaJidloAUbytovaniAleZrusiMerch(): void
    {
        $uzivatel = $this->vytvorUzivatele();

        $idUbytovani = $this->vytvorPredmet('Spacak', TypPredmetu::UBYTOVANI, DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK);
        $idJidlo     = $this->vytvorPredmet('Obed', TypPredmetu::JIDLO, DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK);
        $idMerch     = $this->vytvorPredmet('Predmet', TypPredmetu::PREDMET);

        $this->objednejPredmet($uzivatel->id(), $idUbytovani);
        $this->objednejPredmet($uzivatel->id(), $idJidlo);
        $this->objednejPredmet($uzivatel->id(), $idMerch);

        $nastaveni = $this->nastaveni(jidloUkonceno: true, ubytovaniUkonceno: true);
        $zruseno   = (new Shop($uzivatel, $uzivatel, $nastaveni))
            ->zrusZrusitelneLetosniObjednavky('test');

        self::assertSame(1, $zruseno, 'Zrušit se měl jen merch');
        self::assertTrue($this->jeObjednan($uzivatel->id(), $idUbytovani), 'Ubytování po uzávěrce zůstává naúčtované');
        self::assertTrue($this->jeObjednan($uzivatel->id(), $idJidlo), 'Jídlo po uzávěrce zůstává naúčtované');
        self::assertFalse($this->jeObjednan($uzivatel->id(), $idMerch), 'Merch se ruší vždy');
    }

    /**
     * @test
     */
    public function predUzaverkouZrusiVse(): void
    {
        $uzivatel = $this->vytvorUzivatele();

        $idUbytovani = $this->vytvorPredmet('Spacak', TypPredmetu::UBYTOVANI, DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK);
        $idJidlo     = $this->vytvorPredmet('Obed', TypPredmetu::JIDLO, DateTimeGamecon::PORADI_HERNIHO_DNE_PATEK);
        $idMerch     = $this->vytvorPredmet('Predmet', TypPredmetu::PREDMET);

        $this->objednejPredmet($uzivatel->id(), $idUbytovani);
        $this->objednejPredmet($uzivatel->id(), $idJidlo);
        $this->objednejPredmet($uzivatel->id(), $idMerch);

        $nastaveni = $this->nastaveni(jidloUkonceno: false, ubytovaniUkonceno: false);
        $zruseno   = (new Shop($uzivatel, $uzivatel, $nastaveni))
            ->zrusZrusitelneLetosniObjednavky('test');

        self::assertSame(3, $zruseno, 'Před uzávěrkou se zruší vše');
        self::assertFalse($this->jeObjednan($uzivatel->id(), $idUbytovani));
        self::assertFalse($this->jeObjednan($uzivatel->id(), $idJidlo));
        self::assertFalse($this->jeObjednan($uzivatel->id(), $idMerch));
    }
}
