<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;

class OrderCompletionService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    /**
     * Completing is what freezes the prices: only pending orders are repriced when a role changes.
     *
     * @return bool whether an order was completed
     */
    public function completeCart(User $customer): bool
    {
        $cart = $this->orderRepository->findPendingForCustomer($customer, $this->currentYearProvider->getCurrentYear());

        // The checkout refuses an empty cart too, and there is nothing in it to freeze.
        if ($cart === null || $cart->isEmpty()) {
            return false;
        }

        $cart->complete();
        $this->entityManager->flush();

        return true;
    }
}
