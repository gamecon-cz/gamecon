<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Jak dopadlo odeslání jednomu zařízení. Server ví jen to, co mu řekla push služba prohlížeče: jestli zprávu
 * převzala. Jestli a kdy ji zařízení ukáže, se nedozví nikdy.
 */
enum StavOdeslani: string
{
    /** Push služba zprávu převzala (HTTP 2xx) a doručí ji, až bude zařízení dostupné, nejdéle po dobu TTL. */
    case PREDANO = 'predano';

    /** Push služba subscription nezná (HTTP 404 nebo 410): uživatel notifikace zrušil v prohlížeči. Řádek je smazán. */
    case NEPLATNA_SMAZANA = 'neplatna_smazana';

    /** Cokoli jiného: služba neodpověděla, odmítla podpis, je přetížená. Subscription zůstává. */
    case CHYBA = 'chyba';

    public function getPopis(): string
    {
        return match ($this) {
            self::PREDANO          => 'předáno push službě',
            self::NEPLATNA_SMAZANA => 'neplatná, smazána',
            self::CHYBA            => 'chyba',
        };
    }
}
