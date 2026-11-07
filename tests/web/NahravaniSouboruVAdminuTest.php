<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Tests\Model\Web\TestovaciDokumenty;
use Gamecon\Tests\Model\Web\TestovaciObrazky;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Nahrávání PDF, zvuku a archivů přes skutečnou administraci (Apache, přihlášení, multipart formulář),
 * tak jak to dělá organizátor místo FTP.
 */
class NahravaniSouboruVAdminuTest extends AbstractTestWeb
{
    use TestovaciDokumenty;
    use TestovaciObrazky;
    use ZadostiDoAdmina;

    private const int ID_BEZ_PRAV = 9811;
    private const int ID_STRANKY = 9811;

    protected static bool $disableStrictTransTables = true;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];
    private string $zdroje;

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES (%d, 'test-soubory-bez-prav', 'Test', 'Bezprav', 'test-soubory-bez-prav@example.invalid')
SQL,
                self::ID_BEZ_PRAV,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi) VALUES (%d, 'test-soubory-v-adminu', '[Stáhnout](soubory/obsah/celohra/%s)', 0)
SQL,
                self::ID_STRANKY,
                self::nazevSouboruSOdkazem(),
            ),
        ];
    }

    private static function znacka(): string
    {
        return 'test-soubory-' . getmypid();
    }

    private static function nazevSouboruSOdkazem(): string
    {
        return self::znacka() . '-odkaz.pdf';
    }

    protected function setUp(): void
    {
        parent::setUp();
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_ADMIN);
        $this->zdroje = sys_get_temp_dir() . '/gamecon-soubory-v-adminu-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->zdroje);
        $this->kUklidu[] = $this->zdroje;
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function strankaUkazeVsechnySpravovaneSlozky(): void
    {
        $stranka = $this->stahni($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'web/soubory');

        foreach (['Mistrovství v Dračím doupěti', 'Ke stažení', 'Materiály a formuláře', 'Celohra'] as $nazev) {
            self::assertStringContainsString($nazev, $stranka);
        }
        self::assertStringContainsString('soubory/obsah/drd/', $stranka);
    }

    /**
     * @test
     */
    public function mistrovstviVDrdJeNaKonciStrankyZaCarou(): void
    {
        $stranka = $this->stahni($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'web/soubory');

        $celohra = strpos($stranka, '<code>soubory/obsah/celohra/</code>');
        $drd = strpos($stranka, '<code>soubory/obsah/drd/</code>');
        self::assertNotFalse($celohra);
        self::assertNotFalse($drd);
        self::assertGreaterThan($celohra, $drd);
        self::assertStringContainsString('<hr', substr($stranka, $celohra, $drd - $celohra));
    }

    /**
     * @test
     */
    public function organizatorNahrajeNahradiASmazeSouborVPodslozce(): void
    {
        $celohra = ZpracovaneObrazky::adresarObsahu() . '/celohra';
        $this->uklidNoveAdresare($celohra);
        $this->kUklidu[] = $celohra . '/rocnik_2099';
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $ulozeno = $celohra . '/rocnik_2099/souhlas_rodicu_' . self::znacka() . '.pdf';

        $pdf = $this->soubor('.pdf', self::pdfBajty());
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'celohra',
            'podslozka'       => 'Ročník 2099',
            'nazev'           => 'Souhlas Rodičů ' . self::znacka(),
            'souborWebu'      => new \CURLFile($pdf, 'application/pdf', 'cokoli.mp3'),
        ]));

        self::assertFileEquals($pdf, $ulozeno);
        $stranka = $this->stahni($session, 'web/soubory');
        // zápis do textu je u souboru v tabulce, hláška po nahrání je jen potvrzení
        self::assertStringContainsString('Soubor nahrán.', $stranka);
        self::assertStringNotContainsString('do textu ho vložíš', $stranka);
        self::assertStringContainsString('rocnik_2099/souhlas_rodicu_' . self::znacka() . '.pdf', $stranka);

        $novy = $this->soubor('.pdf', self::pdfBajty() . "% druhá verze\n");
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahradit',
            'slozka'          => 'celohra',
            'cesta'           => 'rocnik_2099/souhlas_rodicu_' . self::znacka() . '.pdf',
            'souborWebu'      => new \CURLFile($novy, 'application/pdf', 'novy.pdf'),
        ]));
        self::assertFileEquals($novy, $ulozeno);
        self::assertStringContainsString(
            'souhlas_rodicu_' . self::znacka() . '.pdf nahrán. Kdo ho už viděl',
            $this->stahni($session, 'web/soubory'),
        );

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'smazat',
            'slozka'          => 'celohra',
            'cesta'           => 'rocnik_2099/souhlas_rodicu_' . self::znacka() . '.pdf',
        ]));
        self::assertFileDoesNotExist($ulozeno);
        self::assertStringContainsString('Soubor je smazaný.', $this->stahni($session, 'web/soubory'));
    }

    /**
     * @test
     */
    public function velkyObrazekSeZmensiAUlozJakoAvif(): void
    {
        $materialy = ZpracovaneObrazky::adresarObsahu() . '/materialy';
        $this->uklidNoveAdresare($materialy);
        $this->kUklidu[] = $materialy . '/rocnik_2099';
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'materialy',
            'podslozka'       => 'Ročník 2099',
            'nazev'           => 'Plakát ' . self::znacka(),
            'souborWebu'      => new \CURLFile($this->soubor('.jpg', self::jpgBajty(3000, 2000)), 'image/jpeg', 'IMG_0001.JPG'),
        ]));

        $ulozeno = $materialy . '/rocnik_2099/plakat_' . self::znacka() . '.avif';
        self::assertFileExists($ulozeno);
        $info = getimagesize($ulozeno);
        self::assertSame([1920, 1280, 'image/avif'], [$info[0], $info[1], $info['mime']]);
        $stranka = $this->stahni($session, 'web/soubory');
        self::assertStringContainsString('Soubor nahrán.', $stranka);
        self::assertStringContainsString('plakat_' . self::znacka() . '.avif', $stranka, 'Výsledný název je v tabulce souborů');
        // soubor, který vznikl převodem, jde nahradit i JPEG, PNG a GIF, prohlížeč je v oknu pro výběr nezakáže
        self::assertStringContainsString('accept="image/avif,image/jpeg,image/png,image/webp,image/gif"', $stranka);

        $pred = md5_file($ulozeno);
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahradit',
            'slozka'          => 'materialy',
            'cesta'           => 'rocnik_2099/plakat_' . self::znacka() . '.avif',
            'souborWebu'      => new \CURLFile($this->soubor('.jpg', self::jpgBajty(2400, 1600)), 'image/jpeg', 'IMG_0004.JPG'),
        ]));
        self::assertNotSame($pred, md5_file($ulozeno));
        $info = getimagesize($ulozeno);
        self::assertSame([1920, 1280, 'image/avif'], [$info[0], $info[1], $info['mime']]);
        self::assertSame([], glob($materialy . '/rocnik_2099/plakat_' . self::znacka() . '.jpg'));

        // PNG se do stávajícího AVIF převede také, název ani odkazy se nemění
        $predPng = md5_file($ulozeno);
        $png = $this->soubor('.png', '');
        imagepng(imagecreatetruecolor(300, 200), $png);
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahradit',
            'slozka'          => 'materialy',
            'cesta'           => 'rocnik_2099/plakat_' . self::znacka() . '.avif',
            'souborWebu'      => new \CURLFile($png, 'image/png', 'plakat.png'),
        ]));
        self::assertNotSame($predPng, md5_file($ulozeno));
        $info = getimagesize($ulozeno);
        self::assertSame([300, 200, 'image/avif'], [$info[0], $info[1], $info['mime']]);
        self::assertSame([], glob($materialy . '/rocnik_2099/plakat_' . self::znacka() . '.png'));
    }

    /**
     * Starý JPEG z doby FTP jde nahradit libovolným obrázkem, zůstane JPEG pod stejným názvem a odkazy na něj fungují dál.
     *
     * @test
     */
    public function starsiJpegSeNahradiPngAZustaneJpeg(): void
    {
        $materialy = ZpracovaneObrazky::adresarObsahu() . '/materialy';
        $this->uklidNoveAdresare($materialy);
        $this->kUklidu[] = $materialy . '/rocnik_2099';
        $starsi = $materialy . '/rocnik_2099/stary_' . self::znacka() . '.jpg';
        (new Filesystem())->mkdir(dirname($starsi));
        file_put_contents($starsi, self::jpgBajty(100, 100));
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $stranka = $this->stahni($session, 'web/soubory');
        self::assertStringContainsString('stary_' . self::znacka() . '.jpg', $stranka);
        self::assertStringContainsString('accept="image/jpeg,image/png,image/webp,image/avif,image/gif"', $stranka);

        $png = $this->soubor('.png', '');
        imagepng(imagecreatetruecolor(300, 200), $png);
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahradit',
            'slozka'          => 'materialy',
            'cesta'           => 'rocnik_2099/stary_' . self::znacka() . '.jpg',
            'souborWebu'      => new \CURLFile($png, 'image/png', 'nove.png'),
        ]));

        $info = getimagesize($starsi);
        self::assertSame([300, 200, 'image/jpeg'], [$info[0], $info[1], $info['mime']]);
        self::assertSame([], glob($materialy . '/rocnik_2099/stary_' . self::znacka() . '.{png,webp,avif}', GLOB_BRACE));
    }

    /**
     * @test
     */
    public function fotkaNadDvaMegabajtyProjdeAZmensiSe(): void
    {
        if (ini_parse_quantity(ini_get('upload_max_filesize')) < 8 * 1024 * 1024) {
            self::markTestSkipped('PHP tu přijme soubor jen do ' . ini_get('upload_max_filesize') . ', viz .docker/php/conf.d/upload.ini.');
        }
        $materialy = ZpracovaneObrazky::adresarObsahu() . '/materialy';
        $this->uklidNoveAdresare($materialy);
        $this->kUklidu[] = $materialy . '/rocnik_2099';
        $fotka = $this->soubor('.jpg', self::fotkaZFotakuBajty(3600, 2400));
        // bez téhle kontroly by test mohl projít i s fotkou, kterou by prošel i dřívější limit
        self::assertGreaterThan(2 * 1024 * 1024, filesize($fotka));
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'materialy',
            'podslozka'       => 'Ročník 2099',
            'nazev'           => 'Velká fotka ' . self::znacka(),
            'souborWebu'      => new \CURLFile($fotka, 'image/jpeg', 'IMG_0002.JPG'),
        ]));

        $ulozeno = $materialy . '/rocnik_2099/velka_fotka_' . self::znacka() . '.avif';
        self::assertFileExists($ulozeno);
        self::assertSame(1920, getimagesize($ulozeno)[0]);
        self::assertLessThan(filesize($fotka) / 4, filesize($ulozeno));
    }

    /**
     * @test
     */
    public function souborNaKterySeOdkazujeTextNesmazeAleNahradi(): void
    {
        $celohra = ZpracovaneObrazky::adresarObsahu() . '/celohra';
        $this->uklidNoveAdresare($celohra);
        (new Filesystem())->mkdir($celohra);
        $soubor = $celohra . '/' . self::nazevSouboruSOdkazem();
        $this->kUklidu[] = $soubor;
        file_put_contents($soubor, self::pdfBajty());
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'smazat',
            'slozka'          => 'celohra',
            'cesta'           => self::nazevSouboruSOdkazem(),
        ]));
        self::assertFileExists($soubor);
        self::assertStringContainsString(
            'Na soubor ' . self::nazevSouboruSOdkazem() . ' odkazuje stránka test-soubory-v-adminu.',
            $this->stahni($session, 'web/soubory'),
        );

        $novy = $this->soubor('.pdf', self::pdfBajty() . "% nová verze\n");
        $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahradit',
            'slozka'          => 'celohra',
            'cesta'           => self::nazevSouboruSOdkazem(),
            'souborWebu'      => new \CURLFile($novy, 'application/pdf', 'nova.pdf'),
        ]);
        self::assertFileEquals($novy, $soubor);
    }

    public static function provideSouboryKterePageNepoveli(): array
    {
        return [
            'textový soubor'        => ['celohra', "obyčejný text\n", 'poznamky.txt', 'Soubor tohoto typu tu nejde nahrát'],
            'Word s makry'          => ['download', self::docxSMakryBajty(), 'dopis.docx', 'Tenhle dokument tu nejde nahrát. Prezentace, dokumenty s makry nebo vloženými objekty'],
            'skript s příponou PDF' => ['download', '<?php echo "ahoj";', 'skript.pdf', 'Soubor tohoto typu tu nejde nahrát'],
        ];
    }

    public static function provideSouboryKteryPageDoKazdeSlozkyPovoli(): array
    {
        return [
            'ZIP do složky Ke stažení'  => ['download', self::zipBajty(), 'archiv.zip', 'zip'],
            'MP3 do složky Celohra'     => ['celohra', self::mp3Bajty(), 'nahravka.mp3', 'mp3'],
            'Word do složky Ke stažení' => ['download', self::docxBajty(), 'dopis.docx', 'docx'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideSouboryKteryPageDoKazdeSlozkyPovoli
     */
    public function souborKazdehoPrijatelnehoTypuSeNahrajeDoKazdeSlozky(string $slozka, string $obsah, string $puvodniNazev, string $pripona): void
    {
        $adresar = ZpracovaneObrazky::adresarObsahu() . '/' . $slozka;
        $this->uklidNoveAdresare($adresar);
        $ulozeno = $adresar . '/' . self::znacka() . '.' . $pripona;
        $this->kUklidu[] = $ulozeno;
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => $slozka,
            'nazev'           => self::znacka(),
            'souborWebu'      => new \CURLFile($this->soubor('.bin', $obsah), 'application/octet-stream', $puvodniNazev),
        ]));

        self::assertFileExists($ulozeno);
        $stranka = $this->stahni($session, 'web/soubory');
        self::assertStringContainsString('Soubor nahrán.', $stranka);
        self::assertStringContainsString('>' . basename($ulozeno) . '</a>', $stranka, 'Výsledný název je v tabulce souborů');
    }

    /**
     * @test
     *
     * @dataProvider provideSouboryKterePageNepoveli
     */
    public function souborNepovolenehoTypuOdmitne(string $slozka, string $obsah, string $puvodniNazev, string $ocekavanaChyba): void
    {
        $adresar = ZpracovaneObrazky::adresarObsahu() . '/' . $slozka;
        $this->uklidNoveAdresare($adresar);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => $slozka,
            'nazev'           => self::znacka(),
            'souborWebu'      => new \CURLFile($this->soubor('.bin', $obsah), 'application/octet-stream', $puvodniNazev),
        ]));

        self::assertSame([], glob($adresar . '/' . self::znacka() . '.*'));
        self::assertStringContainsString($ocekavanaChyba, $this->stahni($session, 'web/soubory'));
    }

    /**
     * @test
     */
    public function galerieEshopuSeZeStrankySouboryNedaMenit(): void
    {
        $eshop = ZpracovaneObrazky::adresarObrazkuEshopu();
        $this->uklidNoveAdresare($eshop);
        (new Filesystem())->mkdir($eshop);
        $obrazek = $eshop . '/zz-' . self::znacka() . '.png';
        $this->kUklidu[] = $obrazek;
        imagepng(imagecreatetruecolor(10, 10), $obrazek);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        // dvojité lomítko služba zahodí, takže `ROCNIK//eshop` je pro ni totéž co `ROCNIK/eshop`
        foreach ([ROCNIK . '/eshop/zz-' . self::znacka() . '.png', ROCNIK . '//eshop/zz-' . self::znacka() . '.png'] as $cesta) {
            $this->odesli($session, 'web/soubory', [
                'souboryWebuAkce' => 'smazat',
                'slozka'          => 'materialy',
                'cesta'           => $cesta,
            ]);
            self::assertFileExists($obrazek, "Přes cestu {$cesta} jde smazat obrázek e-shopu");
        }
        self::assertStringContainsString('Tohle se spravuje jinde', $this->stahni($session, 'web/soubory'));

        $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'materialy',
            'podslozka'       => ROCNIK . '/eshop',
            'nazev'           => self::znacka() . '-podvrh',
            'souborWebu'      => new \CURLFile($this->soubor('.pdf', self::pdfBajty()), 'application/pdf', 'podvrh.pdf'),
        ]);
        self::assertFileDoesNotExist($eshop . '/' . self::znacka() . '-podvrh.pdf');
    }

    public static function provideNesmyslneVstupy(): array
    {
        return [
            'složka mimo seznam' => [[
                'slozka' => '../..',
            ], 'Tahle složka tu není.'],
            'složka, která není v seznamu' => [[
                'slozka' => 'stranky',
            ], 'Tahle složka tu není.'],
            'pole místo textu' => [http_build_query([
                'souboryWebuAkce' => 'smazat',
                'slozka'          => ['celohra'],
            ]), 'Tahle složka tu není.'],
            'cesta jako pole' => [http_build_query([
                'souboryWebuAkce' => 'smazat',
                'slozka'          => 'celohra',
                'cesta'           => ['a.pdf'],
            ]), 'Chybí název souboru.'],
            'cesta nahoru' => [[
                'slozka' => 'celohra',
                'cesta'  => '../index.pdf',
            ], 'Neplatná cesta k souboru.'],
            'skrytý soubor' => [[
                'slozka' => 'celohra',
                'cesta'  => '.htaccess',
            ], 'Neplatná cesta k souboru.'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideNesmyslneVstupy
     *
     * @param array<string, string>|string $pole
     */
    public function nesmyslnyVstupHlasiChybuANicNezmeni(array|string $pole, string $ocekavanaChyba): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/soubory', is_array($pole) ? [
            'souboryWebuAkce' => 'smazat',
            ...$pole,
        ] : $pole));

        self::assertStringContainsString($ocekavanaChyba, $this->stahni($session, 'web/soubory'));
    }

    /**
     * @test
     */
    public function prilisHlubokouPodslozkuOdmitne(): void
    {
        $celohra = ZpracovaneObrazky::adresarObsahu() . '/celohra';
        $this->uklidNoveAdresare($celohra);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'celohra',
            'podslozka'       => 'a/b/c',
            'nazev'           => self::znacka(),
            'souborWebu'      => new \CURLFile($this->soubor('.pdf', self::pdfBajty()), 'application/pdf', 'a.pdf'),
        ]);

        self::assertFileDoesNotExist($celohra . '/a');
        self::assertStringContainsString('Podsložky smí být nejvýš 2 do hloubky.', $this->stahni($session, 'web/soubory'));
    }

    /**
     * @test
     */
    public function bezPravaNaWebNicNenahraje(): void
    {
        $celohra = ZpracovaneObrazky::adresarObsahu() . '/celohra';
        $this->uklidNoveAdresare($celohra);
        $session = $this->prihlas('test-soubory-bez-prav');

        self::assertSame(
            403,
            $this->odesli($session, 'web/soubory', [
                'souboryWebuAkce' => 'nahrat',
                'slozka'          => 'celohra',
                'nazev'           => self::znacka(),
                'souborWebu'      => new \CURLFile($this->soubor('.pdf', self::pdfBajty()), 'application/pdf', 'a.pdf'),
            ]),
        );

        self::assertSame([], glob($celohra . '/' . self::znacka() . '.*'));
    }

    /**
     * @test
     */
    public function souborTypuKterySeVeSlozceNespravujeSeNesmazeAniRucnePoslanymPozadavkem(): void
    {
        $download = ZpracovaneObrazky::adresarObsahu() . '/download';
        $this->uklidNoveAdresare($download);
        (new Filesystem())->mkdir($download);
        // textový soubor v ní zbyl z doby FTP, stránka takový typ nepřijímá a výpis k němu nenabízí žádnou akci
        $poznamky = $download . '/' . self::znacka() . '-poznamky.txt';
        $this->kUklidu[] = $poznamky;
        file_put_contents($poznamky, "obyčejný text\n");
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'smazat',
            'slozka'          => 'download',
            'cesta'           => self::znacka() . '-poznamky.txt',
        ]));

        self::assertFileExists($poznamky);
        self::assertStringContainsString('Soubory tohoto typu se tu nespravují, mění je jen správce serveru.', $this->stahni($session, 'web/soubory'));
    }

    private function soubor(string $pripona, string $obsah): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . $pripona;
        file_put_contents($cesta, $obsah);

        return $cesta;
    }
}
