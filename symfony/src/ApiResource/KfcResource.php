<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Dto\Kfc\KfcGridInputDto;
use App\Dto\Kfc\KfcGridOutputDto;
use App\Dto\Kfc\KfcProductOutputDto;
use App\Dto\Kfc\KfcSaleInputDto;
use App\Dto\Kfc\KfcSaleOutputDto;
use App\State\Kfc\KfcGridProcessor;
use App\State\Kfc\KfcGridProvider;
use App\State\Kfc\KfcProductsProvider;
use App\State\Kfc\KfcSaleProcessor;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/kfc/products',
            output: KfcProductOutputDto::class,
            provider: KfcProductsProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Operation(
                summary: 'List KFC products',
                description: 'Returns all active products with prices and remaining stock for the KFC grid.',
            ),
        ),
        new GetCollection(
            uriTemplate: '/kfc/grids',
            output: KfcGridOutputDto::class,
            provider: KfcGridProvider::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Operation(
                summary: 'Get KFC grid configuration',
                description: 'Returns all grids with their cells for the KFC point-of-sale interface.',
            ),
        ),
        new Post(
            uriTemplate: '/kfc/grids',
            input: KfcGridInputDto::class,
            output: KfcGridOutputDto::class,
            processor: KfcGridProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Operation(
                summary: 'Save KFC grid configuration',
                description: 'Creates or updates grids and their cells.',
            ),
        ),
        new Post(
            uriTemplate: '/kfc/sale',
            input: KfcSaleInputDto::class,
            output: KfcSaleOutputDto::class,
            processor: KfcSaleProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Operation(
                summary: 'Submit KFC sale',
                description: 'Records point-of-sale purchases. Each item is stored as separate purchase records.',
            ),
        ),
    ],
)]
class KfcResource
{
}
