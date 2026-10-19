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
        $rok = ROCNIK;

        return [
            <<<SQL
INSERT INTO stranky(id_stranky, url_stranky, obsah, poradi)
VALUES
    (9901, 'kontakty-test', '<img class="leftOrg" src="{$obrazek}">', 0),
    (9902, 'podobna-test', '![]({$podobny})', 0),
    (9903, 'zakodovana-test', '![]({$zakodovany})', 0)
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

    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->mkdir(ZpracovaneObrazky::adresarObsahu() . '/' . self::adresar());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(ZpracovaneObrazky::adresarObsahu() . '/' . self::adresar());
        parent::tearDown();
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
