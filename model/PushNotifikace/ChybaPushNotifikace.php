<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Chyba, jejíž zprávu smí vidět uživatel: neplatný vstup, vypnutá funkce, překročený limit. Zpráva nenese endpoint,
 * klíče ani obsah notifikace.
 */
final class ChybaPushNotifikace extends \RuntimeException
{
    public function __construct(
        string $zprava,
        private readonly int $httpKod = 400,
    ) {
        parent::__construct($zprava);
    }

    public function getHttpKod(): int
    {
        return $this->httpKod;
    }
}
