<?php

declare(strict_types=1);

namespace Gamecon\Logger;

interface JobResultLoggerInterface
{
    public function logs(string $string, bool $zalogovatCas = true): void;
}
