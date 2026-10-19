<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\GcMail;

/**
 * What the code knows about an e-mail: to whom and when it goes out and which variables it can fill in.
 * The subject and text themselves are in the database and are edited from the admin.
 */
readonly class EmailovaSablonaDefinice
{
    /** @var array<string, EmailovaPromenna> */
    public array $promenne;

    /**
     * @param string $format {@see GcMail::FORMAT_TEXT} or {@see GcMail::FORMAT_HTML}
     * @param list<EmailovaPromenna> $promenne
     */
    public function __construct(
        public string $kod,
        public string $skupina,
        public string $nazev,
        public string $kdyOdchazi,
        public string $prijemce,
        public string $format,
        array $promenne = [],
    ) {
        $promennePodleNazvu = [];
        foreach ($promenne as $promenna) {
            if (isset($promennePodleNazvu[$promenna->nazev])) {
                throw new \LogicException("Šablona '{$this->kod}' má proměnnou '{$promenna->nazev}' dvakrát.");
            }
            $promennePodleNazvu[$promenna->nazev] = $promenna;
        }
        $this->promenne = $promennePodleNazvu;
    }

    public function promenna(string $nazev): ?EmailovaPromenna
    {
        return $this->promenne[$nazev] ?? null;
    }

    /**
     * @return array<string, EmailovaPromenna>
     */
    public function promenneVPredmetu(): array
    {
        return array_filter(
            $this->promenne,
            static fn(EmailovaPromenna $promenna) => $promenna->vPredmetu,
        );
    }

    /**
     * What each variable is replaced with to show what a text turns into (the preview, a test e-mail).
     *
     * @return array<string, string>
     */
    public function ukazky(): array
    {
        return array_map(
            static fn(EmailovaPromenna $promenna) => $promenna->ukazka,
            $this->promenne,
        );
    }

    public function jeHtml(): bool
    {
        return $this->format === GcMail::FORMAT_HTML;
    }
}
