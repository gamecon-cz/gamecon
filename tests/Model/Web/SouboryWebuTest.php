<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\SouboryWebu;
use Gamecon\Web\ZpracovaneObrazky;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Filesystem\Filesystem;

class SouboryWebuTest extends AbstractTestDb
{
    use TestovaciDokumenty;
    use UklidNovychAdresaru;

    protected static bool $disableStrictTransTables = true;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $pouzity = 'soubory/obsah/' . self::slozka() . '/pouzity.pdf';
        $vPodslozce = 'soubory/obsah/' . self::slozka() . '/2027/dve.pdf';

        return [
            <<<SQL
INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi)
VALUES (9921, 'soubory-test', 'Stáhni si [smlouvu]({$pouzity}) a [druhý]({$vPodslozce}?download=1)', 0)
SQL,
        ];
    }

    private static function slozka(): string
    {
        return 'test-soubory-' . getmypid();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pripravXTemplateCache();
        $this->kUklidu = [ZpracovaneObrazky::adresarObsahu() . '/' . self::slozka()];
        (new Filesystem())->mkdir($this->kUklidu);
    }

    private function pripravXTemplateCache(): void
    {
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function vypiseSouboryVeSlozceSMistyKdeSePouzivaji(): void
    {
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::slozka();
        (new Filesystem())->mkdir($slozka . '/2027');
        foreach (['pouzity.pdf', 'volny.pdf', '2027/dve.pdf'] as $pdf) {
            file_put_contents($slozka . '/' . $pdf, self::pdfBajty());
        }
        file_put_contents($slozka . '/archiv.zip', self::zipBajty());
        file_put_contents($slozka . '/stary.docx', 'dokument z doby FTP');
        file_put_contents($slozka . '/.htaccess', 'Deny from all');

        $html = (new SouboryWebu([
            self::slozka() => $this->nastaveni([NahravaniObrazku::PDF, NahravaniObrazku::ZIP]),
        ]))->html();

        self::assertStringContainsString('Testovací složka', $html);
        self::assertStringContainsString('(5)', $html, 'Skrytý soubor se nepočítá');
        self::assertStringNotContainsString('.htaccess', $html);
        self::assertStringContainsString('accept="application/pdf,application/zip"', $html);
        self::assertStringContainsString('<option value="2027">', $html);
        self::assertStringContainsString('Soubor se stejným názvem nejde nahrát znovu, nahraď předchozí.', $html);
        self::assertStringNotContainsString('v seznamu', $html);

        $pouzity = self::radek($html, 'pouzity.pdf');
        self::assertStringContainsString('<a href="web/editace-stranek?id=9921">stránka soubory-test</a>', $pouzity);
        self::assertStringContainsString('value="nahradit"', $pouzity);
        self::assertStringNotContainsString('value="smazat"', $pouzity, 'Soubor, na který text odkazuje, jde nahradit, ale ne smazat');
        self::assertStringNotContainsString('Nikde se nepoužívá', $pouzity);
        self::assertStringContainsString(
            '</a> <span class="soubory-webu__udaje technicke">(PDF, ' . NahravaniObrazku::lidskaVelikost(strlen(self::pdfBajty())) . ')</span>',
            $pouzity,
            'Typ a velikost jsou v závorce za odkazem na soubor, technické písmo (třída technicke), ne vlastní sloupce',
        );
        self::assertStringNotContainsString('<th>Typ</th>', $html);
        self::assertStringNotContainsString('<th>Velikost</th>', $html);
        self::assertStringContainsString('<th>Použit v</th>', $html, 'Sloupec s místy použití, včetně kódu webu');
        self::assertStringNotContainsString('Odkazuje na něj', $html);
        self::assertStringContainsString('<th>Nahrán</th>', $html, 'Datum je čas souboru na disku, tedy nahrání nebo poslední nahrazení');
        self::assertStringNotContainsString('<th>Změněno</th>', $html);

        $vPodslozce = self::radek($html, '2027/dve.pdf');
        self::assertStringContainsString('stránka soubory-test', $vPodslozce, 'Odkaz s ?download=1 platí');
        self::assertStringNotContainsString('value="smazat"', $vPodslozce);

        $volny = self::radek($html, 'volny.pdf');
        self::assertStringContainsString('value="smazat"', $volny);
        self::assertStringContainsString('value="nahradit"', $volny);
        self::assertStringContainsString(
            '<span class="tip" title="Nikde se nepoužívá">—</span>',
            $volny,
            'Soubor, na který nic neodkazuje, má ve sloupci Použit v pomlčku, ne prázdné místo',
        );

        self::assertStringContainsString('value="smazat"', self::radek($html, 'archiv.zip'));

        $stary = self::radek($html, 'stary.docx');
        self::assertStringContainsString('jen přes FTP', $stary);
        self::assertStringNotContainsString('value="nahradit"', $stary);
        self::assertStringNotContainsString('value="smazat"', $stary);
        self::assertStringContainsString(
            '(DOCX, ' . NahravaniObrazku::lidskaVelikost(strlen('dokument z doby FTP')) . ')</span>',
            $stary,
            'Typ se u souboru nepovoleného formátu bere z přípony',
        );
    }

    /**
     * Nahrán je soubor, který vznikl naposled, nahoře. Soubory ze stejné chvíle zůstanou podle názvu.
     *
     * @test
     */
    public function souboryJsouOdNejnovejsihoACasNahraniMaHodinyAMinuty(): void
    {
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::slozka();
        (new Filesystem())->mkdir($slozka . '/2027');
        $casy = [
            'stary.pdf'           => '2026-01-01 10:00:00',
            'nejnovejsi.pdf'      => '2026-03-02 08:05:00',
            '2027/prostredni.pdf' => '2026-02-15 23:59:00',
            'b-stejne.pdf'        => '2025-12-24 12:00:00',
            'a-stejne.pdf'        => '2025-12-24 12:00:00',
        ];
        foreach ($casy as $soubor => $cas) {
            file_put_contents($slozka . '/' . $soubor, self::pdfBajty());
            touch($slozka . '/' . $soubor, strtotime($cas));
        }

        $html = (new SouboryWebu([
            self::slozka() => $this->nastaveni([NahravaniObrazku::PDF]),
        ]))->html();

        $poradi = array_map(
            static fn (string $soubor): int|false => strpos($html, '>' . $soubor . '</a>'),
            ['nejnovejsi.pdf', '2027/prostredni.pdf', 'stary.pdf', 'a-stejne.pdf', 'b-stejne.pdf'],
        );
        self::assertNotContains(false, $poradi, 'Ve výpisu jsou všechny soubory');
        $serazene = $poradi;
        sort($serazene);
        self::assertSame($serazene, $poradi, 'Od nejnovějšího, při stejném čase podle názvu');
        self::assertStringContainsString(
            '<td class="soubory-webu__nahrano">2. 3. 2026 08:05</td>',
            self::radek($html, 'nejnovejsi.pdf'),
            'Datum a čas se v buňce nezalomí',
        );
        self::assertStringContainsString('15. 2. 2026 23:59', self::radek($html, '2027/prostredni.pdf'));
    }

    /**
     * @test
     */
    public function souborBezPriponyMaZaOdkazemJenVelikost(): void
    {
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::slozka();
        file_put_contents($slozka . '/bez-pripony', 'x');

        $html = (new SouboryWebu([
            self::slozka() => $this->nastaveni([NahravaniObrazku::PDF]),
        ]))->html();

        $radek = self::radek($html, 'bez-pripony');
        self::assertStringContainsString('technicke">(1 kB)</span>', $radek);
        self::assertStringNotContainsString('(,', $radek);
    }

    /**
     * Stejný zápis jako u obrázků v editoru stránky (Markdown a HTML pod sebou) a stejné ikony na tlačítkách.
     *
     * @test
     */
    public function souborMaZapisDoTextuVMarkdownuAHtmlAIkonyNaTlacitkach(): void
    {
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::slozka();
        file_put_contents($slozka . '/volny.pdf', self::pdfBajty());

        $html = (new SouboryWebu([
            self::slozka() => $this->nastaveni([NahravaniObrazku::PDF]),
        ]))->html();

        $cesta = 'soubory/obsah/' . self::slozka() . '/volny.pdf';
        $volny = self::radek($html, 'volny.pdf');
        self::assertStringContainsString('<label>Markdown: <input type="text" readonly value="[volny](' . $cesta . ')"', $volny);
        self::assertStringContainsString(
            '<label>HTML: <input type="text" readonly value="&lt;a href=&quot;' . $cesta . '&quot;&gt;volny&lt;/a&gt;"',
            $volny,
        );
        self::assertStringContainsString('<i class="fa fa-upload" aria-hidden="true"></i> Nahradit', $volny);
        self::assertStringContainsString('<i class="fa fa-trash" aria-hidden="true"></i> Smazat', $volny);
        self::assertMatchesRegularExpression(
            '~<div class="soubory-webu__akce">\s*<form.*Nahradit.*<form.*Smazat.*</div>~s',
            $volny,
            'Nahradit a Smazat jsou v jednom bloku, který mezi nimi drží odstup',
        );
        self::assertStringContainsString('<i class="fa fa-upload" aria-hidden="true"></i> Nahrát', $html);
        self::assertStringContainsString(
            '<small class="soubory-webu__pocet" title="Počet souborů ve složce">(1)</small>',
            $html,
        );
    }

    /**
     * @test
     */
    public function odkazZKoduNaSouborZnamenaZeSeNesmaze(): void
    {
        $soubor = ZpracovaneObrazky::adresarObsahu() . '/download/tiskova_kronika.pdf';
        if (file_exists($soubor)) {
            self::markTestSkipped('Soubor už je na disku, test ho nesmí přepsat ani smazat');
        }
        $this->uklidNoveAdresare(dirname($soubor));
        $this->kUklidu[] = $soubor;
        (new Filesystem())->mkdir(dirname($soubor));
        file_put_contents($soubor, self::pdfBajty());

        $html = (new SouboryWebu([
            'download' => [
                'nazev'   => 'Ke stažení',
                'popis'   => 'Popis',
                'formaty' => [NahravaniObrazku::PDF],
            ],
        ]))->html();

        $radek = self::radek($html, 'tiskova_kronika.pdf');
        self::assertStringContainsString('QR kódy (web/moduly/qrka.php) <span class="tip">(v kódu webu)</span>', $radek);
        self::assertStringNotContainsString('value="smazat"', $radek);
        self::assertStringContainsString('value="nahradit"', $radek);
    }

    /**
     * @test
     */
    public function obrazkyEshopuVeSlozceMaterialuNevypisuje(): void
    {
        $koren = sys_get_temp_dir() . '/gamecon-soubory-webu-' . bin2hex(random_bytes(6));
        $this->kUklidu[] = $koren;
        (new Filesystem())->mkdir($koren . '/materialy/2026/eshop');
        file_put_contents($koren . '/materialy/2026/souhlas.pdf', self::pdfBajty());
        file_put_contents($koren . '/materialy/2026/eshop/01_Blok.jpg', 'x');

        $html = (new SouboryWebu(
            [
                'materialy' => $this->nastaveni([NahravaniObrazku::PDF, NahravaniObrazku::JPG]),
            ],
            $koren,
        ))->html();

        self::assertStringContainsString('2026/souhlas.pdf', $html);
        self::assertStringNotContainsString('01_Blok.jpg', $html);
        self::assertStringContainsString('(1)', $html);
    }

    /**
     * web/soubory může být symlink na sdílené úložiště, skutečná cesta souboru pak vede mimo web.
     *
     * @test
     */
    public function souborNaJinemUlozistiMaVeVypisuMistaPouziti(): void
    {
        $uloziste = sys_get_temp_dir() . '/gamecon-soubory-uloziste-' . bin2hex(random_bytes(6));
        $this->kUklidu[] = $uloziste;
        (new Filesystem())->mkdir($uloziste);
        file_put_contents($uloziste . '/pouzity.pdf', self::pdfBajty());
        file_put_contents($uloziste . '/volny.pdf', self::pdfBajty());
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::slozka();
        (new Filesystem())->remove($slozka);
        symlink($uloziste, $slozka);

        $html = (new SouboryWebu([
            self::slozka() => $this->nastaveni([NahravaniObrazku::PDF]),
        ]))->html();

        $pouzity = self::radek($html, 'pouzity.pdf');
        self::assertStringContainsString('stránka soubory-test', $pouzity);
        self::assertStringNotContainsString('value="smazat"', $pouzity);
        self::assertStringContainsString(
            'value="[pouzity](soubory/obsah/' . self::slozka() . '/pouzity.pdf)"',
            $pouzity,
            'Odkaz do textu se skládá ze složky, ne ze skutečné cesty na úložišti',
        );
        self::assertStringContainsString('value="smazat"', self::radek($html, 'volny.pdf'));
    }

    /**
     * Server z podsložky vyrobí název vhodný do adresy, nabídnout jde tedy jen to, co se tak nezmění.
     *
     * @test
     */
    public function nabidkaPodslozekObsahujeJenTyKamSeDaNahrat(): void
    {
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::slozka();
        foreach (['deniky', 'Deníky postav', 'v1.2', 'x/y/z', '_stare', '2026-'] as $podslozka) {
            (new Filesystem())->mkdir($slozka . '/' . $podslozka);
            file_put_contents($slozka . '/' . $podslozka . '/a.pdf', self::pdfBajty());
        }

        $html = (new SouboryWebu([
            self::slozka() => $this->nastaveni([NahravaniObrazku::PDF]),
        ]))->html();

        foreach (['deniky', 'x', 'x/y'] as $nabizena) {
            self::assertStringContainsString('<option value="' . $nabizena . '">', $html);
        }
        // _stare a 2026- server uloží do stare a 2026
        foreach (['Deníky postav', 'v1.2', 'x/y/z', '_stare', '2026-'] as $nenabizena) {
            self::assertStringNotContainsString('<option value="' . $nenabizena . '">', $html);
            self::assertStringContainsString('>' . $nenabizena . '/a.pdf</a>', $html, 'Soubor se přesto vypíše');
        }
    }

    /**
     * Cesta jde ve formuláři jako text, takže se po cestě musí vrátit stejná.
     *
     * @test
     */
    public function souborSNazvemKterySeVeFormulariNezachovaNemaAkce(): void
    {
        $slozka = ZpracovaneObrazky::adresarObsahu() . '/' . self::slozka();
        file_put_contents($slozka . "/P\xF8\xEDru\xE8ka.pdf", self::pdfBajty());
        file_put_contents($slozka . '/ uvodni-mezera.pdf', self::pdfBajty());
        file_put_contents($slozka . '/normalni.pdf', self::pdfBajty());

        $html = (new SouboryWebu([
            self::slozka() => $this->nastaveni([NahravaniObrazku::PDF]),
        ]))->html();

        foreach (["P\xF8\xEDru\xE8ka.pdf", ' uvodni-mezera.pdf'] as $nazev) {
            $radek = self::radek($html, $nazev);
            self::assertStringContainsString('jen přes FTP', $radek, bin2hex($nazev));
            self::assertStringNotContainsString('<button', $radek, bin2hex($nazev));
        }
        self::assertStringContainsString('value="smazat"', self::radek($html, 'normalni.pdf'));
    }

    /**
     * @test
     */
    public function slozkaSCiselnymNazvemNerozbijeStranku(): void
    {
        $html = (new SouboryWebu([
            '2027' => $this->nastaveni([NahravaniObrazku::PDF]),
        ]))->html();

        self::assertStringContainsString('<code>soubory/obsah/2027/</code>', $html);
    }

    /**
     * @test
     */
    public function prazdnaSlozkaRiktaZeTuNicNeni(): void
    {
        $html = (new SouboryWebu([
            self::slozka() => $this->nastaveni([NahravaniObrazku::PDF]),
        ]))->html();

        self::assertStringContainsString('<td colspan="4"><i>Zatím tu žádný soubor není.</i></td>', $html, 'Přes všechny sloupce tabulky');
        self::assertStringContainsString('(0)', $html);
    }

    /**
     * @test
     */
    public function neexistujiciSlozkaSeVypiseJakoPrazdna(): void
    {
        $html = (new SouboryWebu(
            [
                'celohra' => $this->nastaveni([NahravaniObrazku::PDF]),
            ],
            sys_get_temp_dir() . '/gamecon-soubory-neexistuje-' . bin2hex(random_bytes(6)),
        ))->html();

        self::assertStringContainsString('Zatím tu žádný soubor není.', $html);
    }

    /**
     * @test
     */
    public function historickeSlozkyJsouNaKonciOddelenePouzeJednouCarou(): void
    {
        $html = (new SouboryWebu(
            [
                'stara'  => $this->nastaveni([NahravaniObrazku::PDF], 'Stará soutěž', true),
                'prvni'  => $this->nastaveni([NahravaniObrazku::PDF], 'První složka'),
                'starsi' => $this->nastaveni([NahravaniObrazku::PDF], 'Starší soutěž', true),
                'druha'  => $this->nastaveni([NahravaniObrazku::PDF], 'Druhá složka'),
            ],
            sys_get_temp_dir() . '/gamecon-soubory-neexistuje-' . bin2hex(random_bytes(6)),
        ))->html();

        $poradi = [
            strpos($html, 'První složka'),
            strpos($html, 'Druhá složka'),
            strpos($html, '<hr'),
            strpos($html, 'Stará soutěž'),
            strpos($html, 'Starší soutěž'),
        ];
        self::assertNotContains(false, $poradi);
        $serazene = $poradi;
        sort($serazene);
        self::assertSame($serazene, $poradi);
        self::assertSame(1, substr_count($html, '<hr'));
    }

    /**
     * @test
     */
    public function samotneHistorickeSlozkyNemajiCaruNahore(): void
    {
        $html = (new SouboryWebu(
            [
                'stara' => $this->nastaveni([NahravaniObrazku::PDF], 'Stará soutěž', true),
            ],
            sys_get_temp_dir() . '/gamecon-soubory-neexistuje-' . bin2hex(random_bytes(6)),
        ))->html();

        self::assertStringContainsString('Stará soutěž', $html);
        self::assertStringNotContainsString('<hr', $html);
    }

    /**
     * @test
     */
    public function mistrovstviVDrdJeVeVychozimSeznamuPosledniAHistoricke(): void
    {
        self::assertSame('drd', array_key_last(SouboryWebu::SLOZKY));
        self::assertTrue(SouboryWebu::SLOZKY['drd']['historie']);
        foreach (array_slice(SouboryWebu::SLOZKY, 0, -1) as $slozka => $nastaveni) {
            self::assertFalse($nastaveni['historie'] ?? false, $slozka);
        }
    }

    /**
     * @test
     */
    public function vsechnyVychoziSlozkyMajiNazevPopisAFormaty(): void
    {
        foreach (SouboryWebu::SLOZKY as $slozka => $nastaveni) {
            self::assertNotSame('', $nastaveni['nazev'], $slozka);
            self::assertNotSame('', $nastaveni['popis'], $slozka);
            self::assertNotSame([], $nastaveni['formaty'], $slozka);
            self::assertSame(
                [],
                array_diff($nastaveni['formaty'], [...NahravaniObrazku::DOKUMENTY, ...NahravaniObrazku::WEBOVE_OBRAZKY]),
                "Složka {$slozka} povoluje jiný formát než dokumenty a rastrové obrázky (SVG nejde z administrace bez kontroly)",
            );
        }
    }

    /**
     * Složku si organizátor vybírá podle toho, kam soubor patří, ne podle jeho typu.
     *
     * @test
     */
    public function kazdaVychoziSlozkaPrijimaVsechnyFormatyKterePrijimaStranka(): void
    {
        foreach (SouboryWebu::SLOZKY as $slozka => $nastaveni) {
            self::assertSame(
                [],
                array_diff([...NahravaniObrazku::DOKUMENTY, ...NahravaniObrazku::WEBOVE_OBRAZKY], $nastaveni['formaty']),
                "Složka {$slozka} nepřijímá všechny formáty, které stránka přijímá",
            );
        }
    }

    /**
     * @param list<string> $formaty
     *
     * @return array{nazev: string, popis: string, formaty: list<string>, historie: bool}
     */
    private function nastaveni(array $formaty, string $nazev = 'Testovací složka', bool $historie = false): array
    {
        return [
            'nazev'    => $nazev,
            'popis'    => 'Popis testovací složky',
            'formaty'  => $formaty,
            'historie' => $historie,
        ];
    }

    /**
     * Řádek tabulky se souborem, tedy vše mezi `<tr>` a `</tr>`, kde je jeho odkaz.
     */
    private static function radek(string $html, string $soubor): string
    {
        foreach (explode('</tr>', $html) as $radek) {
            if (str_contains($radek, '>' . htmlspecialchars($soubor) . '</a>')) {
                return $radek;
            }
        }
        self::fail("Ve výpisu chybí soubor {$soubor}");
    }
}
