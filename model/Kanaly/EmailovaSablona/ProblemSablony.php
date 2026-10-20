<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

readonly class ProblemSablony
{
    /**
     * @param string $odkaz edit URL relative to the admin (the admin has a `<base href>`)
     * @param list<string> $chyby
     * @param bool $zabranujeOdeslani the e-mail cannot be sent at all, as opposed to going out with a symbol left as written
     */
    public function __construct(
        public KodEmailoveSablonyEnum $kod,
        public string $nazev,
        public string $odkaz,
        public array $chyby,
        public bool $zabranujeOdeslani = false,
    ) {
    }
}
