<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\NahravaniObrazkuException;
use Gamecon\Web\PouzitiObrazku;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class NahravaniObrazkuTest extends TestCase
{
    /**
     * 1 × 1 px bezztrátové WebP, GD v našem obrazu WebP zapsat neumí
     */
    private const string WEBP_1X1 = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

    private string $koren;
    private string $zdroje;
    private array $puvodniServer;

    protected function setUp(): void
    {
        $this->puvodniServer = $_SERVER;
        $zaklad = sys_get_temp_dir() . '/gamecon-nahravani-obrazku-' . bin2hex(random_bytes(6));
        $this->koren = $zaklad . '/koren';
        $this->zdroje = $zaklad . '/zdroje';
        (new Filesystem())->mkdir([$this->koren, $this->zdroje]);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(dirname($this->koren));
        $_SERVER = $this->puvodniServer;
        $_POST = [];
        $_FILES = [];
    }

    /**
     * @test
     */
    public function typAPriponuUrciPodleObsahuNePodleNazvu(): void
    {
        $nahravani = new NahravaniObrazku($this->koren);

        $obrazek = $nahravani->overSoubor($this->png('fotka.jpg'));

        self::assertSame(NahravaniObrazku::PNG, $obrazek->mime);
        self::assertSame('png', $obrazek->pripona);
        self::assertSame([20, 10], [$obrazek->sirka, $obrazek->vyska]);
        self::assertSame($this->koren . '/42.png', $nahravani->ulozJakoJediny($obrazek, '', '42'));
        self::assertFileExists($this->koren . '/42.png');
    }

    /**
     * @test
     */
    public function poznaVsechnyRastroveFormaty(): void
    {
        $nahravani = new NahravaniObrazku($this->koren);

        self::assertSame('jpg', $nahravani->overSoubor($this->jpg('a.jpeg'))->pripona);
        self::assertSame('gif', $nahravani->overSoubor($this->gif('a.gif'))->pripona);
        self::assertSame('webp', $nahravani->overSoubor($this->webp('a.webp'))->pripona);
    }

    /**
     * @test
     */
    public function odmitneSouborKteryNeniObrazek(): void
    {
        $skript = $this->soubor('fotka.png', '<?php echo "ahoj";');

        $this->ocekavejChybu('Soubor není obrázek, nahraj prosím JPG, PNG, GIF nebo WebP.');
        (new NahravaniObrazku($this->koren))->overSoubor($skript);
    }

    /**
     * @test
     */
    public function uFotkyZIphonuPoradiCoSNi(): void
    {
        $heic = $this->soubor('IMG_0001.HEIC', 'ftypheic' . str_repeat("\0", 32));

        $this->ocekavejChybu('HEIC');
        (new NahravaniObrazku($this->koren))->overSoubor($heic);
    }

    /**
     * @test
     */
    public function odmitnePrilisVelkySoubor(): void
    {
        $this->ocekavejChybu('Obrázek je příliš velký, maximum je 1 kB.');
        (new NahravaniObrazku($this->koren, maxVelikostMb: 0.0001))->overSoubor($this->jpg('velka.jpg', 300, 300));
    }

    /**
     * @test
     */
    public function odmitneObrazekSMocPixely(): void
    {
        // malý soubor s obřími rozměry by při výrobě náhledu spolkl paměť
        $this->ocekavejChybu('Obrázek má 20 × 10 px');
        (new NahravaniObrazku($this->koren, maxPixelu: 100))->overSoubor($this->png('a.png'));
    }

    /**
     * @test
     */
    public function odmitneFormatKteryTuNeniPovoleny(): void
    {
        $this->ocekavejChybu('Formát WebP tu nejde použít, nahraj prosím JPG, PNG nebo GIF.');
        (new NahravaniObrazku($this->koren, formaty: [NahravaniObrazku::JPG, NahravaniObrazku::PNG, NahravaniObrazku::GIF]))
            ->overSoubor($this->webp('a.webp'));
    }

    /**
     * @test
     */
    public function svgPustiJenKdyzJePovolene(): void
    {
        $svg = $this->soubor('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');

        $obrazek = (new NahravaniObrazku($this->koren, formaty: [NahravaniObrazku::PNG, NahravaniObrazku::SVG]))->overSoubor($svg);
        self::assertSame(NahravaniObrazku::SVG, $obrazek->mime);
        self::assertSame('svg', $obrazek->pripona);

        $this->ocekavejChybu('Soubor není obrázek');
        (new NahravaniObrazku($this->koren))->overSoubor($svg);
    }

    /**
     * @test
     */
    public function svgSeSkriptemOdmitne(): void
    {
        $svg = $this->soubor('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->ocekavejChybu('SVG soubor obsahuje nepovolený prvek <script>.');
        (new NahravaniObrazku($this->koren, formaty: [NahravaniObrazku::SVG]))->overSoubor($svg);
    }

    /**
     * @test
     */
    public function bezNahranehoSouboruRekneCoSeStalo(): void
    {
        $nahravani = new NahravaniObrazku($this->koren);

        foreach ([
            [null, 'Není vybraný žádný soubor.'],
            [[
                'error' => UPLOAD_ERR_NO_FILE,
            ], 'Není vybraný žádný soubor.'],
            // více souborů pod jedním jménem (name[]), to formuláře neposílají
            [[
                'error' => [UPLOAD_ERR_OK],
            ], 'Není vybraný žádný soubor.'],
            [[
                'error' => UPLOAD_ERR_INI_SIZE,
            ], 'Obrázek je příliš velký'],
            [[
                'error' => UPLOAD_ERR_PARTIAL,
            ], 'chyba 3'],
            // soubor, který nepřišel HTTP uploadem, třeba podstrčená cesta k souboru na serveru
            [[
                'error'    => UPLOAD_ERR_OK,
                'tmp_name' => $this->png('a.png'),
                'name'     => 'a.png',
            ], 'Soubor se nepodařilo nahrát'],
        ] as [$polozkaFiles, $ocekavanaChyba]) {
            try {
                $nahravani->overNahranySoubor($polozkaFiles);
                self::fail('Chybí výjimka pro ' . var_export($polozkaFiles, true));
            } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
                self::assertStringContainsString($ocekavanaChyba, $nahravaniObrazkuException->getMessage());
            }
        }
    }

    /**
     * @test
     */
    public function ulozJakoJedinyNahradiObrazekSJinouPriponou(): void
    {
        file_put_contents($this->koren . '/42.jpg', 'stará fotka');
        file_put_contents($this->koren . '/42.JPEG', 'ještě starší fotka z doby FTP');
        file_put_contents($this->koren . '/421.png', 'fotka jiného uživatele');
        file_put_contents($this->koren . '/42.txt', 'poznámka, ne obrázek');
        $nahravani = new NahravaniObrazku($this->koren);

        $nahravani->ulozJakoJediny($nahravani->overSoubor($this->png('nova.png')), '', '42');

        self::assertSame(['42.png', '42.txt', '421.png'], $this->souboryVKoreni());
    }

    /**
     * @test
     */
    public function ulozJakoJedinyPrepiseObrazekSeStejnouPriponou(): void
    {
        file_put_contents($this->koren . '/42.png', 'stará fotka');
        $nahravani = new NahravaniObrazku($this->koren);
        $nova = $this->png('nova.png');

        $nahravani->ulozJakoJediny($nahravani->overSoubor($nova), '', '42');

        self::assertFileEquals($nova, $this->koren . '/42.png');
        self::assertFileExists($nova, 'Soubor, který nepřišel uploadem, se kopíruje');
        self::assertSame(['42.png'], $this->souboryVKoreni(), 'Nezůstal žádný dočasný soubor');
    }

    /**
     * @test
     */
    public function ulozNovyNeprepiseExistujiciSoubor(): void
    {
        $nahravani = new NahravaniObrazku($this->koren);
        $nahravani->ulozNovy($nahravani->overSoubor($this->png('a.png')), 'stranky/kontakty', 'mapa');

        self::assertFileExists($this->koren . '/stranky/kontakty/mapa.png');
        $this->ocekavejChybu('Obrázek mapa.png už existuje.');
        $nahravani->ulozNovy($nahravani->overSoubor($this->png('b.png')), 'stranky/kontakty', 'mapa');
    }

    /**
     * @test
     */
    public function nahradZachovaCestuAVyzadujeStejnyFormat(): void
    {
        (new Filesystem())->mkdir($this->koren . '/obrazky/organizatori');
        file_put_contents($this->koren . '/obrazky/organizatori/gandalf150.jpg', 'stará fotka');
        $nahravani = new NahravaniObrazku($this->koren);
        $novaFotka = $this->jpg('nova.jpg');

        $nahravani->nahrad($nahravani->overSoubor($novaFotka), 'obrazky/organizatori/gandalf150.jpg');
        self::assertFileEquals($novaFotka, $this->koren . '/obrazky/organizatori/gandalf150.jpg');

        // text může odkazovat na obrázek, který ještě není nahraný
        $nahravani->nahrad($nahravani->overSoubor($this->png('plan.png')), 'materialy/2026/plan.png');
        self::assertFileExists($this->koren . '/materialy/2026/plan.png');

        $this->ocekavejChybu('gandalf150.jpg je ve formátu JPG a nahraný soubor ve formátu PNG.');
        $nahravani->nahrad($nahravani->overSoubor($this->png('b.png')), 'obrazky/organizatori/gandalf150.jpg');
    }

    /**
     * @test
     */
    public function nahradNejdeNaSouborKteryNeniObrazek(): void
    {
        file_put_contents($this->koren . '/index.php', '<?php');
        $nahravani = new NahravaniObrazku($this->koren);

        $this->ocekavejChybu('index.php není obrázek, který by tu šlo nahradit.');
        $nahravani->nahrad($nahravani->overSoubor($this->png('a.png')), 'index.php');
    }

    public static function provideCestyMimoKoren(): array
    {
        return [
            'nadřazený adresář'   => ['../venku.png'],
            'nadřazený uprostřed' => ['obrazky/../../venku.png'],
            'skrytý soubor'       => ['.htaccess'],
            'skrytý adresář'      => ['obrazky/.git/venku.png'],
            'nulový bajt'         => ["venku.png\0.jpg"],
            'zpětné lomítko'      => ['..\\venku.png'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideCestyMimoKoren
     */
    public function odmitneCestuMimoKoren(string $cesta): void
    {
        $nahravani = new NahravaniObrazku($this->koren);
        $obrazek = $nahravani->overSoubor($this->png('a.png'));

        foreach ([
            static fn () => $nahravani->nahrad($obrazek, $cesta),
            static fn () => $nahravani->smaz($cesta),
            static fn () => $nahravani->cestaKSouboru($cesta),
            static fn () => $nahravani->ulozNovy($obrazek, dirname(str_replace('\\', '/', $cesta)), 'x'),
        ] as $volani) {
            try {
                $volani();
                self::fail("Cesta '{$cesta}' měla být odmítnuta");
            } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
                self::assertSame('Neplatná cesta k souboru.', $nahravaniObrazkuException->getMessage());
            }
        }
        self::assertFileDoesNotExist(dirname($this->koren) . '/venku.png');
    }

    /**
     * @test
     */
    public function absolutniCestuBereVuciKoreni(): void
    {
        $nahravani = new NahravaniObrazku($this->koren);

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($this->png('a.png')), '/tmp', 'x');

        self::assertSame($this->koren . '/tmp/x.png', $ulozeno);
    }

    /**
     * @test
     */
    public function nezapiseAniNevytvoriAdresarPresSymlinkVen(): void
    {
        $venku = dirname($this->koren) . '/venku';
        (new Filesystem())->mkdir($venku);
        symlink($venku, $this->koren . '/odkaz');
        $nahravani = new NahravaniObrazku($this->koren);
        $obrazek = $nahravani->overSoubor($this->png('a.png'));

        foreach ([
            static fn () => $nahravani->ulozNovy($obrazek, 'odkaz', 'x'),
            static fn () => $nahravani->ulozNovy($obrazek, 'odkaz/novy-adresar', 'x'),
            static fn () => $nahravani->nahrad($obrazek, 'odkaz/x.png'),
        ] as $volani) {
            try {
                $volani();
                self::fail('Zápis přes symlink ven měl být odmítnut');
            } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
                self::assertSame('Neplatná cesta k souboru.', $nahravaniObrazkuException->getMessage());
            }
        }
        self::assertSame(['.', '..'], scandir($venku));
    }

    /**
     * @test
     */
    public function smazeJenObrazek(): void
    {
        file_put_contents($this->koren . '/poznamky.txt', 'text');
        file_put_contents($this->koren . '/obrazek.png', 'obrázek');
        $nahravani = new NahravaniObrazku($this->koren);

        $nahravani->smaz('obrazek.png');
        self::assertFileDoesNotExist($this->koren . '/obrazek.png');

        $this->ocekavejChybu('Obrázek neexistuje.');
        $nahravani->smaz('poznamky.txt');
    }

    /**
     * @test
     */
    public function nesmazeObrazekNaKteryOdkazujeText(): void
    {
        file_put_contents($this->koren . '/42.jpg', 'fotka');
        $nahravani = new NahravaniObrazku($this->koren, $this->pouzitiKdeVse('stránka kontakty'));

        foreach ([
            static fn () => $nahravani->smaz('42.jpg'),
            static fn () => $nahravani->smazVsechnyVarianty('', '42'),
            // PNG by smazalo 42.jpg, na které text odkazuje
            fn () => $nahravani->ulozJakoJediny($nahravani->overSoubor($this->png('a.png')), '', '42'),
        ] as $volani) {
            try {
                $volani();
                self::fail('Použitý obrázek neměl jít smazat');
            } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
                self::assertStringStartsWith(
                    'Na obrázek 42.jpg odkazuje stránka kontakty.',
                    $nahravaniObrazkuException->getMessage(),
                );
            }
        }
        self::assertSame(['42.jpg'], $this->souboryVKoreni());

        // stejný formát odkaz nerozbije
        $nahravani->ulozJakoJediny($nahravani->overSoubor($this->jpg('a.jpg')), '', '42');
        self::assertSame(['42.jpg'], $this->souboryVKoreni());
    }

    /**
     * @test
     */
    public function vypiseJenObrazkyVAdresari(): void
    {
        (new Filesystem())->mkdir($this->koren . '/eshop/podadresar.png');
        foreach (['02_Taška.jpg', '01_Blok.JPG', '.nahravani-abc', 'RAZENI.csv', 'logo.svg'] as $soubor) {
            file_put_contents($this->koren . '/eshop/' . $soubor, 'x');
        }

        self::assertSame(
            ['01_Blok.JPG', '02_Taška.jpg', 'logo.svg'],
            (new NahravaniObrazku($this->koren))->obrazkyVAdresari('eshop'),
        );
        self::assertSame([], (new NahravaniObrazku($this->koren))->obrazkyVAdresari('neexistuje'));
    }

    public static function provideNazvy(): array
    {
        return [
            'do adresy'            => ['Mapa areálu 2026.PNG', false, 'mapa-arealu-2026'],
            'do adresy bez cesty'  => ['../../etc/passwd', false, 'etc-passwd'],
            'do adresy bez HTML'   => ['<script>alert(1)</script>', false, 'script-alert-1-script'],
            'e-shop s diakritikou' => ['08_Účastnické triko na postavě', true, '08_Účastnické triko na postavě'],
            'e-shop bez cesty'     => ['../x/y', true, 'x y'],
            'e-shop bez přípony'   => ['20_nicknack_vyrocni.JPG', true, '20_nicknack_vyrocni'],
            'e-shop sloučí mezery' => ["Kostka  –\tFate", true, 'Kostka Fate'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideNazvy
     */
    public function bezpecnyNazev(string $nazev, bool $zachovatDiakritikuAMezery, string $ocekavany): void
    {
        self::assertSame($ocekavany, NahravaniObrazku::bezpecnyNazev($nazev, $zachovatDiakritikuAMezery));
    }

    /**
     * @test
     */
    public function nazevBezPismenOdmitne(): void
    {
        foreach (['', '...', '/', '<>'] as $nazev) {
            foreach ([false, true] as $zachovatDiakritikuAMezery) {
                try {
                    NahravaniObrazku::bezpecnyNazev($nazev, $zachovatDiakritikuAMezery);
                    self::fail("Název '{$nazev}' měl být odmítnut");
                } catch (NahravaniObrazkuException) {
                    self::addToAssertionCount(1);
                }
            }
        }
    }

    /**
     * @test
     */
    public function limitSnizeNastaveniPhp(): void
    {
        $nahravani = new NahravaniObrazku($this->koren, maxVelikostMb: 100_000);

        self::assertLessThanOrEqual(ini_parse_quantity(ini_get('upload_max_filesize')), $nahravani->maxVelikostBajtu());
        self::assertStringStartsWith('JPG, PNG, GIF nebo WebP, nejvýš ', $nahravani->popisLimitu());
        self::assertSame('image/jpeg,image/png,image/gif,image/webp', $nahravani->accept());
    }

    /**
     * @test
     */
    public function poznaPozadavekKteryPhpZahodiloProVelikost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];
        $_FILES = [];
        $_SERVER['CONTENT_LENGTH'] = (string) (ini_parse_quantity(ini_get('post_max_size')) + 1);
        self::assertTrue(NahravaniObrazku::bylPozadavekPrilisVelky());

        $_POST = [
            'akceFotky' => 'nahrat',
        ];
        self::assertFalse(NahravaniObrazku::bylPozadavekPrilisVelky());
    }

    private function ocekavejChybu(string $castZpravy): void
    {
        $this->expectException(NahravaniObrazkuException::class);
        $this->expectExceptionMessage($castZpravy);
    }

    /**
     * @return list<string>
     */
    private function souboryVKoreni(): array
    {
        return array_values(array_diff(scandir($this->koren), ['.', '..']));
    }

    private function pouzitiKdeVse(string $popis): PouzitiObrazku
    {
        return new class($popis) extends PouzitiObrazku {
            public function __construct(
                private readonly string $popis,
            ) {
            }

            public function kdeJePouzity(string $absolutniCesta): array
            {
                return str_ends_with($absolutniCesta, '.jpg')
                    ? [[
                        'popis' => $this->popis,
                        'url'   => 'web/editace-stranek?id=1',
                    ]]
                    : [];
            }
        };
    }

    private function soubor(string $nazev, string $obsah): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '-' . $nazev;
        file_put_contents($cesta, $obsah);

        return $cesta;
    }

    private function png(string $nazev, int $sirka = 20, int $vyska = 10): string
    {
        $cesta = $this->soubor($nazev, '');
        imagepng(imagecreatetruecolor($sirka, $vyska), $cesta);

        return $cesta;
    }

    private function jpg(string $nazev, int $sirka = 20, int $vyska = 10): string
    {
        $cesta = $this->soubor($nazev, '');
        $obrazek = imagecreatetruecolor($sirka, $vyska);
        // šum, ať JPEG nejde zkomprimovat na pár bajtů
        for ($x = 0; $x < $sirka; ++$x) {
            for ($y = 0; $y < $vyska; ++$y) {
                imagesetpixel($obrazek, $x, $y, random_int(0, 0xFFFFFF));
            }
        }
        imagejpeg($obrazek, $cesta);

        return $cesta;
    }

    private function gif(string $nazev): string
    {
        $cesta = $this->soubor($nazev, '');
        imagegif(imagecreatetruecolor(20, 10), $cesta);

        return $cesta;
    }

    private function webp(string $nazev): string
    {
        return $this->soubor($nazev, base64_decode(self::WEBP_1X1, true));
    }
}
