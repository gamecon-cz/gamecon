<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Dto\MealMenu\AdminMealMenuOutputDto;
use App\Dto\MealMenu\SetMealMenuSlotInputDto;
use App\Enum\PermissionEnum;
use App\Security\Voter\PermissionVoter;
use App\State\Admin\AdminMealMenuProvider;
use App\State\Admin\SetMealMenuSlotProcessor;

/**
 * Zadávání jídelníčku v adminu (Finance → Jídelníček).
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/meal-menu',
            output: AdminMealMenuOutputDto::class,
            provider: AdminMealMenuProvider::class,
            security: self::MENU_EDITOR,
            securityMessage: 'meal_menu.no_right_to_edit',
            openapi: new Operation(
                summary: 'This year\'s meal menu',
                description: 'Every lunch and dinner with its dishes and how many portions chose each of them.',
            ),
        ),
        new Post(
            uriTemplate: '/admin/meal-menu',
            input: SetMealMenuSlotInputDto::class,
            output: AdminMealMenuOutputDto::class,
            processor: SetMealMenuSlotProcessor::class,
            security: self::MENU_EDITOR,
            securityMessage: 'meal_menu.no_right_to_edit',
            openapi: new Operation(
                summary: 'Set the dishes of one lunch or dinner',
                description: 'Replaces the dishes of the meal named by variantId with exactly the ones sent. A removed dish drops the choices made for it, so those portions get the default dish. Returns the same payload as GET.',
            ),
        ),
    ],
)]
class AdminMealMenuResource
{
    /**
     * Stejné právo, jaké si deklaruje stránka v adminu, tedy to, kterým se spravuje e-shop.
     */
    public const MENU_EDITOR = "is_granted('" . PermissionVoter::ANY_OF . "', ["
        . PermissionEnum::ADMINISTRACE_FINANCE->value . '])';
}
