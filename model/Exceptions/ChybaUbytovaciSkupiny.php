<?php

declare(strict_types=1);

namespace Gamecon\Exceptions;

/**
 * A roommate group action that cannot be done (unknown code, full group, different type of accommodation).
 * Shown to the user as a warning, the rest of the form is saved anyway.
 */
class ChybaUbytovaciSkupiny extends \Chyba
{
}
