<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Co o notifikacích tohoto zařízení ví server. Co ví jen prohlížeč (podpora, oprávnění, web na ploše v iOS),
 * doplní skript web/soubory/push-notifikace.js.
 */
enum StavZarizeni: string
{
    case ZAPNUTO = 'zapnuto';
    case VYPNUTO = 'vypnuto';

    /** Admin přepnutý na cizí účet: na svém zařízení notifikace cizího účtu nezapne. */
    case PREPNUTO = 'prepnuto';

    /** Zařízení není přihlášené trvale, notifikace by neměly s čím skončit. */
    case BEZ_TRVALEHO_PRIHLASENI = 'bez-trvaleho-prihlaseni';
}
