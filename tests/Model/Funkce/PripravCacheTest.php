<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Funkce;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class PripravCacheTest extends TestCase
{
    private string $baseDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->baseDir = sys_get_temp_dir() . '/gamecon-priprav-cache-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->baseDir)) {
            chmod($this->baseDir, 0775);
        }
        $this->filesystem->remove($this->baseDir);
    }

    /**
     * @test
     */
    public function vytvoriNeexistujiciSlozku(): void
    {
        $slozka = $this->baseDir . '/nova';
        self::assertDirectoryDoesNotExist($slozka);

        pripravCache($slozka);

        self::assertDirectoryExists($slozka);
        self::assertDirectoryIsWritable($slozka);
    }

    /**
     * @test
     */
    public function vytvoriNeexistujiciZanorovouSlozku(): void
    {
        $slozka = $this->baseDir . '/uroven-1/uroven-2/uroven-3';
        self::assertDirectoryDoesNotExist($slozka);

        pripravCache($slozka);

        self::assertDirectoryExists($slozka);
    }

    /**
     * @test
     */
    public function idempotentniProJizExistujiciZapisovatelnouSlozku(): void
    {
        $this->filesystem->mkdir($this->baseDir);

        pripravCache($this->baseDir);
        pripravCache($this->baseDir);

        self::assertDirectoryExists($this->baseDir);
    }

    /**
     * @test
     */
    public function vyhodiVyjimkuKdyzSlozkaExistujeAleNeniZapisovatelna(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('Nelze testovat bez zápisu — běžíme jako root.');
        }

        $this->filesystem->mkdir($this->baseDir);
        chmod($this->baseDir, 0555);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('~není možné zapisovat~');

        try {
            pripravCache($this->baseDir);
        } finally {
            chmod($this->baseDir, 0775);
        }
    }
}
