<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\ObrazkyObsahu;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ObrazkyObsahuTest extends TestCase
{
    use TestovaciObrazky;

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
    public function zpetneLomitkoVOdkazuSeBereJakoLomitko(): void
    {
        self::assertSame(
            ['sponzori/podvrh.png', 'materialy/2026/eshop/01_Blok.jpg'],
            ObrazkyObsahu::odkazyVTextu(
                '![](soubory/obsah/sponzori%5Cpodvrh.png) ![](soubory/obsah/materialy%5C2026%5Ceshop%5C01_Blok.jpg)',
            ),
        );
    }

    /**
     * @test
     */
    public function nenabidneObrazkyAdresareJinehoModuluAniZapsaneZpetnymiLomitky(): void
    {
        (new Filesystem())->mkdir([$this->koren . '/sponzori', $this->koren . '/stranky/kontakty']);
        imagepng(imagecreatetruecolor(4, 4), $this->koren . '/sponzori/podvrh.png');
        $text = '![](soubory/obsah/sponzori%5Cpodvrh.png) ![](soubory/obsah/SPONZORI/podvrh.png)';

        $html = $this->widget($text)->html();

        self::assertStringNotContainsString('podvrh.png', $html);
        self::assertStringNotContainsString('Obrázky, na které text odkazuje', $html);
    }

    /**
     * @test
     */
    public function nahledyVEditoruJsouSkutecneMaleNeOriginalVPuvodniVelikosti(): void
    {
        (new Filesystem())->mkdir([$this->koren . '/obrazky', $this->koren . '/stranky/kontakty']);
        imagepng(imagecreatetruecolor(600, 300), $this->koren . '/obrazky/velky.png');
        imagepng(imagecreatetruecolor(600, 300), $this->koren . '/stranky/kontakty/vlastni.png');

        $html = $this->widget('![](soubory/obsah/obrazky/velky.png)')->html();

        preg_match_all('~<img src="([^"]+)" alt="">~', $html, $shody);
        self::assertCount(2, $shody[1], 'Čekáme náhled odkazovaného i vlastního obrázku');
        foreach ($shody[1] as $urlNahledu) {
            $rozmery = getimagesize(self::souborNahledu(html_entity_decode($urlNahledu)));
            self::assertSame([150, 100], [$rozmery[0], $rozmery[1]]);
        }
    }

    /**
     * @test
     */
    public function adresarStrankyKterySymlinkemVedeVenEditorNeshodi(): void
    {
        $venku = $this->koren . '-venku';
        (new Filesystem())->mkdir([$venku, $this->koren . '/stranky']);
        imagepng(imagecreatetruecolor(4, 4), $venku . '/cizi.png');
        symlink($venku, $this->koren . '/stranky/kontakty');

        try {
            $html = $this->widget('text bez obrázků')->html();
        } finally {
            (new Filesystem())->remove($venku);
        }

        self::assertStringContainsString('Nahrát nový obrázek', $html);
        self::assertStringNotContainsString('cizi.png', $html);
    }

    private function widget(string $text): ObrazkyObsahu
    {
        return new ObrazkyObsahu(
            $text,
            'stranky/kontakty',
            'k této stránce',
            new NahravaniObrazku($this->koren, formaty: [...NahravaniObrazku::RASTROVE_FORMATY, NahravaniObrazku::SVG]),
        );
    }
}
