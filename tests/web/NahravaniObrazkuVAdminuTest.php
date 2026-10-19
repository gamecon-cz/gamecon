<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Login\Login;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Nahrávání obrázků přes skutečnou administraci (Apache, přihlášení, multipart formulář),
 * tak jak to dělá organizátor místo FTP.
 */
class NahravaniObrazkuVAdminuTest extends AbstractTestWeb
{
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
            sprintf("INSERT INTO medailonky(id_uzivatele, o_sobe, drd) VALUES (%d, 'O mně', '')", self::ID_VYPRAVECE),
            sprintf(
                "INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi) VALUES (%d, '%s', '# Test\n\n![mapa](%s)', 0)",
                self::ID_STRANKY,
                self::URL_STRANKY,
                $obrazekVTextu,
            ),
            "INSERT INTO novinky(id, typ, vydat, url, nazev, autor, text) VALUES (9801, 2, NOW(), 'test-obrazky-blog', 'Test obrázků', 'Autor', 'Text')",
            sprintf(
                <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES (9801, 'Test obrázků', 'test-obrazku-v-adminu', 1, %d, 2, 'Popis', 0, 5, 0, 0)
SQL,
                ROCNIK,
            ),
            sprintf('INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES (9801, %d)', self::ID_VYPRAVECE),
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
        $idTypu = (int) dbFetchSingle('SELECT id_typu FROM akce_typy WHERE zobrazit_v_menu = 1 ORDER BY id_typu LIMIT 1');
        self::assertGreaterThan(0, $idTypu, 'Testovací DB nemá linii zobrazenou v menu');
        $obrazek = ZpracovaneObrazky::adresarObrazkuLinii() . '/org_' . $idTypu;
        if (glob($obrazek . '.*') !== []) {
            self::markTestSkipped("Linie {$idTypu} už má vlastní obrázek, nechceme ho přepsat");
        }
        $this->kUklidu = [...$this->kUklidu, $obrazek . '.png', $obrazek . '.gif'];
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/hlavicky-linii', [
            'action'  => 'upload-line-header-image',
            'id_typu' => (string) $idTypu,
            'obrazek' => new \CURLFile($this->png(), 'image/png', 'linie.png'),
        ]));
        self::assertFileExists($obrazek . '.png');

        self::assertSame(303, $this->odesli($session, 'web/hlavicky-linii', [
            'action'  => 'delete-line-header-image',
            'id_typu' => (string) $idTypu,
        ]));
        self::assertSame([], glob($obrazek . '.*'));
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
     * Test zapisuje do skutečného stromu webu, uklidí proto i nadřazené adresáře, které sám založí.
     */
    private function uklidNoveAdresare(string $adresar): void
    {
        $nejvyssiNovy = null;
        while (! is_dir($adresar)) {
            $nejvyssiNovy = $adresar;
            $adresar = dirname($adresar);
        }
        if ($nejvyssiNovy !== null) {
            $this->kUklidu[] = $nejvyssiNovy;
        }
    }

    /**
     * Přihlášení posíláme na adresu modulu. Na `/admin` a `/admin/` (to dělá loginToAdmin()) Apache
     * přesměruje a curl z POSTu udělá GET, takže se přihlašovací údaje k PHP vůbec nedostanou.
     */
    private function prihlas(string $login): string
    {
        $cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
        $this->kUklidu[] = $cookieFile;
        $curl = $this->curl($cookieFile, 'aktivity/medailonky');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, [
            Login::LOGIN_INPUT_NAME    => $login,
            Login::PASSWORD_INPUT_NAME => UNIVERZALNI_HESLO,
        ]);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));
        unset($curl); // až teď curl zapíše cookies do souboru

        self::assertStringNotContainsString(
            'name="' . Login::PASSWORD_INPUT_NAME . '"',
            (string) $odpoved,
            "Přihlášení '{$login}' se nepovedlo",
        );

        return $cookieFile;
    }

    /**
     * @return int HTTP kód odpovědi, přesměrování se nenásledují
     */
    private function odesli(string $cookieFile, string $url, array $pole): int
    {
        $curl = $this->curl($cookieFile, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $pole);
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));

        return curl_getinfo($curl, CURLINFO_HTTP_CODE);
    }

    private function stahni(string $cookieFile, string $url): string
    {
        $curl = $this->curl($cookieFile, $url);
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));
        self::assertSame(200, curl_getinfo($curl, CURLINFO_HTTP_CODE), (string) $odpoved);

        return (string) $odpoved;
    }

    private function curl(string $cookieFile, string $url): \CurlHandle
    {
        $curl = curl_init('http://localhost/' . basename(__DIR__ . '/../../admin') . '/' . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $cookieFile);
        // hlášky (oznámení, chyby) chodí v cookies až na další stránku
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookieFile);
        $this->setLocalServerCookieWithGameconTestDb($curl);

        return $curl;
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
