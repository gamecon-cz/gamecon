<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\NahravaniObrazkuException;
use Gamecon\Web\PouzitiObrazku;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

class PouzitiObrazkuTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $obrazek = 'soubory/obsah/' . self::adresar() . '/obrazek_1.jpg';
        // podtržítko je v LIKE zástupný znak, tenhle odkaz se s obrazek_1.jpg splést nesmí
        $podobny = 'soubory/obsah/' . self::adresar() . '/obrazekX1.jpg';
        $zakodovany = 'soubory/obsah/' . self::adresar() . '/m%C3%A1j.png';
        $dvakrat = 'soubory/obsah/' . self::adresar() . '/Dvakrat.jpg';
        $rok = ROCNIK;

        return [
            <<<SQL
INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi)
VALUES
    (9901, 'kontakty-test', '<img class="leftOrg" src="{$obrazek}">', 0),
    (9902, 'podobna-test', '![]({$podobny})', 0),
    (9903, 'zakodovana-test', '![]({$zakodovany})', 0),
    (9904, 'dvakrat-test', '![]({$dvakrat}?v=2) a znovu <a href="/{$dvakrat}#vpravo">tady</a>', 0)
SQL,
            <<<SQL
INSERT INTO novinky(id, typ, vydat, url, nazev, autor, text)
VALUES (9901, 2, NOW(), 'test-pouziti-obrazku', 'Testovací blog', 'Autor', 'Úvod ![](https://gamecon.cz/{$obrazek})')
SQL,
            <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES (9901, 'Testovací aktivita', 'testovaci-aktivita-pouziti-obrazku', 1, {$rok}, 2, 'Popis ![]({$obrazek})', 0, 5, 0, 0)
SQL,
            <<<SQL
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES (9901, 'test-vypravec', 'Test', 'Vypravěč', 'test-vypravec@example.invalid')
SQL,
            <<<SQL
INSERT INTO medailonky(id_uzivatele, o_sobe, drd)
VALUES (9901, '', 'Profil ![]({$obrazek})')
SQL,
        ];
    }

    private static function adresar(): string
    {
        return 'test-pouziti-obrazku-' . getmypid();
    }

    private static function absolutniCesta(string $soubor): string
    {
        return ZpracovaneObrazky::adresarObsahu() . '/' . self::adresar() . '/' . $soubor;
    }

    private ?string $ulozisteVenku = null;

    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->mkdir(ZpracovaneObrazky::adresarObsahu() . '/' . self::adresar());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(array_filter([
            ZpracovaneObrazky::adresarObsahu() . '/' . self::adresar(),
            $this->ulozisteVenku,
        ]));
        parent::tearDown();
    }

    /**
     * web/soubory může být symlink na sdílené úložiště, skutečná cesta souboru pak vede mimo web.
     */
    private function nahravaniSKorenemNaJinemUlozisti(): NahravaniObrazku
    {
        $this->ulozisteVenku = sys_get_temp_dir() . '/gamecon-pouziti-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->ulozisteVenku);
        file_put_contents($this->ulozisteVenku . '/obrazek_1.jpg', 'fotka');
        $koren = ZpracovaneObrazky::adresarObsahu() . '/' . self::adresar();
        (new Filesystem())->remove($koren);
        symlink($this->ulozisteVenku, $koren);

        return new NahravaniObrazku($koren, new PouzitiObrazku());
    }

    /**
     * @test
     */
    public function najdeOdkazyVeVsechTextechWebu(): void
    {
        self::assertSame(
            [
                [
                    'popis' => 'stránka kontakty-test',
                    'url'   => 'web/editace-stranek?id=9901',
                ],
                [
                    'popis' => 'blog Testovací blog',
                    'url'   => 'web/novinka?id=9901',
                ],
                [
                    'popis' => 'aktivita Testovací aktivita (' . ROCNIK . ')',
                    'url'   => 'aktivity/upravy?aktivitaId=9901',
                ],
                [
                    'popis' => 'medailonek Test „test-vypravec“ Vypravěč',
                    'url'   => 'aktivity/medailonky?id=9901',
                ],
            ],
            (new PouzitiObrazku())->kdeJePouzity(self::absolutniCesta('obrazek_1.jpg')),
        );
    }

    /**
     * @test
     */
    public function najdeIOdkazZakodovanyProUrl(): void
    {
        self::assertSame(
            [[
                'popis' => 'stránka zakodovana-test',
                'url'   => 'web/editace-stranek?id=9903',
            ]],
            (new PouzitiObrazku())->kdeJePouzity(self::absolutniCesta('máj.png')),
        );
    }

    /**
     * @test
     */
    public function nepouzityObrazekNikdeNenajde(): void
    {
        self::assertSame([], (new PouzitiObrazku())->kdeJePouzity(self::absolutniCesta('jiny.jpg')));
        self::assertSame([], (new PouzitiObrazku())->kdeJePouzity(sys_get_temp_dir() . '/obrazek_1.jpg'));
    }

    /**
     * @test
     */
    public function najdeOdkazyNaVicObrazkuNajednouJednimDotazemNaKazdouTabulku(): void
    {
        $cesty = [
            self::absolutniCesta('obrazek_1.jpg'),
            self::absolutniCesta('máj.png'),
            self::absolutniCesta('obrazekX1.jpg'),
            self::absolutniCesta('jiny.jpg'),
        ];
        $dotazuPred = dbNumQ();

        $pouziti = (new PouzitiObrazku())->kdeJsouPouzity($cesty);

        self::assertSame(4, dbNumQ() - $dotazuPred, 'Jeden dotaz na stránky, novinky, aktivity a medailonky');
        self::assertSame($cesty, array_keys($pouziti));
        self::assertSame(
            [
                'stránka kontakty-test',
                'blog Testovací blog',
                'aktivita Testovací aktivita (' . ROCNIK . ')',
                'medailonek Test „test-vypravec“ Vypravěč',
            ],
            array_column($pouziti[$cesty[0]], 'popis'),
        );
        self::assertSame(['stránka zakodovana-test'], array_column($pouziti[$cesty[1]], 'popis'));
        self::assertSame(['stránka podobna-test'], array_column($pouziti[$cesty[2]], 'popis'));
        self::assertSame([], $pouziti[$cesty[3]]);
    }

    /**
     * @test
     */
    public function najdeOdkazyDoSlozekJednimDotazemNaTabulkuBezOhleduNaPocetSouboru(): void
    {
        $slozka = 'soubory/obsah/' . self::adresar();
        $dotazuPred = dbNumQ();

        $odkazy = (new PouzitiObrazku())->odkazyVeSlozkach([$slozka, 'soubory/obsah/' . self::adresar() . '-jina']);

        self::assertSame(4, dbNumQ() - $dotazuPred, 'Jeden dotaz na stránky, novinky, aktivity a medailonky');
        ksort($odkazy);
        self::assertSame(
            [
                $slozka . '/dvakrat.jpg',
                $slozka . '/máj.png',
                $slozka . '/obrazek_1.jpg',
                $slozka . '/obrazekx1.jpg',
            ],
            array_keys($odkazy),
            'Klíče jsou malými písmeny a odkazy jsou odkódované',
        );
        self::assertSame(
            [
                'stránka kontakty-test',
                'blog Testovací blog',
                'aktivita Testovací aktivita (' . ROCNIK . ')',
                'medailonek Test „test-vypravec“ Vypravěč',
            ],
            array_column($odkazy[$slozka . '/obrazek_1.jpg'], 'popis'),
        );
        self::assertSame(['stránka zakodovana-test'], array_column($odkazy[$slozka . '/máj.png'], 'popis'));
        self::assertSame(['stránka podobna-test'], array_column($odkazy[$slozka . '/obrazekx1.jpg'], 'popis'));
        self::assertSame(
            [[
                'popis' => 'stránka dvakrat-test',
                'url'   => 'web/editace-stranek?id=9904',
            ]],
            $odkazy[$slozka . '/dvakrat.jpg'],
            'Stránka, která na soubor odkazuje dvakrát, se počítá jednou; za adresou se query ani fragment neberou',
        );
    }

    /**
     * @test
     */
    public function slozkaNaKterouNicNeodkazujeNemaZadneOdkazy(): void
    {
        self::assertSame([], (new PouzitiObrazku())->odkazyVeSlozkach(['soubory/obsah/' . self::adresar() . '-jina']));
    }

    /**
     * @test
     */
    public function bezSlozekNeudelaZadnyDotaz(): void
    {
        $dotazuPred = dbNumQ();

        self::assertSame([], (new PouzitiObrazku())->odkazyVeSlozkach([]));
        self::assertSame($dotazuPred, dbNumQ());
    }

    public static function provideOdkazyZKodu(): array
    {
        $prihlaska = 'přihláška (web/sablony/blackarrow/prihlaska.xtpl)';

        return [
            'tisková kronika'                   => ['soubory/obsah/download/tiskova_kronika.pdf', 'QR kódy (web/moduly/qrka.php)'],
            'souhlas rodičů aktuálního ročníku' => ['soubory/obsah/materialy/' . ROCNIK . '/souhlas_rodicu_' . ROCNIK . '.pdf', $prihlaska],
            'velikosti mikin'                   => ['soubory/obsah/materialy/2026/velikosti_mikin.pdf', $prihlaska],
            'velikost písmen nerozhoduje'       => ['soubory/obsah/Download/Tiskova_Kronika.PDF', 'QR kódy (web/moduly/qrka.php)'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideOdkazyZKodu
     */
    public function znaSouboryNaKterePouzivaKod(string $cestaVuciWebu, string $popis): void
    {
        self::assertSame(
            [[
                'popis' => $popis,
                'url'   => '',
            ]],
            PouzitiObrazku::odkazyZKodu($cestaVuciWebu),
        );
    }

    public static function provideSouboryNaKterePouzivaKodNeodkazuje(): array
    {
        return [
            'jiný soubor ve stejné složce'     => ['soubory/obsah/download/jiny.pdf'],
            'stejný název v jiné složce'       => ['soubory/obsah/drd/tiskova_kronika.pdf'],
            'souhlas rodičů příštího ročníku'  => ['soubory/obsah/materialy/' . (ROCNIK + 1) . '/souhlas_rodicu_' . (ROCNIK + 1) . '.pdf'],
            'souhlas rodičů loňského ročníku'  => ['soubory/obsah/materialy/' . (ROCNIK - 1) . '/souhlas_rodicu_' . (ROCNIK - 1) . '.pdf'],
            'souhlas s ročníkem jen ve složce' => ['soubory/obsah/materialy/' . ROCNIK . '/souhlas_rodicu_' . (ROCNIK - 1) . '.pdf'],
            'o složku hlouběji'                => ['soubory/obsah/x/download/tiskova_kronika.pdf'],
            'jiný kořen'                       => ['neco/soubory/obsah/download/tiskova_kronika.pdf'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideSouboryNaKterePouzivaKodNeodkazuje
     */
    public function souborNaKterySeKodNeodkazujeJeVolny(string $cestaVuciWebu): void
    {
        self::assertSame([], PouzitiObrazku::odkazyZKodu($cestaVuciWebu));
    }

    /**
     * @test
     */
    public function souborNaKterySeOdkazujeKodMaMistoBezAdresyDoAdministrace(): void
    {
        $pouziti = (new PouzitiObrazku())->kdeJePouzity(ZpracovaneObrazky::adresarObsahu() . '/download/tiskova_kronika.pdf');

        self::assertSame(
            [[
                'popis' => 'QR kódy (web/moduly/qrka.php)',
                'url'   => '',
            ]],
            $pouziti,
            'Soubor nemusí existovat, odkaz z kódu platí, i když ho ještě nikdo nenahrál',
        );
    }

    /**
     * Seznam souborů, na které odkazuje kód, je ruční. Test projde zdrojové kódy webu, administrace, Symfony a UI
     * a hlídá, že seznam neztratil žádný odkaz do složek spravovaných z administrace, a naopak že v něm nezůstal
     * odkaz, který kód už nemá.
     *
     * @test
     */
    public function seznamSouboruZKoduOdpovidaKodu(): void
    {
        $koren = dirname(__DIR__, 3);
        $nalezene = [];
        foreach (['web', 'admin', 'model', 'symfony/src', 'symfony/templates', 'ui/src'] as $adresar) {
            if (! is_dir($koren . '/' . $adresar)) {
                continue;
            }
            $soubory = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($koren . '/' . $adresar, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($soubory as $soubor) {
                $cesta = $soubor->getPathname();
                if (! in_array($soubor->getExtension(), ['php', 'xtpl', 'tpl', 'twig', 'js', 'html', 'ts', 'tsx', 'svelte'], true)
                    || str_starts_with($cesta, $koren . '/web/soubory/')
                ) {
                    continue;
                }
                preg_match_all(
                    '~soubory/obsah/(?:drd|download|materialy|celohra)/[^\s\'"<>()?#]*~i',
                    (string) file_get_contents($cesta),
                    $shody,
                );
                foreach ($shody[0] as $odkaz) {
                    $nalezene[str_replace('{rocnik}', (string) ROCNIK, $odkaz)][] = substr($cesta, strlen($koren) + 1);
                }
            }
        }

        $chybiVSeznamu = array_filter(
            $nalezene,
            static fn (string $odkaz): bool => PouzitiObrazku::odkazyZKodu($odkaz) === [],
            ARRAY_FILTER_USE_KEY,
        );
        self::assertSame([], $chybiVSeznamu, 'Odkaz v kódu chybí v PouzitiObrazku::ODKAZY_Z_KODU, soubor by šlo z administrace smazat');

        $nalezeneMalymiPismeny = array_change_key_case($nalezene);
        $prebyva = array_filter(
            array_keys(PouzitiObrazku::ODKAZY_Z_KODU),
            static fn (string $vzor): bool => ! isset($nalezeneMalymiPismeny[mb_strtolower('soubory/' . str_replace('{rocnik}', (string) ROCNIK, $vzor))]),
        );
        self::assertSame([], array_values($prebyva), 'Kód na soubor už neodkazuje, odkaz v PouzitiObrazku::ODKAZY_Z_KODU se má smazat');
    }

    /**
     * @test
     */
    public function bezCestNeudelaZadnyDotaz(): void
    {
        $dotazuPred = dbNumQ();

        self::assertSame([], (new PouzitiObrazku())->kdeJsouPouzity([]));
        self::assertSame($dotazuPred, dbNumQ());
    }

    /**
     * @test
     */
    public function nesmazePouzityObrazekKdyzKorenLeziNaJinemUlozisti(): void
    {
        $nahravani = $this->nahravaniSKorenemNaJinemUlozisti();

        try {
            $nahravani->smaz('obrazek_1.jpg');
            self::fail('Použitý obrázek neměl jít smazat');
        } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
            self::assertStringStartsWith(
                'Na obrázek obrazek_1.jpg odkazuje stránka kontakty-test',
                $nahravaniObrazkuException->getMessage(),
            );
        }
        self::assertFileExists($nahravani->cestaKSouboru('obrazek_1.jpg'));
    }

    /**
     * @test
     */
    public function adresaSouboruVedeNaWebIKdyzKorenLeziNaJinemUlozisti(): void
    {
        $nahravani = $this->nahravaniSKorenemNaJinemUlozisti();

        self::assertSame(
            URL_WEBU . '/soubory/obsah/' . self::adresar() . '/obrazek_1.jpg',
            $nahravani->urlSouboru($nahravani->cestaKSouboru('obrazek_1.jpg')),
        );
    }

    /**
     * @test
     */
    public function souborMimoKorenSluzbyNeniNikdePouzity(): void
    {
        $nahravani = new NahravaniObrazku(ZpracovaneObrazky::adresarObsahu(), new PouzitiObrazku());

        self::assertSame([], $nahravani->kdeJePouzity(sys_get_temp_dir() . '/obrazek_1.jpg'));
        self::assertNull($nahravani->urlSouboru(sys_get_temp_dir() . '/obrazek_1.jpg'));
    }

    /**
     * @test
     */
    public function sluzbaNesmazePouzityObrazek(): void
    {
        file_put_contents(self::absolutniCesta('obrazek_1.jpg'), 'fotka');
        $nahravani = NahravaniObrazku::proObsahWebu();

        try {
            $nahravani->smaz(self::adresar() . '/obrazek_1.jpg');
            self::fail('Použitý obrázek neměl jít smazat');
        } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
            self::assertSame(
                'Na obrázek obrazek_1.jpg odkazuje stránka kontakty-test, blog Testovací blog, aktivita Testovací aktivita ('
                . ROCNIK . ') a 1 další. Nejdřív prosím odkaz odstraň.',
                $nahravaniObrazkuException->getMessage(),
            );
        }
        self::assertFileExists(self::absolutniCesta('obrazek_1.jpg'));
    }
}
