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
 * Genderově rozdělená aktivita, kde zbývají volná místa jen pro opačné pohlaví
 * (volno() === 'f' / 'm'), musí přesto nabídnout sledování — z pohledu uživatele,
 * pro jehož pohlaví už je plno, je aktivita plná a sledovat ji dává smysl.
 */
class SledovaniGenderoveRozdeleneAktivityTest extends AbstractTestDb
{
    use ProbihaRegistraceAktivitTrait;

    protected static bool $disableStrictTransTables = true;

    private SystemoveNastaveni $systemoveNastaveni;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();
    }

    public function testKdyzMuziVyzraliMistaNabidneSeMuziSledovani(): void
    {
        $aktivita = $this->genderoveRozdelenaAktivita(kapacitaMuzu: 2, kapacitaZen: 2);
        $this->zaplnMuzskaMista($aktivita, 2);

        // muž, pro kterého je aktivita plná (zbývají jen ženská místa)
        $muz = $this->prihlasenyUzivatelNaGc(Pohlavi::MUZ_KOD);

        $out = $aktivita->prihlasovatko($muz, 0);

        self::assertStringContainsString('pouze ženská místa', $out);
        self::assertStringContainsString('sledovat', $out);
        self::assertStringContainsString('prihlasSledujiciho', $out);
    }

    public function testZeSledovaniSeLzeOdhlasitIKdyzZbyvajiMistaOpacnehoPohlavi(): void
    {
        $aktivita = $this->genderoveRozdelenaAktivita(kapacitaMuzu: 2, kapacitaZen: 2);
        $this->zaplnMuzskaMista($aktivita, 2);

        $muz = $this->prihlasenyUzivatelNaGc(Pohlavi::MUZ_KOD);
        $aktivita->prihlasSledujiciho($muz, $muz);
        self::assertTrue($aktivita->prihlasenJakoSledujici($muz));

        $out = $aktivita->prihlasovatko($muz, 0);

        self::assertStringContainsString('pouze ženská místa', $out);
        self::assertStringContainsString('zrušit sledování', $out);
        self::assertStringContainsString('odhlasSledujiciho', $out);
    }

    public function testUplnePlnaAktivitaNabidneSledovaniBeztextu(): void
    {
        // regrese: u úplně plné aktivity (volno() === 'x') zůstává jen odkaz na sledování, bez textu „pouze … místa"
        $aktivita = $this->genderoveRozdelenaAktivita(kapacitaMuzu: 1, kapacitaZen: 0);
        $muzNaMiste = $this->prihlasenyUzivatelNaGc(Pohlavi::MUZ_KOD);
        $aktivita->prihlas($muzNaMiste, $muzNaMiste, Aktivita::UKAZAT_DETAILY_CHYBY);
        self::assertSame('x', $aktivita->volno());

        $dalsiMuz = $this->prihlasenyUzivatelNaGc(Pohlavi::MUZ_KOD);
        $out = $aktivita->prihlasovatko($dalsiMuz, 0);

        self::assertStringNotContainsString('pouze', $out);
        self::assertStringContainsString('sledovat', $out);
    }

    private function genderoveRozdelenaAktivita(int $kapacitaMuzu, int $kapacitaZen): Aktivita
    {
        dbInsert(AktivitaSql::AKCE_SEZNAM_TABULKA, [
            AktivitaSql::NAZEV_AKCE => 'Genderově rozdělená aktivita',
            AktivitaSql::TYP        => TypAktivity::LARP,
            AktivitaSql::ROK        => ROCNIK,
            AktivitaSql::STAV       => StavAktivity::AKTIVOVANA,
            AktivitaSql::ZACATEK    => ROCNIK . '-07-16 10:00:00',
            AktivitaSql::KONEC      => ROCNIK . '-07-16 13:00:00',
            AktivitaSql::KAPACITA   => 0,
            AktivitaSql::KAPACITA_M => $kapacitaMuzu,
            AktivitaSql::KAPACITA_F => $kapacitaZen,
            AktivitaSql::CENA       => 0,
            AktivitaSql::TEAMOVA    => 0,
        ]);

        return Aktivita::zId((int) dbInsertId(), false, $this->systemoveNastaveni);
    }

    private function zaplnMuzskaMista(Aktivita $aktivita, int $pocet): void
    {
        for ($i = 0; $i < $pocet; ++$i) {
            $muz = $this->prihlasenyUzivatelNaGc(Pohlavi::MUZ_KOD);
            $aktivita->prihlas($muz, $muz, Aktivita::UKAZAT_DETAILY_CHYBY);
        }
        self::assertSame('f', $aktivita->volno(), 'Muži měli vyžrat všechna mužská místa');
    }

    private function prihlasenyUzivatelNaGc(string $pohlavi): \Uzivatel
    {
        $cislo = self::unikatniCislo();
        dbInsert(UserSql::_table, [
            UserSql::login_uzivatele  => 'test_' . $cislo,
            UserSql::email1_uzivatele => 'godric.cz+gc_test_' . $cislo . '@gmail.com',
            UserSql::pohlavi          => $pohlavi,
        ]);
        $idUzivatele = dbInsertId();
        dbInsert(UserRoleSql::_table, [
            UserRoleSql::id_uzivatele => $idUzivatele,
            UserRoleSql::id_role      => Role::PRIHLASEN_NA_LETOSNI_GC,
        ]);
        $uzivatel = \Uzivatel::zId($idUzivatele);
        self::assertNotNull($uzivatel);
        self::assertTrue($uzivatel->gcPrihlasen());

        return $uzivatel;
    }

    private static function unikatniCislo(): int
    {
        static $pocitadlo = 100000;

        return ++$pocitadlo;
    }
}
