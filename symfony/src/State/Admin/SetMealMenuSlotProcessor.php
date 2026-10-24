<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\MealMenu\AdminMealMenuOutputDto;
use App\Dto\MealMenu\SetMealMenuSlotInputDto;
use App\Service\CurrentYearProviderInterface;
use App\Service\MealMenuEditor;
use App\Service\MealMenuReader;

/**
 * Uloží jídla jednoho obědu nebo večeře a odpoví celým jídelníčkem, jak ho čte GET.
 *
 * @implements ProcessorInterface<SetMealMenuSlotInputDto, AdminMealMenuOutputDto>
 */
readonly class SetMealMenuSlotProcessor implements ProcessorInterface
{
    public function __construct(
        private MealMenuEditor $editor,
        private MealMenuReader $menuReader,
        private CurrentYearProviderInterface $currentYearProvider,
    ) {
    }

    /**
     * @param SetMealMenuSlotInputDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminMealMenuOutputDto
    {
        $year = $this->currentYearProvider->getCurrentYear();
        $this->editor->saveSlot((int) $data->variantId, $data->dishes, $year);

        return $this->menuReader->forAdmin($year);
    }
}
