<?php

declare(strict_types=1);

namespace Gamecon\EatThis;

class EatThis
{
    public function __construct(private readonly int $defaultLength = 1000000, private readonly int $timeLimitInSeconds = 15) {

    }

    public function getRandomBytes(int $length = null) {
        return random_bytes($length ?? $this->defaultLength);
    }

    /**
     * @param resource $stream
     * @param int|null $length
     */
    public function writeRandomBytesToStream($stream, int $length = null) {
        $chunkLength  = 1000;
        $endAt        = microtime(true) + $this->timeLimitInSeconds;
        $bytesRemains = $length ?? $this->defaultLength;
        while ($bytesRemains > 0 && microtime(true) < $endAt) {
            fwrite($stream, $this->getRandomBytes($chunkLength));
            flush();
            $bytesRemains -= $chunkLength;
        }
    }

    public function writeRandomBytesToOutput(int $length = null) {
        $this->writeRandomBytesToStream(fopen('php://output', 'wb'), $length);
    }

    public function sendError500Header() {
        header(($_SERVER['SERVER_PROTOCOL'] ?? '') . ' 500 Internal Server Error', true, 500);
    }
}
