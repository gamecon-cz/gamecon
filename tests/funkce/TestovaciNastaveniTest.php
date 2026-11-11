<?php

declare(strict_types=1);

namespace Gamecon\Tests\funkce;

use PHPUnit\Framework\TestCase;

class TestovaciNastaveniTest extends TestCase
{
    private array $puvodniCookie;

    private string|false $puvodniPovoleni;

    protected function setUp(): void
    {
        $this->puvodniCookie = $_COOKIE;
        $this->puvodniPovoleni = getenv('ALLOW_TEST_SETTINGS');
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->puvodniCookie;
        putenv($this->puvodniPovoleni === false
            ? 'ALLOW_TEST_SETTINGS'
            : 'ALLOW_TEST_SETTINGS=' . $this->puvodniPovoleni);
    }

    /**
     * @test
     */
    public function testovaciNastaveniSeNacitaJenSCookieAPovolenimProstredi()
    {
        $pripady = [
            'bez cookie a bez povolení'       => [null, null, false],
            'cookie bez povolení'             => ['1', null, false],
            'povolení bez cookie'             => [null, '1', false],
            'cookie a povolení'               => ['1', '1', true],
            'cookie a povolení vypnuté nulou' => ['1', '0', false],
            'cookie a povolení jiné než 1'    => ['1', 'yes', false],
            'prázdná cookie a povolení'       => ['', '1', false],
        ];

        foreach ($pripady as $popis => [$cookie, $povoleni, $ocekavano]) {
            $_COOKIE = $cookie === null
                ? []
                : [
                    'unit_tests' => $cookie,
                ];
            putenv($povoleni === null
                ? 'ALLOW_TEST_SETTINGS'
                : 'ALLOW_TEST_SETTINGS=' . $povoleni);

            self::assertSame($ocekavano, jeTestovaciNastaveniPovoleno(), $popis);
        }
    }

    /**
     * @test
     */
    public function zavadecNastaveniNacitaTestovaciNastaveniJenSCookieAPovolenimProstredi()
    {
        self::assertFalse($this->jsouTestovaciNastaveniNactena(cookie: true, povoleni: null), 'cookie bez povolení');
        self::assertFalse($this->jsouTestovaciNastaveniNactena(cookie: false, povoleni: '1'), 'povolení bez cookie');
        self::assertTrue($this->jsouTestovaciNastaveniNactena(cookie: true, povoleni: '1'), 'cookie a povolení');
    }

    /**
     * @test
     */
    public function univerzalniHesloJeJenVLokalnimATestovacimProstredi()
    {
        self::assertFalse($this->jeHesloPovolenoVPodprocesu('gamecon.cz', false), 'gamecon.cz');
        self::assertFalse($this->jeHesloPovolenoVPodprocesu('beta.gamecon.cz', false), 'beta.gamecon.cz');
        self::assertFalse($this->jeHesloPovolenoVPodprocesu('slug.preview.gamecon.cz', false), 'slug.preview.gamecon.cz');
        self::assertTrue($this->jeHesloPovolenoVPodprocesu('localhost', false), 'localhost');
        self::assertTrue($this->jeHesloPovolenoVPodprocesu('gamecon.cz', true), 'gamecon.cz v testech');
    }

    /**
     * Skutečný zavaděč nastavení na hostu `localhost`, aby se načetlo lokální nastavení a nic se nezapisovalo.
     */
    private function jsouTestovaciNastaveniNactena(bool $cookie, ?string $povoleni): bool
    {
        return $this->vysledekPodprocesu(sprintf(
            '$_SERVER["SERVER_NAME"] = "localhost"; $_COOKIE = %s; %s require %s; require %s; include %s; $vysledek = defined("UNIT_TESTS");',
            $cookie
                ? '["unit_tests" => "1"]'
                : '[]',
            $povoleni === null
                ? ''
                : 'putenv(' . var_export('ALLOW_TEST_SETTINGS=' . $povoleni, true) . ');',
            var_export(__DIR__ . '/../../nastaveni/zavadec-autoloader.php', true),
            var_export(__DIR__ . '/../../model/funkce/funkce.php', true),
            var_export(__DIR__ . '/../../nastaveni/zavadec-nastaveni.php', true),
        ));
    }

    private function jeHesloPovolenoVPodprocesu(string $host, bool $vTestech): bool
    {
        return $this->vysledekPodprocesu(sprintf(
            '$_SERVER["SERVER_NAME"] = %s; %s require %s; $vysledek = jeUniverzalniHesloPovoleno();',
            var_export($host, true),
            $vTestech
                ? 'define("UNIT_TESTS", true);'
                : '',
            var_export(__DIR__ . '/../../model/funkce/funkce.php', true),
        ));
    }

    /**
     * Nový proces s prázdným prostředím: konstantu `UNIT_TESTS` nejde v procesu testů zrušit a proměnná `ENV=local`
     * z kontejneru by z libovolného hosta udělala lokální vývoj.
     */
    private function vysledekPodprocesu(string $kod): bool
    {
        $proces = proc_open(
            [PHP_BINARY, '-r', $kod . ' echo "\nVYSLEDEK=" . json_encode($vysledek);'],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $roury,
            null,
            [],
        );
        self::assertIsResource($proces);
        $vystup = stream_get_contents($roury[1]);
        $chyby = stream_get_contents($roury[2]);
        proc_close($proces);

        self::assertSame(1, preg_match('~VYSLEDEK=(true|false)\s*\z~', $vystup, $shoda), 'Podproces nevrátil výsledek: ' . $vystup . $chyby);

        return $shoda[1] === 'true';
    }
}
