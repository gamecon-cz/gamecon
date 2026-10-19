<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\GcMail;

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

    /**
     * The text as the e-mail carries it: an HTML text is a whole document, a plain text goes as it is.
     */
    public function textProEmail(): string
    {
        return $this->format === GcMail::FORMAT_HTML
            ? '<html><body>' . $this->text . '</body></html>'
            : $this->text;
    }
}
