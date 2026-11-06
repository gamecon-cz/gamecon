<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\ObrazkyEshopu;
use Gamecon\Web\OptimalizaceObrazku;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ObrazkyEshopuTest extends TestCase
{
    use TestovaciObrazky;

    private string $koren;

    protected function setUp(): void
    {
        $this->koren = sys_get_temp_dir() . '/gamecon-obrazky-eshopu-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->koren);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->koren);
    }

    /**
     * @test
     */
    public function vypiseObrazkyPodlePoradiSPopiskemAMalymNahledem(): void
    {
        imagepng(imagecreatetruecolor(600, 300), $this->koren . '/02_Taška.png');
        file_put_contents($this->koren . '/01_Účastnické triko.jpg', self::jpgSOrientaci(1, 600, 300));
        foreach (['.nahravani-abc', 'poznamky.txt'] as $nepatriDoGalerie) {
            file_put_contents($this->koren . '/' . $nepatriDoGalerie, 'x');
        }

        $html = $this->galerie()->html();

        self::assertSame(
            ['01_Účastnické triko.jpg', '02_Taška.png'],
            $this->nazvySouboru($html),
        );
        self::assertStringContainsString('<td>Účastnické triko</td>', $html);
        self::assertStringContainsString('<td>Taška</td>', $html);
        self::assertStringNotContainsString('poznamky.txt', $html);
        self::assertStringNotContainsString('Přihláška ho neukáže', $html);
        preg_match_all('~<img src="([^"]+)" alt="">~', $html, $shody);
        self::assertCount(2, $shody[1]);
        foreach ($shody[1] as $urlNahledu) {
            $rozmery = getimagesize(self::souborNahledu(html_entity_decode($urlNahledu)));
            self::assertSame([120, 80], [$rozmery[0], $rozmery[1]], 'Náhled je malý, ne originál');
        }
    }

    /**
     * @test
     */
    public function upozorniNaObrazekKterySiPrihlaskaNeukaze(): void
    {
        file_put_contents($this->koren . '/01_Blok.webp', base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==', true));
        file_put_contents($this->koren . '/02_Falešný.jpg', 'to není obrázek');

        $html = $this->galerie()->html();

        self::assertSame(2, substr_count($html, 'Přihláška ho neukáže'));
    }

    /**
     * @test
     */
    public function souborSeZpetnymLomitkemVNazvuNevypisePadaNeni(): void
    {
        file_put_contents($this->koren . '/01_Blok.jpg', self::jpgSOrientaci(1));
        file_put_contents($this->koren . '/02_Zpětné\\lomítko.jpg', self::jpgSOrientaci(1));

        $html = $this->galerie()->html();

        self::assertSame(['01_Blok.jpg'], $this->nazvySouboru($html));
    }

    /**
     * @test
     */
    public function nazevSouboruVypisujeJakoTextNeJakoHtml(): void
    {
        file_put_contents($this->koren . '/<i>tučně.jpg', self::jpgSOrientaci(1));

        $html = $this->galerie()->html();

        self::assertStringNotContainsString('<i>tučně', $html);
        self::assertStringContainsString('&lt;i&gt;tučně.jpg', $html);
    }

    /**
     * @test
     */
    public function prazdnaGalerieToRekne(): void
    {
        self::assertStringContainsString('Zatím tu žádný obrázek není', $this->galerie()->html());
    }

    /**
     * Formulář pro nahrání vypadá a roztahuje se jako ve stránce Soubory a pod editorem, ne po svém.
     *
     * @test
     */
    public function formularKNahraniMaSpolecnyVzhledSFormularemSouboru(): void
    {
        $html = $this->galerie()->html();

        self::assertStringContainsString('class="nahrani-souboru obrazky-eshopu__nahrat"', $html);
        self::assertStringContainsString('<label class="nahrani-souboru__nazev">Název <input type="text" name="nazev"', $html);
        self::assertStringContainsString('<i class="fa fa-upload" aria-hidden="true"></i> Nahrát</button>', $html, 'Tlačítko má ikonu jako ve stránce Soubory');
    }

    /**
     * Každý obrázek jde nahradit tlačítkem v jeho řádku, pod stejným názvem; okno pro výběr souboru nabídne formáty, které se přijmou.
     *
     * @test
     */
    public function kazdyObrazekMaTlacitkoNahraditSPrijimanymiFormaty(): void
    {
        imagepng(imagecreatetruecolor(60, 30), $this->koren . '/01_Mapa.png');
        file_put_contents($this->koren . '/02_Foto.jpg', self::jpgSOrientaci(1, 60, 30));
        imagegif(imagecreatetruecolor(20, 10), $this->koren . '/03_Animace.gif');

        $html = $this->galerieSPrevodem()->html();

        $ocekavane = [
            '01_Mapa.png'    => 'image/png,image/jpeg,image/gif',
            '02_Foto.jpg'    => 'image/jpeg,image/png,image/gif',
            '03_Animace.gif' => 'image/gif',
        ];
        foreach ($ocekavane as $soubor => $accept) {
            $radek = $this->radekSouboru($html, $soubor);
            self::assertMatchesRegularExpression(
                '~<div class="akce-souboru">\s*<form.*?<i class="fa fa-upload" aria-hidden="true"></i> Nahradit</button>.*?<form.*?<i class="fa fa-trash" aria-hidden="true"></i> Smazat</button>\s*</form>\s*</div>~s',
                $radek,
                $soubor,
            );
            self::assertStringContainsString('accept="' . $accept . '"', $radek, $soubor);
            self::assertSame(2, substr_count($radek, 'name="soubor" value="' . $soubor . '"'), 'Nahradit i Smazat nesou název z řádku ' . $soubor);
            self::assertSame(1, substr_count($radek, 'value="nahradit"'), $soubor);
        }
    }

    /**
     * Název, který formulář nepřenese (kódování z FTP, které není UTF-8), by se po odeslání nenašel, takže tlačítko Nahradit nedostane.
     *
     * @test
     */
    public function obrazekSNazvemKterySeNeprenesePostemNemaTlacitkoNahradit(): void
    {
        file_put_contents($this->koren . '/01_Caf' . "\xE9" . '.jpg', self::jpgSOrientaci(1, 60, 30));
        file_put_contents($this->koren . '/02_Foto.jpg', self::jpgSOrientaci(1, 60, 30));

        $html = $this->galerieSPrevodem()->html();

        self::assertSame(1, substr_count($html, 'value="nahradit"'));
        self::assertStringContainsString('name="soubor" value="02_Foto.jpg"', $html);
    }

    private function radekSouboru(string $html, string $soubor): string
    {
        self::assertSame(1, preg_match('~<tr>(?:(?!</tr>).)*' . preg_quote(htmlspecialchars($soubor), '~') . '(?:(?!</tr>).)*</tr>~s', $html, $radek), 'Řádek ' . $soubor);

        return $radek[0];
    }

    /**
     * Rozbitý JPG jde nahradit, aby se dal opravit; WebP, který galerie nepřijímá, jen smazat.
     *
     * @test
     */
    public function obrazekVeFormatuKterySeNepodporujeJdeJenSmazat(): void
    {
        file_put_contents($this->koren . '/01_Blok.webp', base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==', true));
        file_put_contents($this->koren . '/02_Falešný.jpg', 'to není obrázek');

        $html = $this->galerieSPrevodem()->html();

        self::assertSame(1, substr_count($html, 'value="nahradit"'));
        self::assertStringContainsString('name="soubor" value="02_Falešný.jpg"', $html);
        self::assertSame(2, substr_count($html, 'value="smazat"'));
    }

    private function galerieSPrevodem(): ObrazkyEshopu
    {
        return new ObrazkyEshopu(new NahravaniObrazku(
            $this->koren,
            formaty: [NahravaniObrazku::JPG, NahravaniObrazku::PNG, NahravaniObrazku::GIF],
            optimalizace: new OptimalizaceObrazku(),
        ));
    }

    private function galerie(): ObrazkyEshopu
    {
        return new ObrazkyEshopu(new NahravaniObrazku($this->koren, formaty: [NahravaniObrazku::JPG, NahravaniObrazku::PNG, NahravaniObrazku::GIF]));
    }

    /**
     * @return list<string> názvy souborů ve sloupci Soubor tabulky, v pořadí výpisu
     */
    private function nazvySouboru(string $html): array
    {
        preg_match_all('~<td>\s*(\S[^<]*?)\s*(?:<br>.*?)?</td>\s*<td>[^<]*</td>~su', $html, $shody);

        return array_map(html_entity_decode(...), $shody[1]);
    }
}
