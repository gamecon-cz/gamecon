<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * The item exists but is withdrawn, or its sale deadline has passed.
 */
final class NoLongerAvailableException extends UserFacingException
{
}
