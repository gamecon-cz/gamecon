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
        self::assertStringContainsString(
            'value="&lt;img src=&quot;soubory/obsah/stranky/kontakty/nova-clenka.png&quot; alt=&quot;&quot;&gt;"',
            $html,
        );
        self::assertMatchesRegularExpression('~Markdown:.*?<input[^>]*!\[\]\(soubory/obsah/stranky/kontakty/nova-clenka\.png\)~s', $html);
        self::assertMatchesRegularExpression('~HTML:.*?<input[^>]*&lt;img src=&quot;soubory/obsah/stranky/kontakty/nova-clenka\.png~s', $html);
        self::assertStringContainsString('name="cesta" value="stranky/kontakty/nova-clenka.png"', $html);
        // nahradit jde i obrázek nahraný ke stránce, na který text zatím neodkazuje: dva odkazované a ten nahraný
        self::assertSame(3, substr_count($html, 'value="nahradit"'));
        // zápisy do textu mají stejný vzhled a šířku jako v bloku se soubory, tlačítko Nahradit se vedle výběru souboru vejde
        self::assertSame(2, substr_count($html, 'onclick="this.select()" class="zapisy-k-vlozeni__odkaz"'));
        self::assertStringNotContainsString('size="60"', $html);
        self::assertStringContainsString('Uloží se do <code>soubory/obsah/stranky/kontakty/</code>', $html);
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

    /**
     * Formulář pro nahrání vypadá a roztahuje se jako ve stránce Soubory a v bloku se soubory, ne po svém.
     *
     * @test
     */
    public function formularKNahraniObrazkuMaSpolecnyVzhledSFormularemSouboru(): void
    {
        $html = $this->widget('text')->html();

        self::assertStringContainsString('<form method="post" enctype="multipart/form-data" class="nahrani-souboru">', $html);
        self::assertStringContainsString('<label class="nahrani-souboru__nazev">Název <input type="text" name="nazev"', $html);
        self::assertStringNotContainsString('obrazky-obsahu__nahrat', $html);
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
