<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * The AI's API did not answer, answered with an error, or sent what cannot be read. The message is for the proofreader.
 */
class ChybaApiAi extends \RuntimeException
{
    public function __construct(
        string $message,
        // null when the API did not answer at all
        public readonly ?int $kodHttp = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $kodHttp ?? 0, $previous);
    }

    /**
     * The API does not know the batch (any more): its results are kept for 29 days, a key of another workspace
     * does not see it. Waiting for it makes no sense.
     */
    public function jeNeznamaDavka(): bool
    {
        return $this->kodHttp === 404;
    }

    /**
     * The error is not of one batch but of the API as a whole (unreachable, overloaded, refusing the key),
     * a request about another batch would end the same.
     */
    public function jeChybaCelehoApi(): bool
    {
        return $this->getPrevious() instanceof ClientExceptionInterface
            || in_array($this->kodHttp, [401, 403, 429], true)
            || $this->kodHttp >= 500;
    }
}
