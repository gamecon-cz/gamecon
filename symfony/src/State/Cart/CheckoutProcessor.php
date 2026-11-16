<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Cart\CartOutputDto;
use App\Dto\Cart\CheckoutInputDto;
use App\Entity\User;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProcessorInterface<CheckoutInputDto, CartOutputDto>
 */
readonly class CheckoutProcessor implements ProcessorInterface
{
    public function __construct(
        private CartService $cartService,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartOutputDto
    {
        $user = $this->security->getUser();
        assert($user instanceof User);

        $cart = $this->cartService->getCart($user);

        if ($cart === null || $cart->isEmpty()) {
            throw new BadRequestHttpException($this->translator->trans('cart.empty', [], 'errors'));
        }

        if (! $cart->isPending()) {
            throw new BadRequestHttpException($this->translator->trans('cart.order_closed', [], 'errors'));
        }

        $cart->complete();
        $this->entityManager->flush();

        return CartOutputDto::fromOrder($cart);
    }
}
