<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\CartOutputDto;
use App\Entity\User;
use App\Service\CartService;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<CartOutputDto>
 */
readonly class CartProvider implements ProviderInterface
{
    public function __construct(
        private CartService $cartService,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CartOutputDto
    {
        $user = $this->security->getUser();
        assert($user instanceof User);

        $cart = $this->cartService->getCart($user);

        if ($cart === null) {
            return CartOutputDto::empty();
        }

        return CartOutputDto::fromOrder($cart);
    }
}
