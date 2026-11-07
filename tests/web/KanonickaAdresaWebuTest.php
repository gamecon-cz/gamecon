<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

class KanonickaAdresaWebuTest extends AbstractTestWeb
{
    /**
     * @test
     *
     * @dataProvider provideAdresyTehozProgramu
     */
    public function stejnaStrankaMaVzdyStejnouKanonickouAdresu(string $cesta): void
    {
        $kanonickaAdresa = $this->kanonickaAdresa($cesta);

        self::assertStringEndsWith('/web/program', $kanonickaAdresa);
        self::assertSame($this->kanonickaAdresa('web/program'), $kanonickaAdresa);
    }

    public static function provideAdresyTehozProgramu(): array
    {
        return [
            'bez lomitka'           => ['web/program'],
            's koncovym lomitkem'   => ['web/program/'],
            'se sledovacim dotazem' => ['web/program?utm_source=newsletter&fbclid=1'],
        ];
    }

    /**
     * @test
     */
    public function titulkaMaKanonickouAdresuKorene(): void
    {
        $kanonickaAdresa = $this->kanonickaAdresa('web/');

        self::assertStringEndsWith('/web/', $kanonickaAdresa);
        self::assertSame($kanonickaAdresa, $this->kanonickaAdresa('web/index.php'));
    }

    /**
     * @test
     */
    public function strankaSeznamuMaVKanonickeAdreseUvodniPolozku(): void
    {
        self::assertStringEndsWith('/web/novinky?start=20', $this->kanonickaAdresa('web/novinky?start=20'));
    }

    /**
     * @test
     */
    public function zapornaUvodniPolozkaSeznamuMaKanonickouAdresuPrvniStranky(): void
    {
        self::assertStringEndsWith('/web/novinky', $this->kanonickaAdresa('web/novinky?start=-20'));
    }

    /**
     * @test
     *
     * @dataProvider provideAdresyRobotsTxt
     */
    public function robotsTxtJeTextPodAdresouKorene(string $cesta): void
    {
        $odpoved = $this->stahni($cesta);

        self::assertSame(200, $odpoved['status']);
        self::assertStringStartsWith('text/plain', $odpoved['contentType']);
        self::assertStringStartsWith("User-agent: *\n", $odpoved['telo']);
    }

    public static function provideAdresyRobotsTxt(): array
    {
        return [
            'v koreni domeny' => ['robots.txt'],
            'v adresari webu' => ['web/robots.txt'],
        ];
    }

    private function kanonickaAdresa(string $cesta): string
    {
        $odpoved = $this->stahni($cesta);
        self::assertSame(200, $odpoved['status'], "Stránka {$cesta} vrátila HTTP {$odpoved['status']}");
        self::assertSame(
            1,
            preg_match('~<link rel="canonical" href="([^"]+)">~', $odpoved['telo'], $shoda),
            "Stránka {$cesta} nemá kanonickou adresu",
        );

        return html_entity_decode($shoda[1]);
    }

    /**
     * @return array{status: int, contentType: string, telo: string}
     */
    private function stahni(string $cesta): array
    {
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_WEBU);
        $curlHandle = curl_init('http://localhost/' . $cesta);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, 30);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->setLocalServerCookieWithGameconTestDb($curlHandle);
        $telo = curl_exec($curlHandle);
        self::assertNotFalse($telo, curl_error($curlHandle));
        $odpoved = [
            'status'      => (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE),
            'contentType' => (string) curl_getinfo($curlHandle, CURLINFO_CONTENT_TYPE),
            'telo'        => (string) $telo,
        ];
        curl_close($curlHandle);

        return $odpoved;
    }
}
