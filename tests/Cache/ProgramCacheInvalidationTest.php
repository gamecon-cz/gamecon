<?php

declare(strict_types=1);

namespace Gamecon\Tests\Cache;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\SqlStruktura\TypAktivitySqlStruktura as TypSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\SystemoveNastaveni\SqlMigrace;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;
use Gamecon\Uzivatel\ZpusobZobrazeniNaWebu;

/**
 * Regresní testy zajišťující, že každá cesta, kterou se mění data zobrazovaná
 * ve veřejném programu, nastaví příslušný dirty flag statických JSON souborů.
 */
class ProgramCacheInvalidationTest extends AbstractTestDb
{
    private const ROK = ROCNIK;

    protected static bool $disableStrictTransTables = true;

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            fn () => dbInsertUpdate(
                TypSql::TYP_AKTIVITY_TABULKA,
                [
                    TypSql::ID_TYPU   => TypAktivity::DESKOHERNA,
                    TypSql::STRANKA_O => dbFetchSingle('SELECT id_stranky FROM stranky LIMIT 1'),
                ],
            ),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->smazVsechnyDirtyFlagy();
    }

    protected function tearDown(): void
    {
        $this->smazVsechnyDirtyFlagy();
        parent::tearDown();
    }

    private function cestaKDirtyFlagu(ProgramStaticFileType $typ): string
    {
        return SPEC . '/program/dirty-' . $typ->value . '-' . self::ROK;
    }

    private function smazVsechnyDirtyFlagy(): void
    {
        foreach (ProgramStaticFileType::cases() as $typ) {
            $soubor = $this->cestaKDirtyFlagu($typ);
            if (file_exists($soubor)) {
                unlink($soubor);
            }
        }
    }

    private function assertDirtyFlagNastaven(ProgramStaticFileType $typ, string $kontext = ''): void
    {
        $soubor = $this->cestaKDirtyFlagu($typ);
        self::assertFileExists(
            $soubor,
            "Po akci \"{$kontext}\" musí být nastaven dirty flag pro {$typ->value}",
        );
    }

    private function vlozAktivitu(array $data = []): int
    {
        $defaults = [
            Sql::NAZEV_AKCE   => 'Test aktivita',
            Sql::POPIS_KRATKY => 'Krátký popis',
            Sql::POPIS        => 1,
            Sql::ROK          => self::ROK,
            Sql::STAV         => StavAktivity::AKTIVOVANA,
            Sql::TYP          => TypAktivity::DESKOHERNA,
            Sql::ZACATEK      => date('Y-m-d 10:00:00'),
            Sql::KONEC        => date('Y-m-d 13:00:00'),
            Sql::KAPACITA     => 5,
            Sql::KAPACITA_F   => 0,
            Sql::KAPACITA_M   => 0,
            Sql::CENA         => 100,
            Sql::TEAMOVA      => 0,
        ];
        dbInsertUpdate(Sql::AKCE_SEZNAM_TABULKA, array_merge($defaults, $data));

        return (int) dbInsertId();
    }

    private function vlozUzivatele(int $idUzivatele, string $nick, string $jmeno, string $prijmeni): \Uzivatel
    {
        dbInsertUpdate('uzivatele_hodnoty', [
            UzivatelSql::ID_UZIVATELE             => $idUzivatele,
            UzivatelSql::LOGIN_UZIVATELE          => $nick,
            UzivatelSql::JMENO_UZIVATELE          => $jmeno,
            UzivatelSql::PRIJMENI_UZIVATELE       => $prijmeni,
            UzivatelSql::EMAIL1_UZIVATELE         => $nick . '@example.test',
            UzivatelSql::ZPUSOB_ZOBRAZENI_NA_WEBU => ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA->value,
        ]);

        return \Uzivatel::zId($idUzivatele, true);
    }

    /**
     * @test
     */
    public function zmenaJmenaVypraveceNastaviAktivityFlag(): void
    {
        $idAktivity = $this->vlozAktivitu();
        $idVypravece = 900001;
        $vypravec = $this->vlozUzivatele($idVypravece, 'starynick', 'Jan', 'Novák');
        // zaregistrujeme ho jako organizátora aktivity
        dbInsertUpdate('akce_organizatori', [
            'id_akce'      => $idAktivity,
            'id_uzivatele' => $idVypravece,
        ]);
        $this->smazVsechnyDirtyFlagy();

        $vypravec->uprav([
            'login_uzivatele' => 'novynick',
        ]);

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'změna přezdívky vypravěče aktivity',
        );
    }

    /**
     * @test
     */
    public function zmenaZpusobuZobrazeniNaWebuVypraveceNastaviAktivityFlag(): void
    {
        $idAktivity = $this->vlozAktivitu();
        $idVypravece = 900004;
        $vypravec = $this->vlozUzivatele($idVypravece, 'anonymninick', 'Adam', 'Anonym');
        dbInsertUpdate('akce_organizatori', [
            'id_akce'      => $idAktivity,
            'id_uzivatele' => $idVypravece,
        ]);
        $this->smazVsechnyDirtyFlagy();

        $vypravec->uprav([
            UzivatelSql::ZPUSOB_ZOBRAZENI_NA_WEBU => ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI->value,
        ]);

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'změna anonymizace vypravěče aktivity',
        );
    }

    /**
     * Simuluje cestu skrz admin osobní-údaje controller, který obchází
     * Uzivatel::uprav() a volá dbUpdate + invalidujProgramCacheJeLiVypravecem přímo.
     *
     * @test
     */
    public function zmenaPresAdminControllerNastaviAktivityFlag(): void
    {
        $idAktivity = $this->vlozAktivitu();
        $idVypravece = 900005;
        $vypravec = $this->vlozUzivatele($idVypravece, 'adminovaNick', 'Adam', 'Adminović');
        dbInsertUpdate('akce_organizatori', [
            'id_akce'      => $idAktivity,
            'id_uzivatele' => $idVypravece,
        ]);
        $this->smazVsechnyDirtyFlagy();

        // simulace toho, co dělá osobni_udaje_ovladac.php
        $noveHodnoty = [
            UzivatelSql::ZPUSOB_ZOBRAZENI_NA_WEBU => ZpusobZobrazeniNaWebu::zHodnoty('2')->value,
        ];
        $zmenilo = \Uzivatel::zmeniloSeJmenoNaWebu($vypravec, $noveHodnoty);
        dbUpdate(UzivatelSql::UZIVATELE_HODNOTY_TABULKA, $noveHodnoty, [
            UzivatelSql::ID_UZIVATELE => $idVypravece,
        ]);
        if ($zmenilo) {
            \Uzivatel::invalidujProgramCacheJeLiVypravecem($idVypravece);
        }

        self::assertTrue($zmenilo, 'zmeniloSeJmenoNaWebu musí vrátit true');
        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'admin controller: změna způsobu zobrazení vypravěče',
        );
    }

    /**
     * @test
     */
    public function zmenaJmenaNevypraveceNenastaviFlag(): void
    {
        $idHosta = 900002;
        $host = $this->vlozUzivatele($idHosta, 'hoststarynick', 'Petr', 'Host');
        // NENÍ organizátorem
        $this->smazVsechnyDirtyFlagy();

        $host->uprav([
            'login_uzivatele' => 'hostnovynick',
        ]);

        $soubor = $this->cestaKDirtyFlagu(ProgramStaticFileType::AKTIVITY);
        self::assertFileDoesNotExist(
            $soubor,
            'U neorganizátora nesmí změna jména triggerovat přegenerování programu (zbytečná zátěž).',
        );
    }

    /**
     * @test
     */
    public function zmenaEmailuVypraveceNenastaviFlag(): void
    {
        $idAktivity = $this->vlozAktivitu();
        $idVypravece = 900003;
        $vypravec = $this->vlozUzivatele($idVypravece, 'emailnick', 'Karel', 'Email');
        dbInsertUpdate('akce_organizatori', [
            'id_akce'      => $idAktivity,
            'id_uzivatele' => $idVypravece,
        ]);
        $this->smazVsechnyDirtyFlagy();

        // email se nezobrazuje v programu, takže jeho změna nesmí triggerovat regeneraci
        $vypravec->uprav([
            'email1_uzivatele' => 'novy.email@example.test',
        ]);

        $soubor = $this->cestaKDirtyFlagu(ProgramStaticFileType::AKTIVITY);
        self::assertFileDoesNotExist(
            $soubor,
            'Změna e-mailu (i vypravěče) nesmí triggerovat regeneraci programu.',
        );
    }

    /**
     * @test
     */
    public function plusminusZpracujZvysenieKapacityNastaviObsazenostiFlag(): void
    {
        $idAktivity = $this->vlozAktivitu([
            Sql::TEAMOVA => 1,
        ]);

        $_POST = [
            Aktivita::PN_PLUSMINUSP => (string) $idAktivity,
        ];
        try {
            // Vyvoláme reflexí chráněnou statickou metodu bez reloadu stránky
            $reflection = new \ReflectionMethod(Aktivita::class, 'plusminusZpracuj');
            $reflection->setAccessible(true);
            $reflection->invoke(null, false);
        } finally {
            $_POST = [];
        }

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::OBSAZENOSTI,
            'zvýšení kapacity týmové aktivity tlačítkem +',
        );
    }

    /**
     * @test
     */
    public function legacyUlozeniAktivityNastaviVsechnyProgramFlagy(): void
    {
        $idAktivity = $this->vlozAktivitu([
            Sql::URL_AKCE => 'legacy-ulozeni-' . uniqid('', true),
        ]);
        $this->smazVsechnyDirtyFlagy();

        Aktivita::uloz(
            data: [
                Sql::ID_AKCE      => $idAktivity,
                Sql::NAZEV_AKCE   => 'Legacy upravená aktivita',
                Sql::URL_AKCE     => 'legacy-upravena-' . uniqid('', true),
                Sql::POPIS_KRATKY => 'Upravený krátký popis',
                Sql::ROK          => self::ROK,
                Sql::STAV         => StavAktivity::AKTIVOVANA,
                Sql::TYP          => TypAktivity::DESKOHERNA,
                Sql::ZACATEK      => date('Y-m-d 10:00:00'),
                Sql::KONEC        => date('Y-m-d 13:00:00'),
                Sql::KAPACITA     => 7,
                Sql::KAPACITA_F   => 0,
                Sql::KAPACITA_M   => 0,
                Sql::CENA         => 150,
                Sql::TEAMOVA      => 0,
            ],
            markdownPopis: 'Upravený popis aktivity',
            organizatoriIds: [],
            lokaceIds: [],
            hlavniLokaceId: null,
            tagIds: [],
            systemoveNastaveni: \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(),
        );

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'uložení aktivity přes legacy editor',
        );
        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::POPISY,
            'uložení aktivity přes legacy editor',
        );
        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::OBSAZENOSTI,
            'uložení aktivity přes legacy editor',
        );
    }

    /**
     * @test
     */
    public function aktivujNastaviAktivityFlag(): void
    {
        $idAktivity = $this->vlozAktivitu([
            Sql::STAV => StavAktivity::PUBLIKOVANA,
        ]);

        Aktivita::zId($idAktivity)->aktivuj();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'aktivace aktivity (Aktivita::aktivuj)',
        );
    }

    /**
     * @test
     */
    public function publikujNastaviAktivityFlag(): void
    {
        $idAktivity = $this->vlozAktivitu([
            Sql::STAV => StavAktivity::NOVA,
        ]);

        Aktivita::zId($idAktivity)->publikuj();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'publikace aktivity (Aktivita::publikuj)',
        );
    }

    /**
     * @test
     */
    public function pripravNastaviAktivityFlag(): void
    {
        $idAktivity = $this->vlozAktivitu([
            Sql::STAV => StavAktivity::PUBLIKOVANA,
        ]);

        Aktivita::zId($idAktivity)->priprav();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'příprava aktivity (Aktivita::priprav)',
        );
    }

    /**
     * @test
     */
    public function odpripravNastaviAktivityFlag(): void
    {
        $idAktivity = $this->vlozAktivitu([
            Sql::STAV => StavAktivity::PRIPRAVENA,
        ]);

        Aktivita::zId($idAktivity)->odpriprav();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'návrat aktivity z přípravy (Aktivita::odpriprav)',
        );
    }

    /**
     * @test
     */
    public function pridejDiteNastaviAktivityFlag(): void
    {
        $idRodice = $this->vlozAktivitu();
        $idDitete = $this->vlozAktivitu();

        Aktivita::zId($idRodice)->pridejDite($idDitete);

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'přidání dítěte aktivity',
        );
    }

    /**
     * @test
     */
    public function plusminusZpracujSnizeniKapacityNastaviObsazenostiFlag(): void
    {
        $idAktivity = $this->vlozAktivitu([
            Sql::TEAMOVA => 1,
        ]);

        $_POST = [
            Aktivita::PN_PLUSMINUSM => (string) $idAktivity,
        ];
        try {
            $reflection = new \ReflectionMethod(Aktivita::class, 'plusminusZpracuj');
            $reflection->setAccessible(true);
            $reflection->invoke(null, false);
        } finally {
            $_POST = [];
        }

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::OBSAZENOSTI,
            'snížení kapacity týmové aktivity tlačítkem −',
        );
    }

    /**
     * @test
     * Po úspěšné SQL migraci se musí všechny 4 typy JSON programu označit
     * jako dirty — migrace píší přímo do DB a obcházejí jak legacy kód,
     * tak Doctrine listener.
     */
    public function sqlMigraceOznaciVsechnyProgramCacheFlagy(): void
    {
        SqlMigrace::vytvorZGlobals()->oznacProgramCacheJakoDirty();

        foreach (ProgramStaticFileType::cases() as $typ) {
            $this->assertDirtyFlagNastaven(
                $typ,
                "po SQL migraci pro typ {$typ->value}",
            );
        }
    }
}
