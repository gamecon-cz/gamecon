<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

enum VysledekRezervaceKostymuEnum
{
    case NOVA;
    case VYMENENA;
    /** Účastník už ten kostým na aktivitu má. */
    case BEZE_ZMENY;
}
