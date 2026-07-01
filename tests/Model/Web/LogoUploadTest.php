<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\LogoUpload;
use PHPUnit\Framework\TestCase;

class LogoUploadTest extends TestCase
{
    /**
     * @test
     */
    public function svgJePodporovanaPripona()
    {
        self::assertTrue(LogoUpload::jePodporovanaPripona('svg'));
        self::assertFalse(LogoUpload::jePodporovanaPripona('exe'));
    }

    /**
     * @test
     */
    public function hostZUrlProNazevSouboruOdcistiCestuAQuery()
    {
        self::assertSame(
            'www.albi.cz',
            LogoUpload::hostZUrlProNazevSouboru('https://www.albi.cz/partners?foo=bar#sekce'),
        );
        self::assertSame(
            'blackfire.cz',
            LogoUpload::hostZUrlProNazevSouboru('blackfire.cz/logo'),
        );
    }

    /**
     * @test
     */
    public function neplatnaUrlVratiPrazdnyHost()
    {
        self::assertSame('', LogoUpload::hostZUrlProNazevSouboru('https:///'));
        self::assertSame('', LogoUpload::hostZUrlProNazevSouboru(''));
    }

    /**
     * @test
     */
    public function bezpecneSvgProjede()
    {
        $cesta = $this->vytvorDocasnySoubor(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20">
  <title>Logo</title>
  <rect width="100" height="20" fill="#fff" />
  <path d="M10 10 L90 10" stroke="#000" />
</svg>
SVG);

        try {
            self::assertNull(LogoUpload::validujSvgSoubor($cesta));
        } finally {
            unlink($cesta);
        }
    }

    /**
     * @test
     */
    public function svgSeSkriptemJeOdmitnute()
    {
        $cesta = $this->vytvorDocasnySoubor(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
  <script>alert(1)</script>
</svg>
SVG);

        try {
            self::assertSame(
                'SVG soubor obsahuje nepovolený prvek <script>.',
                LogoUpload::validujSvgSoubor($cesta),
            );
        } finally {
            unlink($cesta);
        }
    }

    /**
     * @test
     */
    public function svgSEventAtributemJeOdmitnute()
    {
        $cesta = $this->vytvorDocasnySoubor(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
  <rect width="10" height="10" onclick="alert(1)" />
</svg>
SVG);

        try {
            self::assertSame(
                'SVG soubor obsahuje nepovolené event atributy.',
                LogoUpload::validujSvgSoubor($cesta),
            );
        } finally {
            unlink($cesta);
        }
    }

    /**
     * @test
     */
    public function svgSExternimOdkazemJeOdmitnute()
    {
        $cesta = $this->vytvorDocasnySoubor(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
  <rect width="10" height="10" fill="url(https://example.com/x)" />
</svg>
SVG);

        try {
            self::assertSame(
                'SVG soubor obsahuje externí URL reference.',
                LogoUpload::validujSvgSoubor($cesta),
            );
        } finally {
            unlink($cesta);
        }
    }

    private function vytvorDocasnySoubor(string $obsah): string
    {
        $cesta = tempnam(sys_get_temp_dir(), 'gamecon-svg-');
        file_put_contents($cesta, $obsah);

        return $cesta;
    }
}
