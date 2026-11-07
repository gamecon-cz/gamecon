<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

class StrukturaHtmlStrankyWebuTest extends AbstractTestWeb
{
    private const string ADRESA_SKRIPTU_GTAG = 'googletagmanager.com/gtag/js';

    /**
     * @var array<string, string>
     */
    private static array $stazeneStranky = [];

    /**
     * @test
     *
     * @dataProvider provideStrankyVeSdileneSablone
     */
    public function hnedZaHeadNasledujeBody(string $cesta): void
    {
        self::assertSame(
            1,
            preg_match('~</head>\s*<body[\s>]~', $this->stahniHtml($cesta)),
            "Stránka {$cesta} nemá <body> hned za </head>",
        );
    }

    /**
     * @test
     *
     * @dataProvider provideStrankyVeSdileneSablone
     */
    public function skriptGoogleTaguJeJednouAJenVHlavicce(string $cesta): void
    {
        $html = $this->stahniHtml($cesta);

        self::assertSame(
            1,
            substr_count($html, self::ADRESA_SKRIPTU_GTAG),
            "Stránka {$cesta} nemá skript Google tagu právě jednou",
        );
        self::assertSame(
            1,
            preg_match('~<head[\s>].*?</head>~s', $html, $hlavicka),
            "Stránka {$cesta} nemá <head>",
        );
        self::assertStringContainsString(self::ADRESA_SKRIPTU_GTAG, $hlavicka[0], "Skript Google tagu na {$cesta} není v <head>");
        self::assertStringContainsString("gtag('config'", $hlavicka[0]);
    }

    /**
     * 'web/zapomenute-heslo' is rendered into the same template by a Symfony controller, not by web/index.php.
     *
     * @return array<string, array{string}>
     */
    public static function provideStrankyVeSdileneSablone(): array
    {
        return [
            'titulka'                       => ['web/'],
            'stranka z modulu webu'         => ['web/program'],
            'stranka ze Symfony kontroleru' => ['web/zapomenute-heslo'],
        ];
    }

    private function stahniHtml(string $cesta): string
    {
        if (! isset(self::$stazeneStranky[$cesta])) {
            $odpoved = $this->stahniVerejnouStranku($cesta);
            self::assertSame(200, $odpoved['status'], "Stránka {$cesta} vrátila HTTP {$odpoved['status']}");
            self::$stazeneStranky[$cesta] = $odpoved['telo'];
        }

        return self::$stazeneStranky[$cesta];
    }
}
