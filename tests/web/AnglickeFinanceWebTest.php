<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Shop\TypPredmetu;

/**
 * Přehled financí přihlášeného (/en/finance) přes skutečný Apache: anglicky celý požadavek (finance se nespočítají
 * dřív, než web zná jazyk), přeložená stránka s hreflang, česká verze česky a QR kódy v obou stejné.
 * Přihlašuje se přes admin, session je s webem společná.
 */
class AnglickeFinanceWebTest extends AbstractTestWeb
{
    private const string NAZEV_AKTIVITY = 'Hra, na kterou nedorazil';
    private const string NAZEV_PREDMETU = 'Hrnek se znakem';

    private ?string $cookieFile = null;

    // AbstractTestWeb zakládá role mimo transakci, další webový test by je zakládal znovu
    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $system = \Uzivatel::SYSTEM;
        $rocnik = ROCNIK;

        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
INSERT INTO shop_predmety (id_predmetu, nazev, model_rok, kod_predmetu, cena_aktualni, stav, nabizet_do, kusu_vyrobeno, typ)
VALUES (77421, $0, $1, CONCAT('test_hrnek_', $1), 250, 1, NOW(), 100, $2)
SQL,
                [
                    0 => self::NAZEV_PREDMETU,
                    1 => ROCNIK,
                    2 => TypPredmetu::PREDMET,
                ],
            ],
            "INSERT INTO shop_nakupy (id_uzivatele, id_predmetu, rok, cena_nakupni) VALUES ({$system}, 77421, {$rocnik}, 250)",
            [
                <<<'SQL'
INSERT INTO akce_seznam (id_akce, nazev_akce, rok, cena, typ, kapacita, kapacita_f, kapacita_m, bez_slevy, nedava_bonus, teamova, popis, popis_kratky, vybaveni)
VALUES (77411, $0, $1, 200, $2, 10, 0, 0, 1, 0, 0, '', '', '')
SQL,
                [
                    0 => self::NAZEV_AKTIVITY,
                    1 => ROCNIK,
                    2 => TypAktivity::DESKOHERNA,
                ],
            ],
            "INSERT INTO akce_prihlaseni (id_akce, id_uzivatele, id_stavu_prihlaseni) VALUES (77411, {$system}, " . StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL . ')',
            // zůstatek z minulých let má systémový uživatel v testovací databázi jiný než nulový
            "UPDATE uzivatele_hodnoty SET zustatek = 0 WHERE id_uzivatele = {$system}",
            // vrácení z banky, aby byl stav záporný a stránka ukázala pokyny k platbě
            "INSERT INTO platby (id_uzivatele, castka, rok, provedeno, poznamka, provedl) VALUES ({$system}, -1000, {$rocnik}, '{$rocnik}-07-05 12:00:00', NULL, {$system})",
        ];
    }

    protected function tearDown(): void
    {
        if ($this->cookieFile !== null) {
            unlink($this->cookieFile);
            $this->cookieFile = null;
        }
        parent::tearDown();
    }

    public function testPrehledFinanciPodAnglictinouJeAnglicky(): void
    {
        $odpoved = $this->stahniPrihlasene('web/en/finance');

        self::assertSame(200, $odpoved['kod']);
        $telo = $odpoved['telo'];
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertStringNotContainsString('<div lang="cs">', $telo, 'Přehled financí je přeložený, obsah není česky.');
        self::assertStringContainsString('hreflang="cs" href="' . URL_WEBU . '/finance"', $telo);
        $obsah = $this->obsahPrehledu($telo);
        foreach ([
            '<h1>Orders and payments</h1>',
            '<h2>Your orders</h2>',
            '<td><b>Activities</b></td>',
            '<td>' . self::NAZEV_AKTIVITY . ' <i>(did not turn up)</i></td><td>200</td>',
            '<td><b>Merchandise</b></td><td><b>250</b></td>',
            '<td>5 July Bank transfer</td><td>-1,000</td>',
            '<td><b>Balance</b></td><td><b>-1,450</b></td>',
            '<h2>Bonuses</h2>',
            '<li>free activities</li>',
            '<h2 id="placeni">Payment</h2>',
            '<strong>Amount to pay:</strong> CZK&nbsp;1,450',
        ] as $anglicky) {
            self::assertStringContainsString($anglicky, $obsah);
        }
        $this->assertBezCeskehoTextuMimoData($obsah);
    }

    public function testPrehledFinanciVCestineZustavaCesky(): void
    {
        $odpoved = $this->stahniPrihlasene('web/finance');

        self::assertSame(200, $odpoved['kod']);
        self::assertStringContainsString('<html lang="cs">', $odpoved['telo']);
        $obsah = $this->obsahPrehledu($odpoved['telo']);
        foreach ([
            '<h1>Přehled financí</h1>',
            '<h2>Objednané věci</h2>',
            '<td><b>Aktivity</b></td>',
            '<td>' . self::NAZEV_AKTIVITY . ' <i>(nedorazil)</i></td><td>200</td>',
            '<td><b>Předměty</b></td><td><b>250</b></td>',
            "<td>5. 7. Platba na účet</td><td>-1\u{a0}000</td>",
            "<td><b>Stav financí</b></td><td><b>-1\u{a0}450</b></td>",
            '<li>aktivity zdarma</li>',
            '<h2 id="placeni">Platba</h2>',
            "<strong>Částka k zaplacení:</strong> 1\u{a0}450&thinsp;Kč",
        ] as $cesky) {
            self::assertStringContainsString($cesky, $obsah);
        }
    }

    public function testQrKodyJsouVObouJazycichStejne(): void
    {
        $qrKod = '~<div class="payment-method__qr">\s*<img src="(data:[^"]+)"~';
        preg_match_all($qrKod, $this->stahniPrihlasene('web/finance')['telo'], $ceske);
        preg_match_all($qrKod, $this->stahniPrihlasene('web/en/finance')['telo'], $anglicke);

        self::assertCount(3, $ceske[1], 'Stránka má mít QR kódy CZ, SK a SEPA.');
        self::assertSame($ceske[1], $anglicke[1]);
    }

    /**
     * @return array{kod: int, telo: string}
     */
    private function stahniPrihlasene(string $cesta): array
    {
        if ($this->cookieFile === null) {
            $cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
            self::assertNotFalse($cookieFile);
            $this->cookieFile = $cookieFile;
            // session je pro admin i web společná
            $this->loginToAdmin($this->cookieFile, \Uzivatel::SYSTEM_LOGIN, UNIVERZALNI_HESLO);
        }
        self::vyresDnsPredPrvniZadosti();
        $curl = curl_init('http://localhost/' . $cesta);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookieFile);
        $this->setLocalServerCookieWithGameconTestDb($curl);
        $telo = (string) curl_exec($curl);
        $kod = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        unset($curl);

        return [
            'kod'  => $kod,
            'telo' => $telo,
        ];
    }

    /**
     * Obsah přehledu od nadpisu po skript přepínače plateb, bílé znaky zdrojového kódu sloučené.
     */
    private function obsahPrehledu(string $telo): string
    {
        $zacatek = strpos($telo, '<div class="stranka">');
        $konec = strpos($telo, '<script src="soubory/qr-prepinac-plateb.js">');
        self::assertNotFalse($zacatek, 'Na stránce chybí přehled financí, je uživatel přihlášený?');
        self::assertNotFalse($konec);

        return (string) preg_replace(
            ['~<style\b.*?</style>~s', '~[ \t\r\n]+~'],
            ['', ' '],
            substr($telo, $zacatek, $konec - $zacatek),
        );
    }

    /**
     * Česky smí zůstat jen názvy položek přehledu (aktivity, předměty, poznámky k platbám jsou data).
     */
    private function assertBezCeskehoTextuMimoData(string $obsah): void
    {
        $bezDat = (string) preg_replace_callback(
            '~<tr class="objednavky--polozka"><td>(.*?)</td>~',
            static fn (array $bunka): string => implode(' ', preg_match_all('~<i>.*?</i>~', $bunka[1], $poznamky) ? $poznamky[0] : []),
            (string) preg_replace('~src="data:[^"]+"~', '', $obsah),
        );
        $text = html_entity_decode(strip_tags($bezDat));

        self::assertDoesNotMatchRegularExpression('~[ěščřžýáíéóúůťďňĚŠČŘŽÝÁÍÉÓÚŮŤĎŇ]~u', $text, 'Na anglické stránce zůstal český text: ' . $text);
        self::assertStringNotContainsString('Kč', $obsah);
    }
}
