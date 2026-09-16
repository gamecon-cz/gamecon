<?php

declare(strict_types=1);

namespace App\State\Cart;

use App\Dto\Cart\AccommodationOutputDto;
use App\Entity\User;

/**
 * Reading the accommodation grid for a named customer, separately from the API Platform
 * provider contract, which always answers for whoever is signed in.
 */
interface AccommodationGridInterface
{
    public function forCustomer(User $user, \Uzivatel $legacyUser): AccommodationOutputDto;
}
