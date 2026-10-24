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
        self::assertStringContainsString('Soubor je nahraný jako souhlas_rodicu_' . self::znacka() . '.pdf', $stranka);
        self::assertStringContainsString('rocnik_2099/souhlas_rodicu_' . self::znacka() . '.pdf', $stranka);

        $novy = $this->soubor('.pdf', self::pdfBajty() . "% druhá verze\n");
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahradit',
            'slozka'          => 'celohra',
            'cesta'           => 'rocnik_2099/souhlas_rodicu_' . self::znacka() . '.pdf',
            'souborWebu'      => new \CURLFile($novy, 'application/pdf', 'novy.pdf'),
        ]));
        self::assertFileEquals($novy, $ulozeno);

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
    public function velkyObrazekSeZmensiAUlozJakoWebp(): void
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

        $ulozeno = $materialy . '/rocnik_2099/plakat_' . self::znacka() . '.webp';
        self::assertFileExists($ulozeno);
        $info = getimagesize($ulozeno);
        self::assertSame([1920, 1280, 'image/webp'], [$info[0], $info[1], $info['mime']]);
        $stranka = $this->stahni($session, 'web/soubory');
        self::assertStringContainsString('Soubor je nahraný jako plakat_' . self::znacka() . '.webp', $stranka);
        // soubor, který vznikl převodem, jde nahradit i JPEG a PNG, prohlížeč je v oknu pro výběr nezakáže
        self::assertStringContainsString('accept="image/webp,image/jpeg,image/png"', $stranka);

        $pred = md5_file($ulozeno);
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahradit',
            'slozka'          => 'materialy',
            'cesta'           => 'rocnik_2099/plakat_' . self::znacka() . '.webp',
            'souborWebu'      => new \CURLFile($this->soubor('.jpg', self::jpgBajty(2400, 1600)), 'image/jpeg', 'IMG_0004.JPG'),
        ]));
        self::assertNotSame($pred, md5_file($ulozeno));
        $info = getimagesize($ulozeno);
        self::assertSame([1920, 1280, 'image/webp'], [$info[0], $info[1], $info['mime']]);
        self::assertSame([], glob($materialy . '/rocnik_2099/plakat_' . self::znacka() . '.jpg'));
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

        $ulozeno = $materialy . '/rocnik_2099/velka_fotka_' . self::znacka() . '.webp';
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
            'ZIP do složky jen pro PDF' => ['download', self::zipBajty(), 'archiv.zip', 'Formát ZIP tu nejde použít, nahraj prosím PDF.'],
            'MP3 do složky jen pro PDF' => ['celohra', self::mp3Bajty(), 'nahravka.mp3', 'Formát MP3 tu nejde použít, nahraj prosím PDF.'],
            'dokument z Wordu'          => ['download', self::docxBajty(), 'dopis.pdf', 'Dokument z Wordu, PowerPointu, Excelu s makry nebo LibreOffice tu nejde nahrát'],
            'skript s příponou PDF'     => ['download', '<?php echo "ahoj";', 'skript.pdf', 'Soubor tohoto typu tu nejde nahrát'],
        ];
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
        // složka download je jen pro PDF, ZIP v ní zbyl z doby FTP a výpis k němu nenabízí žádnou akci
        $archiv = $download . '/' . self::znacka() . '-archiv.zip';
        $this->kUklidu[] = $archiv;
        file_put_contents($archiv, self::zipBajty());
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'smazat',
            'slozka'          => 'download',
            'cesta'           => self::znacka() . '-archiv.zip',
        ]));

        self::assertFileExists($archiv);
        self::assertStringContainsString('Soubory tohoto typu se tu nespravují, jen přes FTP.', $this->stahni($session, 'web/soubory'));
    }

    private function soubor(string $pripona, string $obsah): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . $pripona;
        file_put_contents($cesta, $obsah);

        return $cesta;
    }
}
