<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\ObrazkyObsahu;
use Gamecon\Web\ZpracovaneObrazky;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ObrazkyObsahuTest extends TestCase
{
    private string $koren;

    protected function setUp(): void
    {
        $this->koren = sys_get_temp_dir() . '/gamecon-obrazky-obsahu-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->koren);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->koren);
    }

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
            ObrazkyObsahu::odkazyVTextu($text),
        );
    }

    /**
     * @test
     */
    public function nabidneNahrazeniOdkazanychObrazkuASmazaniJenNepouzitych(): void
    {
        (new Filesystem())->mkdir([$this->koren . '/obrazky/organizatori', $this->koren . '/stranky/kontakty']);
        imagepng(imagecreatetruecolor(4, 4), $this->koren . '/obrazky/organizatori/gandalf150.png');
        imagepng(imagecreatetruecolor(4, 4), $this->koren . '/stranky/kontakty/nova-clenka.png');
        $text = '<img src="soubory/obsah/obrazky/organizatori/gandalf150.png"> ![](soubory/obsah/obrazky/chybi.jpg) '
            . '![](soubory/obsah/sponzori/logo.png) ![](soubory/obsah/materialy/2026/eshop/01_Blok.jpg)';

        $html = (new ObrazkyObsahu(
            $text,
            'stranky/kontakty',
            'k této stránce',
            new NahravaniObrazku($this->koren, formaty: [...NahravaniObrazku::RASTROVE_FORMATY, NahravaniObrazku::SVG]),
        ))->html();

        self::assertStringContainsString('<code>soubory/obsah/obrazky/organizatori/gandalf150.png</code>', $html);
        self::assertStringContainsString('<code>soubory/obsah/obrazky/chybi.jpg</code>', $html);
        self::assertStringContainsString('na serveru chybí', $html);
        // loga spravuje modul Loga a galerii e-shopu Shop, oba s vlastním právem
        self::assertStringNotContainsString('sponzori/logo.png', $html);
        self::assertStringNotContainsString('eshop/01_Blok.jpg', $html);
        self::assertStringContainsString('Obrázky nahrané k této stránce', $html);
        self::assertStringContainsString('value="![](soubory/obsah/stranky/kontakty/nova-clenka.png)"', $html);
        self::assertStringContainsString('name="cesta" value="stranky/kontakty/nova-clenka.png"', $html);
        self::assertStringContainsString('Uloží se do <code>soubory/obsah/stranky/kontakty/</code>', $html);
    }

    /**
     * @test
     */
    public function popisekObrazkuEshopuJeNazevBezPoradiAPripony(): void
    {
        self::assertSame('Účastnické triko na postavě', ZpracovaneObrazky::popisekObrazkuEshopu('08_Účastnické triko na postavě.jpeg'));
        self::assertSame('nicknack vyrocni', ZpracovaneObrazky::popisekObrazkuEshopu('/cesta/20_nicknack_vyrocni.JPG'));
        self::assertSame('Tematická placka Verneovky', ZpracovaneObrazky::popisekObrazkuEshopu('04_Tematická placka - Verneovky.png'));
    }
}
