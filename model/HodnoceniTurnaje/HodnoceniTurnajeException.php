<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Akce, kterou pravidla hodnocení turnaje nedovolí. Zpráva je text pro uživatele (může obsahovat, co kdo napsal).
 */
class HodnoceniTurnajeException extends \RuntimeException
{
}
