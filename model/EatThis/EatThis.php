<?php

declare(strict_types=1);

namespace Gamecon\EatThis;

readonly class EatThis
{
    public function __construct(
        private int $defaultLength = 2**32,
        private int $timeLimitInSeconds = 15,
    ) {

    }

    public function writeRandomBytesToOutput(int $length = null): void
    {
        $previousErrorReporting = error_reporting(0);
        $this->writeRandomBytesToStream(fopen('php://output', 'wb'), $length);
        error_reporting($previousErrorReporting);
    }

    /**
     * @param resource $stream
     * @param int|null $length
     */
    private function writeRandomBytesToStream(
        $stream,
        int $length = null,
    ): void {
        $chunkLength  = 1000;
        $endAt        = microtime(true) + $this->timeLimitInSeconds;
        $bytesRemains = $length ?? $this->defaultLength;
        while ($bytesRemains > 0 && microtime(true) < $endAt) {
            fwrite($stream, $this->getRandomBytes($chunkLength));
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
            $bytesRemains -= $chunkLength;
        }
    }

    private function getRandomBytes(int $length = null): string
    {
        return random_bytes($length ?? $this->defaultLength);
    }

    public function sendError500Header(): void
    {
        header(($_SERVER['SERVER_PROTOCOL'] ?? '') . ' 500 Internal Server Error', true, 500);
    }
}
