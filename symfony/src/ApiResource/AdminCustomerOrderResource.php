<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Dto\Admin\SetCustomerAccommodationInputDto;
use App\State\Admin\SetCustomerAccommodationProcessor;

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
        new Post(
            uriTemplate: '/admin/customer-accommodation',
            input: SetCustomerAccommodationInputDto::class,
            output: false,
            processor: SetCustomerAccommodationProcessor::class,
            // The operator's right is checked in the processor: ROLE_ADMIN is granted by role
            // code, and the codes carrying these rights are per-year, so it matches neither.
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Set a participant\'s accommodation',
                description: 'Replaces the named customer\'s nights with exactly the ones sent; an empty list cancels the booking.',
            ),
        ),
    ],
)]
class AdminCustomerOrderResource
{
}
