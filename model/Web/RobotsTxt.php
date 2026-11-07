<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Prostredi\Prostredi;

class RobotsTxt
{
    public function __construct(
        private readonly Prostredi $prostredi,
    ) {
    }

    public function getObsah(): string
    {
        // beta, preview and local copies of the site must not compete with the real one in search results
        if ($this->prostredi !== Prostredi::Production) {
            return "User-agent: *\nDisallow: /\n";
        }

        return <<<'TXT'
        User-agent: *
        Disallow: /finance$
        Disallow: /finance/
        Disallow: /qrka$
        Disallow: /qrka/
        Disallow: /api/

        TXT;
    }
}
