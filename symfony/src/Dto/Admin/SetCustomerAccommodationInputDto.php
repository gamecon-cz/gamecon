<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

class SetCustomerAccommodationInputDto
{
    #[Assert\NotNull(message: 'Musí být zadán účastník')]
    #[Assert\Positive(message: 'ID účastníka musí být kladné číslo')]
    public ?int $customerId = null;

    /**
     * The nights the customer ends up with. An empty array cancels the whole booking.
     *
     * @var int[]
     */
    #[Assert\NotNull(message: 'Seznam nocí musí být vyplněn')]
    #[Assert\All([
        new Assert\Type(type: 'integer', message: 'Noc musí být číslo varianty'),
        new Assert\Positive(message: 'Noc musí být kladné číslo varianty'),
    ])]
    public array $variantIds = [];

    /**
     * Null means "this screen does not edit the roommate", not "clear it" — the infopult desk
     * has no such field and must not wipe what the participant filled in themselves. An empty
     * string is the way to clear it.
     */
    #[Assert\Length(max: 255, maxMessage: 'Jméno spolubydlícího může mít nejvýše {{ limit }} znaků')]
    public ?string $roommate = null;

    /**
     * Only meaningful with no nights selected: it is the answer "I want none", not "not yet".
     */
    public bool $declined = false;
}
