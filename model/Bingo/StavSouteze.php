<?php

declare(strict_types=1);

namespace Gamecon\Bingo;

/**
 * Soutěž na jednom jednání rady, viz Bingo::getStavSouteze().
 */
enum StavSouteze: string
{
    /** Před startem: karty jde generovat a měnit, odškrtávat ne. */
    case PRIPRAVA = 'priprava';

    /** Od startu do prvního binga: odškrtává se, karty jsou zamčené, kdo kartu nemá, dostane jednu. */
    case BEZI = 'bezi';

    /** Někdo má bingo, soutěž skončila a vítěz je uložený u karty. */
    case VYHRANO = 'vyhrano';

    /** Jednání skončilo a bingo nikdo neměl. */
    case BEZ_VITEZE = 'bez_viteze';
}
