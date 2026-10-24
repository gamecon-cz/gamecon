<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MealMenu\ChooseMealDishInputDto;
use App\Dto\MealMenu\MealMenuOutputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\MealDishChooser;
use App\Service\MealMenuReader;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Účastník si k vlastní porci vybírá jídlo. Odpovídá celým jídelníčkem jako GET, aby se
 * výběr překreslil podle toho, co server uložil.
 *
 * @implements ProcessorInterface<ChooseMealDishInputDto, MealMenuOutputDto>
 */
readonly class ChooseMealDishProcessor implements ProcessorInterface
{
    public function __construct(
        private MealDishChooser $chooser,
        private MealMenuReader $menuReader,
        private CurrentYearProviderInterface $currentYearProvider,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param ChooseMealDishInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MealMenuOutputDto
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException($this->translator->trans('meal_dish.login_required', [], 'errors'));
        }

        $year = $this->currentYearProvider->getCurrentYear();
        $this->chooser->choose($user, (int) $data->orderItemId, $data->dishId, $year, false);

        return $this->menuReader->forCustomer($user, $year, false);
    }
}
