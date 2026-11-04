<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\VerzeSouboru;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class VerzeSouboruTest extends TestCase
{
    private string $adresar;

    protected function setUp(): void
    {
        $this->adresar = sys_get_temp_dir() . '/verze-souboru-' . uniqid('', true);
        (new Filesystem())->mkdir($this->adresar, 0775);
        file_put_contents($this->adresar . '/skript.js', 'console.log(1)');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->adresar);
    }

    public function testVerzeJeHashObsahuSouboru(): void
    {
        $verze = new VerzeSouboru($this->adresar, 'js');

        self::assertSame(
            md5('console.log(1)'),
            $verze->getVerze('skript'),
            'Stejná verze, dokud se soubor nezmění, jinak by prohlížeč skript nikdy necachoval',
        );
    }

    public function testSablonaSePtaJmenemMetody(): void
    {
        $verze = new VerzeSouboru($this->adresar, 'js');

        self::assertSame(
            md5('console.log(1)'),
            // XTemplate z {jsVersions.skript} volá $verze->skript()
            $verze->__call('skript', []),
        );
    }

    public function testPriponaMuzeZacinatTeckou(): void
    {
        self::assertSame(md5('console.log(1)'), (new VerzeSouboru($this->adresar, '.js'))->getVerze('skript'));
    }

    public function testVerzeSouboruZadanehoCestou(): void
    {
        self::assertSame(
            md5('console.log(1)'),
            (new VerzeSouboru($this->adresar, 'js'))->getVerze($this->adresar . '/skript.js'),
            'Tak se ptá web (Modul::zabalJsSoubor), cesta k souboru i s příponou',
        );
    }

    public function testNeznamySouborDostanePokazdeJinouVerzi(): void
    {
        $verze = new VerzeSouboru($this->adresar, 'js');

        self::assertStringStartsWith('neznamySoubor-chybi.js-', $verze->getVerze('chybi'));
        self::assertNotSame($verze->getVerze('chybi'), $verze->getVerze('chybi'));
    }
}
