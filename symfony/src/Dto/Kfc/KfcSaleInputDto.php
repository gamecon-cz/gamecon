<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

use Symfony\Component\Validator\Constraints as Assert;

class KfcSaleInputDto
{
    /**
     * @var KfcSaleItemInputDto[]
     */
    #[Assert\NotBlank(message: 'Musí být zadána alespoň jedna položka')]
    #[Assert\Valid]
    public array $items = [];

    /**
     * Komu se prodává. Infopult s vybraným uživatelem posílá jeho ID a nákup jde na jeho účet;
     * bez něj je to anonymní prodej na sdílený účet. ID posílá stránka, ne server z relace:
     * záložka otevřená u jiného uživatele pak prodá tomu, jehož jméno obsluha v hlavičce vidí.
     */
    #[Assert\Positive]
    public ?int $customerId = null;
}
