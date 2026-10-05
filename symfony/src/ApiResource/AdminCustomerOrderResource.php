<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Dto\Admin\CustomerMealsOutputDto;
use App\Dto\Admin\SetCustomerAccommodationInputDto;
use App\Dto\Admin\SetCustomerMealsInputDto;
use App\Dto\Cart\AccommodationOutputDto;
use App\Enum\PermissionEnum;
use App\Security\Voter\PermissionVoter;
use App\State\Admin\CustomerAccommodationProvider;
use App\State\Admin\CustomerMealsProvider;
use App\State\Admin\SetCustomerAccommodationProcessor;
use App\State\Admin\SetCustomerMealsProcessor;

/**
 * Ordering on a participant's behalf, from the admin desk.
 *
 * The cart endpoints always act on the authenticated user, so the desk needs its own: here
 * the customer is named in the payload and the operator is whoever is signed in.
 *
 * @see SetCustomerAccommodationProcessor for how rights and deadlines differ
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/admin/customer-accommodation',
            output: AccommodationOutputDto::class,
            provider: CustomerAccommodationProvider::class,
            security: self::DESK_OPERATOR,
            securityMessage: 'desk.no_right_to_order',
            openapi: new Operation(
                summary: 'Read a participant\'s accommodation',
                description: 'The same payload the participant sees for themselves, for the customer named in ?customerId.',
            ),
        ),
        new Post(
            uriTemplate: '/admin/customer-accommodation',
            input: SetCustomerAccommodationInputDto::class,
            output: AccommodationOutputDto::class,
            processor: SetCustomerAccommodationProcessor::class,
            security: self::DESK_OPERATOR,
            securityMessage: 'desk.no_right_to_order',
            openapi: new Operation(
                summary: 'Set a participant\'s accommodation',
                description: 'Replaces the named customer\'s nights with exactly the ones sent; an empty list cancels the booking. Returns the same payload as GET.',
            ),
        ),
        new Get(
            uriTemplate: '/admin/customer-meals',
            output: CustomerMealsOutputDto::class,
            provider: CustomerMealsProvider::class,
            security: self::DESK_OPERATOR,
            securityMessage: 'desk.no_right_to_order',
            openapi: new Operation(
                summary: 'Read a participant\'s meals',
                description: 'The variant ids the customer named in ?customerId currently holds. The catalogue comes from /cart/meals.',
            ),
        ),
        new Post(
            uriTemplate: '/admin/customer-meals',
            input: SetCustomerMealsInputDto::class,
            output: CustomerMealsOutputDto::class,
            processor: SetCustomerMealsProcessor::class,
            security: self::DESK_OPERATOR,
            securityMessage: 'desk.no_right_to_order',
            openapi: new Operation(
                summary: 'Set a participant\'s meals',
                description: 'Replaces the named customer\'s meals with exactly the ones sent; an empty list cancels them all. Answers with what the customer ends up holding, which drops a breakfast the hotel covers.',
            ),
        ),
    ],
)]
class AdminCustomerOrderResource
{
    /**
     * The rights the two admin screens declare in their module headers: this is about reaching
     * those screens at all, not about what is being ordered.
     */
    public const DESK_OPERATOR = "is_granted('" . PermissionVoter::ANY_OF . "', ["
        . PermissionEnum::ADMINISTRACE_UBYTOVANI->value . ', '
        . PermissionEnum::ADMINISTRACE_INFOPULT->value . '])';
}
