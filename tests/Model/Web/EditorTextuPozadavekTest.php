<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\EditorTextuPozadavek;
use Gamecon\Web\KontextTextu;
use Gamecon\Web\NahranyObrazek;
use Gamecon\Web\NahravaniObrazku;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class EditorTextuPozadavekTest extends TestCase
{
    private const string ADRESAR_TEXTU = 'blog/zahajeni-registraci';

    private string $koren;
    private string $zdroje;
    private NahravaniObrazku $nahravani;

    protected function setUp(): void
    {
        $this->koren = sys_get_temp_dir() . '/gamecon-editor-textu-' . bin2hex(random_bytes(6));
        $this->zdroje = $this->koren . '-zdroje';
        (new Filesystem())->mkdir([$this->koren, $this->zdroje]);
        // bez optimalizace: PNG zůstane PNG a test nezávisí na ImageMagicku
        $this->nahravani = new NahravaniObrazku($this->koren, formaty: [...NahravaniObrazku::RASTROVE_FORMATY, NahravaniObrazku::SVG]);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->koren, $this->zdroje]);
    }

    /**
     * @test
     */
    public function nahledVykresliMarkdownJakoWebSObrazkyNaWebu(): void
    {
        $odpoved = $this->pozadavek()->zpracuj('nahled', 'POST', true, [
            'text' => "| a | b |\n|---|---|\n| 1 | 2 |\n\n![](soubory/obsah/blog/x/plakat.avif)",
        ], $this->bezObrazku());

        self::assertSame(200, $odpoved['kod']);
        self::assertTrue($odpoved['telo']['ok']);
        // tabulka je Markdown Extra, showdown.js ji v prohlížeči nevykreslil
        self::assertStringContainsString('<table>', $odpoved['telo']['html']);
        self::assertStringContainsString('<img src="' . URL_WEBU . '/soubory/obsah/blog/x/plakat.avif"', $odpoved['telo']['html']);
    }

    /**
     * @test
     */
    public function bezHlavickyEditoruNeboJinakNezPostemNicNeudela(): void
    {
        $bezHlavicky = $this->pozadavek()->zpracuj('nahled', 'POST', false, [
            'text' => 'a',
        ], $this->bezObrazku());
        $get = $this->pozadavek()->zpracuj('nahled', 'GET', true, [
            'text' => 'a',
        ], $this->bezObrazku());

        self::assertSame(400, $bezHlavicky['kod']);
        self::assertSame(400, $get['kod']);
        self::assertFalse($get['telo']['ok']);
    }

    /**
     * @test
     */
    public function neznamaAkceAChybejiciTextJsouChybaProCloveka(): void
    {
        $neznama = $this->pozadavek()->zpracuj('smazat', 'POST', true, [], $this->bezObrazku());
        $bezTextu = $this->pozadavek()->zpracuj('nahled', 'POST', true, [], $this->bezObrazku());

        self::assertSame(422, $neznama['kod']);
        self::assertSame(422, $bezTextu['kod']);
        self::assertSame('Chybí text k náhledu.', $bezTextu['telo']['zprava']);
    }

    /**
     * @test
     */
    public function obrazekSeUlozikTextuAVratiZapisDoTextu(): void
    {
        $odpoved = $this->pozadavek()->zpracuj('obrazek', 'POST', true, [], $this->obrazek('Plakát 2026.png'));

        self::assertSame(200, $odpoved['kod'], json_encode($odpoved['telo'], JSON_UNESCAPED_UNICODE));
        self::assertSame('soubory/obsah/' . self::ADRESAR_TEXTU . '/plakat-2026.png', $odpoved['telo']['cesta']);
        self::assertSame('![](soubory/obsah/' . self::ADRESAR_TEXTU . '/plakat-2026.png)', $odpoved['telo']['markdown']);
        self::assertFileExists($this->koren . '/' . self::ADRESAR_TEXTU . '/plakat-2026.png');
    }

    /**
     * Ze schránky má obrázek pokaždé stejný název a nikdo ho nevolí, další se proto uloží s číslem místo chyby.
     *
     * @test
     */
    public function obrazekSeStejnymNazvemDostaneCislo(): void
    {
        $cesty = [];
        foreach (range(1, 3) as $_) {
            $odpoved = $this->pozadavek()->zpracuj('obrazek', 'POST', true, [], $this->obrazek('obrazek.png'));
            self::assertSame(200, $odpoved['kod'], json_encode($odpoved['telo'], JSON_UNESCAPED_UNICODE));
            $cesty[] = basename($odpoved['telo']['cesta']);
        }
        // obsazený je i název s jinou příponou, nový obrázek by se vedle něj jinak uložil se stejným jménem
        imagepng(imagecreatetruecolor(4, 4), $this->koren . '/' . self::ADRESAR_TEXTU . '/fotka.png');
        $jinaPripona = $this->pozadavek()->zpracuj('obrazek', 'POST', true, [], $this->obrazek('fotka.gif', 'gif'));

        self::assertSame(['obrazek.png', 'obrazek-2.png', 'obrazek-3.png'], $cesty);
        self::assertSame('fotka-2.gif', basename($jinaPripona['telo']['cesta']));
    }

    /**
     * @test
     */
    public function nazevBezPismenADlouhyNazevSeVejdou(): void
    {
        $bezPismen = $this->pozadavek()->zpracuj('obrazek', 'POST', true, [], $this->obrazek('😀.png'));
        $dlouhy = str_repeat('a', 150);
        $prvni = $this->pozadavek()->zpracuj('obrazek', 'POST', true, [], $this->obrazek($dlouhy . '.png'));
        $druhy = $this->pozadavek()->zpracuj('obrazek', 'POST', true, [], $this->obrazek($dlouhy . '.png'));

        self::assertSame('obrazek.png', basename($bezPismen['telo']['cesta']));
        self::assertSame($dlouhy . '.png', basename($prvni['telo']['cesta']));
        self::assertSame(str_repeat('a', 148) . '-2.png', basename($druhy['telo']['cesta']));
    }

    /**
     * @test
     */
    public function bezTextuKamObrazekUlozitHoNeoveruje(): void
    {
        $overeno = false;
        $odpoved = (new EditorTextuPozadavek(static fn (): ?KontextTextu => null, $this->nahravani))
            ->zpracuj('obrazek', 'POST', true, [], function () use (&$overeno): NahranyObrazek {
                $overeno = true;

                return $this->nahravani->overSoubor(__FILE__);
            });

        self::assertSame(422, $odpoved['kod']);
        self::assertSame('Obrázek do textu půjde vložit, až text uložíš.', $odpoved['telo']['zprava']);
        self::assertFalse($overeno);
    }

    /**
     * @test
     */
    public function souborKteryNeniObrazekOdmitne(): void
    {
        file_put_contents($this->zdroje . '/skript.png', '<?php echo 1;');

        $odpoved = $this->pozadavek()->zpracuj(
            'obrazek',
            'POST',
            true,
            [],
            fn (): NahranyObrazek => $this->nahravani->overSoubor($this->zdroje . '/skript.png'),
        );

        self::assertSame(422, $odpoved['kod']);
        self::assertFalse($odpoved['telo']['ok']);
        self::assertDirectoryDoesNotExist($this->koren . '/' . self::ADRESAR_TEXTU);
    }

    private function pozadavek(): EditorTextuPozadavek
    {
        return new EditorTextuPozadavek(
            static fn (): KontextTextu => new KontextTextu('text', self::ADRESAR_TEXTU, 'k tomuto příspěvku'),
            $this->nahravani,
        );
    }

    /**
     * @return \Closure(): NahranyObrazek
     */
    private function obrazek(string $puvodniNazev, string $format = 'png'): \Closure
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '.' . $format;
        $obrazek = imagecreatetruecolor(4, 4);
        $format === 'gif'
            ? imagegif($obrazek, $cesta)
            : imagepng($obrazek, $cesta);

        return fn (): NahranyObrazek => $this->nahravani->overSoubor($cesta, $puvodniNazev);
    }

    /**
     * @return \Closure(): NahranyObrazek
     */
    private function bezObrazku(): \Closure
    {
        return static function (): NahranyObrazek {
            throw new \LogicException('Náhled se na obrázek ptát nemá');
        };
    }
}
