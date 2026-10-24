<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\InvalidRequestException;
use App\Exception\NoLongerAvailableException;
use App\Repository\OrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Výběr konkrétního jídla k už koupené porci.
 *
 * Porce se nekupuje znovu, mění se jen to, co k ní kuchyně vydá — cena, slevy ani kapacita se
 * nehnou. Zápis jde přes entitu, aby změnu zachytil audit nákupů.
 */
class MealDishChooser
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly MealMenuReader $menuReader,
        private readonly MealMenuSchedule $schedule,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param int|null $dishId   NULL vrátí porci k výchozímu jídlu
     * @param bool     $fromDesk vybírá pult — smí i před zveřejněním a po termínu
     *
     * @throws InvalidRequestException    porce není zákazníkova nebo jídlo k ní nepatří
     * @throws NoLongerAvailableException účastníkovi skončil výběr nebo ještě nezačal
     */
    public function choose(User $customer, int $orderItemId, ?int $dishId, int $year, bool $fromDesk): void
    {
        $portion = $this->orderItemRepository->find($orderItemId);
        if ($portion === null
            || $portion->getCustomer()?->getId() !== $customer->getId()
            || $portion->getYear() !== $year
        ) {
            throw new InvalidRequestException($this->translator->trans('meal_dish.portion_not_found', [
                '%id%' => $orderItemId,
            ], 'errors'));
        }

        $slot = $this->menuReader->findSlot($portion->getVariant()?->getId() ?? 0, $year);
        if ($slot === null) {
            throw new InvalidRequestException($this->translator->trans('meal_dish.not_a_meal_with_choice', [
                '%meal%' => $portion->getVariant()?->getFullName() ?? (string) $orderItemId,
            ], 'errors'));
        }

        if (! $fromDesk) {
            if (! $this->schedule->isPublished()) {
                throw new NoLongerAvailableException($this->translator->trans('meal_dish.menu_not_published', [
                    '%date%' => $this->schedule->getPublishedFrom()->format('j. n.'),
                ], 'errors'));
            }
            if ($this->schedule->isChoiceClosed()) {
                throw new NoLongerAvailableException($this->translator->trans('meal_dish.choice_closed', [], 'errors'));
            }
        }

        $dish = null;
        if ($dishId !== null) {
            $dish = $slot->findDish($dishId) ?? throw new InvalidRequestException($this->translator->trans('meal_dish.not_in_slot', [
                '%id%' => $dishId, '%meal%' => $slot->name,
            ], 'errors'));
        }

        $portion->setMealDish($dish);
        $this->entityManager->flush();
    }
}
