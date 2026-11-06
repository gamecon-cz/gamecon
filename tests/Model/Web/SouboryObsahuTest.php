<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\NahravaniPoCastech;
use Gamecon\Web\SouboryObsahu;
use Gamecon\Web\ZpracovaneObrazky;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Filesystem\Filesystem;

class SouboryObsahuTest extends AbstractTestDb
{
    use TestovaciDokumenty;

    protected static bool $disableStrictTransTables = true;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $pouzity = 'soubory/obsah/' . self::stranka() . '/pouzity.pdf';

        return [
            <<<SQL
INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi)
VALUES (9931, 'soubory-obsahu-test', 'Stáhni si [smlouvu]({$pouzity})', 0)
SQL,
        ];
    }

    private static function koren(): string
    {
        return 'test-soubory-obsahu-' . getmypid();
    }

    /**
     * Adresář textu, do kterého se ukládají nové soubory
     */
    private static function stranka(): string
    {
        return self::koren() . '/stranka';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
        $this->kUklidu = [ZpracovaneObrazky::adresarObsahu() . '/' . self::koren()];
        (new Filesystem())->mkdir(ZpracovaneObrazky::adresarObsahu() . '/' . self::stranka());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function nabidneNahrazeniOdkazovanychSouboruKdekoliLezi(): void
    {
        $obsah = ZpracovaneObrazky::adresarObsahu() . '/' . self::koren();
        (new Filesystem())->mkdir($obsah . '/presskit');
        file_put_contents($obsah . '/presskit/press_kit.zip', self::zipBajty());
        $text = '[kit](soubory/obsah/' . self::koren() . '/presskit/press_kit.zip) '
            . '[chybí](https://gamecon.cz/soubory/obsah/' . self::koren() . '/presskit/foto_set.zip) '
            . '[loga](soubory/obsah/sponzori/seznam.pdf) [eshop](soubory/obsah/materialy/2026/eshop/popis.pdf) '
            . '![obrázek](soubory/obsah/' . self::koren() . '/presskit/logo.png) [skript](soubory/obsah/' . self::koren() . '/presskit/spust.php)';

        $html = $this->panel($text)->html();

        self::assertStringContainsString('Soubory, na které text odkazuje', $html);
        $kit = self::radek($html, self::koren() . '/presskit/press_kit.zip');
        self::assertStringContainsString('name="cesta" value="' . self::koren() . '/presskit/press_kit.zip"', $kit);
        self::assertStringContainsString('accept="application/zip"', $kit);
        self::assertStringContainsString('Nahradit', $kit);
        self::assertStringContainsString('(ZIP, 1 kB)', $kit);
        $chybi = self::radek($html, self::koren() . '/presskit/foto_set.zip');
        self::assertStringContainsString('na serveru chybí', $chybi);
        self::assertStringContainsString('Nahrát', $chybi);
        // loga spravuje modul Loga a galerii e-shopu Shop, oba s vlastním právem; obrázky mají vlastní blok a skript není soubor
        self::assertStringNotContainsString('sponzori/seznam.pdf', $html);
        self::assertStringNotContainsString('eshop/popis.pdf', $html);
        self::assertStringNotContainsString('logo.png', $html);
        self::assertStringNotContainsString('spust.php', $html);
    }

    /**
     * @test
     */
    public function vypiseSouboryNahraneKTextuSeZapisyKVlozeniAMazeJenNepouzite(): void
    {
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::stranka();
        file_put_contents($slozka . '/pouzity.pdf', self::pdfBajty());
        file_put_contents($slozka . '/volny.pdf', self::pdfBajty());
        file_put_contents($slozka . '/.htaccess', 'Deny from all');
        file_put_contents($slozka . '/stary.pptx', 'prezentace z doby FTP');
        imagepng(imagecreatetruecolor(4, 4), $slozka . '/obrazek.png');

        $html = $this->panel('text bez odkazů')->html();

        self::assertStringContainsString('Soubory nahrané k této stránce', $html);
        self::assertStringNotContainsString('.htaccess', $html);
        self::assertStringNotContainsString('stary.pptx', $html);
        self::assertStringNotContainsString('obrazek.png', $html, 'Obrázky patří do bloku Obrázky');

        $volny = self::radek($html, 'volny.pdf');
        self::assertStringContainsString('value="[volny](soubory/obsah/' . self::stranka() . '/volny.pdf)"', $volny);
        self::assertStringContainsString(
            'value="&lt;a href=&quot;soubory/obsah/' . self::stranka() . '/volny.pdf&quot;&gt;volny&lt;/a&gt;"',
            $volny,
        );
        self::assertStringContainsString('<span class="udaje-souboru technicke">(PDF, ', $volny);
        self::assertMatchesRegularExpression('~<td class="tabulka-souboru__nahrano">\d{1,2}\. \d{1,2}\. \d{4} \d{2}:\d{2}</td>~', $volny);
        self::assertStringContainsString('Nikde se nepoužívá', $volny);
        self::assertStringContainsString('value="smazat"', $volny);
        self::assertStringContainsString('name="cesta" value="' . self::stranka() . '/volny.pdf"', $volny);
        // nahrát jde i soubor, na který text zatím neodkazuje, a to jen stejného typu, pod stejným názvem
        self::assertStringContainsString('value="nahradit"', $volny);
        self::assertStringContainsString('accept="application/pdf"', $volny);

        $pouzity = self::radek($html, 'pouzity.pdf');
        self::assertStringContainsString('<a href="web/editace-stranek?id=9931">stránka soubory-obsahu-test</a>', $pouzity);
        self::assertStringNotContainsString('value="smazat"', $pouzity, 'Soubor, na který text odkazuje, jde nahradit, ale ne smazat');
        self::assertStringContainsString('value="nahradit"', $pouzity);
    }

    /**
     * Název z doby FTP, který není UTF-8, by se po cestě přes formulář nenašel a hláška by mylně řekla, že na soubor
     * text neodkazuje.
     *
     * @test
     */
    public function souborSNazvemKterySeFormularemNezachovaNenabidneNahrazeniAniMazani(): void
    {
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::stranka();
        file_put_contents($slozka . "/p\xE9ce.pdf", self::pdfBajty());
        $text = '[a](soubory/obsah/' . self::koren() . '/jinde/p%E9ce.pdf)';

        $html = $this->panel($text)->html();

        self::assertSame(2, substr_count($html, 'Tenhle soubor se tu nespravuje, mění ho jen správce serveru.'), 'Odkazovaný i nahraný soubor');
        self::assertStringNotContainsString('value="nahradit"', $html);
        self::assertStringNotContainsString('value="smazat"', $html);
    }

    /**
     * @test
     */
    public function formularKNahraniRiktaKamSeSouborUlozi(): void
    {
        $html = $this->panel('text')->html();

        self::assertStringContainsString('Nahrát nový soubor', $html);
        self::assertStringContainsString('Uloží se do <code>soubory/obsah/' . self::stranka() . '/</code>', $html);
        self::assertStringContainsString('accept="application/pdf,audio/mpeg,audio/ogg,audio/wav,audio/mp4,application/zip', $html);
        self::assertStringContainsString('name="souboryObsahuAkce" value="nahrat"', $html);
        self::assertStringNotContainsString('data-nahravani-po-castech', $html);
    }

    /**
     * @test
     */
    public function velkeSouboryPosilaPoCastechJenKdyzJeNahravaniKDispozici(): void
    {
        $poCastech = new NahravaniPoCastech(1, 1000, sys_get_temp_dir() . '/gamecon-soubory-obsahu-po-castech', 4096);
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::stranka();
        file_put_contents($slozka . '/volny.pdf', self::pdfBajty());
        $text = '[kit](soubory/obsah/' . self::koren() . '/jinde/kit.zip)';

        $html = $this->panel($text, $poCastech)->html();

        // nahrání nového souboru, nahrazení odkazovaného (i chybějícího) a nahrazení nahraného k textu
        self::assertSame(3, substr_count($html, 'data-nahravani-po-castech data-nahravani-prah="4096"'));
        // soubor poslaný po částech omezuje jen nastavení aplikace, ne limit PHP pro jeden požadavek
        self::assertStringContainsString('nejvýš ' . NahravaniObrazku::lidskaVelikost(SOUBOR_MAX_MB * 1024 * 1024), $html);
        self::assertMatchesRegularExpression(
            '~<script src="files/nahravani-po-castech\.js\?v=[0-9a-f]{32}"></script>~',
            $this->panel($text, $poCastech)->getSkript(),
        );
        self::assertSame('', $this->panel($text)->getSkript());
    }

    private function panel(string $text, ?NahravaniPoCastech $poCastech = null): SouboryObsahu
    {
        return new SouboryObsahu($text, self::stranka(), 'k této stránce', poCastech: $poCastech);
    }

    private static function radek(string $html, string $soubor): string
    {
        preg_match_all('~<tr>.*?</tr>~s', $html, $radky);
        foreach ($radky[0] as $radek) {
            if (str_contains($radek, '>' . $soubor . '<')) {
                return $radek;
            }
        }
        self::fail("V tabulce není řádek souboru {$soubor}");
    }
}
