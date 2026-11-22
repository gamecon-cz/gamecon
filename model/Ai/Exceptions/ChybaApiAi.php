<?php

declare(strict_types=1);

namespace Gamecon\Ai\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * The AI's API did not answer, answered with an error, or sent what cannot be read. The message is for the proofreader.
 */
class ChybaApiAi extends \RuntimeException
{
    private bool $davkaSkoncilaBezOdpovedi = false;

    public function __construct(
        string $message,
        // null when the API did not answer at all
        public readonly ?int $kodHttp = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $kodHttp ?? 0, $previous);
    }

    /**
     * The batch has ended and will never bring an answer, the API refused it as a whole (an input it could not read).
     */
    public static function davkaSkoncilaBezOdpovedi(string $message): self
    {
        $chyba = new self($message, 200);
        $chyba->davkaSkoncilaBezOdpovedi = true;

        return $chyba;
    }

    /**
     * Waiting for the batch makes no sense: the API does not know it (any more, results are kept for about
     * a month, a key of another account does not see it), or it ended without any answer.
     */
    public function jeDavkaZtracena(): bool
    {
        return $this->davkaSkoncilaBezOdpovedi || $this->kodHttp === 404;
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
