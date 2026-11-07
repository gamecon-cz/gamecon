<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

class KanonickaAdresaWebuTest extends AbstractTestWeb
{
    /**
     * @return string[]
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return array_merge(
            parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
INSERT INTO akce_seznam (nazev_akce, kapacita, kapacita_f, kapacita_m, cena, bez_slevy, nedava_bonus, typ, rok, stav, teamova, popis, popis_kratky, vybaveni)
VALUES ('Aktivita z minulého ročníku', 0, 0, 0, 100, 0, 0, 4, 2020, 2, 0, '', '', '')
SQL,
            ],
        );
    }

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
        self::assertSame($kanonickaAdresa, $this->kanonickaAdresa('web/titulka'));
    }

    /**
     * @test
     *
     * @dataProvider provideZaznamyNalezeneBezOhleduNaVelikostPismen
     */
    public function zaznamNalezenyBezOhleduNaVelikostPismenMaKanonickouAdresuUlozenehoTvaru(
        string $cesta,
        string $ocekavanyKonec,
    ): void {
        self::assertStringEndsWith($ocekavanyKonec, $this->kanonickaAdresa($cesta));
    }

    public static function provideZaznamyNalezeneBezOhleduNaVelikostPismen(): array
    {
        return [
            'linie malymi'    => ['web/larpy', '/web/larpy'],
            'linie velkymi'   => ['web/LARPY', '/web/larpy'],
            'linie smisene'   => ['web/Larpy', '/web/larpy'],
            'stranka malymi'  => ['web/dnd-info', '/web/dnd-info'],
            'stranka velkymi' => ['web/DND-INFO', '/web/dnd-info'],
        ];
    }

    /**
     * @test
     */
    public function minulyRocnikMaVKanonickeAdreseRocnik(): void
    {
        self::assertStringEndsWith(
            '/web/aktivity?rocnik=2020',
            $this->kanonickaAdresa('web/aktivity?rocnik=2020&utm_source=newsletter'),
        );
    }

    /**
     * @test
     */
    public function rocnikBezAktivitMaKanonickouAdresuBezRocniku(): void
    {
        self::assertStringEndsWith('/web/aktivity', $this->kanonickaAdresa('web/aktivity?rocnik=1999'));
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
        // tests run as the local environment, which is closed to crawlers
        self::assertSame("User-agent: *\nDisallow: /\n", $odpoved['telo']);
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
