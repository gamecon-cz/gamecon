<?php

declare(strict_types=1);

namespace Gamecon\Tests\Cache;

use Gamecon\Cache\QueryCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class QueryCacheTest extends TestCase
{
    private string $cacheDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->cacheDir = sys_get_temp_dir() . '/gamecon-query-cache-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->cacheDir);
    }

    /**
     * @test
     */
    public function constructorCreatesMissingCacheDir(): void
    {
        self::assertDirectoryDoesNotExist($this->cacheDir);

        new QueryCache($this->cacheDir);

        self::assertDirectoryExists($this->cacheDir);
    }

    /**
     * @test
     */
    public function constructorCreatesNestedMissingCacheDir(): void
    {
        $nested = $this->cacheDir . '/level-one/level-two';
        self::assertDirectoryDoesNotExist($nested);

        new QueryCache($nested);

        self::assertDirectoryExists($nested);
    }

    /**
     * @test
     */
    public function constructorIsIdempotentWhenCacheDirAlreadyExists(): void
    {
        $this->filesystem->mkdir($this->cacheDir);

        new QueryCache($this->cacheDir);
        new QueryCache($this->cacheDir);

        self::assertDirectoryExists($this->cacheDir);
    }

    /**
     * @test
     */
    public function getReturnsFalseForMissingKey(): void
    {
        $queryCache = new QueryCache($this->cacheDir);

        self::assertFalse($queryCache->get('neexistujici-klic'));
    }
}
