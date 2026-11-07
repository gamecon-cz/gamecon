<?php

declare(strict_types=1);

namespace App\Dto\Cart;

use App\Service\EntryFeeService;
use Symfony\Component\Validator\Constraints as Assert;

class SetEntryFeeInputDto
{
    #[Assert\NotNull(message: 'Částka musí být vyplněna')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Částka nesmí být záporná')]
    #[Assert\LessThanOrEqual(value: EntryFeeService::MAXIMUM_AMOUNT, message: 'Nejvyšší možná částka je {{ compared_value }} Kč')]
    public ?int $amount = null;
}
