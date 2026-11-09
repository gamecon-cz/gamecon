<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\OptimalizaceObrazku;
use Gamecon\Web\PolozkaPrevoduObrazku;
use Gamecon\Web\PouzitiObrazku;
use Gamecon\Web\PrevodObrazkuNaAvif;
use Gamecon\Web\StavPrevoduObrazkuEnum;
use Gamecon\Web\VysledekPrevoduObrazku;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Fotky uživatelů leží ve `soubory/systemove/fotky` jako `<id uživatele>.<přípona>` a web je najde podle id, žádný text
 * na ně neodkazuje. Test pracuje ve složce uvnitř téhle složky, ať se nesahá na fotky, které tam v prostředí jsou.
 */
class PrevodFotekUzivateluNaAvifTest extends AbstractTestDb
{
    use TestovaciObrazky;
    use UklidNovychAdresaru;

    protected static bool $disableStrictTransTables = true;

    private const int ID_STRANKY = 9930;
    private const int ID_FOTKY = 99431;
    private const int ID_DALSI_FOTKY = 99432;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->uklidNoveAdresare($this->absolutne());
        (new Filesystem())->mkdir($this->absolutne());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        $this->kUklidu = [];
        parent::tearDown();
    }

    /**
     * @test
     */
    public function prevedeFotkuVedleOriginaluBezMetadatAOtocenouPodleEXIFu(): void
    {
        $this->ulozObrazek(self::ID_FOTKY . '.jpg', self::jpgSOrientaci(6, 400, 200, true));
        $this->vlozStranku('Text bez obrázků');

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $this->polozka($vysledek, $this->cesta('.jpg'))->stav);
        self::assertSame(['99431.avif', '99431.jpg'], $this->souborySlozky(), 'originál zůstane, smaže se až druhým krokem');
        $avif = $this->absolutne(self::ID_FOTKY . '.avif');
        self::assertSame('image/avif', getimagesize($avif)['mime']);
        self::assertSame([200, 400], array_slice(getimagesize($avif), 0, 2), 'orientace z EXIFu se použila');
        self::assertTrue(self::jeCervena($avif, 100, 10));
        self::assertTrue(self::jeModra($avif, 100, 390));
        self::assertNotContains('exif', (new \Imagick($avif))->getImageProfiles('*', false), 'GPS z telefonu zmizí');
        self::assertSame([], $vysledek->prepsanoRadku);
        self::assertSame(0, $vysledek->nepouzitychObrazku);
        self::assertSame('Text bez obrázků', $this->textStranky());
        // `Uzivatel::fotka()` bere první shodu z `glob`, který řadí podle abecedy
        self::assertSame($avif, glob($this->absolutne(self::ID_FOTKY . '.*'))[0]);
    }

    /**
     * @test
     */
    public function suchyBehNicNezapise(): void
    {
        $this->ulozFotku(self::ID_FOTKY . '.jpg');

        $vysledek = $this->prevod()->proved(false);

        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $this->polozka($vysledek, $this->cesta('.jpg'))->stav);
        self::assertGreaterThan(0, $vysledek->getUsporaBajtu());
        self::assertSame(['99431.jpg'], $this->souborySlozky());
    }

    /**
     * @test
     */
    public function prevedePngIWebPAnechaGifAAvif(): void
    {
        $png = imagecreatetruecolor(300, 200);
        imagefilledrectangle($png, 0, 0, 149, 199, 0xFF0000);
        ob_start();
        imagepng($png);
        $this->ulozObrazek(self::ID_FOTKY . '.png', (string) ob_get_clean());
        $webp = new \Imagick();
        $webp->newPseudoImage(400, 300, 'plasma:fractal');
        $webp->setImageFormat('webp');
        $this->ulozObrazek(self::ID_DALSI_FOTKY . '.webp', $webp->getImageBlob());
        $gif = imagecreatetruecolor(20, 20);
        ob_start();
        imagegif($gif);
        $this->ulozObrazek('99433.gif', (string) ob_get_clean());
        $this->ulozObrazek('99434.avif', file_get_contents($this->avifZ(self::fotkaZFotakuBajty(200, 100))));

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $this->polozka($vysledek, $this->cesta('.png'))->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $this->polozka($vysledek, self::ID_DALSI_FOTKY . '.webp', true)->stav);
        self::assertCount(2, $vysledek->polozky, 'GIF a AVIF se nepřevádějí');
        self::assertSame(['99431.avif', '99431.png', '99432.avif', '99432.webp', '99433.gif', '99434.avif'], $this->souborySlozky());
    }

    /**
     * @test
     */
    public function hotovyAvifSePouzijeJinyStejnojmennyAvifSeNepreprise(): void
    {
        $this->ulozFotku(self::ID_FOTKY . '.jpg');
        $this->zapisAvifZ(self::ID_FOTKY . '.jpg', self::ID_FOTKY . '.avif');
        $this->ulozFotku(self::ID_DALSI_FOTKY . '.jpg');
        $jinyAvif = $this->avifZ(self::fotkaZFotakuBajty(1200, 800));
        copy($jinyAvif, $this->absolutne(self::ID_DALSI_FOTKY . '.avif'));
        $puvodniJinyAvif = md5_file($this->absolutne(self::ID_DALSI_FOTKY . '.avif'));

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::UZ_PREVEDENO, $this->polozka($vysledek, $this->cesta('.jpg'))->stav);
        self::assertSame(StavPrevoduObrazkuEnum::JINY_AVIF_UZ_EXISTUJE, $this->polozka($vysledek, self::ID_DALSI_FOTKY . '.jpg', true)->stav);
        self::assertSame($puvodniJinyAvif, md5_file($this->absolutne(self::ID_DALSI_FOTKY . '.avif')));
    }

    /**
     * @test
     */
    public function dveFotkyTehoUzivateleMajiJedenCilAPrevedeSeJenPrvni(): void
    {
        $this->ulozFotku(self::ID_FOTKY . '.jpg');
        $png = imagecreatetruecolor(300, 200);
        ob_start();
        imagepng($png);
        $this->ulozObrazek(self::ID_FOTKY . '.png', (string) ob_get_clean());

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $this->polozka($vysledek, $this->cesta('.jpg'))->stav);
        self::assertSame(StavPrevoduObrazkuEnum::JINY_AVIF_UZ_EXISTUJE, $this->polozka($vysledek, $this->cesta('.png'))->stav);
    }

    /**
     * @test
     */
    public function symlinkSeNeprevadi(): void
    {
        $this->ulozFotku('99435-cil.jpg');
        symlink($this->absolutne('99435-cil.jpg'), $this->absolutne('99435.jpg'));

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(['99435-cil.avif', '99435-cil.jpg', '99435.jpg'], $this->souborySlozky());
        foreach ($vysledek->polozky as $polozka) {
            self::assertNotSame('99435.jpg', basename($polozka->cesta), 'symlink se mezi kandidáty vůbec neobjeví');
        }
    }

    /**
     * Text s odkazem na obrázek v obsahu webu, který se jmenuje stejně jako fotka, převod fotek nepřepíše.
     *
     * @test
     */
    public function textyWebuSePriPrevoduFotekNepripisuji(): void
    {
        $this->ulozFotku(self::ID_FOTKY . '.jpg');
        $text = '![](soubory/obsah/' . $this->slozka() . '/' . self::ID_FOTKY . '.jpg)';
        $this->vlozStranku($text);

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $this->polozka($vysledek, $this->cesta('.jpg'))->stav);
        self::assertSame([], $vysledek->prepsanoRadku);
        self::assertSame($text, $this->textStranky());
    }

    /**
     * @test
     */
    public function smazePuvodniFotkuKdyzJeVedleStejnyAvifANikdoNaNiNeodkazuje(): void
    {
        $this->ulozFotku(self::ID_FOTKY . '.jpg');
        $this->zapisAvifZ(self::ID_FOTKY . '.jpg', self::ID_FOTKY . '.avif');

        $vysledek = $this->prevod()->smazPuvodni(true);

        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_SMAZAN, $this->polozka($vysledek, $this->cesta('.jpg'))->stav);
        self::assertSame(['99431.avif'], $this->souborySlozky());
    }

    /**
     * @test
     */
    public function puvodniFotkuNechaKdyzNaNiOdkazujeTextNeboKdyzAvifNeniStejnyObrazek(): void
    {
        $this->ulozFotku(self::ID_FOTKY . '.jpg');
        $this->zapisAvifZ(self::ID_FOTKY . '.jpg', self::ID_FOTKY . '.avif');
        // text e-mailu, ve kterém odkaz hledá až dotaz nad všemi textovými sloupci
        dbQuery(
            'INSERT INTO emailova_sablona(kod, predmet, text) VALUES ($0, $1, $2)',
            ['test-prevod-fotek', 'Předmět', '<img src="https://gamecon.cz/soubory/systemove/fotky/' . $this->slozka() . '/' . self::ID_FOTKY . '.jpg">'],
        );
        $this->ulozFotku(self::ID_DALSI_FOTKY . '.jpg');
        copy($this->avifZ(self::jpgBajty(1200, 800)), $this->absolutne(self::ID_DALSI_FOTKY . '.avif'));

        $vysledek = $this->prevod()->smazPuvodni(true);

        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $this->polozka($vysledek, $this->cesta('.jpg'))->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_BEZ_STEJNEHO_AVIF, $this->polozka($vysledek, self::ID_DALSI_FOTKY . '.jpg', true)->stav);
        self::assertSame(['99431.avif', '99431.jpg', '99432.avif', '99432.jpg'], $this->souborySlozky());
    }

    private function prevod(): PrevodObrazkuNaAvif
    {
        return PrevodObrazkuNaAvif::proFotkyUzivatelu(new OptimalizaceObrazku(), new PouzitiObrazku(), $this->slozka());
    }

    private function polozka(VysledekPrevoduObrazku $vysledek, string $cesta, bool $jenNazevSouboru = false): PolozkaPrevoduObrazku
    {
        $hledana = $jenNazevSouboru ? $this->slozka() . '/' . $cesta : $cesta;
        foreach ($vysledek->polozky as $polozka) {
            if ($polozka->cesta === $hledana) {
                return $polozka;
            }
        }
        self::fail("Výsledek nemá obrázek {$hledana}, má " . implode(', ', array_map(static fn ($polozka): string => $polozka->cesta, $vysledek->polozky)));
    }

    /**
     * @return string cesta vůči složce fotek
     */
    private function cesta(string $pripona): string
    {
        return $this->slozka() . '/' . self::ID_FOTKY . $pripona;
    }

    private function slozka(): string
    {
        return 'test-prevod-fotek-' . getmypid();
    }

    private function absolutne(string $soubor = ''): string
    {
        return ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . $this->slozka() . ($soubor === '' ? '' : '/' . $soubor);
    }

    private function ulozFotku(string $nazev, int $sirka = 1200, int $vyska = 800): void
    {
        $this->ulozObrazek($nazev, self::fotkaZFotakuBajty($sirka, $vyska));
    }

    private function ulozObrazek(string $nazev, string $bajty): void
    {
        file_put_contents($this->absolutne($nazev), $bajty);
    }

    /**
     * AVIF, jaký by vyrobil převod z téhle fotky.
     */
    private function zapisAvifZ(string $fotka, string $avif): void
    {
        [$sirka, $vyska] = getimagesize($this->absolutne($fotka));
        (new OptimalizaceObrazku())->zapis($this->absolutne($fotka), NahravaniObrazku::JPG, $this->absolutne($avif), NahravaniObrazku::AVIF, $sirka, $vyska);
    }

    /**
     * @return string cesta k AVIF vyrobenému z předaného JPEG, mimo složku testu
     */
    private function avifZ(string $jpeg): string
    {
        $zdroj = sys_get_temp_dir() . '/prevod-fotek-zdroj-' . getmypid() . '.jpg';
        $cil = sys_get_temp_dir() . '/prevod-fotek-cil-' . getmypid() . '.avif';
        $this->kUklidu[] = $zdroj;
        $this->kUklidu[] = $cil;
        file_put_contents($zdroj, $jpeg);
        [$sirka, $vyska] = getimagesize($zdroj);
        (new OptimalizaceObrazku())->zapis($zdroj, NahravaniObrazku::JPG, $cil, NahravaniObrazku::AVIF, $sirka, $vyska);

        return $cil;
    }

    /**
     * @return list<string>
     */
    private function souborySlozky(): array
    {
        return array_values(array_diff(scandir($this->absolutne()), ['.', '..']));
    }

    private function vlozStranku(string $text): void
    {
        dbQuery(
            'INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi) VALUES ($0, $1, $2, 0)',
            [self::ID_STRANKY, 'test-prevod-fotek', $text],
        );
    }

    private function textStranky(): string
    {
        return (string) dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]);
    }
}
