<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Jazyk\JazykPozadavku;

/**
 * Anglická verze webu přes skutečný Apache: prefix /en/, přepínač, zapamatování volby a soubory pod prefixem.
 */
class AnglickyWebTest extends AbstractTestWeb
{
    // AbstractTestWeb zakládá role mimo transakci, další webový test by je zakládal znovu
    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    public function testAnglickaTitulkaMaAnglickeMenuAOdkazyNaJazyky(): void
    {
        ['kod' => $kod, 'telo' => $telo] = $this->stahni('web/en/');

        self::assertSame(200, $kod);
        self::assertStringContainsString('<html lang="en">', $telo);
        self::assertStringContainsString('<base href="' . URL_WEBU . '/en/">', $telo);
        self::assertStringContainsString('<meta property="og:locale" content="en_GB">', $telo);
        self::assertStringContainsString('hreflang="cs" href="' . URL_WEBU . '/"', $telo);
        self::assertStringContainsString('hreflang="en" href="' . URL_WEBU . '/en/"', $telo);
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
    }

    public function testKdoSiZvolilAnglictinuPrijdeZCeskeAdresyNaAnglickou(): void
    {
        ['kod' => $kod, 'hlavicky' => $hlavicky] = $this->stahni('web/program', JazykPozadavku::COOKIE . '=en');

        self::assertSame(302, $kod);
        self::assertStringContainsString('Location: ' . URL_WEBU . '/en/program', $hlavicky);
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
    }

    public function testApiPodAnglickymPrefixemFunguje(): void
    {
        ['kod' => $kod] = $this->stahni('web/en/api/prihlasenyUzivatel');

        self::assertSame(200, $kod);
    }

    /**
     * @return array{kod: int, hlavicky: string, telo: string}
     */
    private function stahni(string $cesta, string $cookie = ''): array
    {
        get_headers(URL_WEBU); // DNS a Apache připravené dřív než curl, viz StrankyWebuTest

        $curl = curl_init('http://localhost/' . $cesta);
        self::assertNotFalse($curl);
        $this->setLocalServerCookieWithGameconTestDb($curl);
        if ($cookie !== '') {
            curl_setopt($curl, CURLOPT_COOKIE, 'gamecon_test_db=' . DB_NAME . '; unit_tests=1; ' . $cookie);
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
