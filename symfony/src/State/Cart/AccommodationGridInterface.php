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
    /**
     * @param bool      $zPultu  volá to obsluha za účastníka — pak neplatí termín prodeje,
     *                           protože doobjednat po termínu je smysl admin obrazovek
     * @param User|null $orderer obsluha, která objednává; její práva a role rozhodují o jedné noci,
     *                           o neděli a o přeplnění, jako to dělal formulář, který se ptal
     *                           objednatele i ubytovaného. Bez ní objednává ubytovaný sám.
     */
    public function forCustomer(User $user, \Uzivatel $legacyUser, bool $zPultu, ?User $orderer = null): AccommodationOutputDto;
}
