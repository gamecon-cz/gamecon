<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

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

    /**
     * Nabízí formulář přihlášky předmět ke koupi? Čte vykreslené HTML, tedy
     * to, co účastník opravdu vidí — hledá políčko `shopP[<id>]`, které se
     * pro nenabízené předměty nevykreslí.
     */
    protected function jeNabizenKProdeji(
        \Uzivatel $uzivatel,
        int $idPredmetu,
    ): bool {
        // Termíny konce prodeje jsou konstanty, které testovací bootstrap nedefinuje,
        // a jejich výchozí hodnoty leží uprostřed ročníku — bez posunutí „teď“ na
        // začátek roku by vykreslení hlásilo ukončený prodej.
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict(ROCNIK . '-01-01 00:00:00'),
        );
        foreach ([
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE',
        ] as $klic) {
            try_define($klic, $systemoveNastaveni->dejVychoziHodnotu($klic));
        }

        // Bez nastavené cache si XTemplate odkládá zkompilovanou šablonu vedle
        // zdroje, tedy do gitem sledovaného stromu.
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);

        $uzivatel = \Uzivatel::zIdUrcite($uzivatel->id());
        $shop = new Shop($uzivatel, $uzivatel, $systemoveNastaveni);

        return str_contains($shop->predmetyHtml(), 'name="shopP[' . $idPredmetu . ']"');
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

    protected function pocetNakupu(
        \Uzivatel $uzivatel,
        int $idPredmetu,
    ): int {
        return (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = $0 AND id_predmetu = $1 AND rok = $2',
            [
                0 => $uzivatel->id(),
                1 => $idPredmetu,
                2 => ROCNIK,
            ],
        );
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

    /**
     * Kolik kusů předmětu ještě zbývá k prodeji — stejný výpočet, jaký hlídá
     * Shop::prodat(). Vrací null pro předmět s neomezenou zásobou.
     */
    protected function zbyvajiciKusy(int $idPredmetu): ?int
    {
        $kusuVyrobeno = dbOneCol(
            'SELECT kusu_vyrobeno FROM shop_predmety WHERE id_predmetu = $0',
            [
                0 => $idPredmetu,
            ],
        );
        if ($kusuVyrobeno === null) {
            return null;
        }

        $prodano = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_predmetu = $0 AND rok = $1',
            [
                0 => $idPredmetu,
                1 => ROCNIK,
            ],
        );

        return max(0, (int) $kusuVyrobeno - $prodano);
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
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    model_rok = $2,
    cena_aktualni = $3,
    stav = $4,
    kusu_vyrobeno = $5,
    typ = $6,
    ubytovani_den = $7,
    nabizet_do = $8
SQL,
            [
                0 => $nazev,
                1 => strtoupper(preg_replace('~[^A-Za-z0-9]+~', '_', $nazev)) . '_' . strtoupper(uniqid()),
                2 => $modelRok,
                3 => $cena,
                4 => $stav,
                5 => $kusuVyrobeno,
                6 => $typ,
                7 => $ubytovaniDen,
                8 => $nabizetDo,
            ],
        );

        return dbInsertId();
    }
}
