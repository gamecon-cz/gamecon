<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Jazyk\JazykPozadavku;

/**
 * Anglická verze webu přes skutečný Apache: prefix /en/, přepínač, zapamatování volby a soubory pod prefixem.
 */
class AnglickyWebTest extends AbstractTestWeb
{
    /**
     * Stránka z CMS, která se nepřekládá: pod /en/ má anglické jen menu a patičku.
     */
    private const string NEPRELOZENA_STRANKA = 'neprelozena-testovaci-stranka';

    // AbstractTestWeb zakládá role mimo transakci, další webový test by je zakládal znovu
    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            // uvozovky kvůli typografii anglického textu
            "INSERT INTO stranky (url_stranky, obsah) VALUES ('en', '# Welcome to GameCon test page\n\nSay \"hello\" to us.')",
            "INSERT INTO stranky (url_stranky, obsah) VALUES ('" . self::NEPRELOZENA_STRANKA . "', '# Nepřeložená stránka')",
        ];
    }

    public function testAnglickaTitulkaJeStrankaEnZCms(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/');

        self::assertSame(200, $kod);
        self::assertStringContainsString('Welcome to GameCon test page', $telo, 'Dokud není titulka přeložená, je anglickou titulkou stránka en z CMS.');
        self::assertStringContainsString('Say “hello” to us.', $telo, 'Anglický text má anglické uvozovky, ne české „“.');

        ['telo' => $ceskaTitulka] = $this->stahni('web/');
        self::assertStringNotContainsString('Welcome to GameCon test page', $ceskaTitulka);
    }

    public function testStrankaEnPodAnglictinouPresmerujeNaAnglickouTitulku(): void
    {
        foreach (['web/en/en', 'web/EN'] as $cesta) {
            ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni($cesta);

            self::assertSame(301, $kod, "{$cesta} by byla kopií anglické titulky");
            self::assertMatchesRegularExpression('~^Location: ' . preg_quote(URL_WEBU . '/en/', '~') . '\r?$~m', $hlavicky, $cesta);
        }
    }

    public function testAnglickaTitulkaMaAnglickeMenuAOdkazyNaJazyky(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/');

        self::assertSame(200, $kod);
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertStringContainsString('<base href="' . URL_WEBU . '/en/">', $telo);
        self::assertStringContainsString('<meta property="og:locale" content="en_GB">', $telo);
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/en/">', $telo);
        self::assertStringContainsString('hreflang="cs" href="' . URL_WEBU . '/"', $telo);
        self::assertStringContainsString('hreflang="en" href="' . URL_WEBU . '/en/"', $telo);
        self::assertStringNotContainsString('<div lang="cs">', $telo, 'Anglická titulka je přeložená, obsah není česky.');
        self::assertStringContainsString('>programme</a>', $telo);
        self::assertStringContainsString('href="' . URL_WEBU . '/?jazyk=cs"', $telo, 'Přepínač do češtiny');
    }

    public function testCeskaTitulkaZustavaCesky(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/');

        self::assertSame(200, $kod);
        self::assertStringContainsString('<html lang="cs">', $telo);
        self::assertStringContainsString('>program</a>', $telo);
        self::assertStringContainsString('href="' . URL_WEBU . '/en/?jazyk=en"', $telo, 'Přepínač do angličtiny');
        self::assertStringContainsString('hreflang="en" href="' . URL_WEBU . '/en/"', $telo, 'Titulka má anglickou verzi.');
    }

    public function testNeprelozenaStrankaPodAnglictinouJeKanonickyCeska(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/' . self::NEPRELOZENA_STRANKA);

        self::assertSame(200, $kod);
        self::assertStringContainsString('<html lang="en">', $telo, 'Menu a patička jsou anglicky.');
        self::assertStringContainsString('<div lang="cs">', $telo, 'Obsah je česky.');
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/' . self::NEPRELOZENA_STRANKA . '">', $telo);
        self::assertStringContainsString('<meta property="og:url" content="' . URL_WEBU . '/' . self::NEPRELOZENA_STRANKA . '">', $telo);
        self::assertStringContainsString('<meta property="og:locale" content="cs_CZ">', $telo);
        self::assertStringNotContainsString('<link rel="alternate" hreflang', $telo, 'Český obsah pod /en/ se vyhledávačům nehlásí jako anglická verze.');
    }

    public function testNeprelozenaCeskaStrankaNemaJazykoveVerze(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/' . self::NEPRELOZENA_STRANKA);

        self::assertSame(200, $kod);
        self::assertStringContainsString('<link rel="canonical" href="' . URL_WEBU . '/' . self::NEPRELOZENA_STRANKA . '">', $telo);
        self::assertStringNotContainsString('<link rel="alternate" hreflang', $telo);
        self::assertStringNotContainsString('<div lang=', $telo, 'Obsah je v jazyce stránky.');
    }

    public function testNenalezenaStrankaNemaJazykoveVerze(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/tahle-stranka-neexistuje');

        self::assertSame(404, $kod);
        self::assertStringNotContainsString('<link rel="alternate" hreflang', $telo);
    }

    public function testKdoSiZvolilAnglictinuPrijdeZCeskeAdresyNaAnglickou(): void
    {
        ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni('web/program', JazykPozadavku::COOKIE . '=en');

        self::assertSame(302, $kod);
        self::assertMatchesRegularExpression('~^Location: ' . preg_quote(URL_WEBU . '/en/program', '~') . '\r?$~m', $hlavicky);
    }

    public function testFetchSeNepresmerovava(): void
    {
        ['kod' => $kod] = $this->stahni(
            'web/' . self::NEPRELOZENA_STRANKA,
            JazykPozadavku::COOKIE . '=en',
            ['Sec-Fetch-Mode: cors'],
        );

        self::assertSame(200, $kod, 'fetch() (třeba výzva Altchy) není navigace, přesměrovat se nemá.');
    }

    public function testNavigaceNaAnglickouAdresuSiZapamatujeVolbu(): void
    {
        ['hlavicky' => $hlavicky] = $this->stahni('web/en/' . self::NEPRELOZENA_STRANKA, '', ['Sec-Fetch-Mode: navigate']);

        self::assertMatchesRegularExpression('~^Set-Cookie: ' . JazykPozadavku::COOKIE . '=en;~mi', $hlavicky);
    }

    public function testAnglickaAdresaMimoNavigaciVolbuNemeni(): void
    {
        ['hlavicky' => $fetch] = $this->stahni('web/en/' . self::NEPRELOZENA_STRANKA, JazykPozadavku::COOKIE . '=cs', ['Sec-Fetch-Mode: cors']);
        self::assertStringNotContainsString(JazykPozadavku::COOKIE . '=', $fetch, 'fetch()');

        ['hlavicky' => $api] = $this->stahni('web/en/api/prihlasenyUzivatel', JazykPozadavku::COOKIE . '=cs');
        self::assertStringNotContainsString(JazykPozadavku::COOKIE . '=', $api, 'Stará anglická záložka volající API nesmí přepnout volbu zpět.');

        ['kod' => $kodFormulare, 'hlavicky' => $formular] = $this->stahni(
            'web/en/' . self::NEPRELOZENA_STRANKA,
            JazykPozadavku::COOKIE . '=cs',
            ['Referer: ' . URL_WEBU . '/en/' . self::NEPRELOZENA_STRANKA],
            post: true,
        );
        self::assertSame(200, $kodFormulare, 'Formulář musí projít ochranou proti CSRF, jinak test nic neověří.');
        self::assertStringNotContainsString(JazykPozadavku::COOKIE . '=', $formular, 'formulář');
    }

    public function testPrepinacUloziVolbuAPresmerujeBezParametru(): void
    {
        ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni('web/en/program?jazyk=cs', JazykPozadavku::COOKIE . '=en');

        self::assertSame(302, $kod);
        self::assertMatchesRegularExpression('~^Location: ' . preg_quote(URL_WEBU . '/program', '~') . '\r?$~m', $hlavicky);
        self::assertStringContainsString('Set-Cookie: ' . JazykPozadavku::COOKIE . '=cs', $hlavicky);
    }

    public function testSouborPodAnglickymPrefixemJeStejnySoubor(): void
    {
        ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni('web/en/soubory/blackarrow/_spolecne/favicon.png');

        self::assertSame(200, $kod);
        self::assertMatchesRegularExpression('~^Content-Type: image/png~mi', $hlavicky);
    }

    public function testStrankaSymfonyPodAnglickymPrefixemFunguje(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/zapomenute-heslo');

        self::assertSame(200, $kod);
        self::assertStringContainsString('>programme</a>', $telo, 'Symfony stránka vykresluje menu webu, i to má být anglicky.');
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertStringContainsString('<base href="' . URL_WEBU . '/en/">', $telo, 'Relativní odkazy mají zůstat v angličtině.');
        self::assertStringContainsString('<div lang="cs">', $telo, 'Obnova hesla zatím přeložená není, obsah je česky.');
    }

    public function testCestaZUrlSeVPrepinaciJazykaNeprovede(): void
    {
        ['telo' => $telo] = $this->stahni('web/x%22%3E%3Cb%3Eahoj%3C/b%3E');

        self::assertStringNotContainsString('<b>ahoj</b>', $telo);
        self::assertStringContainsString('href="' . URL_WEBU . '/en/x%22%3E%3Cb%3Eahoj%3C/b%3E?jazyk=en"', $telo);
    }

    public function testApiPodAnglickymPrefixemFunguje(): void
    {
        ['kod' => $kod] = $this->stahni('web/en/api/prihlasenyUzivatel');

        self::assertSame(200, $kod);
    }

    /**
     * @param list<string> $hlavicky další hlavičky požadavku
     *
     * @return array{kod: int, hlavicky: string, telo: string}
     */
    private function stahni(string $cesta, string $cookie = '', array $hlavicky = [], bool $post = false): array
    {
        get_headers(URL_WEBU); // DNS a Apache připravené dřív než curl, viz StrankyWebuTest

        $curl = curl_init('http://localhost/' . $cesta);
        self::assertNotFalse($curl);
        $this->setLocalServerCookieWithGameconTestDb($curl);
        if ($cookie !== '') {
            curl_setopt($curl, CURLOPT_COOKIE, 'gamecon_test_db=' . DB_NAME . '; unit_tests=1; ' . $cookie);
        }
        if ($hlavicky !== []) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $hlavicky);
        }
        if ($post) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, '');
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $odpoved = (string) curl_exec($curl);
        $kod = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $velikostHlavicek = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        return [
            'kod'      => $kod,
            'hlavicky' => substr($odpoved, 0, $velikostHlavicek),
            'telo'     => substr($odpoved, $velikostHlavicek),
        ];
    }
}
