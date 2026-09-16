<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Admin\CustomerMealsOutputDto;
use App\Dto\Admin\SetCustomerMealsInputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\CustomerDeskRights;
use App\Service\MealWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Saving a participant's meals from the admin desk.
 *
 * The whole selection arrives at once, as the screen submits it, and the writer diffs it
 * against what the customer holds. The sale deadline is not checked, for the same reason as
 * with accommodation: booking people in after it has passed is most of what the desk is for.
 *
 * The answer is what the customer ends up holding, which is not always what was sent: a
 * breakfast the hotel covers is cancelled right after it is ordered.
 *
 * @implements ProcessorInterface<SetCustomerMealsInputDto, CustomerMealsOutputDto>
 */
readonly class SetCustomerMealsProcessor implements ProcessorInterface
{
    public function __construct(
        private MealWriter $mealWriter,
        private CustomerDeskRights $deskRights,
        private CurrentYearProviderInterface $currentYearProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param SetCustomerMealsInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CustomerMealsOutputDto
    {
        $this->deskRights->verifyOperator('Objednávání jídla za účastníka');

        $customer = $this->entityManager->find(User::class, $data->customerId);
        if ($customer === null) {
            throw new BadRequestHttpException(sprintf('Uživatel s ID %d nebyl nalezen.', $data->customerId));
        }

        $year = $this->currentYearProvider->getCurrentYear();

        try {
            $this->mealWriter->save($customer, array_map('intval', $data->variantIds), $year);
        } catch (\RuntimeException $error) {
            throw new BadRequestHttpException($error->getMessage(), $error);
        }

        $dto = new CustomerMealsOutputDto();
        $dto->variantIds = $this->mealWriter->heldMeals($customer, $year);

        return $dto;
    }
}
