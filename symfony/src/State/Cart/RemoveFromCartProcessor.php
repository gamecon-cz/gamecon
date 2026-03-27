<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Cart\CartOutputDto;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, CartOutputDto>
 */
readonly class RemoveFromCartProcessor implements ProcessorInterface
{
    public function __construct(
        private CartService $cartService,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartOutputDto
    {
        $user = $this->security->getUser();
        assert($user instanceof User);

        $itemId = (int) ($uriVariables['itemId'] ?? 0);
        $item = $this->entityManager->find(OrderItem::class, $itemId);

        if ($item === null) {
            throw new NotFoundHttpException(sprintf('Položka s ID %d nebyla nalezena.', $itemId));
        }

        if ($item->getCustomer()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Nemáte oprávnění odebrat tuto položku.');
        }

        $cart = $item->getOrder();
        if ($cart === null) {
            throw new NotFoundHttpException('Položka není součástí žádné objednávky.');
        }

        $roleMeanings = $user->getRoleMeanings();

        // If item is part of a forced bundle, remove the whole bundle
        $bundle = $item->getBundle();
        if ($bundle !== null && $bundle->isMandatoryForUser($roleMeanings)) {
            $this->cartService->removeBundle($cart, $bundle);
        } else {
            $this->cartService->removeItem($cart, $item, $roleMeanings);
        }

        return CartOutputDto::fromOrder($cart);
    }
}
