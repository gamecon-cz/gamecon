<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\OdkazyVTextu;
use PHPUnit\Framework\TestCase;

class OdkazyVTextuTest extends TestCase
{
    /**
     * @test
     */
    public function najdeOdkazyNaObrazkyVMarkdownuIHtml(): void
    {
        // skutečné zápisy ze stránek a blogu na ostré
        $text = <<<'TEXT'
            ![mapa](https://gamecon.cz/soubory/obsah/materialy/2023/mapa.jpg)
            <img class="leftOrg" src="soubory/obsah/obrazky/organizatori/gandalf150.jpg">
            ![](soubory/obsah/drd/web/2023/organizace.jpg) a znovu ![](soubory/obsah/drd/web/2023/organizace.jpg)
            [brožurka](https://gamecon.cz/soubory/obsah/download/Bro%C5%BEurka_2023.pdf)
            [souhlas](https://gamecon.cz/soubory/obsah//materialy/2023/souhlas.png)
            ![](soubory/obsah/blog/m%C3%A1j.PNG?v=2)
            <img src="soubory/obsah/obrazky/contract.svg">
            ![](soubory/obsah/../nastaveni/heslo.png) ![](soubory/obsah/zaloha.jpg.bak)
            ![](soubory/systemove/fotky/42.jpg)
            TEXT;

        self::assertSame(
            [
                'materialy/2023/mapa.jpg',
                'obrazky/organizatori/gandalf150.jpg',
                'drd/web/2023/organizace.jpg',
                'materialy/2023/souhlas.png',
                'blog/máj.PNG',
                'obrazky/contract.svg',
            ],
            OdkazyVTextu::getCesty($text, self::priponyObrazku()),
        );
    }

    /**
     * @test
     */
    public function odkazNaAvifSeBereJakoOdkazNaObrazek(): void
    {
        self::assertSame(
            ['materialy/2026/mapa.avif', 'blog/foto.AVIF'],
            OdkazyVTextu::getCesty('![](soubory/obsah/materialy/2026/mapa.avif) ![](soubory/obsah/blog/foto.AVIF?v=2)', self::priponyObrazku()),
        );
    }

    /**
     * @test
     */
    public function najdeOdkazyNaDokumentyVMarkdownuIHtml(): void
    {
        // zápisy tak, jak je na ostré píšou stránky: absolutní odkaz s doménou i bez ní, zakódovaná diakritika, velká přípona
        $text = <<<'TEXT'
            [Press kit](https://gamecon.cz/soubory/obsah/presskit/GameCon_press_kit.zip) a [fotky](//gamecon.cz/soubory/obsah/presskit/GameCon_foto_set.zip)
            <a href="soubory/obsah/download/Bro%C5%BEurka_2023.PDF">brožurka</a>
            [znovu](soubory/obsah/presskit/GameCon_press_kit.zip) ![logo](soubory/obsah/presskit/GameCon_logo.png)
            [skript](soubory/obsah/download/run.php) [záloha](soubory/obsah/download/zaloha.zip.bak)
            [nahrávka](soubory/obsah/drd/audio/Ukázka.mp3?v=2) [tabulka](soubory/obsah/drd/denik.xlsx#list)
            TEXT;

        self::assertSame(
            [
                'presskit/GameCon_press_kit.zip',
                'presskit/GameCon_foto_set.zip',
                'download/Brožurka_2023.PDF',
                'drd/audio/Ukázka.mp3',
                'drd/denik.xlsx',
            ],
            OdkazyVTextu::getCesty($text, NahravaniObrazku::getPriponyFormatu(NahravaniObrazku::DOKUMENTY)),
        );
    }

    /**
     * @test
     */
    public function neodkazujeVenZAdresareAniNaSkryteSoubory(): void
    {
        $text = '[a](soubory/obsah/../nastaveni/heslo.zip) [b](soubory/obsah/.htaccess.zip) [c](soubory/obsah/drd/.skryty.pdf) '
            . '[d](soubory/obsah/drd/%2E%2E/tajny.pdf) [e](soubory/systemove/fotky/42.pdf) [f](soubory/obsah/drd/dobry.pdf)';

        self::assertSame(
            ['drd/dobry.pdf'],
            OdkazyVTextu::getCesty($text, ['pdf', 'zip']),
        );
    }

    /**
     * @test
     */
    public function zpetneLomitkoVOdkazuSeBereJakoLomitko(): void
    {
        self::assertSame(
            ['sponzori/podvrh.png', 'materialy/2026/eshop/01_Blok.jpg', 'materialy/2026/souhlas.pdf'],
            OdkazyVTextu::getCesty(
                '![](soubory/obsah/sponzori%5Cpodvrh.png) ![](soubory/obsah/materialy%5C2026%5Ceshop%5C01_Blok.jpg) '
                . '[souhlas](soubory/obsah/materialy%5C2026%5Csouhlas.pdf)',
                [...self::priponyObrazku(), 'pdf'],
            ),
        );
    }

    /**
     * @test
     */
    public function priponyFormatuJsouZeVsechPodporovanychPripon(): void
    {
        self::assertEqualsCanonicalizing(
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'],
            NahravaniObrazku::getPriponyFormatu([...NahravaniObrazku::WEBOVE_OBRAZKY, NahravaniObrazku::SVG]),
        );
        self::assertEqualsCanonicalizing(
            ['pdf', 'mp3', 'ogg', 'wav', 'm4a', 'zip', 'xlsx', 'docx', 'odt', 'ods'],
            NahravaniObrazku::getPriponyFormatu(NahravaniObrazku::DOKUMENTY),
        );
    }

    /**
     * @return list<string>
     */
    private static function priponyObrazku(): array
    {
        return NahravaniObrazku::getPriponyFormatu([...NahravaniObrazku::WEBOVE_OBRAZKY, NahravaniObrazku::SVG]);
    }
}
