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

    public function __construct(
        private readonly ZdrojPrivateCacheDir $zdrojPrivateCacheDir,
    ) {
    }

    public function __destruct()
    {
        foreach (array_keys($this->handles) as $name) {
            $this->unlock($name);
        }
    }

    /**
     * Získá mezi-procesní zámek. $name MUSÍ být deterministický a bezpečný pro
     * filesystém (alfanumerické znaky, pomlčky, podtržítka) — celé jméno se
     * překládá 1:1 na cestu lockfile, aby všichni volající se stejným $name
     * sahali na tentýž soubor a flock je skutečně serializoval.
     */
    public function lock(string $name): void
    {
        $dir = $this->zdrojPrivateCacheDir->privateCacheDir() . '/locks';
        (new Filesystem())->mkdir($dir);

        $lockFile = $dir . '/' . $name . '.lock';
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
