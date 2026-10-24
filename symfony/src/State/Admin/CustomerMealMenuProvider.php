<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\MealMenu\MealMenuOutputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\MealMenuReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Jídelníček s porcemi účastníka, kterého má pult otevřeného.
 *
 * @implements ProviderInterface<MealMenuOutputDto>
 */
readonly class CustomerMealMenuProvider implements ProviderInterface
{
    public function __construct(
        private MealMenuReader $menuReader,
        private CurrentYearProviderInterface $currentYearProvider,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MealMenuOutputDto
    {
        // Jen to, co už číslo je: opakované ?customerId přijde jako pole a přetypovalo by se na 1.
        $requested = $context['filters']['customerId'] ?? null;
        if (! is_string($requested) && ! is_int($requested)) {
            throw new BadRequestHttpException($this->translator->trans('customer.exactly_one_required', [], 'errors'));
        }

        $customerId = filter_var($requested, FILTER_VALIDATE_INT);
        if ($customerId === false || $customerId <= 0) {
            throw new BadRequestHttpException($this->translator->trans('customer.required', [], 'errors'));
        }

        $customer = $this->entityManager->find(User::class, $customerId);
        if ($customer === null) {
            throw new BadRequestHttpException($this->translator->trans('customer.not_found', [
                '%id%' => $customerId,
            ], 'errors'));
        }

        return $this->menuReader->forCustomer($customer, $this->currentYearProvider->getCurrentYear(), true);
    }
}
