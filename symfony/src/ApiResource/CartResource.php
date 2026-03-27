<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Dto\Cart\AddToCartInputDto;
use App\Dto\Cart\CartOutputDto;
use App\Dto\Cart\CheckoutInputDto;
use App\State\Cart\AddToCartProcessor;
use App\State\Cart\CartProvider;
use App\State\Cart\CheckoutProcessor;
use App\State\Cart\RemoveFromCartProcessor;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/cart',
            output: CartOutputDto::class,
            provider: CartProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Get current cart',
                description: 'Returns the current pending order (cart) for the authenticated user.',
            ),
        ),
        new Post(
            uriTemplate: '/cart/items',
            input: AddToCartInputDto::class,
            output: CartOutputDto::class,
            processor: AddToCartProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Add item to cart',
                description: 'Adds a product variant (or bundle) to the cart. Creates a new cart if none exists.',
            ),
        ),
        new Delete(
            uriTemplate: '/cart/items/{itemId}',
            output: CartOutputDto::class,
            processor: RemoveFromCartProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Remove item from cart',
                description: 'Removes an item from the cart and returns stock.',
            ),
        ),
        new Post(
            uriTemplate: '/cart/checkout',
            input: CheckoutInputDto::class,
            output: CartOutputDto::class,
            processor: CheckoutProcessor::class,
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Checkout cart',
                description: 'Confirms the current cart and marks the order as completed.',
            ),
        ),
    ],
)]
class CartResource
{
}
