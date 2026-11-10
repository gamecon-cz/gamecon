<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use App\Structure\Sql\TagSqlStructure as TagSql;
use App\Structure\Sql\UserRoleSqlStructure as UserRoleSql;
use App\Structure\Sql\UserSqlStructure as UserSql;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\SqlStruktura\AkceSjednoceneTagySqlStruktura as AktivitaTagSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\Tag;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\KategorieTagu;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Pohlavi;

/**
 * Minimální věk aktivity ze štítku „Věk: 15+" a upozornění mladšího účastníka před přihlášením.
 * Přihlášení se nebrání, účastník se má jen předem domluvit se šéfem programu.
 */
class UpozorneniNaVekTest extends AbstractTestDb
{
    use ProbihaRegistraceAktivitTrait;

    private const string ZACATEK_AKTIVITY = ROCNIK . '-07-16 10:00:00';

    protected static bool $disableStrictTransTables = true;

    private SystemoveNastaveni $systemoveNastaveni;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();
        Tag::smazCache();
    }

    public function testMinimalniVekJeZeStitkuVeku(): void
    {
        self::assertSame(18, $this->stitek('Věk: 18+', KategorieTagu::OMEZENI_VEK)->getMinimalniVek());
        self::assertSame(15, $this->stitek('Věk: 15+', KategorieTagu::OMEZENI_VEK)->getMinimalniVek());
    }

    public function testHorniHraniceVekuNeniMinimalniVek(): void
    {
        self::assertNull($this->stitek('Věk: do 15', KategorieTagu::OMEZENI_VEK)->getMinimalniVek());
    }

    public function testStitekMimoKategoriiVekuNemaMinimalniVek(): void
    {
        $jinaKategorie = (int) dbOneCol(<<<'SQL'
            SELECT MIN(id) FROM kategorie_sjednocenych_tagu WHERE id != $0
            SQL, [
            0 => KategorieTagu::OMEZENI_VEK,
        ]);

        self::assertNull($this->stitek('Věk: 99+', $jinaKategorie)->getMinimalniVek());
    }

    public function testAktivitaBezStitkuVekuNemaMinimalniVek(): void
    {
        $aktivita = $this->aktivita();

        self::assertNull($aktivita->getMinimalniVek());
        self::assertFalse($aktivita->isMladsiNezMinimalniVek($this->ucastnik(vekVDenAktivity: 10)));
    }

    public function testMladsiNezMinimalniVekVDenAktivity(): void
    {
        $aktivita = $this->aktivita('Věk: 18+');

        self::assertSame(18, $aktivita->getMinimalniVek());
        self::assertTrue($aktivita->isMladsiNezMinimalniVek($this->ucastnik(vekVDenAktivity: 17)));
        self::assertFalse($aktivita->isMladsiNezMinimalniVek($this->ucastnik(vekVDenAktivity: 18)));
    }

    public function testNarozeninyVDenAktivityStaci(): void
    {
        $aktivita = $this->aktivita('Věk: 18+');
        $narozeniny = (new \DateTimeImmutable(self::ZACATEK_AKTIVITY))->modify('-18 years')->format('Y-m-d');

        self::assertFalse($aktivita->isMladsiNezMinimalniVek($this->ucastnik(datumNarozeni: $narozeniny)));
    }

    public function testBezDataNarozeniNeupozornujeme(): void
    {
        $aktivita = $this->aktivita('Věk: 18+');

        self::assertFalse($aktivita->isMladsiNezMinimalniVek($this->ucastnik(datumNarozeni: '0000-00-00')));
    }

    public function testMladsimuPrihlasovatkoNejdrivUpozorni(): void
    {
        $aktivita = $this->aktivita('Věk: 18+');

        $prihlasovatko = $aktivita->prihlasovatko($this->ucastnik(vekVDenAktivity: 15), 0);

        self::assertStringContainsString('name="prihlasit"', $prihlasovatko);
        self::assertStringContainsString('data-minimalni-vek="18"', $prihlasovatko);
        self::assertStringContainsString('upozorneniNaVek', $prihlasovatko);
        self::assertStringContainsString(Aktivita::KONTAKT_NA_SEFA_PROGRAMU, $prihlasovatko);
    }

    public function testDostatecneStaremuPrihlasovatkoRovnouPrihlasi(): void
    {
        $aktivita = $this->aktivita('Věk: 18+');

        $prihlasovatko = $aktivita->prihlasovatko($this->ucastnik(vekVDenAktivity: 30), 0);

        self::assertStringContainsString('name="prihlasit"', $prihlasovatko);
        self::assertStringNotContainsString('upozorneniNaVek', $prihlasovatko);
    }

    private function aktivita(?string $stitekVeku = null): Aktivita
    {
        dbInsert(AktivitaSql::AKCE_SEZNAM_TABULKA, [
            AktivitaSql::NAZEV_AKCE => 'Larp pro starší ' . self::unikatniCislo(),
            AktivitaSql::TYP        => TypAktivity::LARP,
            AktivitaSql::ROK        => ROCNIK,
            AktivitaSql::STAV       => StavAktivity::AKTIVOVANA,
            AktivitaSql::ZACATEK    => self::ZACATEK_AKTIVITY,
            AktivitaSql::KONEC      => ROCNIK . '-07-16 13:00:00',
            AktivitaSql::KAPACITA   => 5,
            AktivitaSql::KAPACITA_M => 0,
            AktivitaSql::KAPACITA_F => 0,
            AktivitaSql::CENA       => 0,
            AktivitaSql::TEAMOVA    => 0,
        ]);
        $idAktivity = (int) dbInsertId();
        if ($stitekVeku !== null) {
            dbInsert(AktivitaTagSql::AKCE_SJEDNOCENE_TAGY_TABULKA, [
                AktivitaTagSql::ID_AKCE => $idAktivity,
                AktivitaTagSql::ID_TAGU => $this->stitek($stitekVeku, KategorieTagu::OMEZENI_VEK)->id(),
            ]);
        }

        return Aktivita::zId($idAktivity, false, $this->systemoveNastaveni);
    }

    private function stitek(string $nazev, int $idKategorie): Tag
    {
        $existujici = Tag::zNazvu($nazev);
        if ($existujici) {
            self::assertSame($idKategorie, $existujici->idKategorieTagu(), "Štítek {$nazev} už je v jiné kategorii");

            return $existujici;
        }
        dbInsert(TagSql::_table, [
            TagSql::nazev             => $nazev,
            TagSql::poznamka          => '',
            TagSql::id_kategorie_tagu => $idKategorie,
        ]);

        return Tag::zId((int) dbInsertId());
    }

    private function ucastnik(?int $vekVDenAktivity = null, ?string $datumNarozeni = null): \Uzivatel
    {
        // další narozeniny až den po aktivitě, v den aktivity je mu tedy přesně $vekVDenAktivity
        $datumNarozeni ??= (new \DateTimeImmutable(self::ZACATEK_AKTIVITY))
            ->modify('-' . ($vekVDenAktivity + 1) . ' years +1 day')
            ->format('Y-m-d');
        $cislo = self::unikatniCislo();
        dbInsert(UserSql::_table, [
            UserSql::login_uzivatele  => 'test_vek_' . $cislo,
            UserSql::email1_uzivatele => 'godric.cz+gc_test_vek_' . $cislo . '@gmail.com',
            UserSql::pohlavi          => Pohlavi::ZENA_KOD,
            UserSql::datum_narozeni   => $datumNarozeni,
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
