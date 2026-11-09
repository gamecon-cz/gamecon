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
            NahravaniObrazku::getPriponyFormatu([...NahravaniObrazku::RASTROVE_FORMATY, NahravaniObrazku::SVG]),
        );
        self::assertEqualsCanonicalizing(
            ['pdf', 'mp3', 'ogg', 'wav', 'm4a', 'zip', 'xlsx', 'docx', 'odt', 'ods'],
            NahravaniObrazku::getPriponyFormatu(NahravaniObrazku::DOKUMENTY),
        );
    }

    /**
     * @test
     *
     * @dataProvider provideZapisyOdkazu
     */
    public function poznaObrazekVlozenyDoTextuOdSouboruKeStazeni(string $text, bool $jeVlozeny): void
    {
        $odkazy = OdkazyVTextu::getOdkazy($text, ['jpg', 'png']);

        self::assertCount(1, $odkazy);
        self::assertSame('blog/foto.jpg', $odkazy[0]->cesta);
        self::assertSame($jeVlozeny, $odkazy[0]->jeVlozeny);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideZapisyOdkazu(): iterable
    {
        yield 'Markdown obrázek' => ['![popis](soubory/obsah/blog/foto.jpg)', true];
        yield 'Markdown obrázek bez popisu s doménou' => ['![](https://gamecon.cz/soubory/obsah/blog/foto.jpg)', true];
        yield 'Markdown obrázek se závorkou v textu před ním' => ['Text (viz ![popis](//gamecon.cz/soubory/obsah/blog/foto.jpg?v=2))', true];
        yield 'Markdown obrázek s lomítkem na začátku' => ['![](/soubory/obsah/blog/foto.jpg)', true];
        yield 'Markdown obrázek ve špičatých závorkách' => ['![](<soubory/obsah/blog/foto.jpg>)', true];
        yield 'HTML img' => ['<img class="leftOrg" src="soubory/obsah/blog/foto.jpg">', true];
        yield 'HTML img s apostrofy a mezerami' => ["<img\n  alt='a'\n  src = 'soubory/obsah/blog/foto.jpg' />", true];
        yield 'HTML source' => ['<picture><source srcset="soubory/obsah/blog/foto.jpg"></picture>', true];
        yield 'CSS pozadí' => ['<div style="background: url(soubory/obsah/blog/foto.jpg)">', true];
        yield 'obrázek jako odkaz na stránku' => ['[![popis](soubory/obsah/blog/foto.jpg)](jina-stranka)', true];
        yield 'Markdown odkaz ke stažení' => ['[stáhni](soubory/obsah/blog/foto.jpg)', false];
        yield 'HTML odkaz ke stažení' => ['<a href="https://gamecon.cz/soubory/obsah/blog/foto.jpg">stáhni</a>', false];
        yield 'holá adresa v textu' => ['Najdeš ho na https://gamecon.cz/soubory/obsah/blog/foto.jpg i jinde', false];
        yield 'odkaz, jehož zápis se nepozná' => ['[popis]: soubory/obsah/blog/foto.jpg', false];
        yield 'obrázek, ale jiná značka' => ['<video poster="soubory/obsah/blog/foto.jpg"></video>', false];
    }

    /**
     * @test
     */
    public function nahradiPriponuJenUPozadovanychSouboruAZbytekOdkazuNecha(): void
    {
        $text = <<<'TEXT'
            ![](soubory/obsah/blog/foto.jpg) ![](https://gamecon.cz/soubory/obsah/blog/foto.JPG?v=2)
            <img src="/soubory/obsah/blog//m%C3%A1j.png"> ![](soubory/obsah/blog/jiny.jpg)
            ![](soubory/obsah/blog/foto.jpg.bak) ![](soubory/obsah/blog/xfoto.jpg) [soubor](soubory/obsah/blog/foto.jpg)
            TEXT;

        self::assertSame(
            <<<'TEXT'
            ![](soubory/obsah/blog/foto.avif) ![](https://gamecon.cz/soubory/obsah/blog/foto.avif?v=2)
            <img src="/soubory/obsah/blog//m%C3%A1j.avif"> ![](soubory/obsah/blog/jiny.jpg)
            ![](soubory/obsah/blog/foto.jpg.bak) ![](soubory/obsah/blog/xfoto.jpg) [soubor](soubory/obsah/blog/foto.avif)
            TEXT,
            // velká přípona je jiný soubor než malá, takže se přepisuje jen tam, kde je v seznamu
            OdkazyVTextu::nahradPripony($text, ['jpg', 'png'], [
                'blog/foto.jpg' => 'avif',
                'blog/foto.JPG' => 'avif',
                'blog/máj.png'  => 'avif',
            ]),
        );
    }

    /**
     * @test
     */
    public function prepisJenVlozeneObrazkyKdyzToZadameANechaOdkazKeStazeni(): void
    {
        // odkaz ke stažení mohl přibýt po tom, co se rozhodlo, že se obrázek převede
        $text = '![](soubory/obsah/blog/foto.jpg) [stáhni](soubory/obsah/blog/foto.jpg) <a href="soubory/obsah/blog/foto.jpg"><img src="soubory/obsah/blog/foto.jpg"></a>';

        self::assertSame(
            '![](soubory/obsah/blog/foto.avif) [stáhni](soubory/obsah/blog/foto.jpg) <a href="soubory/obsah/blog/foto.jpg"><img src="soubory/obsah/blog/foto.avif"></a>',
            OdkazyVTextu::nahradPripony($text, ['jpg'], [
                'blog/foto.jpg' => 'avif',
            ], jenVlozene: true),
        );
    }

    /**
     * @test
     */
    public function textBezPrepisovanychOdkazuZustaneBajtPoBajtu(): void
    {
        $text = "Příběh bez obrázku, \$1 a 50 % slevy.\n![](soubory/obsah/blog/jiny.jpg)\n";

        self::assertSame($text, OdkazyVTextu::nahradPripony($text, ['jpg'], [
            'blog/foto.jpg' => 'avif',
        ]));
    }

    /**
     * @test
     */
    public function textSNevalidnimUtf8SeNechaJakJe(): void
    {
        $text = "![](soubory/obsah/blog/foto.jpg) \xB1 nevalidní";

        self::assertSame($text, OdkazyVTextu::nahradPripony($text, ['jpg'], [
            'blog/foto.jpg' => 'avif',
        ]));
        self::assertSame([], OdkazyVTextu::getOdkazy($text, ['jpg']));
    }

    /**
     * @return list<string>
     */
    private static function priponyObrazku(): array
    {
        return NahravaniObrazku::getPriponyFormatu([...NahravaniObrazku::RASTROVE_FORMATY, NahravaniObrazku::SVG]);
    }
}
