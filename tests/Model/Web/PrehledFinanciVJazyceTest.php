<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Pravo;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Finance;

require_once __DIR__ . '/../../../web/tridy/modul.php';
require_once __DIR__ . '/../../../web/tridy/vyjimky.php';

/**
 * Přehled financí (web/moduly/finance.php) vykreslený modulem: anglicky bez češtiny mimo data (názvy aktivit,
 * předmětů, poznámky k platbám), česky beze změny a s QR kódy, které na jazyku nezávisí.
 */
class PrehledFinanciVJazyceTest extends AbstractTestDb
{
    private const int ID_UZIVATELE = 7731;
    private const int ID_ROLE = -7731;

    /**
     * daleko v budoucnosti, `pred()` ve stránce porovnává se skutečným časem
     */
    private const string ZAPLATIT_DO_POZDEJI = '2099-07-01 23:59:00';
    private const string ZAPLATIT_DO_UPLYNULO = '2001-07-01 23:59:00';

    /**
     * @return list<string|array{0: string, 1: array<int, mixed>}>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $id = self::ID_UZIVATELE;
        $idRole = self::ID_ROLE;
        $rocnik = ROCNIK;
        $system = \Uzivatel::SYSTEM;

        return [
            <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = {$id}, login_uzivatele = 'TestPrehledFinanci', jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'Finance', email1_uzivatele = 'test.prehled.financi@example.org', pohlavi = 'f', zustatek = 100,
    stat_uzivatele = 1
SQL,
            [
                <<<'SQL'
INSERT INTO shop_predmety (id_predmetu, nazev, model_rok, kod_predmetu, cena_aktualni, stav, nabizet_do, kusu_vyrobeno, typ, ubytovani_den)
VALUES (77321, 'Hrnek se znakem', $0, CONCAT('test_hrnek_', $0), 1234.50, 1, NOW(), 100, $1, NULL),
       (77322, 'Postel ve středu', $0, CONCAT('test_postel_', $0), 400, 1, NOW(), 100, $2, 0),
       (77323, 'Oběd čtvrtek', $0, CONCAT('test_obed_', $0), 120, 1, NOW(), 100, $3, 1)
SQL,
                [
                    0 => ROCNIK,
                    1 => TypPredmetu::PREDMET,
                    2 => TypPredmetu::UBYTOVANI,
                    3 => TypPredmetu::JIDLO,
                ],
            ],
            <<<SQL
INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, rok, cena_nakupni)
VALUES ({$id}, 77321, {$rocnik}, 1234.50), ({$id}, 77322, {$rocnik}, 400), ({$id}, 77323, {$rocnik}, 120)
SQL,
            [
                <<<'SQL'
INSERT INTO akce_seznam (id_akce, nazev_akce, rok, cena, typ, kapacita, kapacita_f, kapacita_m, bez_slevy, nedava_bonus, teamova, popis, popis_kratky, vybaveni)
VALUES (77311, 'Hra, na které byla', $0, 300, $1, 10, 0, 0, 0, 0, 0, '', '', ''),
       (77312, 'Hra, na kterou nedorazila', $0, 200, $1, 10, 0, 0, 0, 0, 0, '', '', ''),
       (77313, 'Hra, ze které se odhlásila pozdě', $0, 100, $1, 10, 0, 0, 0, 0, 0, '', '', '')
SQL,
                [
                    0 => ROCNIK,
                    1 => TypAktivity::DESKOHERNA,
                ],
            ],
            [
                <<<'SQL'
INSERT INTO akce_prihlaseni (id_akce, id_uzivatele, id_stavu_prihlaseni)
VALUES (77311, $0, $1), (77312, $0, $2), (77313, $0, $3)
SQL,
                [
                    0 => $id,
                    1 => StavPrihlaseni::PRIHLASEN_A_DORAZIL,
                    2 => StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL,
                    3 => StavPrihlaseni::POZDE_ZRUSIL,
                ],
            ],
            // sleva k aktivitě (v přehledu „Sleva z …“) a obecná sleva („Sleva“)
            "INSERT INTO discounts_generated (id_uzivatele, rok, castka, id_akce) VALUES ({$id}, {$rocnik}, 50, 77311)",
            "INSERT INTO slevy (id_uzivatele, rok, castka, poznamka) VALUES ({$id}, {$rocnik}, 30, 'Za pomoc se stavbou')",
            // platba z banky (popis z katalogu), platba s poznámkou (data) a bez poznámky
            <<<SQL
INSERT INTO platby (id_uzivatele, castka, rok, provedeno, pripsano_na_ucet_banky, poznamka, provedl)
VALUES ({$id}, 500, {$rocnik}, '{$rocnik}-07-05 12:00:00', '{$rocnik}-07-05 12:00:00', NULL, {$system}),
       ({$id}, 150, {$rocnik}, '{$rocnik}-07-18 10:00:00', NULL, 'Hotovost na infopultu', {$id}),
       ({$id}, 50, {$rocnik}, '{$rocnik}-07-19 10:00:00', NULL, NULL, {$id})
SQL,
            // výhoda z práva bez vlivu na cenu, aby byl vidět seznam bonusů
            'INSERT IGNORE INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava) VALUES (' . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . ", 'test', 'test')",
            "INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role) VALUES ({$idRole}, 'TEST_PREHLED_FINANCI', 'Test přehledu financí', '', -1, 'trvala', '')",
            "INSERT INTO prava_role (id_role, id_prava) VALUES ({$idRole}, " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . ')',
            "INSERT INTO uzivatele_role (id_uzivatele, id_role, posadil) VALUES ({$id}, {$idRole}, {$id})",
        ];
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // na webu ze systémového nastavení, testovací bootstrap ho nedefinuje
        try_define('KURZ_EURO', 25.0);
    }

    protected function tearDown(): void
    {
        AktualniJazyk::reset();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function anglickyPrehledNemaCeskyTextMimoData(): void
    {
        ['html' => $html, 'prelozeny' => $prelozeny] = $this->vykresli(Jazyk::ANGLICTINA, self::ZAPLATIT_DO_POZDEJI);

        self::assertTrue($prelozeny, 'Stránka se má prohlásit za přeloženou (hreflang, kanonická adresa v jazyce stránky).');
        foreach ([
            '<h1>Orders and payments</h1>',
            'you will find <b>payment</b> instructions <a href="finance#placeni">at the very bottom</a>.',
            '<h2>Your orders</h2>',
            '<td><b>Activities</b></td><td><b>500</b></td>',
            '<td>Hra, na kterou nedorazila <i>(did not turn up)</i></td><td>200</td>',
            '<td>Hra, ze které se odhlásila pozdě <i>(cancelled late)</i></td><td>50</td>',
            '<td>Discount on Hra, na které byla</td><td>-50</td>',
            '<td><b>Food</b></td>',
            '<td><b>Merchandise</b></td><td><b>1,234.50</b></td>',
            '<td><b>Accommodation</b></td><td><b>400</b></td>',
            '<td><b>Voluntary admission</b></td>',
            '<td><b>Total price</b></td><td><b>2,254.50</b></td>',
            '<td><b>Balance from previous years</b></td><td><b>100</b></td>',
            '<td><b>Payments and credits</b></td><td><b>730</b></td>',
            '<td>Discount</td><td>30</td>',
            '<td>5 July Bank transfer</td><td>500</td>',
            '<td>18 July Hotovost na infopultu</td><td>150</td>',
            '<td>19 July (no note)</td><td>50</td>',
            '<td><b>Balance</b></td><td><b>-1,424.50</b></td>',
            '<h2>Bonuses</h2>',
            '<li>you can book accommodation for a single night</li>',
            '<h2 id="placeni">Payment</h2>',
            'aria-label="QR payment options"',
            '<h3 class="payment-method__title">Payment (CZ)</h3>',
            '<strong>Account number:</strong> ' . UCET_CZ,
            '<strong>Variable symbol:</strong> ' . self::ID_UZIVATELE,
            '<strong>Payment reference:</strong> VS:' . self::ID_UZIVATELE,
            '<strong>Amount to pay:</strong> CZK&nbsp;1,424.50',
            'GameCon has to be paid by bank transfer <strong>by 1 July at 23:59</strong> (the money must be in GameCon’s account on that day).',
            'In total you pay <strong>CZK&nbsp;1,424.50<span data-qr-eur-text hidden> / ' . $this->castkaVEurech(Jazyk::ANGLICTINA) . '</span></strong>; you will find the exact payment details above.',
            'If you pay later, the system will automatically cancel your GameCon registration on <strong>1 July</strong>.',
            'You can use any extra money to sign up for activities at GameCon, and we will gladly refund any overpayment after GameCon.',
        ] as $anglicky) {
            self::assertStringContainsString($anglicky, $html);
        }
        self::assertStringContainsString('<strong>Amount to pay:</strong> €', $html);
        $this->assertBezCeskehoTextuMimoData($html);
    }

    /**
     * @test
     */
    public function anglickyPrehledPoTerminuPlatby(): void
    {
        ['html' => $html] = $this->vykresli(Jazyk::ANGLICTINA, self::ZAPLATIT_DO_UPLYNULO);

        self::assertStringContainsString('You can pay by bank transfer or on site. In total you pay <strong>CZK&nbsp;1,424.50', $html);
        self::assertStringNotContainsString('automatically cancel', $html);
        $this->assertBezCeskehoTextuMimoData($html);
    }

    /**
     * Texty, které stránka měla, než se přeložila (rozdíl je jen v bílých znacích kolem nich).
     *
     * @test
     */
    public function ceskyPrehledZustavaJakBylNapsany(): void
    {
        ['html' => $html, 'prelozeny' => $prelozeny] = $this->vykresli(Jazyk::CESTINA, self::ZAPLATIT_DO_POZDEJI);

        self::assertTrue($prelozeny);
        foreach ([
            '<h1>Přehled financí</h1>',
            'V následujícím přehledu vidíš seznam všech položek, které sis na GameConu objednala, s výslednými cenami po započítání všech slev. Pokud je tvůj celkový stav financí záporný, pokyny k <b>zaplacení</b> najdeš <a href="finance#placeni">úplně dole</a>.',
            '<h2>Objednané věci</h2>',
            '<table class="objednavky"><tr><td><b>Aktivity</b></td><td><b>500</b></td></tr>',
            '<td>Hra, na kterou nedorazila <i>(nedorazila)</i></td><td>200</td>',
            '<td>Hra, ze které se odhlásila pozdě <i>(odhlášena pozdě)</i></td><td>50</td>',
            '<td>Sleva z Hra, na které byla</td><td>-50</td>',
            '<td><b>Strava</b></td>',
            "<td><b>Předměty</b></td><td><b>1\u{a0}234,50</b></td>",
            '<td><b>Ubytování</b></td><td><b>400</b></td>',
            '<td><b>Dobrovolné vstupné</b></td>',
            "<td><b>Celková cena</b></td><td><b>2\u{a0}254,50</b></td>",
            '<td><b>Zůstatek z minulých let</b></td><td><b>100</b></td>',
            '<td><b>Připsané platby</b></td><td><b>730</b></td>',
            '<td>Sleva</td><td>30</td>',
            '<td>5. 7. Platba na účet</td><td>500</td>',
            '<td>18. 7. Hotovost na infopultu</td><td>150</td>',
            '<td>19. 7. (bez poznámky)</td><td>50</td>',
            "<td><b>Stav financí</b></td><td><b>-1\u{a0}424,50</b></td>",
            '<h2>Bonusy</h2>',
            '<li>můžeš si objednat ubytování i pro jedinou noc</li>',
            '<h2 id="placeni">Platba</h2>',
            'aria-label="Výběr QR platby"',
            '<h3 class="payment-method__title">Platba (CZ)</h3>',
            '<strong>Číslo účtu:</strong> ' . UCET_CZ,
            '<strong>Variabilní symbol:</strong> ' . self::ID_UZIVATELE,
            '<strong>Reference platby:</strong> VS:' . self::ID_UZIVATELE,
            "<strong>Částka k zaplacení:</strong> 1\u{a0}424,50&thinsp;Kč",
            'Částka v eurech zahrnuje kurzovou rezervu, nemusí proto přesně odpovídat aktuálnímu kurzu. Případný přeplatek ti zůstane na GC účtu nebo ti ho rádi vrátíme.',
            'GameCon je nutné zaplatit převodem <strong>do 1. července 23:59</strong> (tento den musejí být peníze na účtu GameConu).',
            "Platíš celkem <strong>1\u{a0}424,50&thinsp;Kč<span data-qr-eur-text hidden> / " . $this->castkaVEurech(Jazyk::CESTINA) . '</span></strong>, přesné údaje o platbě nalezneš výše.',
            'Eurová částka zahrnuje kurzovou rezervu, nemusí proto přesně odpovídat aktuálnímu kurzu; případný přeplatek ti zůstane na GC účtu nebo ti ho rádi vrátíme.',
            'Při pozdější platbě tě systém dne <strong>1. července</strong> automaticky odhlásí.',
            'Peníze navíc můžeš využít na přihlášení aktivit na GameConu a přeplatek ti po GameConu rádi vrátíme.',
        ] as $cesky) {
            self::assertStringContainsString($cesky, $html);
        }
        // datum ve větě je stejné jako dřív z datum4() / datum3()
        self::assertStringContainsString('do ' . datum4(self::ZAPLATIT_DO_POZDEJI, Jazyk::CESTINA) . '</strong>', $html);
        self::assertStringContainsString('dne <strong>' . datum3(self::ZAPLATIT_DO_POZDEJI, Jazyk::CESTINA) . '</strong>', $html);

        ['html' => $poTerminu] = $this->vykresli(Jazyk::CESTINA, self::ZAPLATIT_DO_UPLYNULO);
        self::assertStringContainsString("Zaplatit můžeš převodem nebo na místě. Platíš celkem <strong>1\u{a0}424,50&thinsp;Kč", $poTerminu);
    }

    /**
     * QR kódy (SPAYD, Pay by Square, EPC) jsou na jazyku stránky nezávislé: stejný obrázek jako bez jazyka.
     *
     * @test
     */
    public function qrKodyJsouVObouJazycichStejne(): void
    {
        $finance = \Uzivatel::zIdUrcite(self::ID_UZIVATELE)->finance();
        $ocekavane = [
            $finance->dejQrKodProCeskouPlatbu()->getDataUri(),
            $finance->dejQrKodProSlovenskouPlatbu()->getDataUri(),
            $finance->dejQrKodProSepaPlatbu()->getDataUri(),
        ];

        foreach ([Jazyk::CESTINA, Jazyk::ANGLICTINA] as $jazyk) {
            ['html' => $html] = $this->vykresli($jazyk, self::ZAPLATIT_DO_POZDEJI);
            preg_match_all('~<img src="(data:[^"]+)"~', $html, $obrazky);
            self::assertSame($ocekavane, $obrazky[1], $jazyk->value);
        }
    }

    /**
     * Admin a reporty jazyk nenastavují, texty z financí v nich zůstávají česky.
     *
     * @test
     */
    public function bezJazykaStrankyJsouTextyFinanciCesky(): void
    {
        $uzivatel = \Uzivatel::zIdUrcite(self::ID_UZIVATELE);

        $nazvy = array_map(static fn ($polozka) => $polozka->nazev, $uzivatel->finance()->dejPolozkyProBfgr());
        self::assertContains('Hra, na kterou nedorazila <i>(nedorazila)</i>', $nazvy);
        self::assertContains('5. 7. Platba na účet', $nazvy);
        self::assertContains('Zůstatek z minulých let', $nazvy);
        self::assertSame(['můžeš si objednat ubytování i pro jedinou noc'], $uzivatel->finance()->slevyVse());
    }

    /**
     * @return array{html: string, prelozeny: bool}
     */
    private function vykresli(Jazyk $jazyk, string $zaplatitDo): array
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $termin = new DateTimeImmutableStrict($zaplatitDo);
        $systemoveNastaveni->method('nejblizsiHromadneOdhlasovaniKdy')->willReturn($termin);
        $systemoveNastaveni->method('nejpozdejiZaplatitDo')->willReturn($termin);
        $systemoveNastaveni->method('ted')->willReturn(new DateTimeImmutableStrict());

        $puvodniAdresar = getcwd();
        // moduly se hledají relativně k web/, jako v web/index.php
        chdir(__DIR__ . '/../../../web');
        try {
            return AktualniJazyk::provedVJazyce($jazyk, static function () use ($systemoveNastaveni): array {
                // nový objekt uživatele, finance si texty pamatují v jazyce prvního výpočtu
                $uzivatel = \Uzivatel::zIdUrcite(self::ID_UZIVATELE);
                // web/tridy/modul.php nenačítá autoloader ani PHPStan, test si ho vyžádá nahoře
                $modul = \Modul::zNazvu('finance', null, $systemoveNastaveni); // @phpstan-ignore class.notFound
                self::assertNotNull($modul);
                $modul->param('u', $uzivatel);
                $modul->spust();

                return [
                    // jen bílé znaky zdrojového kódu, pevná mezera mezi tisíci zůstává
                    'html'      => (string) preg_replace('~[ \t\r\n]+~', ' ', (string) $modul->vystup()),
                    'prelozeny' => $modul->isObsahPrelozeny(),
                ];
            });
        } finally {
            chdir($puvodniAdresar);
        }
    }

    private function castkaVEurech(Jazyk $jazyk): string
    {
        return Finance::formatujCastkuVEurechHtml(
            \Uzivatel::zIdUrcite(self::ID_UZIVATELE)->finance()->dejCastkuProQrPlatbuVEurech(),
            $jazyk,
        );
    }

    /**
     * Na anglické stránce nesmí zůstat česká diakritika, kromě názvů položek přehledu (aktivity, předměty,
     * poznámky k platbám jsou data). Poznámka v závorce za názvem aktivity data není, ta se kontroluje.
     */
    private function assertBezCeskehoTextuMimoData(string $html): void
    {
        $bezDat = (string) preg_replace_callback(
            '~<tr class="objednavky--polozka"><td>(.*?)</td>~',
            static fn (array $bunka): string => implode(' ', preg_match_all('~<i>.*?</i>~', $bunka[1], $poznamky) ? $poznamky[0] : []),
            (string) preg_replace(['~<style\b.*?</style>~s', '~src="data:[^"]+"~'], '', $html),
        );
        $text = html_entity_decode(strip_tags($bezDat));

        self::assertDoesNotMatchRegularExpression('~[ěščřžýáíéóúůťďňĚŠČŘŽÝÁÍÉÓÚŮŤĎŇ]~u', $text, 'Na anglické stránce zůstal český text: ' . $text);
        self::assertStringNotContainsString('Kč', $html);
    }
}
