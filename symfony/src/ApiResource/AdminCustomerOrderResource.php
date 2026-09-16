<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Dto\Admin\SetCustomerAccommodationInputDto;
use App\Dto\Admin\CustomerMealsOutputDto;
use App\Dto\Admin\SetCustomerMealsInputDto;
use App\Dto\Cart\AccommodationOutputDto;
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
            // The operator's right is checked in the provider, for the same reason as below.
            security: "is_granted('ROLE_USER')",
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
            // The operator's right is checked in the processor: ROLE_ADMIN is granted by role
            // code, and the codes carrying these rights are per-year, so it matches neither.
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Set a participant\'s accommodation',
                description: 'Replaces the named customer\'s nights with exactly the ones sent; an empty list cancels the booking. Returns the same payload as GET.',
            ),
        ),
        new Get(
            uriTemplate: '/admin/customer-meals',
            output: CustomerMealsOutputDto::class,
            provider: CustomerMealsProvider::class,
            // The operator's right is checked in the provider, for the same reason as above.
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Read a participant\'s meals',
                description: 'The variant ids the customer named in ?customerId currently holds. The catalogue comes from /cart/meals.',
            ),
        ),
        new Post(
            uriTemplate: '/admin/customer-meals',
            input: SetCustomerMealsInputDto::class,
            output: false,
            processor: SetCustomerMealsProcessor::class,
            // The operator's right is checked in the processor, for the same reason as above.
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Set a participant\'s meals',
                description: 'Replaces the named customer\'s meals with exactly the ones sent; an empty list cancels them all.',
            ),
        ),
    ],
)]
class AdminCustomerOrderResource
{
}
