<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Tests\Model\Web\TestovaciDokumenty;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Soubory ke stažení pod editorem stránky a novinky přes skutečnou administraci (Apache, přihlášení, formuláře i
 * požadavky skriptu pro nahrávání po částech), tak jak je používá organizátor místo FTP.
 */
class NahravaniSouboruKTextuVAdminuTest extends AbstractTestWeb
{
    use PosilaniPoCastech;
    use TestovaciDokumenty;
    use ZadostiDoAdmina;

    private const int ID_STRANKY = 9841;
    private const int ID_NOVINKY = 9841;
    private const int ID_BEZ_PRAV = 9842;
    private const string URL_STRANKY = 'test-soubory-v-editoru';
    private const string URL_BLOGU = 'test-soubory-v-blogu';

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
        $existujici = 'soubory/obsah/' . self::koren() . '/presskit/press_kit.zip';
        $chybejici = 'soubory/obsah/' . self::koren() . '/presskit/foto_set.zip';

        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES (%d, 'test-soubory-editor-bez-prav', 'Test', 'Bezprav', 'test-soubory-editor-bez-prav@example.invalid')
SQL,
                self::ID_BEZ_PRAV,
            ),
            // \n zpracuje až MariaDB, v textu stránky z toho jsou odřádkování
            sprintf(
                <<<'SQL'
INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi)
VALUES (%d, '%s', '# Test\n\n[Press kit](%s) a [fotky](https://gamecon.cz/%s)', 0)
SQL,
                self::ID_STRANKY,
                self::URL_STRANKY,
                $existujici,
                $chybejici,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO novinky(id, typ, vydat, url, nazev, autor, text)
VALUES (%d, 2, NOW(), '%s', 'Test souborů', 'Autor', 'Text')
SQL,
                self::ID_NOVINKY,
                self::URL_BLOGU,
            ),
        ];
    }

    /**
     * Složka v `soubory/obsah` pro odkazované soubory z doby FTP, ať test nesahá na skutečné složky
     */
    private static function koren(): string
    {
        return 'test-soubory-v-editoru-' . getmypid();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_ADMIN);
        $this->zdroje = sys_get_temp_dir() . '/gamecon-soubory-v-editoru-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->zdroje);
        $this->kUklidu[] = $this->zdroje;
        $obsah = ZpracovaneObrazky::adresarObsahu();
        $this->uklidNoveAdresare($obsah . '/' . self::koren());
        $this->uklidNoveAdresare($obsah . '/stranky/' . self::URL_STRANKY);
        $this->uklidNoveAdresare($obsah . '/blog/' . self::URL_BLOGU);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function souborKTextuStrankyJdeNahratNahraditASmazat(): void
    {
        $obsah = ZpracovaneObrazky::adresarObsahu();
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'web/editace-stranek?id=' . self::ID_STRANKY;
        $staryZip = $obsah . '/' . self::koren() . '/presskit/press_kit.zip';
        (new Filesystem())->mkdir(dirname($staryZip));
        file_put_contents($staryZip, self::zipBajty());

        $stranka = $this->stahni($session, $url);
        self::assertStringContainsString('Soubory ke stažení', $stranka);
        self::assertStringContainsString('Uloží se do <code>soubory/obsah/stranky/' . self::URL_STRANKY . '/</code>', $stranka);
        self::assertStringContainsString('na serveru chybí', $stranka, 'Text odkazuje na soubor, který ještě nikdo nenahrál');

        // soubor z doby FTP, na který text odkazuje, se nahradí na místě
        $novyZip = $this->zdroje . '/nova-verze.zip';
        file_put_contents($novyZip, self::zipSObsahem([
            'jine.txt' => 'Nová verze',
        ]));
        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'nahradit',
            'cesta'             => self::koren() . '/presskit/press_kit.zip',
            'souborObsahu'      => new \CURLFile($novyZip, 'application/zip', 'GameCon_press_kit_2027.zip'),
        ]));
        self::assertFileEquals($novyZip, $staryZip);
        self::assertStringContainsString('press_kit.zip nahrán. Kdo ho už viděl', $this->stahni($session, $url));

        // odkazovaný soubor, který na serveru chybí, se vytvoří
        $chybejici = $obsah . '/' . self::koren() . '/presskit/foto_set.zip';
        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'nahradit',
            'cesta'             => self::koren() . '/presskit/foto_set.zip',
            'souborObsahu'      => new \CURLFile($novyZip, 'application/zip', 'fotky.zip'),
        ]));
        self::assertFileEquals($novyZip, $chybejici);

        // cesta, na kterou text neodkazuje, nahradit nejde
        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'nahradit',
            'cesta'             => 'sponzori/podvrh.zip',
            'souborObsahu'      => new \CURLFile($novyZip, 'application/zip', 'podvrh.zip'),
        ]));
        self::assertFileDoesNotExist($obsah . '/sponzori/podvrh.zip');
        self::assertStringContainsString('text na něj neodkazuje', $this->stahni($session, $url));

        // nový soubor jde do adresáře stránky
        $pdf = $this->zdroje . '/plakat.pdf';
        file_put_contents($pdf, self::pdfBajty());
        $formular = [
            'souboryObsahuAkce' => 'nahrat',
            'nazev'             => 'Plakát A3',
            'souborObsahu'      => new \CURLFile($pdf, 'application/pdf', 'cokoli.pdf'),
        ];
        self::assertSame(303, $this->odesli($session, $url, $formular));
        $novy = $obsah . '/stranky/' . self::URL_STRANKY . '/plakat_a3.pdf';
        self::assertFileEquals($pdf, $novy);
        $stranka = $this->stahni($session, $url);
        self::assertStringContainsString('Soubor nahrán.', $stranka);
        self::assertStringContainsString('[plakat_a3](soubory/obsah/stranky/' . self::URL_STRANKY . '/plakat_a3.pdf)', $stranka);

        // stejný název podruhé nejde, soubor se nepřepíše
        file_put_contents($novy, 'původní obsah');
        self::assertSame(303, $this->odesli($session, $url, $formular));
        self::assertSame('původní obsah', file_get_contents($novy));
        self::assertStringContainsString('plakat_a3.pdf už existuje', $this->stahni($session, $url));
        file_put_contents($novy, self::pdfBajty());

        // smazat jde jen nepoužitý soubor z adresáře stránky, ne soubor, na který text odkazuje
        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'smazat',
            'cesta'             => self::koren() . '/presskit/press_kit.zip',
        ]));
        self::assertFileExists($staryZip);
        self::assertStringContainsString('Smazat tu jde jen soubor nahraný k této stránce', $this->stahni($session, $url));
        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'smazat',
            'cesta'             => 'stranky/' . self::URL_STRANKY . '/plakat_a3.pdf',
        ]));
        self::assertFileDoesNotExist($novy);
        self::assertStringContainsString('Soubor je smazaný.', $this->stahni($session, $url));
    }

    /**
     * Obrázky se k textu nahrávají zvlášť a upraví se, soubor ke stažení se ukládá, jak přišel.
     *
     * @test
     */
    public function souborKeStazeniNeprijmeObrazekNiSkript(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'web/editace-stranek?id=' . self::ID_STRANKY;
        $obsah = ZpracovaneObrazky::adresarObsahu() . '/stranky/' . self::URL_STRANKY;
        $skript = $this->zdroje . '/skript.pdf';
        file_put_contents($skript, '<?php echo "ahoj";');
        $obrazek = $this->zdroje . '/logo.png';
        imagepng(imagecreatetruecolor(40, 40), $obrazek);

        foreach ([
            'obrázek' => [$obrazek, 'image/png', 'logo.png'],
            'skript'  => [$skript, 'application/pdf', 'skript.php.pdf'],
        ] as $popis => [$cesta, $typ, $nazev]) {
            self::assertSame(303, $this->odesli($session, $url, [
                'souboryObsahuAkce' => 'nahrat',
                'nazev'             => 'nemelo-projit',
                'souborObsahu'      => new \CURLFile($cesta, $typ, $nazev),
            ]));

            self::assertSame([], glob($obsah . '/*') ?: [], $popis);
        }
        self::assertStringContainsString('Soubor tohoto typu tu nejde nahrát', $this->stahni($session, $url));
    }

    /**
     * Na požadavky skriptu má editor v adrese vlastní `id` stránky, které s `id` nahrávání nesmí splynout.
     *
     * @test
     */
    public function velkySouborKTextuStrankyProjdePoCastech(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'web/editace-stranek?id=' . self::ID_STRANKY;
        $pdf = $this->pdfSOdsazenim(9);

        $id = $this->posliSouborPoCastech($session, $pdf, 'program-2027.pdf', $url);
        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'nahrat',
            'nazev'             => 'Program 2027',
            'nahranoId'         => $id,
        ]));

        $ulozeno = ZpracovaneObrazky::adresarObsahu() . '/stranky/' . self::URL_STRANKY . '/program_2027.pdf';
        self::assertFileEquals($pdf, $ulozeno);
        self::assertStringContainsString('Soubor nahrán.', $this->stahni($session, $url));
    }

    /**
     * Press kit z doby FTP má přes 90 MB a každý rok se mění.
     *
     * @test
     */
    public function odkazovanySouborZDobyFtpJdeNahraditPoCastech(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'web/editace-stranek?id=' . self::ID_STRANKY;
        $staryZip = ZpracovaneObrazky::adresarObsahu() . '/' . self::koren() . '/presskit/press_kit.zip';
        (new Filesystem())->mkdir(dirname($staryZip));
        file_put_contents($staryZip, self::zipBajty());
        $velkyZip = $this->velkyZip(20);

        $id = $this->posliSouborPoCastech($session, $velkyZip, 'GameCon_press_kit.zip', $url);
        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'nahradit',
            'cesta'             => self::koren() . '/presskit/press_kit.zip',
            'nahranoId'         => $id,
        ]));

        self::assertFileEquals($velkyZip, $staryZip);
        self::assertStringContainsString('press_kit.zip nahrán. Kdo ho už viděl', $this->stahni($session, $url));
    }

    /**
     * @test
     */
    public function souborKPrispevkuNaBlogJdeNahratPoCastech(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'web/novinka?id=' . self::ID_NOVINKY;

        self::assertStringContainsString(
            'Uloží se do <code>soubory/obsah/blog/' . self::URL_BLOGU . '/</code>',
            $this->stahni($session, $url),
        );
        $pdf = $this->pdfSOdsazenim(6);
        $id = $this->posliSouborPoCastech($session, $pdf, 'zapisek.pdf', $url);
        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'nahrat',
            'nazev'             => 'Zápisek',
            'nahranoId'         => $id,
        ]));

        self::assertFileEquals($pdf, ZpracovaneObrazky::adresarObsahu() . '/blog/' . self::URL_BLOGU . '/zapisek.pdf');
        self::assertStringContainsString('Soubor nahrán.', $this->stahni($session, $url));
    }

    /**
     * @test
     */
    public function odmitnutyFormularEditoruSmazeMezisoubor(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'web/editace-stranek?id=' . self::ID_STRANKY;
        $id = $this->posliSouborPoCastech($session, $this->pdfSOdsazenim(5), 'dopis.pdf', $url);
        $mezisoubor = __DIR__ . '/../../cache/private/tests/nahravani-po-castech/' . $id;
        self::assertDirectoryExists($mezisoubor);

        self::assertSame(303, $this->odesli($session, $url, [
            'souboryObsahuAkce' => 'nahradit',
            'cesta'             => 'sponzori/podvrh.pdf',
            'nahranoId'         => $id,
        ]));

        // Apache ho smazal, ale tenhle proces si pamatuje, že adresář existoval
        clearstatcache();
        self::assertDirectoryDoesNotExist($mezisoubor);
    }

    /**
     * @test
     */
    public function bezPravaNaWebSeNahravaniVEditoruNezacne(): void
    {
        $odpoved = $this->posli(
            $this->prihlas('test-soubory-editor-bez-prav'),
            'zahaj',
            [],
            http_build_query([
                'velikost' => 100,
                'nazev'    => 'a.zip',
            ]),
            'application/x-www-form-urlencoded',
            adresa: 'web/editace-stranek?id=' . self::ID_STRANKY,
        );

        self::assertSame(403, $odpoved['kod']);
        self::assertNull($odpoved['json']);
    }

    /**
     * @test
     */
    public function editoryMajiVsechnyBlokyAKazdyZeSkriptuJednou(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        foreach (['web/editace-stranek?id=' . self::ID_STRANKY, 'web/novinka?id=' . self::ID_NOVINKY] as $url) {
            $editor = $this->stahni($session, $url);

            self::assertStringContainsString('<summary>Obrázky</summary>', $editor, $url);
            self::assertStringContainsString('<summary>Soubory ke stažení</summary>', $editor, $url);
            self::assertSame(1, substr_count($editor, 'files/nahravani-po-castech.js?v='), $url);
            self::assertSame(1, substr_count($editor, 'files/neulozeny-text-editoru.js?v='), $url);
            // nahrání nového souboru a nahrazení odkazovaného (v novince žádný odkaz není)
            self::assertGreaterThanOrEqual(1, substr_count($editor, 'data-nahravani-po-castech data-nahravani-prah="'), $url);
        }
        self::assertStringContainsString(
            'Obrázky a soubory půjde nahrát, až stránku uložíš.',
            $this->stahni($session, 'web/editace-stranek?akce=nova'),
        );
    }
}
