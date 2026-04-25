<?php

declare(strict_types=1);

namespace Gamecon\Cache;

use Gamecon\SystemoveNastaveni\ZdrojPrivateCacheDir;
use Symfony\Component\Filesystem\Filesystem;

class FileLock
{
    /**
     * @var array<string, resource>
     */
    private array $handles = [];
    private string $hash;

    public function __construct(
        private readonly ZdrojPrivateCacheDir $zdrojPrivateCacheDir,
    ) {
        $this->hash = uniqid('file-lock-');
    }

    public function __destruct()
    {
        foreach (array_keys($this->handles) as $name) {
            $this->unlock($name);
        }
    }

    public function lock(string $name): void
    {
        $dir = $this->zdrojPrivateCacheDir->privateCacheDir() . '/locks';
        (new Filesystem())->mkdir($dir);

        $lockFile = $dir . '/' . $this->hash . $name . '.lock';
        $handle = fopen($lockFile, 'c+');
        if (! $handle || ! flock($handle, LOCK_EX)) {
            throw new \RuntimeException("Cannot acquire lock: {$name}");
        }

        $this->handles[$name] = $handle;
    }

    public function unlock(string $name): void
    {
        if (! isset($this->handles[$name])) {
            return;
        }

        flock($this->handles[$name], LOCK_UN);
        fclose($this->handles[$name]);
        unset($this->handles[$name]);
    }
}
