<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Model\Web\UklidNovychAdresaru;
use Gamecon\Web\ZpracovaneObrazky;
use Symfony\Component\Filesystem\Filesystem;

class UzivatelFotkaTest extends AbstractTestDb
{
    use UklidNovychAdresaru;

    protected static bool $disableStrictTransTables = true;

    private const int ID_UZIVATELE = 99437;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->uklidNoveAdresare(ZpracovaneObrazky::adresarFotekUzivatelu());
        (new Filesystem())->mkdir(ZpracovaneObrazky::adresarFotekUzivatelu());
        dbQuery(
            'INSERT INTO uzivatele_hodnoty SET id_uzivatele = $0, login_uzivatele = $1, email1_uzivatele = $2, jmeno_uzivatele = $3, prijmeni_uzivatele = $4',
            [self::ID_UZIVATELE, 'test_fotka', 'test.fotka@example.org', 'Test', 'Fotka'],
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        $this->kUklidu = [];
        parent::tearDown();
    }

    /**
     * @test
     */
    public function bezAvifuVratiOriginal(): void
    {
        $this->ulozFotku('JPG');

        self::assertSame($this->cesta('JPG'), $this->fotka());
    }

    /**
     * `glob` řadí bajty, takže `.JPG` z fotoaparátu by šel před `.avif` a převedená fotka by se nikdy neukázala.
     *
     * @test
     */
    public function avifMaPrednostPredOriginalemSVelkouPriponou(): void
    {
        $this->ulozFotku('JPG');
        $this->ulozFotku('avif');
        self::assertSame($this->cesta('JPG'), glob($this->cesta('*'))[0], 'předpoklad testu: glob dává originál první');

        self::assertSame($this->cesta('avif'), $this->fotka());
    }

    /**
     * @test
     */
    public function beresJenPriponyFotekAPoslediPriponuZVicenasobnePripony(): void
    {
        $this->ulozFotku('txt');
        self::assertNull($this->fotka());

        $this->ulozFotku('tar.JPG');
        self::assertSame($this->cesta('tar.JPG'), $this->fotka());
    }

    /**
     * @test
     */
    public function nacitatelnyOriginalPomuzeKdyzAvifNejdePrecist(): void
    {
        $this->ulozFotku('jpg');
        $this->ulozFotku('avif');
        chmod($this->cesta('avif'), 0000);
        if (is_readable($this->cesta('avif'))) {
            self::markTestSkipped('Soubor jde číst i bez práv (root), nejde ho udělat nečitelným.');
        }

        self::assertSame($this->cesta('jpg'), $this->fotka());
    }

    private function fotka(): ?string
    {
        return \Uzivatel::zIdUrcite(self::ID_UZIVATELE)->fotka()?->soubor();
    }

    private function ulozFotku(string $pripona): void
    {
        $cesta = $this->cesta($pripona);
        $this->kUklidu[] = $cesta;
        file_put_contents($cesta, 'obsah fotky, který Náhled nečte');
    }

    private function cesta(string $pripona): string
    {
        return ZpracovaneObrazky::adresarFotekUzivatelu() . '/' . self::ID_UZIVATELE . '.' . $pripona;
    }
}
