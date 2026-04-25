<?php

declare(strict_types=1);

namespace Gamecon\Tests\Cache;

use Gamecon\Cache\FileLock;
use Gamecon\SystemoveNastaveni\ZdrojPrivateCacheDir;
use PHPUnit\Framework\TestCase;

class FileLockTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/gamecon-file-lock-test-' . uniqid();
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/locks/*');
        if ($files) {
            array_map('unlink', $files);
        }
        @rmdir($this->tempDir . '/locks');
        @rmdir($this->tempDir);
    }

    private function createFileLock(): FileLock
    {
        $zdroj = $this->createMock(ZdrojPrivateCacheDir::class);
        $zdroj->method('privateCacheDir')->willReturn($this->tempDir);

        return new FileLock($zdroj);
    }

    /**
     * @test
     */
    public function lockCreateLockFile(): void
    {
        $fileLock = $this->createFileLock();

        $fileLock->lock('test');

        $lockFiles = glob($this->tempDir . '/locks/*.lock');
        self::assertNotEmpty($lockFiles);

        $fileLock->unlock('test');
    }

    /**
     * @test
     */
    public function unlockReleasesLock(): void
    {
        $fileLock = $this->createFileLock();

        $fileLock->lock('test');
        $fileLock->unlock('test');

        // Locking again should succeed without blocking
        $fileLock->lock('test');

        $lockFiles = glob($this->tempDir . '/locks/*.lock');
        self::assertNotEmpty($lockFiles, 'Lock file should exist after re-lock');

        $fileLock->unlock('test');
    }

    /**
     * @test
     */
    public function unlockWithoutLockDoesNothing(): void
    {
        $fileLock = $this->createFileLock();

        // Should not throw
        $fileLock->unlock('nonexistent');

        self::assertTrue(true);
    }

    /**
     * @test
     */
    public function destructReleasesAllLocks(): void
    {
        $fileLock = $this->createFileLock();

        $fileLock->lock('one');
        $fileLock->lock('two');

        // Find lock files to verify they're locked
        $lockFiles = glob($this->tempDir . '/locks/*.lock');
        self::assertCount(2, $lockFiles, 'Two lock files should exist');

        // Destroy the object — should release locks
        unset($fileLock);

        // If locks were released, we can acquire them with LOCK_NB (non-blocking)
        foreach ($lockFiles as $lockFile) {
            $handle = fopen($lockFile, 'c+');
            self::assertNotFalse($handle);
            $locked = flock($handle, LOCK_EX | LOCK_NB);
            self::assertTrue($locked, "Lock should be acquirable after destruct: {$lockFile}");
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @test
     */
    public function lockBlocksConcurrentProcess(): void
    {
        $fileLock = $this->createFileLock();
        $fileLock->lock('concurrent');

        // Find one of the lock files to try non-blocking lock from same process
        $lockFiles = glob($this->tempDir . '/locks/*concurrent.lock');
        self::assertNotEmpty($lockFiles);

        $handle = fopen($lockFiles[0], 'c+');
        self::assertNotFalse($handle);

        // LOCK_NB should fail because the lock is held
        $locked = flock($handle, LOCK_EX | LOCK_NB);
        self::assertFalse($locked);

        fclose($handle);
        $fileLock->unlock('concurrent');
    }

    /**
     * @test
     */
    public function multipleLockNamesAreIndependent(): void
    {
        $fileLock = $this->createFileLock();

        $fileLock->lock('alpha');
        $fileLock->lock('beta');

        $fileLock->unlock('alpha');

        // 'beta' should still be locked
        $betaFiles = glob($this->tempDir . '/locks/*beta.lock');
        self::assertNotEmpty($betaFiles);
        $handle = fopen($betaFiles[0], 'c+');
        $locked = flock($handle, LOCK_EX | LOCK_NB);
        self::assertFalse($locked, 'Beta lock should still be held after unlocking alpha');
        fclose($handle);

        $fileLock->unlock('beta');
    }
}
