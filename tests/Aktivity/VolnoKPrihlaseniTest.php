<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use App\Structure\Sql\UserRoleSqlStructure as UserRoleSql;
use App\Structure\Sql\UserSqlStructure as UserSql;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Pohlavi;

/**
 * Aktivita::isVolnoKPrihlaseni(), podle které filtr „Jen volná místa“ na stránce linie nechává karty.
 */
class VolnoKPrihlaseniTest extends AbstractTestDb
{
    use ProbihaRegistraceAktivitTrait;

    protected static bool $disableStrictTransTables = true;

    private SystemoveNastaveni $systemoveNastaveni;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();
    }

    public function testVolneUniverzalniMistoJeVolnoProKohokoli(): void
    {
        $aktivita = $this->aktivita(kapacitaUnisex: 2);

        self::assertTrue($aktivita->isVolnoKPrihlaseni());
        self::assertTrue($aktivita->isVolnoKPrihlaseni($this->uzivatel(Pohlavi::MUZ_KOD)));
        self::assertTrue($aktivita->isVolnoKPrihlaseni($this->uzivatel(Pohlavi::ZENA_KOD)));
    }

    public function testPlnaAktivitaNeniVolna(): void
    {
        $aktivita = $this->aktivita(kapacitaUnisex: 1);
        $this->prihlas($aktivita, $this->uzivatel(Pohlavi::ZENA_KOD));

        self::assertFalse($aktivita->isVolnoKPrihlaseni());
        self::assertFalse($aktivita->isVolnoKPrihlaseni($this->uzivatel(Pohlavi::ZENA_KOD)));
    }

    public function testKdyzZbyvajiJenZenskaMistaNeniVolnoProMuze(): void
    {
        $aktivita = $this->aktivita(kapacitaMuzu: 1, kapacitaZen: 1);
        $this->prihlas($aktivita, $this->uzivatel(Pohlavi::MUZ_KOD));

        self::assertFalse($aktivita->isVolnoKPrihlaseni($this->uzivatel(Pohlavi::MUZ_KOD)));
        self::assertTrue($aktivita->isVolnoKPrihlaseni($this->uzivatel(Pohlavi::ZENA_KOD)));
        self::assertTrue($aktivita->isVolnoKPrihlaseni(), 'Bez uživatele stačí místo pro kohokoli');
    }

    public function testNeprihlasovatelnaAktivitaNeniVolnaAniSVolnymMistem(): void
    {
        $aktivita = $this->aktivita(kapacitaUnisex: 5, stav: StavAktivity::PUBLIKOVANA);

        self::assertFalse($aktivita->isVolnoKPrihlaseni());
    }

    public function testTymovaAktivitaSePocitaPoTymech(): void
    {
        self::assertTrue($this->aktivita(kapacitaUnisex: 5, tymovaKapacita: 1)->isVolnoKPrihlaseni());
        self::assertFalse(
            $this->aktivita(kapacitaUnisex: 5, tymovaKapacita: 0)->isVolnoKPrihlaseni(),
            'Místa po hlavách nerozhodují, když se na aktivitu nevejde žádný tým',
        );
    }

    private function aktivita(
        int $kapacitaUnisex = 0,
        int $kapacitaMuzu = 0,
        int $kapacitaZen = 0,
        int $stav = StavAktivity::AKTIVOVANA,
        ?int $tymovaKapacita = null,
    ): Aktivita {
        dbInsert(AktivitaSql::AKCE_SEZNAM_TABULKA, [
            AktivitaSql::NAZEV_AKCE    => 'Volno k přihlášení ' . self::unikatniCislo(),
            AktivitaSql::TYP           => TypAktivity::LARP,
            AktivitaSql::ROK           => ROCNIK,
            AktivitaSql::STAV          => $stav,
            AktivitaSql::ZACATEK       => ROCNIK . '-07-16 10:00:00',
            AktivitaSql::KONEC         => ROCNIK . '-07-16 13:00:00',
            AktivitaSql::KAPACITA      => $kapacitaUnisex,
            AktivitaSql::KAPACITA_M    => $kapacitaMuzu,
            AktivitaSql::KAPACITA_F    => $kapacitaZen,
            AktivitaSql::CENA          => 0,
            AktivitaSql::TEAMOVA       => $tymovaKapacita !== null ? 1 : 0,
            AktivitaSql::TEAM_KAPACITA => $tymovaKapacita,
        ]);

        return Aktivita::zId((int) dbInsertId(), false, $this->systemoveNastaveni);
    }

    private function prihlas(Aktivita $aktivita, \Uzivatel $uzivatel): void
    {
        $aktivita->prihlas($uzivatel, $uzivatel, Aktivita::UKAZAT_DETAILY_CHYBY);
    }

    private function uzivatel(string $pohlavi): \Uzivatel
    {
        $cislo = self::unikatniCislo();
        dbInsert(UserSql::_table, [
            UserSql::login_uzivatele  => 'test_volno_' . $cislo,
            UserSql::email1_uzivatele => 'godric.cz+gc_test_volno_' . $cislo . '@gmail.com',
            UserSql::pohlavi          => $pohlavi,
        ]);
        $idUzivatele = dbInsertId();
        dbInsert(UserRoleSql::_table, [
            UserRoleSql::id_uzivatele => $idUzivatele,
            UserRoleSql::id_role      => Role::PRIHLASEN_NA_LETOSNI_GC,
        ]);
        $uzivatel = \Uzivatel::zId($idUzivatele);
        self::assertNotNull($uzivatel);

        return $uzivatel;
    }

    private static function unikatniCislo(): int
    {
        static $pocitadlo = 200000;

        return ++$pocitadlo;
    }
}
