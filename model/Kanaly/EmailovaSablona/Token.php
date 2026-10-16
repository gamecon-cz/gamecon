<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

readonly class Token
{
    /**
     * @param string $hodnota for text the bare text (`%%` is already one percent sign), for symbols its name without the percent signs
     */
    public function __construct(
        public TypTokenuEnum $typ,
        public string $hodnota,
    ) {
    }

    /** The form in which the author wrote it - for error messages. */
    public function jakoZapsano(): string
    {
        return match ($this->typ) {
            TypTokenuEnum::SYMBOL => '%' . $this->hodnota . '%',
            TypTokenuEnum::NEUZAVRENY_SYMBOL, TypTokenuEnum::CISLICNY_SYMBOL => '%' . $this->hodnota,
            TypTokenuEnum::TEXT => $this->hodnota,
        };
    }
}
