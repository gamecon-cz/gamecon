<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

enum VysledekZkusebnihoEmailuEnum
{
    case ODESLANO;

    /** On this environment the address is not among those that get e-mails delivered, the message only went to a file. */
    case JEN_DO_SOUBORU;

    case NEODESLANO;
}
