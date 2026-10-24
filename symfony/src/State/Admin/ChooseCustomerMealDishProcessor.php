<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MealMenu\ChooseCustomerMealDishInputDto;
use App\Dto\MealMenu\MealMenuOutputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\MealDishChooser;
use App\Service\MealMenuReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Pult vybírá jídlo za účastníka. Termíny neplatí, stejně jako u objednávání porcí: na pultu
 * se řeší právě to, co už online nejde.
 *
 * @implements ProcessorInterface<ChooseCustomerMealDishInputDto, MealMenuOutputDto>
 */
readonly class ChooseCustomerMealDishProcessor implements ProcessorInterface
{
    public function __construct(
        private MealDishChooser $chooser,
        private MealMenuReader $menuReader,
        private CurrentYearProviderInterface $currentYearProvider,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param ChooseCustomerMealDishInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MealMenuOutputDto
    {
        $customer = $this->entityManager->find(User::class, $data->customerId);
        if ($customer === null) {
            throw new BadRequestHttpException($this->translator->trans('customer.not_found', [
                '%id%' => $data->customerId,
            ], 'errors'));
        }

        $year = $this->currentYearProvider->getCurrentYear();
        $this->chooser->choose($customer, (int) $data->orderItemId, $data->dishId, $year, true);

        return $this->menuReader->forCustomer($customer, $year, true);
    }
}
