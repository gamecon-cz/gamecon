<?php

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\SqlStruktura\TypAktivitySqlStruktura as TypSql;
use Uzivatel;

class AktivitaTest extends AbstractTestDb
{
    private const KOLIK_MINUT_JE_ODHLASENI_BEZ_POKUTY = 12345;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, rok)
VALUES
    (1, 'foo', 2022),
    (2, 'bar', 2023),
    (3, 'baz', 2023)
SQL,
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 123, login_uzivatele = 'BylJsemTam', jmeno_uzivatele = 'BylJsem', prijmeni_uzivatele = 'Tam', email1_uzivatele = 'byl.jsem.tam@dot.com'
SQL,
        <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = 124, login_uzivatele = 'JsemNekdoJiny', jmeno_uzivatele = 'JsemNekdo', prijmeni_uzivatele = 'Jiny', email1_uzivatele = 'jsem.nekdo.jiny@dot.com'
SQL,
        <<<SQL
INSERT INTO akce_prihlaseni_log(id_akce, rocnik, id_uzivatele, zdroj_zmeny)
VALUES
    (1, 2022, 123, 'neco'),
    (2, 2023, 123, 'neco'),
    (3, 2023, 123, 'neco')
SQL,
    ];

    protected static function getInitCallbacks(): array
    {
        return [
            fn() => dbInsertUpdate(
                TypSql::TYP_AKTIVITY_TABULKA,
                [TypSql::ID_TYPU => TypAktivity::DESKOHERNA, TypSql::STRANKA_O => dbFetchSingle('SELECT id_stranky FROM stranky LIMIT 1')],
            ),
        ];
    }

    protected static bool $disableStrictTransTables = true;

    private static function ted(): DateTimeImmutableStrict
    {
        static $ted;
        if (!$ted) {
            $ted = new DateTimeImmutableStrict();
        }
        return $ted;
    }

    /**
     * @test
     */
    public function Nedostanu_zadnou_zmenu_stavu_aktivit_kdyz_nedam_aktivity()
    {
        self::assertNull(Aktivita::posledniZmenaStavuAktivit([]));
    }

    /**
     * @test
     */
    public function Nedostanu_zadne_posledni_zmeny_stavu_aktivit_kdyz_nedam_zname_stavy()
    {
        $posledniZmenyStavuAktivit = Aktivita::dejPosledniZmenyStavuAktivit([]);
        self::assertSame([], $posledniZmenyStavuAktivit->zmenyStavuAktivit());
    }

    /**
     * @test
     * @dataProvider provideZdrojOdhlaseni
     */
    public function Muzu_ziskat_nazvy_zrusenych_aktivit_uzivatele(
        int    $idUzivatele,
        string $zdrojOdhlaseni,
        int    $rocnik,
        array  $ocekavaneNazvy,
    )
    {
        $zruseneAktivityUzivatele = Aktivita::dejZruseneAktivityUzivatele(
            Uzivatel::zIdUrcite($idUzivatele),
            $zdrojOdhlaseni,
            $rocnik,
        );
        $nazvyZrusenychAktivit    = array_map(static fn(Aktivita $aktivita) => $aktivita->nazev(), $zruseneAktivityUzivatele);
        self::assertSame($ocekavaneNazvy, $nazvyZrusenychAktivit);
    }

    public static function provideZdrojOdhlaseni()
    {
        return [
            'ten rok tam nebyl'                           => [123, 'neco', 2019, []],
            'ten rok byl na GC ale tohle neodhlasil'      => [123, 'jineho', 2022, []],
            'ten rok byl na GC a jednu aktivitu odhlasil' => [123, 'neco', 2022, ['foo']],
            'ten rok byl na GC a dvě aktivity odhlasil'   => [123, 'neco', 2023, ['bar', 'baz']],
            'někdo jiný'                                  => [124, 'neco', 2023, []],
        ];
    }

    /**
     * @test
     * @dataProvider provideCasyPrihlaseniAOdhlaseni
     */
    public function Storno_se_zapocita_pro_dele_prihlasene(
        DateTimeImmutableStrict $prihlasilSeKdy,
        bool                    $maPLatitStorno,
    )
    {
        dbUpdate(
            Sql::AKCE_SEZNAM_TABULKA,
            /** začátek "teď" je kvůli @see \Gamecon\Aktivita\Aktivita::zbyvaHodinDoZacatku */
            [Sql::ZACATEK => new DateTimeImmutableStrict(), Sql::TYP => TypAktivity::DESKOHERNA],
            [Sql::ID_AKCE => 1],
        );

        $aktivita = Aktivita::zId(id: 1);

        $uzivatel = Uzivatel::zId(1);
        $uzivatel->gcPrihlas($uzivatel);
        self::assertTrue($uzivatel->gcPrihlasen());

        $aktivita->prihlas($uzivatel, $uzivatel, 0b111111111111);
        dbUpdate(
            'akce_prihlaseni_log',
            ['kdy' => $prihlasilSeKdy], // jenom malý hack
            ['id_uzivatele' => $uzivatel->id(), 'id_akce' => $aktivita->id()],
        );

        $systemoveNastaveniProStorno = $this->systemoveNastaveniProStorno();
        $aktivita                    = Aktivita::zId(id: 1, systemoveNastaveni: $systemoveNastaveniProStorno); // reload

        self::assertTrue($aktivita->prihlasen($uzivatel), 'Měl by být přihlášen');
        self::assertFalse($aktivita->platiStorno($uzivatel), 'Zatím by neměl mít důvod platit storno');

        $aktivita->odhlas($uzivatel, $uzivatel, 'testy');
        $aktivita = Aktivita::zId(id: 1); // reload
        self::assertFalse($aktivita->prihlasen($uzivatel), 'Měl by být odhlášen');

        self::assertSame(
            $maPLatitStorno,
            $aktivita->platiStorno($uzivatel),
            'Očekávali jsme jiný výsledek zda má platit storno za zrušení aktivity',
        );
    }

    private function systemoveNastaveniProStorno(int $kolikMinutJeOdhlaseniBezPokuty = self::KOLIK_MINUT_JE_ODHLASENI_BEZ_POKUTY)
    {
        return new class($kolikMinutJeOdhlaseniBezPokuty, self::ted()) extends SystemoveNastaveni {
            public function __construct(
                private readonly int    $kolikMinutJeOdhlaseniBezPokuty,
                DateTimeImmutableStrict $ted,
            )
            {
                parent::__construct(
                    ROCNIK,
                    $ted,
                    false,
                    false,
                    DatabazoveNastaveni::vytvorZGlobals(),
                    '',
                    sys_get_temp_dir(),
                );
            }

            public function kontrolovatPokutuZaOdhlaseni(): bool
            {
                return true;
            }

            public function kolikMinutJeOdhlaseniBezPokuty(): int
            {
                return $this->kolikMinutJeOdhlaseniBezPokuty;
            }

            public function kolikHodinPredAktivitouUzJePokutaZaOdhlaseni(): int
            {
                return 24;
            }
        };
    }

    public static function provideCasyPrihlaseniAOdhlaseni(): array
    {
        return [
            'odhlásil se hned'                     => [self::ted(), false],
            'odhlásil se jen tak tak bez pokuty'   => [self::ted()->modify('-' . self::KOLIK_MINUT_JE_ODHLASENI_BEZ_POKUTY . ' minutes'), false],
            'těsně se nestihl odhlásit bez pokuty' => [self::ted()->modify('-' . self::KOLIK_MINUT_JE_ODHLASENI_BEZ_POKUTY . ' minutes')->modify('+1 second'), false],
            'odhlásil se pozdě'                    => [self::ted()->modify('-' . (self::KOLIK_MINUT_JE_ODHLASENI_BEZ_POKUTY + 1) . ' minutes'), true],
        ];
    }

    /**
     * @test
     */
    public function Muzu_zkusit_ziskat_aktivitu_podle_id_pomoci_null()
    {
        self::assertSame(null, Aktivita::zId(null));
    }
}
