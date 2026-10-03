<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Entity\OrderItem;
use PHPUnit\Framework\TestCase;

class OrderCancelTest extends TestCase
{
    public function testEmptyOrderCanBeCancelled(): void
    {
        $order = new Order();

        $order->cancel();

        self::assertTrue($order->isCancelled());
    }

    /**
     * Stock is capacity minus the purchase rows, so a cancelled order still holding rows
     * would keep its pieces sold — and billed.
     */
    public function testOrderStillHoldingItemsCannotBeCancelled(): void
    {
        $order = new Order();
        $order->addItem(new OrderItem());

        $this->expectException(\LogicException::class);

        $order->cancel();
    }
}
