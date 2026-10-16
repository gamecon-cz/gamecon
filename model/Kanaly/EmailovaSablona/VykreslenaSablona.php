<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

readonly class VykreslenaSablona
{
    /**
     * @param string $format {@see \Gamecon\Kanaly\GcMail::FORMAT_TEXT} or {@see \Gamecon\Kanaly\GcMail::FORMAT_HTML}
     */
    public function __construct(
        public string $predmet,
        public string $text,
        public string $format,
    ) {
    }
}
