<?php

declare(strict_types=1);

namespace Gamecon\Ai\Exceptions;

/**
 * The developer asked for something the stored connection does not allow (no token to keep, a provider without
 * a token to use). The message is for them.
 */
class PripojeniAiNelzeUlozit extends \RuntimeException
{
}
