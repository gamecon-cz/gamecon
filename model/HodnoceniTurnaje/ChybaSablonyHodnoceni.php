<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Kritéria hodnocení ze souboru nejde přečíst nebo z editoru uložit. Nese všechny nalezené chyby, ať je org opraví
 * naráz.
 */
final class ChybaSablonyHodnoceni extends HodnoceniTurnajeException
{
    /**
     * @param list<string> $chyby text pro uživatele, u souboru většinou s číslem řádku
     */
    public function __construct(private readonly array $chyby)
    {
        parent::__construct(implode("\n", $chyby));
    }

    /**
     * @return list<string>
     */
    public function getChyby(): array
    {
        return $this->chyby;
    }
}
