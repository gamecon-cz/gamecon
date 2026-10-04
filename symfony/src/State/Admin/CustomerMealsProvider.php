<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Admin\CustomerMealsOutputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\CustomerDeskRights;
use App\Service\MealWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Which meals a participant holds, for the desk.
 *
 * The catalogue itself is customer-agnostic and already served by `/cart/meals`, so only the
 * selection needs an endpoint of its own — the participant's own matrix reads it off their
 * cart, which the desk has no equivalent of.
 *
 * @implements ProviderInterface<CustomerMealsOutputDto>
 */
readonly class CustomerMealsProvider implements ProviderInterface
{
    public function __construct(
        private MealWriter $mealWriter,
        private CustomerDeskRights $deskRights,
        private CurrentYearProviderInterface $currentYearProvider,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerMealsOutputDto
    {
        $this->deskRights->verifyOperator('desk.action.view_meals');

        // Cast only what is already a number: a repeated ?customerId arrives as an array and
        // would cast to 1, quietly answering for whoever that is.
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

        $dto = new CustomerMealsOutputDto();
        $dto->variantIds = $this->mealWriter->heldMeals($customer, $this->currentYearProvider->getCurrentYear());

        return $dto;
    }
}
