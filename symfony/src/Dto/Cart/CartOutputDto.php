<?php

declare(strict_types=1);

namespace App\Dto\Cart;

use App\Entity\Order;

class CartOutputDto
{
    public ?int $id;
    public string $status;
    public string $totalPrice;
    public int $itemCount;

    /**
     * @var CartItemOutputDto[]
     */
    public array $items;

    public static function fromOrder(Order $order): self
    {
        $dto = new self();
        $dto->id = $order->getId();
        $dto->status = $order->getStatus();
        $dto->totalPrice = $order->getTotalPrice();
        $dto->itemCount = $order->getItemCount();
        $dto->items = array_map(
            static fn ($item) => CartItemOutputDto::fromOrderItem($item),
            $order->getItems()->toArray(),
        );

        return $dto;
    }

    public static function empty(): self
    {
        $dto = new self();
        $dto->id = null;
        $dto->status = 'empty';
        $dto->totalPrice = '0.00';
        $dto->itemCount = 0;
        $dto->items = [];

        return $dto;
    }
}
