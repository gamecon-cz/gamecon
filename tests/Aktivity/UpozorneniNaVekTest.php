<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\KontaktSefaLinie;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\KategorieTagu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Minimální věk aktivity ze štítku „Věk: 15+" a upozornění mladšího účastníka před přihlášením.
 * Přihlášení se nebrání: šéf sekce dostane e-mail (MladsiUcastnikNaAktiviteTest) a ozve se mu.
 */
class UpozorneniNaVekTest extends AbstractTestDb
{
    use ProbihaRegistraceAktivitTrait;
    use VekAktivityTestTrait;

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
        $this->nastavSefaSekceLarpu('šéfka Larpů', 'larpy.test@example.test');
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
        // z kontaktu udělá dialog v Preactu odkaz
        self::assertStringContainsString('data-kontakt="larpy.test@example.test"', $prihlasovatko);
        self::assertStringContainsString('kontakt: formular.dataset.kontakt', $prihlasovatko);
    }

    public function testDostatecneStaremuPrihlasovatkoRovnouPrihlasi(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        $prihlasovatko = $aktivita->prihlasovatko($this->vytvorUcastnika(vekVDenAktivity: 30), 0);

        self::assertStringContainsString('name="prihlasit"', $prihlasovatko);
        self::assertStringNotContainsString('upozorneniNaVek', $prihlasovatko);
    }

    public function testUpozorneniMaVNadpisuAktivituAVekAVTextuSefaSekce(): void
    {
        $this->nastavSefaSekceLarpu('Šéfka Larpů', 'larpy.test@example.test');
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        $upozorneni = $aktivita->getUpozorneniNaVek($this->vytvorUcastnika(vekVDenAktivity: 15));

        self::assertNotNull($upozorneni);
        self::assertSame("Aktivita {$aktivita->nazev()} je od 18 let", $upozorneni['nadpis']);
        self::assertStringContainsString('ještě nebude 18', $upozorneni['text']);
        // šéf sekce dostane e-mail a ozve se, může ho odhlásit
        self::assertStringContainsString('šéfka Larpů se to ale dozví a ozve se ti e-mailem', $upozorneni['text']);
        self::assertStringContainsString('může tě z aktivity odhlásit, sleduj proto e-mail', $upozorneni['text']);
        self::assertStringContainsString('larpy.test@example.test', $upozorneni['text']);
        self::assertSame('larpy.test@example.test', $upozorneni['kontakt']);
        self::assertNull($aktivita->getUpozorneniNaVek($this->vytvorUcastnika(vekVDenAktivity: 30)));
    }

    public function testBezHlavickyLinieUpozorneniPosilaNaSefaProgramu(): void
    {
        $this->nastavSefaSekceLarpu(null);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        $upozorneni = $aktivita->getUpozorneniNaVek($this->vytvorUcastnika(vekVDenAktivity: 15));

        self::assertNotNull($upozorneni);
        self::assertStringContainsString('šéf programu se to ale dozví', $upozorneni['text']);
        self::assertSame(Aktivita::KONTAKT_NA_SEFA_PROGRAMU, $upozorneni['kontakt']);
    }

    public function testSekceKteraNeniRoleJdeDoVetyJakoSefSekce(): void
    {
        // sekce je v hlavičce linie volný text, „larpy se to ale dozví“ by nedávalo smysl
        $this->nastavSefaSekceLarpu('LKD tým', 'larpy.test@example.test');
        $aktivita = $this->vytvorAktivitu('Věk: 18+');

        $upozorneni = $aktivita->getUpozorneniNaVek($this->vytvorUcastnika(vekVDenAktivity: 15));

        self::assertNotNull($upozorneni);
        self::assertStringContainsString('Přihlásit se můžeš, šéf sekce se to ale dozví', $upozorneni['text']);
        self::assertSame('larpy.test@example.test', $upozorneni['kontakt']);
    }

    public function testHlavickaBezEmailuPosilaNaSefaProgramuIKdyzMaSekci(): void
    {
        $this->nastavSefaSekceLarpu('Šéf D&D/JaD turnaje');

        $kontakt = KontaktSefaLinie::zTypu(TypAktivity::LARP);

        self::assertSame('šéf programu', $kontakt->getKdo());
        self::assertSame(Aktivita::KONTAKT_NA_SEFA_PROGRAMU, $kontakt->getEmail());
        // hlavička stránky linie ukazuje sekci a jméno, jak jsou
        self::assertSame('Šéf D&D/JaD turnaje', $kontakt->getSekce());
        self::assertSame('Lenka „Cemi“ Testovací', $kontakt->getJmeno());
    }

    public function testBezHlavickyLinieNemaStrankaLinieSekciAniJmeno(): void
    {
        $this->nastavSefaSekceLarpu(null);

        $kontakt = KontaktSefaLinie::zTypu(TypAktivity::LARP);

        self::assertNull($kontakt->getSekce());
        self::assertNull($kontakt->getJmeno());
        self::assertSame(Aktivita::KONTAKT_NA_SEFA_PROGRAMU, $kontakt->getEmail());
    }

    public function testNaTurnajiUpozorniJenNaVekPrvnihoKola(): void
    {
        // přihlášení na turnaj přihlásí i na další kola, stačí ale upozornit na první (záměr)
        [[$prvniKolo]] = $this->vytvorTurnaj([[null], ['Věk: 15+']]);
        [[$prvniKoloSVekem]] = $this->vytvorTurnaj([['Věk: 15+'], ['Věk: 18+']]);
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 12);

        $upozorneni = $prvniKoloSVekem->getUpozorneniNaVek($ucastnik);

        self::assertNull($prvniKolo->getUpozorneniNaVek($ucastnik), 'Další kolo pro starší se neupozorňuje');
        self::assertNotNull($upozorneni);
        self::assertSame("Aktivita {$prvniKoloSVekem->nazev()} je od 15 let", $upozorneni['nadpis']);
    }

    public function testPoOdhlaseniDostaneUpozorneniHned(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);
        $aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);
        $aktivita = Aktivita::zId($aktivita->id(), false, $this->systemoveNastaveni);
        self::assertNull($aktivita->getUpozorneniNaVekProPrihlasovatko($ucastnik, $ucastnik), 'Přihlášený se nehlásí');

        $aktivita->odhlas($ucastnik, $ucastnik, 'test');

        // tohle pošle odpověď na odhlášení (aktivitaAkce.php), program pak nabídne přihlášení s upozorněním
        self::assertSame(
            $aktivita->getUpozorneniNaVek($ucastnik),
            Aktivita::zId($aktivita->id(), false, $this->systemoveNastaveni)
                ->getUpozorneniNaVekProPrihlasovatko($ucastnik, $ucastnik),
        );
        self::assertNotNull($aktivita->getUpozorneniNaVek($ucastnik));
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
}
