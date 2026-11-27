<?php

declare(strict_types=1);

namespace Gamecon\Tests\PushNotifikace;

use Gamecon\Pravo;
use Gamecon\PushNotifikace\ChybaPushNotifikace;
use Gamecon\PushNotifikace\OdeslaniZAdminu;
use Gamecon\PushNotifikace\PushNaWebu;
use Gamecon\PushNotifikace\PushNotifikace;
use Gamecon\PushNotifikace\StavOdeslani;
use Gamecon\PushNotifikace\StrankaAdminu;
use Gamecon\PushNotifikace\TestovaciNotifikace;
use Gamecon\PushNotifikace\TypNotifikace;
use Gamecon\PushNotifikace\ZaznamOdeslani;
use Gamecon\PushNotifikace\ZpravaNotifikace;
use Gamecon\Tests\Db\AbstractTestDb;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Odeslání z adminu: jen s právem, jen když jsou notifikace zapnuté, jen vybranému uživateli a ne moc často.
 */
class OdeslaniZAdminuTest extends AbstractTestDb
{
    use PushNotifikaceTestTrait;

    private const string URL_ADMINU = 'https://admin.gamecon.test';

    private const string URL_STRANKY = self::URL_ADMINU . '/web/push-notifikace';

    private FalesnyHttpKlient $pushSluzba;

    private MockClock $hodiny;

    private string $adresarLogu;

    /**
     * @var array<mixed>|null
     */
    private ?array $sessionPredTestem = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pushSluzba = new FalesnyHttpKlient();
        $this->hodiny = new MockClock('2026-07-16 12:00:00');
        $this->adresarLogu = LOGY . '/odeslani-z-adminu-test-' . bin2hex(random_bytes(4));
        $this->sessionPredTestem = $_SESSION ?? null;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->sessionPredTestem === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionPredTestem;
        }
        \Chyba::vyzvedniHtml();
        (new Filesystem())->remove($this->adresarLogu);
        parent::tearDown();
    }

    public function testSPravemOdesleVybranemuUzivateli(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $endpoint = $this->zapniNaNovemZarizeni($idPrijemce);
        $this->zapniNaNovemZarizeni($this->vytvorUzivatelePush());

        $vysledky = $this->pushNotifikace()->getOdeslaniZAdminu()->posli(
            $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]),
            $this->uzivatel($idPrijemce),
            self::zprava(),
        );

        self::assertCount(1, $vysledky);
        self::assertSame(StavOdeslani::PREDANO, $vysledky[0]->stav);
        self::assertSame([$endpoint], $this->pushSluzba->getEndpointy());
    }

    /**
     * @param list<int> $prava
     */
    #[DataProvider('pravaBezOdesilani')]
    public function testBezPravaNeodesle(array $prava): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idPrijemce);

        try {
            $this->pushNotifikace()->getOdeslaniZAdminu()->posli(
                $this->uzivatel($this->vytvorUzivatelePush(), $prava),
                $this->uzivatel($idPrijemce),
                self::zprava(),
            );
            self::fail('Bez práva na push notifikace se nesmí odeslat nic.');
        } catch (ChybaPushNotifikace $chyba) {
            self::assertSame(403, $chyba->getHttpKod());
        }
        self::assertSame([], $this->pushSluzba->pozadavky);
    }

    /**
     * @return \Generator<string, array{list<int>}>
     */
    public static function pravaBezOdesilani(): \Generator
    {
        yield 'účastník bez práv' => [[]];
        yield 'správce webu' => [[Pravo::ADMINISTRACE_WEB]];
        yield 'infopult a finance' => [[Pravo::ADMINISTRACE_INFOPULT, Pravo::ADMINISTRACE_FINANCE]];
    }

    public function testVypnuteNotifikaceNeodeslouAniSPravem(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idPrijemce);

        try {
            $this->pushNotifikace(zapnuto: false)->getOdeslaniZAdminu()->posli(
                $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]),
                $this->uzivatel($idPrijemce),
                self::zprava(),
            );
            self::fail('Vypnuté notifikace nesmí nic odeslat.');
        } catch (ChybaPushNotifikace $chyba) {
            self::assertSame(409, $chyba->getHttpKod());
        }
        self::assertSame([], $this->pushSluzba->pozadavky);
    }

    /**
     * Vypínač platí i pro toho, kdo posílá sám za sebe (cron, fronta), ne jen pro formulář v adminu.
     */
    public function testVypnuteNotifikaceNevydajiOdesilac(): void
    {
        try {
            $this->pushNotifikace(zapnuto: false)->getOdesilac();
            self::fail('Vypnuté notifikace nesmí vydat odesílač.');
        } catch (ChybaPushNotifikace $chyba) {
            self::assertSame(409, $chyba->getHttpKod());
        }
        self::assertNotNull($this->pushNotifikace()->getOdesilac());
    }

    public function testStrankaNeplatneZnakyVNadpisuOdmitneHlaskou(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idPrijemce);

        $this->stranka($idPrijemce)->zpracuj(
            [
                'pushNotifikaceAkce' => StrankaAdminu::VARIANTA_RUCNI,
                'idPrijemce'         => (string) $idPrijemce,
                'nadpis'             => "Nadpis \xFF",
                'text'               => 'Text',
            ],
            [
                'HTTP_ORIGIN' => self::URL_ADMINU,
            ],
            self::URL_ADMINU,
        );

        self::assertSame([], $this->pushSluzba->pozadavky);
        self::assertStringContainsString('neplatné znaky', \Chyba::vyzvedniHtml());
    }

    public function testBezVapidKlicuJeFunkceVypnuta(): void
    {
        $bezKlicu = new PushNotifikace(true, null, self::URL_WEBU_TESTU, $this->adresarLogu, $this->hodiny, $this->pushSluzba);

        self::assertFalse($bezKlicu->isZapnuto(), 'Přepínač v Nastavení bez klíčů prostředí nestačí.');
        self::assertFalse($this->pushNotifikace(zapnuto: false)->isZapnuto(), 'Klíče bez přepínače v Nastavení nestačí.');
        self::assertTrue($this->pushNotifikace()->isZapnuto());

        $this->expectException(ChybaPushNotifikace::class);
        $bezKlicu->getOdeslaniZAdminu()->posli(
            $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]),
            $this->uzivatel($this->vytvorUzivatelePush()),
            self::zprava(),
        );
    }

    public function testBezVybranehoUzivateleNeodesle(): void
    {
        $this->expectException(ChybaPushNotifikace::class);

        $this->pushNotifikace()->getOdeslaniZAdminu()->posli(
            $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]),
            null,
            self::zprava(),
        );
    }

    public function testLimitOdesilatele(): void
    {
        $odesilatel = $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]);
        $odeslani = $this->pushNotifikace()->getOdeslaniZAdminu();
        // každému příjemci jen pár, ať se nepřekročí limit příjemce
        $prijemci = [];
        for ($i = 0; $i < 4; ++$i) {
            $prijemci[] = $this->uzivatel($this->vytvorUzivatelePush());
        }
        for ($i = 0; $i < ZaznamOdeslani::LIMIT_ODESILATELE; ++$i) {
            $odeslani->posli($odesilatel, $prijemci[$i % 4], self::zprava());
        }

        try {
            $odeslani->posli($odesilatel, $prijemci[0], self::zprava());
            self::fail('Další notifikace přes limit odesílatele nesmí odejít.');
        } catch (ChybaPushNotifikace $chyba) {
            self::assertSame(429, $chyba->getHttpKod());
        }

        // jiný odesílatel limit nesdílí, a po uplynutí okna smí i ten první
        $odeslani->posli(
            $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]),
            $this->uzivatel($this->vytvorUzivatelePush()),
            self::zprava(),
        );
        $this->hodiny->modify('+' . (ZaznamOdeslani::OKNO_SEKUND + 1) . ' seconds');
        $odeslani->posli($odesilatel, $prijemci[0], self::zprava());
        $this->addToAssertionCount(1);
    }

    public function testLimitPrijemcePlatiIProVicOdesilatelu(): void
    {
        $prijemce = $this->uzivatel($this->vytvorUzivatelePush());
        $odeslani = $this->pushNotifikace()->getOdeslaniZAdminu();
        for ($i = 0; $i < ZaznamOdeslani::LIMIT_PRIJEMCE; ++$i) {
            $odeslani->posli(
                $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]),
                $prijemce,
                self::zprava(),
            );
        }

        $this->expectException(ChybaPushNotifikace::class);
        $odeslani->posli(
            $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]),
            $prijemce,
            self::zprava(),
        );
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('puvodyPozadavku')]
    public function testFormularSmiPrijitJenZAdminu(array $server, bool $ocekavane): void
    {
        self::assertSame($ocekavane, OdeslaniZAdminu::isPozadavekZAdminu($server, self::URL_ADMINU));
    }

    /**
     * @return \Generator<string, array{array<string, string>, bool}>
     */
    public static function puvodyPozadavku(): \Generator
    {
        yield 'Origin adminu' => [[
            'HTTP_ORIGIN' => 'https://admin.gamecon.test',
        ], true];
        yield 'Referer adminu bez Origin' => [[
            'HTTP_REFERER' => 'https://admin.gamecon.test/web/push-notifikace',
        ], true];
        yield 'cizí Origin' => [[
            'HTTP_ORIGIN' => 'https://example.com',
        ], false];
        yield 'cizí Origin má přednost před Refererem' => [[
            'HTTP_ORIGIN'  => 'https://example.com',
            'HTTP_REFERER' => 'https://admin.gamecon.test/',
        ], false];
        yield 'admin jen jako začátek jména' => [[
            'HTTP_ORIGIN' => 'https://admin.gamecon.test.example.com',
        ], false];
        yield 'Origin null (soubor, sandbox)' => [[
            'HTTP_ORIGIN' => 'null',
        ], false];
        yield 'bez hlaviček' => [[], false];
    }

    public function testStrankaOdesleTestovaciNotifikaciAUkazeVysledek(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $endpoint = $this->zapniNaNovemZarizeni($idPrijemce);
        $stranka = $this->stranka($idPrijemce);

        $presmerovani = $stranka->zpracuj(
            [
                'pushNotifikaceAkce' => StrankaAdminu::VARIANTA_TESTOVACI,
                'idPrijemce'         => (string) $idPrijemce,
                'testovaci'          => TestovaciNotifikace::PRIPOMINKA_AKTIVITY->value,
            ],
            [
                'HTTP_ORIGIN' => self::URL_ADMINU,
            ],
            self::URL_ADMINU,
        );

        self::assertSame(self::URL_STRANKY, $presmerovani, 'Po odeslání se přesměruje, aby obnovení stránky neposlalo znovu.');
        self::assertSame([$endpoint], $this->pushSluzba->getEndpointy());
        $html = $stranka->getHtml(StrankaAdminu::VARIANTA_TESTOVACI);
        self::assertStringContainsString('Výsledek posledního odeslání', $html);
        self::assertStringContainsString('předáno push službě', $html);
        self::assertStringContainsString(TestovaciNotifikace::OZNACENI, $html);
        self::assertStringNotContainsString($endpoint, $html, 'Endpoint se v adminu neukazuje.');
        self::assertStringNotContainsString(
            'Výsledek posledního odeslání',
            $stranka->getHtml(StrankaAdminu::VARIANTA_TESTOVACI),
            'Výsledek se ukáže jen jednou.',
        );
    }

    public function testStrankaNeodesleKdyzSeMezitimZmenilVybranyUzivatel(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $idPuvodnihoPrijemce = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idPrijemce);
        $this->zapniNaNovemZarizeni($idPuvodnihoPrijemce);

        $presmerovani = $this->stranka($idPrijemce)->zpracuj(
            // formulář načtený, když byl vybraný někdo jiný
            [
                'pushNotifikaceAkce' => StrankaAdminu::VARIANTA_RUCNI,
                'idPrijemce'         => (string) $idPuvodnihoPrijemce,
                'nadpis'             => 'Nadpis',
                'text'               => 'Text',
            ],
            [
                'HTTP_ORIGIN' => self::URL_ADMINU,
            ],
            self::URL_ADMINU,
        );

        self::assertSame(self::URL_STRANKY, $presmerovani);
        self::assertSame([], $this->pushSluzba->pozadavky);
        self::assertStringContainsString('se mezitím změnil', \Chyba::vyzvedniHtml());
    }

    public function testStrankaNeodesleFormularOdjinud(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $this->zapniNaNovemZarizeni($idPrijemce);

        $this->stranka($idPrijemce)->zpracuj(
            [
                'pushNotifikaceAkce' => StrankaAdminu::VARIANTA_RUCNI,
                'idPrijemce'         => (string) $idPrijemce,
                'nadpis'             => 'Nadpis',
                'text'               => 'Text',
            ],
            [
                'HTTP_ORIGIN' => 'https://example.com',
            ],
            self::URL_ADMINU,
        );

        self::assertSame([], $this->pushSluzba->pozadavky);
    }

    public function testStrankaPoChybeNechaRozepsanyFormular(): void
    {
        $idPrijemce = $this->vytvorUzivatelePush();
        $stranka = $this->stranka($idPrijemce);

        $stranka->zpracuj(
            [
                'pushNotifikaceAkce' => StrankaAdminu::VARIANTA_RUCNI,
                'idPrijemce'         => (string) $idPrijemce,
                'nadpis'             => 'Můj <nadpis>',
                'text'               => 'Rozepsaný text',
                'odkaz'              => 'https://example.com',
            ],
            [
                'HTTP_ORIGIN' => self::URL_ADMINU,
            ],
            self::URL_ADMINU,
        );

        self::assertSame([], $this->pushSluzba->pozadavky, 'Odkaz mimo web GameConu se neodešle.');
        $html = $stranka->getHtml(StrankaAdminu::VARIANTA_RUCNI);
        self::assertStringContainsString('Rozepsaný text', $html);
        self::assertStringContainsString('Můj &lt;nadpis&gt;', $html);
    }

    public function testStrankaBezOdeslanehoFormulareNicNedela(): void
    {
        self::assertNull($this->stranka($this->vytvorUzivatelePush())->zpracuj([], [], self::URL_ADMINU));
    }

    public function testWebOVypnutychNotifikacichNevi(): void
    {
        $prihlaseny = $this->uzivatel($this->vytvorUzivatelePush());

        $vypnute = new PushNaWebu($this->pushNotifikace(zapnuto: false), WWW);
        self::assertSame('', $vypnute->getHtmlHlavicky($prihlaseny));
        self::assertSame('', $vypnute->getHtmlNastaveni());

        $zapnute = new PushNaWebu($this->pushNotifikace(), WWW);
        $proNeprihlaseneho = $zapnute->getHtmlHlavicky(null);
        self::assertStringContainsString('rel="manifest"', $proNeprihlaseneho);
        self::assertStringNotContainsString('<script', $proNeprihlaseneho, 'Skript notifikací je jen pro přihlášené.');
        $proPrihlaseneho = $zapnute->getHtmlHlavicky($prihlaseny);
        self::assertStringContainsString('data-vapid-klic="' . self::vapidKlice()->verejny . '"', $proPrihlaseneho);
        self::assertStringContainsString('data-api="https://gamecon.test/api/pushSubscription"', $proPrihlaseneho);
        self::assertStringContainsString('data-service-worker="https://gamecon.test/sw.js"', $proPrihlaseneho);
        self::assertStringContainsString('data-scope="https://gamecon.test/"', $proPrihlaseneho);
        self::assertStringNotContainsString(
            self::vapidKlice()->getKonfiguraceKnihovny()['privateKey'],
            $proPrihlaseneho,
            'Soukromý klíč do stránky nesmí.',
        );
        self::assertStringContainsString('data-push-notifikace', $zapnute->getHtmlNastaveni());
    }

    private function pushNotifikace(bool $zapnuto = true): PushNotifikace
    {
        return new PushNotifikace(
            $zapnuto,
            self::vapidKlice(),
            self::URL_WEBU_TESTU,
            $this->adresarLogu,
            $this->hodiny,
            $this->pushSluzba,
        );
    }

    private function stranka(int $idPrijemce): StrankaAdminu
    {
        return new StrankaAdminu(
            $this->pushNotifikace(),
            $this->uzivatel($this->vytvorUzivatelePush(), [Pravo::ADMINISTRACE_PUSH_NOTIFIKACE]),
            $this->uzivatel($idPrijemce),
            self::URL_STRANKY,
        );
    }

    /**
     * @param list<int> $prava
     */
    private function uzivatel(int $id, array $prava = []): \Uzivatel
    {
        $uzivatel = $this->createPartialMock(\Uzivatel::class, ['prava', 'id', 'jmenoNick']);
        $uzivatel->method('prava')->willReturn($prava);
        $uzivatel->method('id')->willReturn($id);
        $uzivatel->method('jmenoNick')->willReturn('Test Uživatel');

        return $uzivatel;
    }

    private static function zprava(): ZpravaNotifikace
    {
        return ZpravaNotifikace::vytvor(TypNotifikace::OBECNA, 'Nadpis', 'Text', 'program/muj');
    }
}
