<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona\Exceptions;

/**
 * Error of the calling code: it did not pass exactly the variables the template declares.
 */
class ChybiPromennaSablony extends \LogicException
{
}
