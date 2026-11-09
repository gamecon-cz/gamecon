<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\ImagickAvif;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\OptimalizaceObrazku;
use Gamecon\Web\PolozkaPrevoduObrazku;
use Gamecon\Web\PouzitiObrazku;
use Gamecon\Web\PrevodObrazkuNaAvif;
use Gamecon\Web\StavPrevoduObrazkuEnum;
use Gamecon\Web\VysledekPrevoduObrazku;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

class PrevodObrazkuNaAvifTest extends AbstractTestDb
{
    use TestovaciObrazky;
    use UklidNovychAdresaru;

    protected static bool $disableStrictTransTables = true;

    private const int ID_STRANKY = 9920;
    private const int ID_NOVINKY = 9920;
    private const int ID_AKTIVITY = 9920;
    private const int ID_UZIVATELE = 9920;

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
    public function prevedeFotkuVlozenouDoVsechTextuAPrepiseNaNiOdkazy(): void
    {
        $cesta = $this->ulozFotku('foto.jpg');
        $odkaz = $this->odkaz('foto.jpg');
        $novyOdkaz = $this->odkaz('foto.avif');
        $this->vlozStranku('Stránka ![](' . $odkaz . ') a znovu <img src="https://gamecon.cz/' . $odkaz . '?v=2">');
        $this->vlozNovinku('Úvod ![](' . $odkaz . ')');
        $this->vlozAktivitu('Popis ![](' . $odkaz . ')');
        $this->vlozMedailonek('o sobě ![](' . $odkaz . ')', 'drd ![](' . $odkaz . ')');

        $vysledek = $this->prevod()->proved(true);

        $polozka = $this->polozka($vysledek, $cesta);
        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $polozka->stav);
        self::assertLessThan($polozka->puvodniBajtu * 0.75, $polozka->novychBajtu);
        self::assertSame($polozka->novychBajtu, filesize($this->absolutne('foto.avif')));
        self::assertSame('image/avif', getimagesize($this->absolutne('foto.avif'))['mime']);
        self::assertFileExists($this->absolutne('foto.jpg'), 'originál se při převodu nemaže');
        self::assertSame(['foto.avif', 'foto.jpg'], $this->souborySlozky());

        self::assertSame(
            'Stránka ![](' . $novyOdkaz . ') a znovu <img src="https://gamecon.cz/' . $novyOdkaz . '?v=2">',
            dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]),
        );
        self::assertSame('Úvod ![](' . $novyOdkaz . ')', dbOneCol('SELECT text FROM novinky WHERE id = $0', [self::ID_NOVINKY]));
        self::assertSame('Popis ![](' . $novyOdkaz . ')', dbOneCol('SELECT popis FROM akce_seznam WHERE id_akce = $0', [self::ID_AKTIVITY]));
        self::assertSame(
            [
                'o_sobe' => 'o sobě ![](' . $novyOdkaz . ')',
                'drd'    => 'drd ![](' . $novyOdkaz . ')',
            ],
            dbOneLine('SELECT o_sobe, drd FROM medailonky WHERE id_uzivatele = $0', [self::ID_UZIVATELE]),
        );
        self::assertSame([
            'stranky'     => 1,
            'novinky'     => 1,
            'akce_seznam' => 1,
            'medailonky'  => 1,
        ], $vysledek->prepsanoRadku);
        self::assertSame(0, $vysledek->radkuZmenenychSoubezne);
        self::assertGreaterThan(0, $vysledek->getUsporaBajtu());
    }

    /**
     * @test
     */
    public function pripadnySpecialniZnakyVTextuPrezijiPrepis(): void
    {
        $this->ulozFotku('foto.jpg');
        $text = "Cena \$1, 50 % slev, \\ lomítko, 'apostrof', \"uvozovky\", emoji 🎲, %s a %d\n![](" . $this->odkaz('foto.jpg') . ")\n";
        $this->vlozStranku($text);

        $this->prevod()->proved(true);

        self::assertSame(
            str_replace('foto.jpg', 'foto.avif', $text),
            dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]),
        );
    }

    /**
     * @test
     */
    public function suchyBehNicNezmeniAleRekneCoByUdelal(): void
    {
        $cesta = $this->ulozFotku('foto.jpg');
        $text = 'Stránka ![](' . $this->odkaz('foto.jpg') . ')';
        $this->vlozStranku($text);

        $vysledek = $this->prevod()->proved(false);

        $polozka = $this->polozka($vysledek, $cesta);
        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $polozka->stav);
        self::assertGreaterThan(0, $vysledek->getUsporaBajtu());
        self::assertSame([
            'stranky' => 1,
        ], $vysledek->prepsanoRadku);
        self::assertSame(['foto.jpg'], $this->souborySlozky(), 'ani dočasný soubor nezůstane');
        self::assertSame($text, dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]));
    }

    /**
     * @test
     */
    public function obrazekSOdkazemKeStazeniAObrazekZEmailuZustanou(): void
    {
        $this->ulozFotku('ke-stazeni.jpg');
        $this->ulozFotku('z-emailu.jpg');
        $this->vlozStranku(
            '![](' . $this->odkaz('ke-stazeni.jpg') . ') [stáhni](' . $this->odkaz('ke-stazeni.jpg') . ') ![](' . $this->odkaz('z-emailu.jpg') . ')',
        );
        dbQuery(
            'INSERT INTO emailova_sablona(kod, predmet, text) VALUES ($0, $1, $2)',
            ['test-prevod-avif', 'Předmět', '<img src="https://gamecon.cz/' . $this->odkaz('z-emailu.jpg') . '">'],
        );

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(
            StavPrevoduObrazkuEnum::ODKAZ_KE_STAZENI,
            $this->polozka($vysledek, $this->slozka() . '/ke-stazeni.jpg')->stav,
        );
        self::assertSame(
            StavPrevoduObrazkuEnum::ODKAZ_Z_EMAILU,
            $this->polozka($vysledek, $this->slozka() . '/z-emailu.jpg')->stav,
        );
        self::assertSame(['ke-stazeni.jpg', 'z-emailu.jpg'], $this->souborySlozky());
        self::assertSame([], $vysledek->prepsanoRadku);
    }

    /**
     * @test
     */
    public function malyObrazekAObrazekBezOdkazuZustanou(): void
    {
        $this->ulozObrazek('maly.jpg', self::jpgBajty(16, 16));
        $this->ulozFotku('sirotek.jpg');
        $this->vlozStranku('![](' . $this->odkaz('maly.jpg') . ')');

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::MALA_USPORA, $this->polozka($vysledek, $this->slozka() . '/maly.jpg')->stav);
        self::assertCount(1, $vysledek->polozky);
        self::assertSame(1, $vysledek->nepouzitychObrazku);
        self::assertSame(['maly.jpg', 'sirotek.jpg'], $this->souborySlozky());
    }

    /**
     * @test
     */
    public function odkazNaSouborKteryNeexistujeSeSpocita(): void
    {
        $this->ulozFotku('je.jpg');
        $this->vlozStranku('![](' . $this->odkaz('je.jpg') . ') ![](' . $this->odkaz('neni.jpg') . ') ![](' . $this->odkaz('neni.avif') . ')');

        $vysledek = $this->prevod()->proved(false);

        self::assertSame(2, $vysledek->odkazuNaChybejiciSoubor);
        self::assertCount(1, $vysledek->polozky);
    }

    /**
     * @test
     */
    public function pngSPruhlednostiSePrevedeAPruhlednostZustane(): void
    {
        $png = new \Imagick();
        $png->newPseudoImage(800, 600, 'plasma:fractal');
        $png->setImageFormat('png');
        $png->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
        $png->setImageAlpha(0.6);
        $this->ulozObrazek('logo.png', $png->getImageBlob());
        $this->vlozStranku('<img src="' . $this->odkaz('logo.png') . '">');

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $this->polozka($vysledek, $this->slozka() . '/logo.png')->stav);
        $avif = new \Imagick();
        $avif->readImage('avif:' . $this->absolutne('logo.avif'));
        ImagickAvif::pripravPoNacteni($avif, $this->absolutne('logo.avif'));
        self::assertEqualsWithDelta(0.6, $avif->getImagePixelColor(400, 300)->getColorValue(\Imagick::COLOR_ALPHA), 0.1);
        self::assertSame(
            '<img src="' . $this->odkaz('logo.avif') . '">',
            dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]),
        );
    }

    /**
     * @test
     */
    public function ciziAvifStejnehoNazvuSeNeprepisuje(): void
    {
        $this->ulozFotku('foto.jpg');
        // jiný obrázek než foto.jpg: každá plazma je jiná
        $this->ulozFotku('jiny.jpg');
        $this->zapisAvifZ('jiny.jpg', 'foto.avif');
        $puvodniAvif = md5_file($this->absolutne('foto.avif'));
        $text = '![](' . $this->odkaz('foto.jpg') . ')';
        $this->vlozStranku($text);

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::JINY_AVIF_UZ_EXISTUJE, $this->polozka($vysledek, $this->slozka() . '/foto.jpg')->stav);
        self::assertSame($puvodniAvif, md5_file($this->absolutne('foto.avif')));
        self::assertSame($text, dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]));
    }

    /**
     * @test
     */
    public function pokracujeUPrerusenehoBehu(): void
    {
        $this->ulozFotku('foto.jpg');
        // běh skončil po zápisu AVIF, ještě než přepsal texty
        $this->zapisAvifZ('foto.jpg', 'foto.avif');
        $avifPred = md5_file($this->absolutne('foto.avif'));
        $this->vlozStranku('![](' . $this->odkaz('foto.jpg') . ')');

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::UZ_PREVEDENO, $this->polozka($vysledek, $this->slozka() . '/foto.jpg')->stav);
        self::assertSame($avifPred, md5_file($this->absolutne('foto.avif')), 'hotový AVIF se nepřepisuje');
        self::assertSame(
            '![](' . $this->odkaz('foto.avif') . ')',
            dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]),
        );
    }

    /**
     * @test
     */
    public function doplniAvifKOdkazuZDatabazeZIneProstredi(): void
    {
        // databáze zkopírovaná z ostré už na AVIF odkazuje, soubory na betě jsou ale pořád JPG
        $this->ulozFotku('foto.jpg');
        $text = '![](' . $this->odkaz('foto.avif') . ')';
        $this->vlozStranku($text);

        $vysledek = $this->prevod()->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::PREVEDENO, $this->polozka($vysledek, $this->slozka() . '/foto.jpg')->stav);
        self::assertSame('image/avif', getimagesize($this->absolutne('foto.avif'))['mime']);
        self::assertSame([], $vysledek->prepsanoRadku);
        self::assertSame($text, dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]));
        self::assertSame(0, $vysledek->odkazuNaChybejiciSoubor);
    }

    /**
     * @test
     */
    public function limitOmeziPocetNoveVyrobenychAvif(): void
    {
        foreach (['a', 'b', 'c'] as $nazev) {
            $this->ulozFotku($nazev . '.jpg');
        }
        $this->vlozStranku('![](' . $this->odkaz('a.jpg') . ') ![](' . $this->odkaz('b.jpg') . ') ![](' . $this->odkaz('c.jpg') . ')');

        $vysledek = $this->prevod()->proved(true, 2);

        self::assertSame(['a.avif', 'a.jpg', 'b.avif', 'b.jpg', 'c.jpg'], $this->souborySlozky());
        self::assertSame(
            '![](' . $this->odkaz('a.avif') . ') ![](' . $this->odkaz('b.avif') . ') ![](' . $this->odkaz('c.jpg') . ')',
            dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]),
        );
        self::assertSame([
            'stranky' => 1,
        ], $vysledek->prepsanoRadku);
    }

    /**
     * @test
     */
    public function novyAvifMaPravaOriginalu(): void
    {
        $this->ulozFotku('foto.jpg');
        chmod($this->absolutne('foto.jpg'), 0640);
        $this->vlozStranku('![](' . $this->odkaz('foto.jpg') . ')');

        $this->prevod()->proved(true);

        self::assertSame(0640, fileperms($this->absolutne('foto.avif')) & 0777);
    }

    /**
     * @test
     */
    public function prevodOtociFotkuPodleExifuAZahodiPolohu(): void
    {
        // pixely vodorovně, EXIF říká „otočit o 90° po směru hodin“ a nese GPS
        $this->ulozObrazek('telefon.jpg', self::jpgSOrientaci(6, 600, 400, true));
        $this->zapisAvifZ('telefon.jpg', 'telefon.avif');
        $jeStejny = new \ReflectionMethod(PrevodObrazkuNaAvif::class, 'isStejnyObrazek');

        self::assertSame([400, 600], array_slice(getimagesize($this->absolutne('telefon.avif')), 0, 2));
        self::assertTrue($jeStejny->invoke($this->prevod(), $this->absolutne('telefon.jpg'), $this->absolutne('telefon.avif')));
        self::assertSame('N', (new \Imagick($this->absolutne('telefon.jpg')))->getImageProperty('exif:GPSLatitudeRef'), 'originál polohu nese');
        self::assertFalse((new \Imagick($this->absolutne('telefon.avif')))->getImageProperty('exif:GPSLatitudeRef'));
    }

    /**
     * @test
     */
    public function smazaniPuvodnihoRespektujeOdkazVNeznamemTextovemSloupci(): void
    {
        $this->ulozFotku('vybaveni.jpg');
        $this->zapisAvifZ('vybaveni.jpg', 'vybaveni.avif');
        $this->ulozFotku('volny.jpg');
        $this->zapisAvifZ('volny.jpg', 'volny.avif');
        // vybavení aktivity převod nepřepisuje, ale odkaz v něm by se po smazání rozbil
        $this->vlozAktivitu('Bez obrázku');
        dbQuery('UPDATE akce_seznam SET vybaveni = $0 WHERE id_akce = $1', ['Přines ![](' . $this->odkaz('vybaveni.jpg') . ')', self::ID_AKTIVITY]);

        $vysledek = $this->prevod()->smazPuvodni(true);

        $polozka = $this->polozka($vysledek, $this->slozka() . '/vybaveni.jpg');
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $polozka->stav);
        self::assertSame('sloupec akce_seznam.vybaveni', $polozka->podrobnosti);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_SMAZAN, $this->polozka($vysledek, $this->slozka() . '/volny.jpg')->stav);
        self::assertFileExists($this->absolutne('vybaveni.jpg'));
        self::assertFileDoesNotExist($this->absolutne('volny.jpg'));
    }

    /**
     * @test
     */
    public function obrazkyGalerieEshopuSeNeprevedou(): void
    {
        $eshop = ZpracovaneObrazky::adresarObsahu() . '/materialy/9999/eshop';
        $this->uklidNoveAdresare($eshop);
        (new Filesystem())->mkdir($eshop);
        file_put_contents($eshop . '/foto.jpg', self::fotkaZFotakuBajty(1200, 800));
        $this->vlozStranku('![](soubory/obsah/materialy/9999/eshop/foto.jpg)');

        $vysledek = (new PrevodObrazkuNaAvif(new OptimalizaceObrazku(), new PouzitiObrazku(), ZpracovaneObrazky::adresarObsahu(), 'materialy/9999'))
            ->proved(true);

        self::assertSame(StavPrevoduObrazkuEnum::MIMO_ROZSAH, $this->polozka($vysledek, 'materialy/9999/eshop/foto.jpg')->stav);
        self::assertFileDoesNotExist($eshop . '/foto.avif');
    }

    /**
     * @test
     */
    public function smazePuvodniJenKdyzNaNehoNicNeodkazujeAMaStejnyAvif(): void
    {
        $this->ulozFotku('hotovy.jpg');
        $this->zapisAvifZ('hotovy.jpg', 'hotovy.avif');
        $this->ulozFotku('pouzivany.jpg');
        $this->zapisAvifZ('pouzivany.jpg', 'pouzivany.avif');
        $this->ulozFotku('cizi.jpg');
        $this->ulozFotku('jiny.jpg');
        $this->zapisAvifZ('jiny.jpg', 'cizi.avif');
        $this->ulozFotku('bez-avif.jpg');
        $this->vlozStranku('![](' . $this->odkaz('hotovy.avif') . ') ![](' . $this->odkaz('pouzivany.jpg') . ')');

        $suchy = $this->prevod()->smazPuvodni(false);
        self::assertSame(['bez-avif.jpg', 'cizi.avif', 'cizi.jpg', 'hotovy.avif', 'hotovy.jpg', 'jiny.jpg', 'pouzivany.avif', 'pouzivany.jpg'], $this->souborySlozky());

        $vysledek = $this->prevod()->smazPuvodni(true);

        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_SMAZAN, $this->polozka($suchy, $this->slozka() . '/hotovy.jpg')->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_SMAZAN, $this->polozka($vysledek, $this->slozka() . '/hotovy.jpg')->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $this->polozka($vysledek, $this->slozka() . '/pouzivany.jpg')->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_BEZ_STEJNEHO_AVIF, $this->polozka($vysledek, $this->slozka() . '/cizi.jpg')->stav);
        self::assertCount(3, $vysledek->polozky, 'originál bez AVIF vedle sebe není kandidát');
        self::assertSame(['bez-avif.jpg', 'cizi.avif', 'cizi.jpg', 'hotovy.avif', 'jiny.jpg', 'pouzivany.avif', 'pouzivany.jpg'], $this->souborySlozky());
    }

    /**
     * @test
     */
    public function neprepiseRadekKteryMezitimNekdoZmenil(): void
    {
        $this->vlozStranku('text, jak ho načetl převod');
        $zapis = new \ReflectionMethod(PrevodObrazkuNaAvif::class, 'zapisZmenuRadku');
        $prevod = $this->prevod();

        $zapsano = $zapis->invoke($prevod, 'stranky', 'id_stranky', (string) self::ID_STRANKY, [
            'obsah' => [
                'puvodni' => 'text, jak ho načetl převod, a nová věta',
                'novy'    => 'přepis',
            ],
        ]);
        self::assertFalse($zapsano);
        self::assertSame('text, jak ho načetl převod', dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]));

        // kolace tabulky bere velká písmena za shodu, změna velikosti písmen je ale změna textu
        $zapsano = $zapis->invoke($prevod, 'stranky', 'id_stranky', (string) self::ID_STRANKY, [
            'obsah' => [
                'puvodni' => 'TEXT, JAK HO NAČETL PŘEVOD',
                'novy'    => 'přepis',
            ],
        ]);
        self::assertFalse($zapsano);

        $zapsano = $zapis->invoke($prevod, 'stranky', 'id_stranky', (string) self::ID_STRANKY, [
            'obsah' => [
                'puvodni' => 'text, jak ho načetl převod',
                'novy'    => 'přepis',
            ],
        ]);
        self::assertTrue($zapsano);
        self::assertSame('přepis', dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]));
    }

    /**
     * @test
     */
    public function poznaAvifKterySeOdOriginaluLisi(): void
    {
        $this->ulozFotku('foto.jpg');
        $this->ulozFotku('jina.jpg');
        $this->zapisAvifZ('foto.jpg', 'stejny.avif');
        $this->zapisAvifZ('jina.jpg', 'jiny.avif');
        // JPEG s jiným poměrem stran než originál
        $this->ulozFotku('uzka.jpg', 600, 800);
        $this->zapisAvifZ('uzka.jpg', 'uzky.avif');
        // průhledný PNG, jehož AVIF o průhlednost přišel
        $png = new \Imagick();
        $png->newPseudoImage(400, 300, 'plasma:fractal');
        $png->setImageFormat('png');
        $png->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
        $png->setImageAlpha(0.3);
        $this->ulozObrazek('pruhledne.png', $png->getImageBlob());
        $bezPruhlednosti = new \Imagick();
        $bezPruhlednosti->readImageBlob($png->getImageBlob());
        $bezPruhlednosti->setImageBackgroundColor(new \ImagickPixel('black'));
        $bezPruhlednosti->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        ImagickAvif::nastavZapis($bezPruhlednosti, 80);
        $this->ulozObrazek('pruhledne-bez-pruhlednosti.avif', $bezPruhlednosti->getImageBlob());

        $jeStejny = new \ReflectionMethod(PrevodObrazkuNaAvif::class, 'isStejnyObrazek');
        $prevod = $this->prevod();

        self::assertTrue($jeStejny->invoke($prevod, $this->absolutne('foto.jpg'), $this->absolutne('stejny.avif')));
        self::assertFalse($jeStejny->invoke($prevod, $this->absolutne('foto.jpg'), $this->absolutne('jiny.avif')));
        self::assertFalse($jeStejny->invoke($prevod, $this->absolutne('foto.jpg'), $this->absolutne('uzky.avif')));
        self::assertFalse($jeStejny->invoke($prevod, $this->absolutne('pruhledne.png'), $this->absolutne('pruhledne-bez-pruhlednosti.avif')));
        self::assertFalse($jeStejny->invoke($prevod, $this->absolutne('foto.jpg'), $this->absolutne('neexistuje.avif')));
    }

    /**
     * @test
     */
    public function zmensenyAvifSeBereZaStejnyObrazek(): void
    {
        // převod zmenší obrázek na OBRAZEK_MAX_ROZMER_PX, porovnává se proto po zmenšení originálu
        $this->ulozFotku('velka.jpg', 2400, 1600);
        $this->zapisAvifZ('velka.jpg', 'velka.avif');
        $jeStejny = new \ReflectionMethod(PrevodObrazkuNaAvif::class, 'isStejnyObrazek');

        self::assertSame([1920, 1280], array_slice(getimagesize($this->absolutne('velka.avif')), 0, 2));
        self::assertTrue($jeStejny->invoke($this->prevod(), $this->absolutne('velka.jpg'), $this->absolutne('velka.avif')));
    }

    /**
     * @test
     */
    public function dvaZdrojeSJednimCilemHlasiSuchyBehStejneJakoOstry(): void
    {
        $this->ulozFotku('foto.jpg');
        $this->ulozFotku('foto.png');
        // druhý soubor je ve skutečnosti PNG
        $png = new \Imagick();
        $png->newPseudoImage(1200, 800, 'plasma:fractal');
        $png->setImageFormat('png');
        $this->ulozObrazek('foto.png', $png->getImageBlob());
        $this->vlozStranku('![](' . $this->odkaz('foto.jpg') . ') ![](' . $this->odkaz('foto.png') . ')');

        $suchy = $this->prevod()->proved(false);
        $ostry = $this->prevod()->proved(true);

        $stavy = static fn (VysledekPrevoduObrazku $vysledek): array => array_map(
            static fn (PolozkaPrevoduObrazku $polozka): string => $polozka->cesta . ' ' . $polozka->stav->value,
            $vysledek->polozky,
        );
        self::assertSame($stavy($ostry), $stavy($suchy));
        self::assertSame(StavPrevoduObrazkuEnum::JINY_AVIF_UZ_EXISTUJE, $this->polozka($ostry, $this->slozka() . '/foto.png')->stav);
        self::assertSame(
            '![](' . $this->odkaz('foto.avif') . ') ![](' . $this->odkaz('foto.png') . ')',
            dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = $0', [self::ID_STRANKY]),
        );
    }

    /**
     * @test
     */
    public function nepiseZaSymlinkemMimoAdresarObsahu(): void
    {
        $venku = sys_get_temp_dir() . '/gamecon-prevod-avif-venku-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($venku);
        file_put_contents($venku . '/foto.jpg', self::fotkaZFotakuBajty(1200, 800));
        symlink($venku, $this->absolutne('odkaz'));
        symlink($venku . '/foto.jpg', $this->absolutne('soubor.jpg'));
        $this->vlozStranku('![](' . $this->odkaz('odkaz/foto.jpg') . ') ![](' . $this->odkaz('soubor.jpg') . ')');

        try {
            $vysledek = $this->prevod()->proved(true);

            self::assertSame(StavPrevoduObrazkuEnum::NELZE_ZPRACOVAT, $this->polozka($vysledek, $this->slozka() . '/odkaz/foto.jpg')->stav);
            self::assertSame(StavPrevoduObrazkuEnum::NELZE_ZPRACOVAT, $this->polozka($vysledek, $this->slozka() . '/soubor.jpg')->stav);
            self::assertSame(['foto.jpg'], array_values(array_diff(scandir($venku), ['.', '..'])));
            self::assertSame([], $vysledek->prepsanoRadku);
        } finally {
            (new Filesystem())->remove($venku);
        }
    }

    /**
     * @test
     */
    public function ulozeniNeprepiseSouborKterySeObjevilPoKontrole(): void
    {
        $this->ulozObrazek('docasny', 'nový AVIF');
        $this->ulozObrazek('cil.avif', 'obrázek, který mezitím nahrál správce');
        $uloz = new \ReflectionMethod(PrevodObrazkuNaAvif::class, 'ulozBezPrepsani');

        self::assertFalse($uloz->invoke($this->prevod(), $this->absolutne('docasny'), $this->absolutne('cil.avif')));
        self::assertSame('obrázek, který mezitím nahrál správce', file_get_contents($this->absolutne('cil.avif')));

        // symlink, který nikam nevede, existující soubor není, ale přepsat ho taky nejde
        symlink($this->absolutne('nikam-nevede'), $this->absolutne('rozbity.avif'));
        self::assertFalse($uloz->invoke($this->prevod(), $this->absolutne('docasny'), $this->absolutne('rozbity.avif')));
        self::assertTrue(is_link($this->absolutne('rozbity.avif')));

        self::assertTrue($uloz->invoke($this->prevod(), $this->absolutne('docasny'), $this->absolutne('novy.avif')));
        self::assertSame('nový AVIF', file_get_contents($this->absolutne('novy.avif')));
    }

    /**
     * @test
     */
    public function smazaniPuvodnihoNechaSouborKdyzNaNejOdkazujeJenPodretezecTextu(): void
    {
        foreach (['mail.jpg', 'vybaveni.jpg', 'máj.jpg', 'můj obrázek.jpg', 'č (2).jpg', 'volny.jpg'] as $soubor) {
            $this->ulozFotku($soubor);
            $this->zapisAvifZ($soubor, str_replace('.jpg', '.avif', $soubor));
        }
        // zápisy, které rozbor odkazu nepozná: tečka za větou, název s mezerou a závorkou, zakódovaná diakritika, smíšené kódování
        dbQuery(
            'INSERT INTO emailova_sablona(kod, predmet, text) VALUES ($0, $1, $2)',
            ['test-prevod-avif', 'Předmět', "Podívej se na https://gamecon.cz/{$this->odkaz('mail.jpg')}. Díky, i na {$this->odkaz('můj%20obrázek.jpg')}"],
        );
        $this->vlozAktivitu('Bez obrázku');
        dbQuery('UPDATE akce_seznam SET vybaveni = $0 WHERE id_akce = $1', ["Přines <img src='{$this->odkaz('vybaveni.jpg')}.' alt='x (1)'>", self::ID_AKTIVITY]);
        // stránka je známý zdroj textů, ale tenhle zápis (diakritika a mezera zakódované, závorky ne) zná jen kontrola všech sloupců
        $this->vlozStranku('<a href="' . $this->odkaz('m%C3%A1j.jpg') . '">odkaz</a> <a href="' . $this->odkaz('%C4%8D%20(2).jpg') . '">druhý</a>');

        $vysledek = $this->prevod()->smazPuvodni(true);

        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $this->polozka($vysledek, $this->slozka() . '/mail.jpg')->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $this->polozka($vysledek, $this->slozka() . '/vybaveni.jpg')->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $this->polozka($vysledek, $this->slozka() . '/máj.jpg')->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $this->polozka($vysledek, $this->slozka() . '/můj obrázek.jpg')->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $this->polozka($vysledek, $this->slozka() . '/č (2).jpg')->stav);
        self::assertSame(StavPrevoduObrazkuEnum::PUVODNI_SMAZAN, $this->polozka($vysledek, $this->slozka() . '/volny.jpg')->stav);
        self::assertSame(
            ['mail.avif', 'mail.jpg', 'máj.avif', 'máj.jpg', 'můj obrázek.avif', 'můj obrázek.jpg', 'volny.avif', 'vybaveni.avif', 'vybaveni.jpg', 'č (2).avif', 'č (2).jpg'],
            $this->souborySlozky(),
        );
    }

    /**
     * @test
     */
    public function mazaniSeOmeziNaAdresarObsahuAVyzadujeSkutecnySoubor(): void
    {
        $venku = sys_get_temp_dir() . '/gamecon-prevod-avif-venku-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($venku);
        file_put_contents($venku . '/cizi.jpg', self::fotkaZFotakuBajty(1200, 800));
        (new OptimalizaceObrazku())->zapis($venku . '/cizi.jpg', NahravaniObrazku::JPG, $venku . '/cizi.avif', NahravaniObrazku::AVIF, 1200, 800);
        symlink($venku, $this->absolutne('odkaz'));
        // originál, jehož „AVIF“ je symlink: zmizí s cílem, a pak by po smazaném originálu nezbylo nic
        $this->ulozFotku('hotovy.jpg');
        $this->zapisAvifZ('hotovy.jpg', 'skutecny.avif');
        symlink($this->absolutne('skutecny.avif'), $this->absolutne('hotovy.avif'));

        try {
            $zaSymlinkem = (new PrevodObrazkuNaAvif(new OptimalizaceObrazku(), new PouzitiObrazku(), ZpracovaneObrazky::adresarObsahu(), $this->slozka() . '/odkaz'))
                ->smazPuvodni(true);
            $vysledek = $this->prevod()->smazPuvodni(true);

            self::assertSame([], $zaSymlinkem->polozky);
            self::assertSame(['cizi.avif', 'cizi.jpg'], array_values(array_diff(scandir($venku), ['.', '..'])));
            self::assertSame([], $vysledek->polozky);
            self::assertFileExists($this->absolutne('hotovy.jpg'));
        } finally {
            (new Filesystem())->remove($venku);
        }
    }

    /**
     * @test
     */
    public function najdeOdkazyVEmailuVeVsechZapisechAPriVicDavkach(): void
    {
        // pět souborů s mezerou a diakritikou v názvu, každý je v textu zapsaný jinak, rozházené mezi 70 dalších cest
        $zapisy = [
            'p/ž1 ž.jpg'   => 'p/ž1 ž.jpg',
            'p/ž2 ž.jpg'   => 'p/%C5%BE2%20%C5%BE.jpg',
            'p/ž3 ž.jpg'   => 'p/ž3%20ž.jpg',
            'p/ž4 ž.jpg'   => 'p/%C5%BE4 %C5%BE.jpg',
            'p/ž5 (ž).jpg' => 'p/%C5%BE5%20(%C5%BE).jpg',
        ];
        $cesty = [];
        foreach (array_keys($zapisy) as $poradi => $cesta) {
            for ($vypln = 0; $vypln < 14; ++$vypln) {
                $cesty[] = sprintf('p/vypln-%d-%d.jpg', $poradi, $vypln);
            }
            $cesty[] = $cesta;
        }
        $text = implode(' ', array_map(static fn (string $zapis): string => '<img src="https://gamecon.cz/soubory/obsah/' . $zapis . '">', $zapisy));
        dbQuery('INSERT INTO emailova_sablona(kod, predmet, text) VALUES ($0, $1, $2)', ['test-prevod-avif-davky', 'Předmět', $text]);
        $prevod = $this->prevod();
        $sloupce = new \ReflectionMethod(PrevodObrazkuNaAvif::class, 'getSloupceKeKontrole');
        $najdi = new \ReflectionMethod(PrevodObrazkuNaAvif::class, 'najdiOdkazyVSloupcich');

        $nalezene = $najdi->invoke($prevod, $cesty, $sloupce->invoke($prevod, false));

        self::assertGreaterThan(30, count($cesty), 'víc než jedna dávka');
        self::assertSame(array_keys($zapisy), array_keys($nalezene));
        self::assertSame(['emailova_sablona.text'], array_values(array_unique($nalezene)));
    }

    /**
     * @test
     */
    public function sloupecSOdkazemVeZnakoveSaditeNeboKolaciBezDiakritikyHlasiChybuANemaze(): void
    {
        $this->vlozStranku('![](' . $this->odkaz('foto.jpg') . ')');
        $najdi = new \ReflectionMethod(PrevodObrazkuNaAvif::class, 'najdiOdkazyVSloupcich');
        $prevod = $this->prevod();
        $sloupec = static fn (string $tabulka, string $nazev, ?string $sada, ?string $kolace): array => [
            'tabulka'      => $tabulka,
            'sloupec'      => $nazev,
            'znakova_sada' => $sada,
            'kolace'       => $kolace,
        ];

        // sloupec, který na soubory neodkazuje vůbec, nevadí, ať má jakoukoli sadu
        self::assertSame([], $najdi->invoke($prevod, [$this->slozka() . '/foto.jpg'], [$sloupec('novinky', 'text', 'latin1', 'latin1_bin')]));
        self::assertSame(
            [
                $this->slozka() . '/foto.jpg' => 'stranky.obsah',
            ],
            $najdi->invoke($prevod, [$this->slozka() . '/foto.jpg'], [$sloupec('stranky', 'obsah', 'utf8mb4', 'utf8mb4_czech_ci')]),
        );

        foreach ([['latin1', 'latin1_swedish_ci'], ['utf8mb4', 'utf8mb4_bin']] as [$sada, $kolace]) {
            try {
                $najdi->invoke($prevod, [$this->slozka() . '/foto.jpg'], [$sloupec('stranky', 'obsah', $sada, $kolace)]);
                self::fail("Sloupec se sadou {$sada} a kolací {$kolace} měl hlásit chybu");
            } catch (\RuntimeException $runtimeException) {
                self::assertStringContainsString('nic se nemaže', $runtimeException->getMessage());
            }
        }
    }

    private function prevod(): PrevodObrazkuNaAvif
    {
        return new PrevodObrazkuNaAvif(new OptimalizaceObrazku(), new PouzitiObrazku(), ZpracovaneObrazky::adresarObsahu(), $this->slozka());
    }

    private function polozka(VysledekPrevoduObrazku $vysledek, string $cesta): PolozkaPrevoduObrazku
    {
        foreach ($vysledek->polozky as $polozka) {
            if ($polozka->cesta === $cesta) {
                return $polozka;
            }
        }
        self::fail("Výsledek nemá obrázek {$cesta}, má " . implode(', ', array_map(static fn ($polozka): string => $polozka->cesta, $vysledek->polozky)));
    }

    private function slozka(): string
    {
        return 'test-prevod-avif-' . getmypid();
    }

    private function absolutne(string $soubor = ''): string
    {
        return ZpracovaneObrazky::adresarObsahu() . '/' . $this->slozka() . ($soubor === '' ? '' : '/' . $soubor);
    }

    private function odkaz(string $soubor): string
    {
        return 'soubory/obsah/' . $this->slozka() . '/' . $soubor;
    }

    /**
     * @return string cesta vůči adresáři obsahu
     */
    private function ulozFotku(string $nazev, int $sirka = 1200, int $vyska = 800): string
    {
        $this->ulozObrazek($nazev, self::fotkaZFotakuBajty($sirka, $vyska));

        return $this->slozka() . '/' . $nazev;
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
            [self::ID_STRANKY, 'test-prevod-avif', $text],
        );
    }

    private function vlozNovinku(string $text): void
    {
        dbQuery(
            'INSERT INTO novinky(id, typ, vydat, url, nazev, autor, text) VALUES ($0, 2, NOW(), $1, $2, $3, $4)',
            [self::ID_NOVINKY, 'test-prevod-avif', 'Testovací blog', 'Autor', $text],
        );
    }

    private function vlozAktivitu(string $popis): void
    {
        dbQuery(
            'INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES ($0, $1, $2, 1, $3, 2, $4, 0, 5, 0, 0)',
            [self::ID_AKTIVITY, 'Testovací aktivita', 'testovaci-aktivita-prevod-avif', ROCNIK, $popis],
        );
    }

    private function vlozMedailonek(string $oSobe, string $drd): void
    {
        dbQuery(
            'INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES ($0, $1, $2, $3, $4)',
            [self::ID_UZIVATELE, 'test-prevod-avif', 'Test', 'Vypravěč', 'test-prevod-avif@example.invalid'],
        );
        dbQuery('INSERT INTO medailonky(id_uzivatele, o_sobe, drd) VALUES ($0, $1, $2)', [self::ID_UZIVATELE, $oSobe, $drd]);
    }
}
