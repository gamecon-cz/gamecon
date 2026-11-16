<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A refusal written for the person who caused it, so its message may be shown to them as is.
 * Anything else is a fault: it is logged and the client gets only a generic error.
 */
abstract class UserFacingException extends \RuntimeException
{
}
