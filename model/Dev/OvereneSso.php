<?php

declare(strict_types=1);

namespace Gamecon\Dev;

/**
 * Ověřený obsah `?gcsso=` tokenu — výsledek {@see CrossSiteLogin::over}.
 * Podpis i platnost už jsou ověřené; shodu {@see self::$nonce} se spárovanou
 * cookie kontroluje volající (to teprve potvrdí, že jde o prohlížeč, který na
 * odkaz klikl).
 */
final readonly class OvereneSso
{
    public function __construct(
        public string $email,
        public string $nonce,
    ) {
    }
}
