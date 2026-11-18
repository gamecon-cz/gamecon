<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura\Exceptions;

/**
 * Another request is sending texts to the AI and did not finish in time. The message is meant for the proofreader.
 */
class OdesilaniAiUzBezi extends \RuntimeException
{
}
