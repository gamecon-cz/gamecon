<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Nahrávání obrázků přes skutečnou administraci (Apache, přihlášení, multipart formulář),
 * tak jak to dělá organizátor místo FTP.
 */
class NahravaniObrazkuVAdminuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    private const int ID_VYPRAVECE = 9801;
    private const int ID_BEZ_PRAV = 9802;
    private const int ID_STRANKY = 9801;
    private const string URL_STRANKY = 'test-obrazky-v-adminu';

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
        $obrazekVTextu = 'soubory/obsah/' . self::adresarObsahu() . '/mapa.png';

        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (%1$d, 'test-fotka-vypravec', 'Test', 'Vypravěč', 'test-fotka-vypravec@example.invalid'),
    (%2$d, 'test-fotka-bez-prav', 'Test', 'Bezprav', 'test-fotka-bez-prav@example.invalid')
SQL,
                self::ID_VYPRAVECE,
                self::ID_BEZ_PRAV,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO medailonky(id_uzivatele, o_sobe, drd) VALUES (%d, 'O mně', '')
SQL,
                self::ID_VYPRAVECE,
            ),
            // \n zpracuje až MariaDB, v textu stránky z toho jsou odřádkování
            sprintf(
                <<<'SQL'
INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi) VALUES (%d, '%s', '# Test\n\n![mapa](%s)', 0)
SQL,
                self::ID_STRANKY,
                self::URL_STRANKY,
                $obrazekVTextu,
            ),
            <<<'SQL'
INSERT INTO novinky(id, typ, vydat, url, nazev, autor, text)
VALUES (9801, 2, NOW(), 'test-obrazky-blog', 'Test obrázků', 'Autor', 'Text')
SQL,
            sprintf(
                <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES (9801, 'Test obrázků', 'test-obrazku-v-adminu', 1, %d, 2, 'Popis', 0, 5, 0, 0)
SQL,
                ROCNIK,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES (9801, %d)
SQL,
                self::ID_VYPRAVECE,
            ),
        ];
    }

    private static function adresarObsahu(): string
    {
        return 'test-obrazky-v-adminu-' . getmypid();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_ADMIN);
        $this->zdroje = sys_get_temp_dir() . '/gamecon-obrazky-v-adminu-' . bin2hex(random_bytes(6));
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
    public function organizatorNahrajeVymeniASmazeFotkuKMedailonku(): void
    {
        $fotky = ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . self::ID_VYPRAVECE;
        $this->kUklidu = [...$this->kUklidu, $fotky . '.png', $fotky . '.jpg'];
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'aktivity/medailonky?id=' . self::ID_VYPRAVECE;

        self::assertStringContainsString('Fotka chybí', $this->stahni($session, $url));

        $png = $this->png();
        self::assertSame(303, $this->odesli($session, $url, [
            'akceFotky' => 'nahrat',
            'fotka'     => new \CURLFile($png, 'image/png', 'portret.png'),
        ]));
        self::assertFileEquals($png, $fotky . '.png');
        $stranka = $this->stahni($session, $url);
        self::assertStringContainsString('Fotka je nahraná.', $stranka);
        self::assertStringContainsString('Smazat fotku', $stranka);

        // fotka z telefonu jako JPG nahradí PNG, dvě verze vedle sebe by si web vybíral náhodně
        self::assertSame(303, $this->odesli($session, $url, [
            'akceFotky' => 'nahrat',
            'fotka'     => new \CURLFile($this->jpg(), 'image/jpeg', 'IMG_0001.JPG'),
        ]));
        self::assertFileExists($fotky . '.jpg');
        self::assertFileDoesNotExist($fotky . '.png');

        self::assertSame(303, $this->odesli($session, $url, [
            'akceFotky' => 'smazat',
        ]));
        self::assertFileDoesNotExist($fotky . '.jpg');
        self::assertStringContainsString('Fotka je smazaná', $this->stahni($session, $url));
    }

    /**
     * @test
     */
    public function souborKteryNeniObrazekOdmitne(): void
    {
        $fotky = ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . self::ID_VYPRAVECE;
        $this->kUklidu[] = $fotky . '.png';
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'aktivity/medailonky?id=' . self::ID_VYPRAVECE;
        $skript = $this->zdroje . '/fotka.png';
        file_put_contents($skript, '<?php echo "ahoj";');

        self::assertSame(303, $this->odesli($session, $url, [
            'akceFotky' => 'nahrat',
            'fotka'     => new \CURLFile($skript, 'image/png', 'fotka.php.png'),
        ]));

        self::assertSame([], glob($fotky . '.*'));
        self::assertStringContainsString('Soubor není obrázek', $this->stahni($session, $url));
    }

    /**
     * @test
     */
    public function bezPravaNaMedailonkyNicNenahraje(): void
    {
        $fotky = ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . self::ID_VYPRAVECE;
        $this->kUklidu[] = $fotky . '.png';
        $session = $this->prihlas('test-fotka-bez-prav');

        self::assertSame(
            403,
            $this->odesli($session, 'aktivity/medailonky?id=' . self::ID_VYPRAVECE, [
                'akceFotky' => 'nahrat',
                'fotka'     => new \CURLFile($this->png(), 'image/png', 'a.png'),
            ]),
        );

        self::assertSame([], glob($fotky . '.*'));
    }

    /**
     * @test
     */
    public function uzivatelBezMedailonkuMuzeMitFotkuAZalozitSiMedailonek(): void
    {
        $stranka = $this->stahni($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'aktivity/medailonky?id=' . self::ID_BEZ_PRAV);

        self::assertStringContainsString('Uživatel zatím nemá medailonek.', $stranka);
        self::assertStringContainsString('name="akceFotky" value="nahrat"', $stranka);
    }

    /**
     * @test
     */
    public function prehledMedailonkuUkazeKdoMaFotku(): void
    {
        self::assertStringContainsString('<th>Fotka</th>', $this->stahni($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'aktivity/medailonky'));
    }

    /**
     * @test
     */
    public function editorAktivityOdkazujeNaMedailonkyVypravecu(): void
    {
        $editor = $this->stahni($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'aktivity/upravy?aktivitaId=9801');

        self::assertStringContainsString(
            '<a href="aktivity/medailonky?id=' . self::ID_VYPRAVECE . '" target="_blank">Test „test-fotka-vypravec“ Vypravěč</a> (bez fotky)',
            $editor,
        );
    }

    /**
     * @test
     */
    public function editorNovinkyNabidneNahraniObrazkuKPrispevku(): void
    {
        self::assertStringContainsString(
            'Uloží se do <code>soubory/obsah/blog/test-obrazky-blog/</code>',
            $this->stahni($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'web/novinka?id=9801'),
        );
    }

    /**
     * @test
     */
    public function editorTextuHlidaNeulozeneZmenyPredNahranimObrazku(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        foreach (['web/novinka?id=9801', 'web/editace-stranek?id=' . self::ID_STRANKY] as $url) {
            $editor = $this->stahni($session, $url);

            // widget hledá editor podle třídy, kterou mu dává DbForm
            self::assertMatchesRegularExpression('~<form[^>]*class="dbForm"~', $editor, $url);
            self::assertStringContainsString("querySelector('form.dbForm')", $editor, $url);
            self::assertStringContainsString("addEventListener('beforeunload'", $editor, $url);

            // Uložit je <button> kvůli ikoně, hlídání ho proto nesmí hledat jen mezi <input>
            self::assertStringContainsString('<button type="submit"><i class="fa fa-save" aria-hidden="true"></i> Uložit</button>', $editor, $url);
            self::assertStringContainsString("querySelectorAll('[type=submit]')", $editor, $url);
        }
    }

    /**
     * @test
     */
    public function odkazyZLogNaWebVedouNaKotvyVeVerejnychSablonach(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(
            4,
            preg_match_all('~Zobrazují se na <a href="([^"]+)"~', $this->stahni($session, 'web/loga'), $shody),
        );
        self::assertCount(4, array_unique($shody[1]));
        foreach ($shody[1] as $odkaz) {
            $odkaz = html_entity_decode($odkaz);
            $casti = parse_url($odkaz);
            self::assertArrayHasKey('fragment', $casti, $odkaz);

            // prohlížeč na kotvu odskočí jen tehdy, když ji veřejná stránka opravdu má
            self::assertStringContainsString(
                'id="' . $casti['fragment'] . '"',
                $this->stahniVerejnou($session, $casti['path']),
                $odkaz,
            );
        }
    }

    /**
     * @test
     */
    public function obrazekEshopuSeObjeviVSeznamuSPopiskem(): void
    {
        $nazev = '99_Testovací předmět ' . getmypid();
        $soubor = ZpracovaneObrazky::adresarObrazkuEshopu() . '/' . $nazev . '.png';
        $this->uklidNoveAdresare(dirname($soubor));
        $this->kUklidu[] = $soubor;
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'finance/shop', [
            'obrazkyEshopuAkce' => 'nahrat',
            'nazev'             => $nazev,
            'obrazekEshopu'     => new \CURLFile($this->png(), 'image/png', 'cokoli.png'),
        ]));

        self::assertFileExists($soubor);
        self::assertStringContainsString('Testovací předmět ' . getmypid(), $this->stahni($session, 'finance/shop'));

        self::assertSame(303, $this->odesli($session, 'finance/shop', [
            'obrazkyEshopuAkce' => 'smazat',
            'soubor'            => basename($soubor),
        ]));
        self::assertFileDoesNotExist($soubor);
    }

    /**
     * @test
     */
    public function obrazekVTextuStrankyNahrajeANoveNahraneSmaze(): void
    {
        $obsah = ZpracovaneObrazky::adresarObsahu();
        $this->uklidNoveAdresare($obsah . '/' . self::adresarObsahu());
        $this->uklidNoveAdresare($obsah . '/stranky/' . self::URL_STRANKY);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $url = 'web/editace-stranek?id=' . self::ID_STRANKY;

        self::assertStringContainsString('na serveru chybí', $this->stahni($session, $url));

        // text na obrázek odkazuje, ale na serveru ještě není
        $mapa = $this->png();
        self::assertSame(303, $this->odesli($session, $url, [
            'obrazkyObsahuAkce' => 'nahradit',
            'cesta'             => self::adresarObsahu() . '/mapa.png',
            'obrazekObsahu'     => new \CURLFile($mapa, 'image/png', 'mapa-2026.png'),
        ]));
        self::assertFileEquals($mapa, $obsah . '/' . self::adresarObsahu() . '/mapa.png');

        // cesta, na kterou text neodkazuje, nahradit nejde
        self::assertSame(303, $this->odesli($session, $url, [
            'obrazkyObsahuAkce' => 'nahradit',
            'cesta'             => 'sponzori/podvrh.png',
            'obrazekObsahu'     => new \CURLFile($this->png(), 'image/png', 'podvrh.png'),
        ]));
        self::assertFileDoesNotExist($obsah . '/sponzori/podvrh.png');

        self::assertSame(303, $this->odesli($session, $url, [
            'obrazkyObsahuAkce' => 'nahrat',
            'nazev'             => 'Nová členka Rady',
            'obrazekObsahu'     => new \CURLFile($this->jpg(), 'image/jpeg', 'IMG_0002.jpg'),
        ]));
        $novy = $obsah . '/stranky/' . self::URL_STRANKY . '/nova-clenka-rady.jpg';
        self::assertFileExists($novy);
        $stranka = $this->stahni($session, $url);
        self::assertStringContainsString('![](soubory/obsah/stranky/' . self::URL_STRANKY . '/nova-clenka-rady.jpg)', $stranka);
        // zápis do textu je v seznamu obrázků, hláška po nahrání je jen potvrzení
        self::assertStringContainsString('Obrázek je nahraný.', $stranka);
        self::assertStringNotContainsString('do textu ho vložíš', $stranka);
        self::assertStringContainsString('<i class="fa fa-upload" aria-hidden="true"></i> Nahrát</button>', $stranka);
        self::assertStringContainsString('<i class="fa fa-upload" aria-hidden="true"></i> Nahradit</button>', $stranka);
        self::assertStringContainsString('<i class="fa fa-trash" aria-hidden="true"></i> Smazat</button>', $stranka);

        self::assertSame(303, $this->odesli($session, $url, [
            'obrazkyObsahuAkce' => 'smazat',
            'cesta'             => 'stranky/' . self::URL_STRANKY . '/nova-clenka-rady.jpg',
        ]));
        self::assertFileDoesNotExist($novy);
    }

    /**
     * @test
     */
    public function obrazekLinieNahrajeSpolecnaSluzba(): void
    {
        $idTypu = (int) dbFetchSingle(<<<'SQL'
SELECT id_typu FROM akce_typy WHERE zobrazit_v_menu = 1 ORDER BY id_typu LIMIT 1
SQL);
        self::assertGreaterThan(0, $idTypu, 'Testovací DB nemá linii zobrazenou v menu');
        $obrazek = ZpracovaneObrazky::adresarObrazkuLinii() . '/org_' . $idTypu;
        if (glob($obrazek . '.*') !== []) {
            self::markTestSkipped("Linie {$idTypu} už má vlastní obrázek, nechceme ho přepsat");
        }
        $this->kUklidu = [...$this->kUklidu, $obrazek . '.png', $obrazek . '.gif'];
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $cestaVTextu = 'soubory/systemove/linie-ikony/org_' . $idTypu . '.png';

        // výchozí avatar není obrázek té linie, zápis do textu k němu nenabízíme
        $strankaPredNahranim = $this->stahni($session, 'web/hlavicky-linii');
        self::assertStringNotContainsString($cestaVTextu, $strankaPredNahranim);
        self::assertStringContainsString('Vlastní obrázek chybí, používá se fallback.', $strankaPredNahranim);

        self::assertSame(303, $this->odesli($session, 'web/hlavicky-linii', [
            'action'  => 'upload-line-header-image',
            'id_typu' => (string) $idTypu,
            'obrazek' => new \CURLFile($this->png(), 'image/png', 'linie.png'),
        ]));
        self::assertFileExists($obrazek . '.png');

        $stranka = $this->stahni($session, 'web/hlavicky-linii');
        self::assertStringContainsString('value="![](' . $cestaVTextu . ')"', $stranka);
        self::assertStringContainsString(
            'value="&lt;img src=&quot;' . $cestaVTextu . '&quot; alt=&quot;&quot;&gt;"',
            $stranka,
        );
        // cesta je v hotových zápisech, vlastní obrázek nepotřebuje ani stav, ani samostatný řádek s cestou
        self::assertStringNotContainsString('Používá se vlastní obrázek', $stranka);
        self::assertStringNotContainsString('Cesta: <code>' . $cestaVTextu, $stranka);

        self::assertSame(303, $this->odesli($session, 'web/hlavicky-linii', [
            'action'  => 'delete-line-header-image',
            'id_typu' => (string) $idTypu,
        ]));
        self::assertSame([], glob($obrazek . '.*'));
        self::assertStringNotContainsString($cestaVTextu, $this->stahni($session, 'web/hlavicky-linii'));
    }

    /**
     * @test
     */
    public function mazaniLogaNesmazeSouborMimoAdresarLog(): void
    {
        $adresarLog = ZpracovaneObrazky::adresarSponzoruPrehled();
        (new Filesystem())->mkdir($adresarLog);
        $navnada = dirname($adresarLog) . '/' . self::adresarObsahu() . '-navnada.png';
        file_put_contents($navnada, 'nesmí zmizet');
        $this->kUklidu[] = $navnada;

        $this->odesli($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'web/loga', [
            'action'   => 'remove_sponzor_prehled',
            'filename' => '../' . basename($navnada),
        ]);

        self::assertFileExists($navnada);
    }

    /**
     * @test
     */
    public function poradiLogaSeMeniPresAdministraciBezPristupuKCsv(): void
    {
        $adresar = ZpracovaneObrazky::adresarSponzoruPrehled();
        $razeni = $adresar . '/RAZENI.csv';
        // v adresáři může být skutečné pořadí, které test nesmí zničit
        $puvodniRazeni = is_file($razeni)
            ? (string) file_get_contents($razeni)
            : null;
        $this->uklidNoveAdresare($adresar);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $soubory = [];
        foreach (['a', 'b', 'c'] as $pismeno) {
            $host = sprintf('test-poradi-%s-%d.example.com', $pismeno, getmypid());
            $soubory[$pismeno] = $host . '.png';
            $this->kUklidu[] = $adresar . '/' . $soubory[$pismeno];
        }

        try {
            foreach ($soubory as $soubor) {
                $this->odesli($session, 'web/loga', [
                    'action'    => 'upload_sponzor_prehled',
                    'url'       => 'https://' . basename($soubor, '.png'),
                    'logo_file' => new \CURLFile($this->png(), 'image/png', 'logo.png'),
                ]);
                self::assertFileExists($adresar . '/' . $soubor);
            }
            self::assertSame(
                [$soubory['a'], $soubory['b'], $soubory['c']],
                $this->poradiTestovacichLog($session, 'sponzor_prehled'),
                'Bez uloženého pořadí jdou loga podle abecedy',
            );

            [$kod, $odpoved] = $this->ulozPoradiLog($session, 'sponzor_prehled', [$soubory['c'], '../../x.png', $soubory['a']]);

            self::assertSame(200, $kod);
            self::assertSame([
                'ok' => true,
            ], $odpoved);
            self::assertSame(
                [$soubory['c'], $soubory['a'], $soubory['b']],
                $this->poradiTestovacichLog($session, 'sponzor_prehled'),
                'Logo, které v odeslaném pořadí chybí, se zařadí za ostatní',
            );
            self::assertStringNotContainsString('..', (string) file_get_contents($razeni));

            $verejnaStranka = $this->stahniVerejnou($session, parse_url(URL_WEBU, PHP_URL_PATH) . '/sponzori');
            $pozice = array_map(
                fn (string $soubor): int|false => strpos($verejnaStranka, 'href="https://' . basename($soubor, '.png') . '"'),
                [$soubory['c'], $soubory['a'], $soubory['b']],
            );
            self::assertNotContains(false, $pozice, 'Loga jsou na veřejné stránce');
            $serazenePozice = $pozice;
            sort($serazenePozice);
            self::assertSame($serazenePozice, $pozice, 'Veřejná stránka drží pořadí z administrace');

            $stranka = $this->stahni($session, 'web/loga');
            self::assertSame(4, substr_count($stranka, 'data-typ="'));
            // ikona říká, že se loga dají přetahovat, každá karta s logem ji má
            self::assertGreaterThanOrEqual(3, substr_count($stranka, 'class="logo-item"'));
            self::assertSame(
                substr_count($stranka, 'class="logo-item"'),
                substr_count($stranka, 'class="fa fa-arrows logo-uchyt"'),
            );
            self::assertStringNotContainsString('RAZENI', $stranka, 'Soubor s pořadím se v administraci neukazuje');
        } finally {
            if ($puvodniRazeni === null) {
                (new Filesystem())->remove($razeni);
            } else {
                file_put_contents($razeni, $puvodniRazeni);
            }
        }
    }

    /**
     * @test
     */
    public function neplatnePoradiLogaAdministraceOdmitne(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        [$kod, $odpoved] = $this->ulozPoradiLog($session, 'sponzor_prehled', ['neexistuje.cz.png']);
        self::assertSame(400, $kod);
        self::assertFalse($odpoved['ok']);

        [$kod, $odpoved] = $this->ulozPoradiLog($session, 'neznama_skupina', ['neexistuje.cz.png']);
        self::assertSame(400, $kod);
        self::assertSame('Neznámá skupina log.', $odpoved['error']);

        // skupina poslaná jako pole nesmí skončit chybou serveru
        $odpoved = $this->odesliSTelem($session, 'web/loga', [
            'action'    => 'save_razeni',
            'typ[0]'    => 'sponzor_prehled',
            'poradi[0]' => 'neexistuje.cz.png',
        ]);
        self::assertSame(400, $odpoved['kod']);
        self::assertSame('Neznámá skupina log.', json_decode($odpoved['telo'], true, flags: JSON_THROW_ON_ERROR)['error']);
    }

    /**
     * @param list<string> $poradi
     *
     * @return array{0: int, 1: array{ok: bool, error?: string}}
     */
    private function ulozPoradiLog(string $session, string $typ, array $poradi): array
    {
        $pole = [
            'action' => 'save_razeni',
            'typ'    => $typ,
        ];
        foreach ($poradi as $index => $soubor) {
            $pole["poradi[{$index}]"] = $soubor;
        }
        $odpoved = $this->odesliSTelem($session, 'web/loga', $pole);

        return [$odpoved['kod'], json_decode($odpoved['telo'], true, flags: JSON_THROW_ON_ERROR)];
    }

    /**
     * @return list<string> názvy souborů log, které založil test, v pořadí, v jakém je ukazuje administrace
     */
    private function poradiTestovacichLog(string $session, string $typ): array
    {
        $stranka = $this->stahni($session, 'web/loga');
        self::assertSame(1, preg_match('~data-typ="' . $typ . '">(.*?)<div class="logo-poradi-stav"~s', $stranka, $skupina), $typ);
        preg_match_all('~data-soubor="([^"]+)"~', $skupina[1], $shody);

        return array_values(array_filter(
            $shody[1],
            fn (string $soubor): bool => str_starts_with($soubor, 'test-poradi-'),
        ));
    }

    private function png(): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '.png';
        imagepng(imagecreatetruecolor(40, 40), $cesta);

        return $cesta;
    }

    private function jpg(): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '.jpg';
        imagejpeg(imagecreatetruecolor(40, 40), $cesta);

        return $cesta;
    }
}
