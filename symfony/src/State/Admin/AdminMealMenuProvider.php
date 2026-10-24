<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\MealMenu\AdminMealMenuOutputDto;
use App\Service\CurrentYearProviderInterface;
use App\Service\MealMenuReader;

/**
 * Letošní jídelníček pro editor v adminu.
 *
 * @implements ProviderInterface<AdminMealMenuOutputDto>
 */
readonly class AdminMealMenuProvider implements ProviderInterface
{
    public function __construct(
        private MealMenuReader $menuReader,
        private CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminMealMenuOutputDto
    {
        return $this->menuReader->forAdmin($this->currentYearProvider->getCurrentYear());
    }
}
