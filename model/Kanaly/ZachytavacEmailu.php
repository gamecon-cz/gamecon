<?php

declare(strict_types=1);

namespace Gamecon\Kanaly;

use Gamecon\Dev\GateLink;
use Gamecon\Prostredi\Prostredi;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Where to read e-mails on the environments that catch them instead of delivering: Mailpit in the local
 * Docker stack and the one shared by all previews.
 *
 * Locally the address comes from the environment because only the host port mapping in `.env` knows it.
 */
class ZachytavacEmailu
{
    public const URL_PREVIEW = 'https://webmail.preview.gamecon.cz/';

    public static function zGlobals(): self
    {
        return new self(
            SystemoveNastaveni::zGlobals()->prostredi(),
            (string) getenv('MAILHOG_URL'),
            (string) try_constant('PREVIEW_GATE_SECRET'),
        );
    }

    public function __construct(
        private readonly Prostredi $prostredi,
        private readonly string    $urlLokalne,
        private readonly string    $tajemstviBranyPreview,
    ) {
    }

    /**
     * @return string|null null where the e-mails are delivered to real people (or the address is not known)
     */
    public function getUrl(): ?string
    {
        return match ($this->prostredi) {
            Prostredi::Locale => $this->urlLokalne !== ''
                ? $this->urlLokalne
                : null,
            // the same signed link as the Previews page gives, so the gate in front of the webmail does not ask for a login
            Prostredi::Preview => GateLink::podepis(self::URL_PREVIEW, $this->tajemstviBranyPreview),
            Prostredi::Beta, Prostredi::Production => null,
        };
    }
}
