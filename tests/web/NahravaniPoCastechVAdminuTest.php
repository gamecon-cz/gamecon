<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Tests\Model\Web\TestovaciDokumenty;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Nahrávání velkého souboru po částech přes skutečnou administraci: JSON požadavky tak, jak je posílá
 * admin/files/nahravani-po-castech.js, a formulář, který místo souboru pošle jeho `id`.
 */
class NahravaniPoCastechVAdminuTest extends AbstractTestWeb
{
    use TestovaciDokumenty;
    use ZadostiDoAdmina;

    private const int ID_BEZ_PRAV = 9812;

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
VALUES (%d, 'test-po-castech-bez-prav', 'Test', 'Bezprav', 'test-po-castech-bez-prav@example.invalid')
SQL,
                self::ID_BEZ_PRAV,
            ),
        ];
    }

    private static function znacka(): string
    {
        return 'test-po-castech-' . getmypid();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_ADMIN);
        $this->zdroje = sys_get_temp_dir() . '/gamecon-po-castech-v-adminu-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->zdroje);
        $this->kUklidu[] = $this->zdroje;
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        parent::tearDown();
    }

    /**
     * Velký soubor, jaký FTP dovolilo: ZIP o desítkách MB, tedy víc částí, než kolik jich jde poslat jedním požadavkem.
     *
     * @test
     */
    public function velkyZipProjdePoCastechAUlozeSeJakoPriBeznemNahrani(): void
    {
        $drd = ZpracovaneObrazky::adresarObsahu() . '/drd';
        $this->uklidNoveAdresare($drd);
        $this->kUklidu[] = $drd . '/rocnik_2099';
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $zip = $this->velkyZip(60);
        $nazev = 'Dobrodružství ' . self::znacka();

        $id = $this->posliSouborPoCastech($session, $zip, 'GC99_Dobrodruzstvi.zip');
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'drd',
            'podslozka'       => 'Ročník 2099',
            'nazev'           => $nazev,
            'nahranoId'       => $id,
        ]));

        $ulozeno = $drd . '/rocnik_2099/' . NahravaniObrazku::bezpecnyNazevDokumentu($nazev) . '.zip';
        self::assertFileEquals($zip, $ulozeno);
        $stranka = $this->stahni($session, 'web/soubory');
        self::assertStringContainsString('Soubor nahrán.', $stranka);
        self::assertStringContainsString('>rocnik_2099/' . basename($ulozeno) . '</a>', $stranka, 'Výsledný název je v tabulce souborů');
    }

    /**
     * @test
     */
    public function souborPoCastechSeNeulozeDvakrat(): void
    {
        $celohra = ZpracovaneObrazky::adresarObsahu() . '/celohra';
        $this->uklidNoveAdresare($celohra);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $id = $this->posliSouborPoCastech($session, $this->pdfSOdsazenim(5), 'dopis.pdf');
        $formular = [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'celohra',
            'nazev'           => self::znacka() . '-jedenkrat',
            'nahranoId'       => $id,
        ];

        self::assertSame(303, $this->odesli($session, 'web/soubory', $formular));
        $this->kUklidu[] = $celohra . '/' . NahravaniObrazku::bezpecnyNazevDokumentu($formular['nazev']) . '.pdf';
        self::assertStringContainsString('Soubor nahrán.', $this->stahni($session, 'web/soubory'));

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            ...$formular,
            'nazev' => self::znacka() . '-podruhe',
        ]));
        self::assertStringContainsString('Nahrávání už neexistuje', $this->stahni($session, 'web/soubory'));
        self::assertSame([], glob($celohra . '/*' . self::znacka() . '-podruhe*') ?: []);
    }

    /**
     * @test
     */
    public function souborPoCastechNahradiExistujiciSouborNaMiste(): void
    {
        $celohra = ZpracovaneObrazky::adresarObsahu() . '/celohra';
        $this->uklidNoveAdresare($celohra);
        (new Filesystem())->mkdir($celohra);
        $soubor = $celohra . '/' . self::znacka() . '-nahrazeny.pdf';
        $this->kUklidu[] = $soubor;
        file_put_contents($soubor, self::pdfBajty());
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $novy = $this->pdfSOdsazenim(6);

        $id = $this->posliSouborPoCastech($session, $novy, 'nova-verze.pdf');
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahradit',
            'slozka'          => 'celohra',
            'cesta'           => basename($soubor),
            'nahranoId'       => $id,
        ]));

        self::assertFileEquals($novy, $soubor);
        self::assertStringContainsString('nahrán. Kdo ho už viděl', $this->stahni($session, 'web/soubory'));
    }

    /**
     * @test
     */
    public function souborPoCastechProjdeStejnouKontrolouTypuJakoKazdyJiny(): void
    {
        $celohra = ZpracovaneObrazky::adresarObsahu() . '/celohra';
        $this->uklidNoveAdresare($celohra);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $skript = $this->zdroje . '/skript.pdf';
        file_put_contents($skript, '<?php echo "ahoj"; // ' . str_repeat('x', 5 * 1024 * 1024));

        $id = $this->posliSouborPoCastech($session, $skript, 'skript.pdf');
        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'celohra',
            'nazev'           => self::znacka() . '-skript',
            'nahranoId'       => $id,
        ]));

        self::assertSame([], glob($celohra . '/*' . self::znacka() . '-skript*') ?: []);
        self::assertStringContainsString('Soubor tohoto typu tu nejde nahrát', $this->stahni($session, 'web/soubory'));
    }

    /**
     * @test
     */
    public function nedokoncenyNahranySouborSeVeFormulariOdmitne(): void
    {
        $celohra = ZpracovaneObrazky::adresarObsahu() . '/celohra';
        $this->uklidNoveAdresare($celohra);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $pdf = $this->pdfSOdsazenim(6);
        $zacatek = $this->posli($session, 'zahaj', [], http_build_query([
            'velikost' => filesize($pdf),
            'nazev'    => 'dopis.pdf',
        ]), 'application/x-www-form-urlencoded');
        $id = $zacatek['json']['id'];
        $this->posli($session, 'cast', [
            'id'    => $id,
            'index' => 0,
        ], (string) file_get_contents($pdf, false, null, 0, $zacatek['json']['castBajtu']));

        self::assertSame(303, $this->odesli($session, 'web/soubory', [
            'souboryWebuAkce' => 'nahrat',
            'slozka'          => 'celohra',
            'nazev'           => self::znacka() . '-polovina',
            'nahranoId'       => $id,
        ]));

        self::assertSame([], glob($celohra . '/*' . self::znacka() . '-polovina*') ?: []);
        self::assertStringContainsString('Soubor se nenahrál celý', $this->stahni($session, 'web/soubory'));
    }

    /**
     * Formulář odmítnutý dřív, než na soubor přijde řada (špatná podsložka, cíl, který zmizel), nesmí nechat mezisoubor
     * ležet: `id` je na jedno použití a rozpracované nahrávání zabírá místo.
     *
     * @test
     */
    public function odmitnutyFormularSmazeMezisoubor(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $pdf = $this->pdfSOdsazenim(5);
        $zpusoby = [
            'příliš hluboká podsložka' => [
                'souboryWebuAkce' => 'nahrat',
                'slozka'          => 'celohra',
                'podslozka'       => 'a/b/c',
                'nazev'           => self::znacka() . '-hluboko',
            ],
            'neexistující soubor k nahrazení' => [
                'souboryWebuAkce' => 'nahradit',
                'slozka'          => 'celohra',
                'cesta'           => self::znacka() . '-neexistuje.pdf',
            ],
            'složka mimo seznam' => [
                'souboryWebuAkce' => 'nahrat',
                'slozka'          => 'stranky',
                'nazev'           => self::znacka() . '-mimo',
            ],
        ];

        foreach ($zpusoby as $popis => $formular) {
            $id = $this->posliSouborPoCastech($session, $pdf, 'dopis.pdf');
            $mezisoubor = __DIR__ . '/../../cache/private/tests/nahravani-po-castech/' . $id;
            self::assertDirectoryExists($mezisoubor, "Před odesláním formuláře ({$popis}) mezisoubor je");

            self::assertSame(303, $this->odesli($session, 'web/soubory', [
                ...$formular,
                'nahranoId' => $id,
            ]));

            // Apache ho smazal, ale tenhle proces si pamatuje, že adresář existoval
            clearstatcache();
            self::assertDirectoryDoesNotExist($mezisoubor, "Odmítnutý formulář ({$popis}) nechal mezisoubor ležet");
        }
    }

    /**
     * @test
     */
    public function nahraniVetsiNezLimitAplikaceSeOdmitneUzNaZacatku(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $odpoved = $this->posli($session, 'zahaj', [], http_build_query([
            'velikost' => (SOUBOR_MAX_MB + 1) * 1024 * 1024,
            'nazev'    => 'obri.zip',
        ]), 'application/x-www-form-urlencoded');

        self::assertSame(422, $odpoved['kod']);
        self::assertFalse($odpoved['json']['ok']);
        self::assertStringContainsString('Soubor je příliš velký, maximum je', $odpoved['json']['zprava']);
    }

    /**
     * @test
     */
    public function castSNespravnouDelkouSeOdmitne(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);
        $zacatek = $this->posli($session, 'zahaj', [], http_build_query([
            'velikost' => 3 * 1024 * 1024 * 4,
            'nazev'    => 'a.zip',
        ]), 'application/x-www-form-urlencoded');
        $id = $zacatek['json']['id'];

        $odpoved = $this->posli($session, 'cast', [
            'id'    => $id,
            'index' => 0,
        ], str_repeat('a', 100));

        self::assertSame(422, $odpoved['kod']);
        self::assertStringContainsString('nahrávání se musí začít znovu', $odpoved['json']['zprava']);
        $this->posli($session, 'zrus', [], http_build_query([
            'id' => $id,
        ]), 'application/x-www-form-urlencoded');
    }

    /**
     * @test
     */
    public function pozadavekBezHlavickyProhlizeceSeOdmitne(): void
    {
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $odpoved = $this->posli(
            $session,
            'zahaj',
            [],
            http_build_query([
                'velikost' => 100,
                'nazev'    => 'a.zip',
            ]),
            'application/x-www-form-urlencoded',
            sHlavickou: false,
        );

        self::assertSame(400, $odpoved['kod']);
        self::assertFalse($odpoved['json']['ok']);
    }

    /**
     * @test
     */
    public function bezPravaNaWebSeNahravaniNezacne(): void
    {
        $session = $this->prihlas('test-po-castech-bez-prav');

        $odpoved = $this->posli($session, 'zahaj', [], http_build_query([
            'velikost' => 100,
            'nazev'    => 'a.zip',
        ]), 'application/x-www-form-urlencoded');

        self::assertSame(403, $odpoved['kod']);
        self::assertNull($odpoved['json']);
    }

    /**
     * @test
     */
    public function neprihlasenySpravceNenahraje(): void
    {
        $odpoved = $this->posli(
            (string) tempnam(sys_get_temp_dir(), 'cookie'),
            'zahaj',
            [],
            http_build_query([
                'velikost' => 100,
                'nazev'    => 'a.zip',
            ]),
            'application/x-www-form-urlencoded',
        );

        self::assertNull($odpoved['json'], 'Bez přihlášení přijde přihlašovací stránka, ne odpověď nahrávání');
    }

    /**
     * @test
     */
    public function strankaNabidnePosilaniPoCastechVsemFormularum(): void
    {
        $stranka = $this->stahni($this->prihlas(\Uzivatel::SYSTEM_LOGIN), 'web/soubory');

        self::assertGreaterThanOrEqual(
            4,
            substr_count($stranka, 'data-nahravani-po-castech data-nahravani-prah="'),
            'Formulář pro nahrání v každé složce a pro nahrazení každého souboru',
        );
        self::assertStringContainsString('<script src="files/nahravani-po-castech.js?v=', $stranka);
        self::assertStringContainsString('nejvýš ' . NahravaniObrazku::lidskaVelikost(SOUBOR_MAX_MB * 1024 * 1024), $stranka);
    }

    /**
     * @return string id nahrávání, které je celé poslané
     */
    private function posliSouborPoCastech(string $session, string $cesta, string $nazev): string
    {
        $zacatek = $this->posli($session, 'zahaj', [], http_build_query([
            'velikost' => filesize($cesta),
            'nazev'    => $nazev,
        ]), 'application/x-www-form-urlencoded');
        self::assertSame(200, $zacatek['kod'], $zacatek['telo']);
        $id = $zacatek['json']['id'];
        $castBajtu = $zacatek['json']['castBajtu'];

        $soubor = fopen($cesta, 'rb');
        self::assertNotFalse($soubor);
        try {
            for ($index = 0; ! feof($soubor); ++$index) {
                $cast = (string) fread($soubor, $castBajtu);
                if ($cast === '') {
                    break;
                }
                $odpoved = $this->posli($session, 'cast', [
                    'id'    => $id,
                    'index' => $index,
                ], $cast);
                self::assertSame(200, $odpoved['kod'], $odpoved['telo']);
                self::assertTrue($odpoved['json']['ok']);
            }
        } finally {
            fclose($soubor);
        }

        return $id;
    }

    /**
     * @param array<string, int|string> $dotaz
     *
     * @return array{kod: int, telo: string, json: array<string, mixed>|null}
     */
    private function posli(
        string $cookieFile,
        string $krok,
        array $dotaz,
        string $telo,
        string $typTela = 'application/octet-stream',
        bool $sHlavickou = true,
    ): array {
        $curl = $this->curl($cookieFile, 'web/soubory?' . http_build_query([
            'nahravaniPoCastech' => $krok,
        ] + $dotaz));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $telo);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $typTela,
            ...($sHlavickou ? ['X-Gamecon-Nahravani: 1'] : []),
        ]);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));

        return [
            'kod'  => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'telo' => (string) $odpoved,
            'json' => json_decode((string) $odpoved, true),
        ];
    }

    /**
     * ZIP bez komprese, aby měl skutečně tolik megabajtů, kolik se řekne.
     */
    private function velkyZip(int $megabajtu): string
    {
        $data = $this->zdroje . '/data.bin';
        $soubor = fopen($data, 'wb');
        for ($blok = 0; $blok < $megabajtu / 4; ++$blok) {
            fwrite($soubor, random_bytes(4 * 1024 * 1024));
        }
        fclose($soubor);
        $cesta = $this->zdroje . '/velky.zip';
        $zip = new \ZipArchive();
        $zip->open($cesta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($data, 'data.bin');
        $zip->setCompressionName('data.bin', \ZipArchive::CM_STORE);
        $zip->close();
        unlink($data);

        return $cesta;
    }

    /**
     * PDF o několika MB, tedy z víc než jedné části.
     */
    private function pdfSOdsazenim(int $megabajtu): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($cesta, self::pdfBajty() . str_repeat('% vata ' . "\n", $megabajtu * 1024 * 1024 / 8));

        return $cesta;
    }
}
