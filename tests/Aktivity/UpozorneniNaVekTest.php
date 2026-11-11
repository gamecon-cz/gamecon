<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use App\Structure\Sql\TagSqlStructure as TagSql;
use App\Structure\Sql\UserRoleSqlStructure as UserRoleSql;
use App\Structure\Sql\UserSqlStructure as UserSql;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSqlStruktura as PrihlaseniSql;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\SqlStruktura\AkceSjednoceneTagySqlStruktura as AktivitaTagSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
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
    }

    public function testMinimalniVekJeZeStitkuVeku(): void
    {
        self::assertSame(18, $this->getStitek('Věk: 18+', KategorieTagu::OMEZENI_VEK)->getMinimalniVek());
        self::assertSame(15, $this->getStitek('Věk: 15+', KategorieTagu::OMEZENI_VEK)->getMinimalniVek());
    }

    public function testHorniHraniceVekuNeniMinimalniVek(): void
    {
        self::assertNull($this->getStitek('Věk: do 15', KategorieTagu::OMEZENI_VEK)->getMinimalniVek());
    }

    public function testStitekMimoKategoriiVekuNemaMinimalniVek(): void
    {
        $jinaKategorie = (int) dbOneCol(<<<'SQL'
            SELECT MIN(id) FROM kategorie_sjednocenych_tagu WHERE id != $0
            SQL, [
            0 => KategorieTagu::OMEZENI_VEK,
        ]);

        self::assertNull($this->getStitek('Věk: 99+', $jinaKategorie)->getMinimalniVek());
    }

    public function testAktivitaBezStitkuVekuNemaMinimalniVek(): void
    {
        $aktivita = $this->vytvorAktivitu();

        self::assertNull($aktivita->getMinimalniVek());
        self::assertNull($aktivita->getNesplnenyMinimalniVek($this->vytvorUcastnika(vekVDenAktivity: 10)));
    }

    public function testZVicStitkuVekuPlatiNejvyssi(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 15+', 'Věk: 18+');

        self::assertSame(18, $aktivita->getMinimalniVek());
        self::assertSame(18, $aktivita->getNesplnenyMinimalniVek($this->vytvorUcastnika(vekVDenAktivity: 16)));
    }

    public function testMladsiNezMinimalniVekVDenAktivity(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        self::assertSame(18, $aktivita->getMinimalniVek());
        self::assertSame(18, $aktivita->getNesplnenyMinimalniVek($this->vytvorUcastnika(vekVDenAktivity: 17)));
        self::assertNull($aktivita->getNesplnenyMinimalniVek($this->vytvorUcastnika(vekVDenAktivity: 18)));
    }

    public function testNarozeninyVDenAktivityStaci(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $narozeniny = (new \DateTimeImmutable(self::ZACATEK_AKTIVITY))->modify('-18 years')->format('Y-m-d');

        self::assertNull($aktivita->getNesplnenyMinimalniVek($this->vytvorUcastnika(datumNarozeni: $narozeniny)));
    }

    public function testBezDataNarozeniNeupozornujeme(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        self::assertNull($aktivita->getNesplnenyMinimalniVek($this->vytvorUcastnika(datumNarozeni: '0000-00-00')));
    }

    public function testRychloregistraceSDnemRegistraceMistoNarozeniUpozorneniNedostane(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $rychloregistrovany = \Uzivatel::zId(\Uzivatel::rychloregistrace($this->systemoveNastaveni));
        self::assertNotNull($rychloregistrovany);

        self::assertFalse($rychloregistrovany->isDatumNarozeniSkutecne());
        self::assertNull($aktivita->getNesplnenyMinimalniVek($rychloregistrovany));
    }

    public function testDatumNarozeniVDenRegistraceNeboPoNiNeniSkutecne(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $minulyRocnik = ROCNIK - 1;

        // dospělý rychloregistrovaný loni na GameConu, letos se hlásí sám přes web; stejně admin anonymizované DB (NOW())
        self::assertNull($aktivita->getNesplnenyMinimalniVek(
            $this->vytvorUcastnika(datumNarozeni: "{$minulyRocnik}-07-19", registrovan: "{$minulyRocnik}-07-19 10:00:00"),
        ));
        // překlep do budoucnosti
        self::assertNull($aktivita->getNesplnenyMinimalniVek(
            $this->vytvorUcastnika(datumNarozeni: '2066-01-01', registrovan: "{$minulyRocnik}-07-19 10:00:00"),
        ));
    }

    public function testStaryUcetBezDataRegistraceUpozorneniDostane(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        self::assertSame(18, $aktivita->getNesplnenyMinimalniVek(
            $this->vytvorUcastnika(vekVDenAktivity: 15, registrovan: '0000-00-00 00:00:00'),
        ));
    }

    public function testKdoPrihlasujeZaJinehoUpozorneniNedostane(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);

        self::assertSame(18, $aktivita->getNesplnenyMinimalniVek($ucastnik, $ucastnik), 'Kdo se hlásí sám, toho upozorníme');
        self::assertNull(
            $aktivita->getNesplnenyMinimalniVek($ucastnik, $this->vytvorUcastnika(vekVDenAktivity: 40)),
            'Kdo přihlašuje za jiného z adminu, má výjimku domluvenou',
        );
    }

    public function testMladsimuPrihlasovatkoNejdrivUpozorni(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        $prihlasovatko = $aktivita->prihlasovatko($this->vytvorUcastnika(vekVDenAktivity: 15), 0);

        self::assertStringContainsString('name="prihlasit"', $prihlasovatko);
        self::assertStringContainsString(
            'data-nadpis="' . htmlspecialchars("Aktivita {$aktivita->nazev()} je od 18 let", ENT_QUOTES) . '"',
            $prihlasovatko,
        );
        // podle data-upozorneni ho zachovejScroll() v aktivity.xtpl vynechá, scroll se uloží až s potvrzením
        self::assertStringContainsString('data-upozorneni="', $prihlasovatko);
        self::assertStringContainsString('upozorneniNaVek', $prihlasovatko);
        self::assertStringContainsString(Aktivita::KONTAKT_NA_SEFA_PROGRAMU, $prihlasovatko);
    }

    public function testDostatecneStaremuPrihlasovatkoRovnouPrihlasi(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        $prihlasovatko = $aktivita->prihlasovatko($this->vytvorUcastnika(vekVDenAktivity: 30), 0);

        self::assertStringContainsString('name="prihlasit"', $prihlasovatko);
        self::assertStringNotContainsString('upozorneniNaVek', $prihlasovatko);
    }

    public function testUpozorneniMaVNadpisuAktivituAVekAVTextuKontakt(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        $upozorneni = $aktivita->getUpozorneniNaVek($this->vytvorUcastnika(vekVDenAktivity: 15));

        self::assertNotNull($upozorneni);
        self::assertSame("Aktivita {$aktivita->nazev()} je od 18 let", $upozorneni['nadpis']);
        self::assertStringContainsString('ještě nebude 18', $upozorneni['text']);
        self::assertStringContainsString(Aktivita::KONTAKT_NA_SEFA_PROGRAMU, $upozorneni['text']);
        self::assertNull($aktivita->getUpozorneniNaVek($this->vytvorUcastnika(vekVDenAktivity: 30)));
    }

    public function testTlacitkoTymuMladsimuNejdrivUpozorni(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        $tlacitko = $aktivita->getPrihlasovatkoTymu($this->vytvorUcastnika(vekVDenAktivity: 15));

        self::assertStringContainsString('upozorneniNaVek', $tlacitko);
        self::assertStringContainsString('prihlaseniTymu', $tlacitko);
        self::assertStringContainsString('Přihlásit tým', $tlacitko);
    }

    public function testTlacitkoTymuNeupozorniDostatecneStarehoAniClenaTymu(): void
    {
        $mladsi = $this->vytvorUcastnika(vekVDenAktivity: 15);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $this->prihlas($aktivita, $mladsi);
        $aktivita = Aktivita::zId($aktivita->id(), false, $this->systemoveNastaveni);

        $tlacitkoClena = $aktivita->getPrihlasovatkoTymu($mladsi);
        $tlacitkoStarsiho = $aktivita->getPrihlasovatkoTymu($this->vytvorUcastnika(vekVDenAktivity: 30));

        self::assertStringContainsString('Nastavení týmu', $tlacitkoClena);
        self::assertStringNotContainsString('upozorneniNaVek', $tlacitkoClena, 'Kdo v týmu už je, ten se nehlásí');
        self::assertStringContainsString('prihlaseniTymu', $tlacitkoStarsiho);
        self::assertStringNotContainsString('upozorneniNaVek', $tlacitkoStarsiho);
    }

    public function testApiProgramuPosilaUpozorneniJenUcastnikoviSamotnemu(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);

        self::assertSame(
            $aktivita->getUpozorneniNaVek($ucastnik),
            $this->getAktivitaZApiProgramu($aktivita->id(), $ucastnik)['upozorneniNaVek'],
        );
        self::assertNull(
            $this->getAktivitaZApiProgramu($aktivita->id(), $this->vytvorUcastnika(vekVDenAktivity: 40), $ucastnik)['upozorneniNaVek'],
            'Kdo přihlašuje za jiného z adminu, upozornění nedostane',
        );
    }

    public function testApiProgramuNeupozorniDostatecneStarehoUcastnika(): void
    {
        $idAktivity = $this->vytvorAktivitu('Věk: 18+')->id();

        $aktivitaZApi = $this->getAktivitaZApiProgramu($idAktivity, $this->vytvorUcastnika(vekVDenAktivity: 30));

        self::assertArrayHasKey('upozorneniNaVek', $aktivitaZApi);
        self::assertNull($aktivitaZApi['upozorneniNaVek']);
    }

    public function testApiProgramuNeupozorniUzPrihlaseneho(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);
        $this->prihlas($aktivita, $ucastnik);

        self::assertNull($this->getAktivitaZApiProgramu($aktivita->id(), $ucastnik)['upozorneniNaVek']);
    }

    private function vytvorAktivitu(string ...$stitkyVeku): Aktivita
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
        foreach ($stitkyVeku as $stitekVeku) {
            dbInsert(AktivitaTagSql::AKCE_SJEDNOCENE_TAGY_TABULKA, [
                AktivitaTagSql::ID_AKCE => $idAktivity,
                AktivitaTagSql::ID_TAGU => $this->getStitek($stitekVeku, KategorieTagu::OMEZENI_VEK)->id(),
            ]);
        }

        return Aktivita::zId($idAktivity, false, $this->systemoveNastaveni);
    }

    /**
     * Aktivita z web/moduly/api/aktivityUzivatel.php, jak ji dostane Preact program.
     * $prihlasujici je přihlášený uživatel, $ucastnik ten, za koho přihlašuje z adminu.
     *
     * @return array<string, mixed>
     */
    private function getAktivitaZApiProgramu(int $idAktivity, \Uzivatel $prihlasujici, ?\Uzivatel $ucastnik = null): array
    {
        // skript dostane jen proměnné, které čte z kontextu webu / adminu, a svými nepřepíše proměnné testu
        $spustApi = static function (\Uzivatel $u, ?\Uzivatel $uPracovni, SystemoveNastaveni $systemoveNastaveni): void {
            require __DIR__ . '/../../web/moduly/api/aktivityUzivatel.php';
        };

        $puvodniMetoda = $_SERVER['REQUEST_METHOD'] ?? null;
        $puvodniGet = $_GET;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        ob_start();
        try {
            $spustApi($prihlasujici, $ucastnik, $this->systemoveNastaveni);
        } finally {
            $vystup = (string) ob_get_clean();
            $_GET = $puvodniGet;
            if ($puvodniMetoda === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $puvodniMetoda;
            }
        }

        $odpoved = json_decode($vystup, true, 512, JSON_THROW_ON_ERROR);
        foreach ($odpoved['data']['aktivityUzivatel'] as $aktivita) {
            if ($aktivita['id'] === $idAktivity) {
                return $aktivita;
            }
        }
        self::fail("API programu aktivitu {$idAktivity} nevrátilo");
    }

    private function getStitek(string $nazev, int $idKategorie): Tag
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

    /**
     * @param string $registrovan narození v den registrace nebo po ní je jen výplň (rychloregistrace), výchozí je proto
     *                            začátek ročníku, kdy už se každý z testů narodil
     */
    private function vytvorUcastnika(
        ?int $vekVDenAktivity = null,
        ?string $datumNarozeni = null,
        string $registrovan = ROCNIK . '-01-01 00:00:00',
    ): \Uzivatel {
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
            UserSql::registrovan      => $registrovan,
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

    private function prihlas(Aktivita $aktivita, \Uzivatel $ucastnik): void
    {
        dbInsert(PrihlaseniSql::AKCE_PRIHLASENI_TABULKA, [
            PrihlaseniSql::ID_AKCE             => $aktivita->id(),
            PrihlaseniSql::ID_UZIVATELE        => $ucastnik->id(),
            PrihlaseniSql::ID_STAVU_PRIHLASENI => StavPrihlaseni::PRIHLASEN,
        ]);
    }

    private static function unikatniCislo(): int
    {
        static $pocitadlo = 200000;

        return ++$pocitadlo;
    }
}
