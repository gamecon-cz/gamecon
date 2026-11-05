<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahledMarkdownu;
use PHPUnit\Framework\TestCase;

class NahledMarkdownuTest extends TestCase
{
    /**
     * @test
     */
    public function obrazekZeSlozkySouboryVedeNaWebNeDoAdresareAdministrace(): void
    {
        $html = '<p><img src="soubory/obsah/presskit/GameCon_logo.png" alt="Logo" /></p>';

        self::assertSame(
            '<p><img src="' . URL_WEBU . '/soubory/obsah/presskit/GameCon_logo.png" alt="Logo" /></p>',
            NahledMarkdownu::obrazkyNaWebu($html),
        );
    }

    /**
     * @test
     */
    public function najdeObrazekBezOhleduNaPoradiAtributuAVelikostPismen(): void
    {
        $html = '<img class="leftOrg" alt="" SRC="soubory/a.png"><IMG src="soubory/b.png">';

        $upraveno = NahledMarkdownu::obrazkyNaWebu($html);

        self::assertStringContainsString('SRC="' . URL_WEBU . '/soubory/a.png"', $upraveno);
        self::assertStringContainsString('src="' . URL_WEBU . '/soubory/b.png"', $upraveno);
    }

    /**
     * Markdown dává obrázku uvozovky, ale HTML ručně napsané v textu stránky mívá apostrofy nebo žádné.
     *
     * @test
     */
    public function najdeIObrazekSAdresouVApostrafechNeboBezUvozovek(): void
    {
        $html = "<img src='soubory/a.png'> <img alt=\"\" src=soubory/b.png>";

        self::assertSame(
            "<img src='" . URL_WEBU . "/soubory/a.png'> <img alt=\"\" src=" . URL_WEBU . '/soubory/b.png>',
            NahledMarkdownu::obrazkyNaWebu($html),
        );
    }

    /**
     * @test
     */
    public function jinouAdresuAJinouNezObrazkovouZminkuNechaBezZmeny(): void
    {
        $html = '<p><img src="https://example.invalid/soubory/a.png"> <img src="/soubory/b.png"> '
            . '<img src="obrazky/soubory/c.png"> <a href="soubory/d.pdf">soubory/e.png</a> <img alt="soubory/f.png" src="g.png"></p>';

        self::assertSame($html, NahledMarkdownu::obrazkyNaWebu($html));
    }
}
