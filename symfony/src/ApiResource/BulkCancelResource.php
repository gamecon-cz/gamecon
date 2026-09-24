<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Dto\Admin\BulkCancelInputDto;
use App\Dto\Admin\BulkCancelOutputDto;
use App\State\Admin\BulkCancelProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/admin/bulk-cancel',
            input: BulkCancelInputDto::class,
            output: BulkCancelOutputDto::class,
            processor: BulkCancelProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Operation(
                summary: 'Bulk cancel orders',
                description: 'Cancels items for multiple users, optionally filtered by product tag. Archives cancelled items and returns stock.',
            ),
        ),
    ],
)]
class BulkCancelResource
{
}
