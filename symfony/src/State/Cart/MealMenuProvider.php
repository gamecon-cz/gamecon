<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\MealMenu\MealMenuOutputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\MealMenuReader;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Jídelníček s porcemi přihlášeného účastníka.
 *
 * @implements ProviderInterface<MealMenuOutputDto>
 */
readonly class MealMenuProvider implements ProviderInterface
{
    public function __construct(
        private MealMenuReader $menuReader,
        private CurrentYearProviderInterface $currentYearProvider,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MealMenuOutputDto
    {
        $user = $this->security->getUser();
        if (! $user instanceof User) {
            throw new AccessDeniedHttpException($this->translator->trans('meal_dish.login_required', [], 'errors'));
        }

        return $this->menuReader->forCustomer($user, $this->currentYearProvider->getCurrentYear(), false);
    }
}
