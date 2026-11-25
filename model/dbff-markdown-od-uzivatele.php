<?php

declare(strict_types=1);

use Gamecon\Web\BezpecneHtml;

/**
 * Pole s Markdownem, který si píše uživatel sám (medailonek vypravěče): náhled v adminu jen s HTML, které nic nespustí,
 * stejně jako na webu. Jinak by vypravěč mohl vložit skript, který by se spustil organizátorovi s jeho právy.
 */
class DbffMarkdownOdUzivatele extends DbffMarkdownDirect
{
    protected function nahledHtml(): string
    {
        return BezpecneHtml::zMarkdownu($this->value());
    }

    protected function isTextOdUzivatele(): bool
    {
        return true;
    }
}
