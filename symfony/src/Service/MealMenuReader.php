<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\MealMenu\AdminMealDishOutputDto;
use App\Dto\MealMenu\AdminMealMenuOutputDto;
use App\Dto\MealMenu\AdminMealMenuSlotOutputDto;
use App\Dto\MealMenu\MealDishOutputDto;
use App\Dto\MealMenu\MealMenuOutputDto;
use App\Dto\MealMenu\MealMenuSlotOutputDto;
use App\Dto\MealMenu\MealPortionOutputDto;
use App\Entity\MealDish;
use App\Entity\OrderItem;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductTagCode;
use App\Repository\MealDishRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Gamecon\Role\Role;

/**
 * Jídelníček: obědy a večeře letošní nabídky a jídla, ze kterých se k nim vybírá.
 *
 * Kdo si jídlo nevybere, dostane výchozí jídlo slotu. Neukládá se to nikam — výchozí se
 * dosazuje při čtení, takže změna výchozího jídla v adminu platí i pro ty, kdo nevybrali.
 */
class MealMenuReader
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly MealDishRepository $mealDishRepository,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly MealMenuSchedule $schedule,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return MealSlot[] po dnech, v každém dni oběd před večeří
     */
    public function getSlots(int $year): array
    {
        $dishesByVariant = [];
        foreach ($this->mealDishRepository->findByYear($year) as $dish) {
            $dishesByVariant[$dish->getVariant()->getId()][] = $dish;
        }

        $slots = [];
        foreach ($this->productRepository->findByTag(ProductTagCode::JIDLO) as $product) {
            // Snídaně je švédský stůl, není z čeho vybírat.
            if ($product->hasTag(ProductTagCode::SNIDANE->value)) {
                continue;
            }
            $variant = $product->getVariants()->first();
            if (! $variant instanceof ProductVariant || $variant->getId() === null) {
                continue;
            }
            $day = $variant->getAccommodationDay() ?? $product->getAccommodationDay();
            if ($day === null) {
                continue;
            }
            $slots[] = new MealSlot($variant, $day, $product->getName(), $dishesByVariant[$variant->getId()] ?? []);
        }

        usort($slots, static fn (MealSlot $a, MealSlot $b): int => [$a->day, MealSlot::getMealOrder($a->name), $a->name]
            <=> [$b->day, MealSlot::getMealOrder($b->name), $b->name]);

        return $slots;
    }

    public function findSlot(int $variantId, int $year): ?MealSlot
    {
        foreach ($this->getSlots($year) as $slot) {
            if ($slot->getVariantId() === $variantId) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * @param bool $fromDesk čte pult — vidí jídla i před zveřejněním a výběr se mu nezavírá
     */
    public function forCustomer(User $customer, int $year, bool $fromDesk): MealMenuOutputDto
    {
        $published = $this->schedule->isPublished();
        $showDishes = $published || $fromDesk;

        $dto = new MealMenuOutputDto();
        $dto->published = $published;
        $dto->publishedFrom = $this->schedule->getPublishedFrom()->format('Y-m-d');
        $dto->locked = ! $fromDesk && $this->schedule->isChoiceClosed();
        $dto->choiceDeadline = $this->schedule->getChoiceDeadline()->format('Y-m-d');

        $slots = $this->getSlots($year);
        $portionsByVariant = $this->portionsByVariant($customer, $year, $slots);
        foreach ($slots as $slot) {
            $slotDto = $this->slotDto($slot);
            $defaultDish = $slot->getDefaultDish();
            if ($showDishes) {
                $slotDto->dishes = array_map(
                    static fn (MealDish $dish): MealDishOutputDto => MealDishOutputDto::fromDish($dish, $dish === $defaultDish),
                    $slot->dishes,
                );
            }
            foreach ($portionsByVariant[$slot->getVariantId()] ?? [] as $portion) {
                $portionDto = new MealPortionOutputDto();
                $portionDto->orderItemId = $portion->getId() ?? throw new \LogicException('Porce ještě nemá id.');
                if ($showDishes) {
                    $portionDto->chosenDishId = $portion->getMealDish()?->getId();
                    $portionDto->effectiveDishId = $slot->getEffectiveDish($portion)?->getId();
                }
                $slotDto->portions[] = $portionDto;
            }
            $dto->slots[] = $slotDto;
        }

        return $dto;
    }

    public function forAdmin(int $year): AdminMealMenuOutputDto
    {
        $dto = new AdminMealMenuOutputDto();
        $dto->published = $this->schedule->isPublished();
        $dto->publishedFrom = $this->schedule->getPublishedFrom()->format('Y-m-d');
        $dto->choiceClosed = $this->schedule->isChoiceClosed();
        $dto->choiceDeadline = $this->schedule->getChoiceDeadline()->format('Y-m-d');

        $slots = $this->getSlots($year);
        $counts = $this->countPortions($year, $slots);
        foreach ($slots as $slot) {
            $slotDto = new AdminMealMenuSlotOutputDto();
            $slotDto->variantId = $slot->getVariantId();
            $slotDto->day = $slot->day;
            $slotDto->dayName = $slot->getDayName();
            $slotDto->name = $slot->name;
            $slotCounts = $counts[$slot->getVariantId()] ?? [];
            $slotDto->portionCount = array_sum($slotCounts);
            $defaultDish = $slot->getDefaultDish();
            $chosen = 0;
            foreach ($slot->dishes as $dish) {
                $chosenCount = $slotCounts[$dish->getId()] ?? 0;
                $chosen += $chosenCount;
                $slotDto->dishes[] = AdminMealDishOutputDto::fromDish($dish, $dish === $defaultDish, $chosenCount);
            }
            $slotDto->unchosenCount = $slotDto->portionCount - $chosen;
            $dto->slots[] = $slotDto;
        }

        return $dto;
    }

    /**
     * @param MealSlot[] $slots
     *
     * @return array<int, OrderItem[]> podle id varianty
     */
    private function portionsByVariant(User $customer, int $year, array $slots): array
    {
        if ($slots === []) {
            return [];
        }

        $portions = [];
        foreach ($this->orderItemRepository->findBy([
            'customer' => $customer->getId(),
            'year'     => $year,
            'variant'  => array_map(static fn (MealSlot $slot): int => $slot->getVariantId(), $slots),
        ], [
            'id' => 'ASC',
        ]) as $portion) {
            $portions[$portion->getVariant()?->getId()][] = $portion;
        }

        return $portions;
    }

    /**
     * @param mealSlot[] $slots
     *
     * Jen porce přihlášených na letošní GameCon, stejně jako stravenky a export pro jídelnu
     * (JidlaZJidelnicku) — jinak by admin a jídelna dostali jiná čísla
     *
     * @return array<int, array<int, int>> id varianty => (id jídla, 0 = nevybráno) => počet porcí
     */
    private function countPortions(int $year, array $slots): array
    {
        if ($slots === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT shop_nakupy.variant_id, COALESCE(shop_nakupy.meal_dish_id, 0) AS meal_dish_id, COUNT(*) AS pocet
            FROM shop_nakupy
            JOIN platne_role_uzivatelu
                ON platne_role_uzivatelu.id_uzivatele = shop_nakupy.id_uzivatele
                AND platne_role_uzivatelu.id_role = :registered
            WHERE shop_nakupy.rok = :year
                AND shop_nakupy.variant_id IN (:variants)
            GROUP BY shop_nakupy.variant_id, COALESCE(shop_nakupy.meal_dish_id, 0)
            SQL,
            [
                'year'       => $year,
                'registered' => Role::PRIHLASEN_NA_LETOSNI_GC($year),
                'variants'   => array_map(static fn (MealSlot $slot): int => $slot->getVariantId(), $slots),
            ],
            [
                'variants' => ArrayParameterType::INTEGER,
            ],
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['variant_id']][(int) $row['meal_dish_id']] = (int) $row['pocet'];
        }

        return $counts;
    }

    private function slotDto(MealSlot $slot): MealMenuSlotOutputDto
    {
        $dto = new MealMenuSlotOutputDto();
        $dto->variantId = $slot->getVariantId();
        $dto->day = $slot->day;
        $dto->dayName = $slot->getDayName();
        $dto->name = $slot->name;

        return $dto;
    }
}
