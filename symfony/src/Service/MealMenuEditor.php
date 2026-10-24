<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\MealMenu\MealDishInputDto;
use App\Entity\MealDish;
use App\Exception\InvalidRequestException;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Zadávání jídelníčku v adminu, vždy celý oběd nebo večeře najednou.
 *
 * Smazané jídlo nejdřív odebere výběry, které na něj ukazují — ti lidé spadnou na výchozí
 * jídlo. Dělá se to přes entity, ne cizím klíčem `SET NULL`, aby to viděl audit nákupů.
 */
class MealMenuEditor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly MealMenuReader $menuReader,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param MealDishInputDto[] $dishes jídla slotu v pořadí, jak je má jídelníček ukázat
     *
     * @throws InvalidRequestException
     */
    public function saveSlot(int $variantId, array $dishes, int $year): void
    {
        $slot = $this->menuReader->findSlot($variantId, $year)
            ?? throw new InvalidRequestException($this->translator->trans('meal_menu.not_a_meal_slot', [
                '%id%' => $variantId,
            ], 'errors'));

        $dishes = array_values($dishes);
        $this->validate($dishes, $slot);

        $defaults = array_filter($dishes, static fn (MealDishInputDto $dish): bool => $dish->default);
        // Výchozí musí být vždycky: bez něj by ten, kdo nevybral, nedostal nic.
        $defaultIndex = $defaults === [] ? 0 : array_key_first($defaults);

        $this->entityManager->wrapInTransaction(function () use ($slot, $dishes, $defaultIndex, $year): void {
            $kept = [];
            foreach ($dishes as $position => $input) {
                $dish = $input->id === null
                    ? new MealDish($slot->variant, $year, trim($input->name))
                    : $slot->findDish($input->id);
                \assert($dish instanceof MealDish);
                $dish->setName(trim($input->name));
                $description = trim((string) $input->description);
                $dish->setDescription($description === '' ? null : $description);
                $dish->setVegetarian($input->vegetarian);
                $dish->setDefault($position === $defaultIndex);
                $dish->setPosition($position);
                $this->entityManager->persist($dish);
                if ($input->id !== null) {
                    $kept[$input->id] = true;
                }
            }

            foreach ($slot->dishes as $dish) {
                if (isset($kept[$dish->getId()])) {
                    continue;
                }
                foreach ($this->orderItemRepository->findBy([
                    'mealDish' => $dish,
                ]) as $portion) {
                    $portion->setMealDish(null);
                }
                $this->entityManager->remove($dish);
            }

            $this->entityManager->flush();
        });
    }

    /**
     * @param MealDishInputDto[] $dishes
     */
    private function validate(array $dishes, MealSlot $slot): void
    {
        $defaults = array_filter($dishes, static fn (MealDishInputDto $dish): bool => $dish->default);
        if (count($defaults) > 1) {
            throw new InvalidRequestException($this->translator->trans('meal_menu.more_than_one_default', [
                '%meal%' => $slot->name,
            ], 'errors'));
        }

        $names = [];
        $ids = [];
        foreach ($dishes as $dish) {
            $name = mb_strtolower(trim($dish->name));
            if ($name === '') {
                throw new InvalidRequestException($this->translator->trans('meal_menu.dish_name_required', [
                    '%meal%' => $slot->name,
                ], 'errors'));
            }
            if (isset($names[$name])) {
                throw new InvalidRequestException($this->translator->trans('meal_menu.duplicate_dish', [
                    '%dish%' => trim($dish->name), '%meal%' => $slot->name,
                ], 'errors'));
            }
            $names[$name] = true;

            if ($dish->id === null) {
                continue;
            }
            // Jídlo jiného slotu by se tu tiše přestěhovalo i s výběry lidí, kteří ho brali k jiné porci.
            if ($slot->findDish($dish->id) === null || isset($ids[$dish->id])) {
                throw new InvalidRequestException($this->translator->trans('meal_menu.dish_not_in_slot', [
                    '%id%' => $dish->id, '%meal%' => $slot->name,
                ], 'errors'));
            }
            $ids[$dish->id] = true;
        }
    }
}
