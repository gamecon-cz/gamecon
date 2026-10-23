<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Tests\Model\Web\TestovaciObrazky;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class NahledTest extends TestCase
{
    use TestovaciObrazky;

    private string $zdroje;

    /**
     * @var list<string>
     */
    private array $nahledyKUklidu = [];

    protected function setUp(): void
    {
        $this->zdroje = sys_get_temp_dir() . '/gamecon-nahled-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->zdroje);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->zdroje, ...$this->nahledyKUklidu]);
    }

    /**
     * @test
     */
    public function fotkaZTelefonuSeVNahleduOtociStejneJakoVProhlizeci(): void
    {
        // 40 × 20 px vodorovně, EXIF říká „otočit o 90° po směru hodin“
        $fotka = $this->zdroje . '/IMG_0001.jpg';
        file_put_contents($fotka, self::jpgSOrientaci(6));

        $nahled = $this->vytvorNahled(\Nahled::zeSouboru($fotka)->pokryjOrez(20, 40));

        // levá (červená) strana fotky je po otočení nahoře
        self::assertTrue(self::jeCervena($nahled, 10, 5));
        self::assertTrue(self::jeModra($nahled, 10, 35));
    }

    /**
     * @test
     */
    public function fotkaBezOrientaceSeNeotoci(): void
    {
        $fotka = $this->zdroje . '/IMG_0002.jpg';
        file_put_contents($fotka, self::jpgSOrientaci(1));

        $nahled = $this->vytvorNahled(\Nahled::zeSouboru($fotka)->pokryjOrez(40, 20));

        self::assertTrue(self::jeCervena($nahled, 5, 10));
        self::assertTrue(self::jeModra($nahled, 35, 10));
    }

    private function vytvorNahled(\Nahled $nahled): string
    {
        $soubor = self::souborNahledu($nahled->url());
        $this->nahledyKUklidu[] = $soubor;

        return $soubor;
    }
}
