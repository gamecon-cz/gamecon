<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\ObrazkyEshopu;
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
