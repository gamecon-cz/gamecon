<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona\Exceptions;

/**
 * A template that is not filled in must not be silently replaced by something else - it would send
 * what nobody approved and is not visible in the admin.
 */
class EmailovaSablonaNeniVyplnena extends \RuntimeException
{
}
