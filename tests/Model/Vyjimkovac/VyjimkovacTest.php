<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Vyjimkovac;

use Gamecon\Vyjimkovac\Vyjimkovac;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class VyjimkovacTest extends TestCase
{
    private string $souborDatabaze;
    private array $puvodniPost;

    protected function setUp(): void
    {
        $this->souborDatabaze = SPEC . '/testy-vyjimkovac-' . getmypid() . '.sqlite';
        $this->puvodniPost = $_POST;
    }

    protected function tearDown(): void
    {
        $_POST = $this->puvodniPost;
        (new Filesystem())->remove($this->souborDatabaze);
    }

    /**
     * @test
     *
     * @dataProvider provideJsChybyKtereNeumimeOpravit
     */
    public function jsChybuKterouNeumimeOpravitNezaznamenam(string $zprava): void
    {
        $this->zpracujJsChybu($zprava);

        self::assertSame(0, $this->dejPocetZaznamenanychChyb());
    }

    public static function provideJsChybyKtereNeumimeOpravit(): array
    {
        return [
            'chyba cizího skriptu bez detailů'     => ['Script error.'],
            'skript vkládaný Chromem na iOS'       => ["Can't find variable: __gCrWeb"],
            'skript vkládaný Chromem na iOS v URL' => ["ReferenceError: Can't find variable: __gCrWeb.autofill"],
        ];
    }

    /**
     * @test
     */
    public function jsChybuKterouUmimeOpravitZaznamenam(): void
    {
        $this->zpracujJsChybu("Cannot set properties of null (setting 'disabled')");

        self::assertSame(1, $this->dejPocetZaznamenanychChyb());
    }

    private function zpracujJsChybu(string $zprava): void
    {
        $_POST = [
            'msg'  => $zprava,
            'url'  => 'https://gamecon.cz/ui/bundle.js',
            'line' => '42',
        ];

        (new Vyjimkovac($this->souborDatabaze, []))->jsZpracuj();
    }

    private function dejPocetZaznamenanychChyb(): int
    {
        if (! file_exists($this->souborDatabaze)) {
            return 0;
        }

        return (int) (new \EPDO('sqlite:' . $this->souborDatabaze))->fetchSingleValue('SELECT COUNT(*) FROM chyby');
    }
}
