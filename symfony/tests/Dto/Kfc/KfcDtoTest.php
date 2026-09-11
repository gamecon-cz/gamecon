<?php

declare(strict_types=1);

namespace App\Tests\Dto\Kfc;

use App\Dto\Kfc\KfcGridCellOutputDto;
use App\Dto\Kfc\KfcGridOutputDto;
use App\Dto\Kfc\KfcProductOutputDto;
use App\Dto\Kfc\KfcSaleOutputDto;
use PHPUnit\Framework\TestCase;

class KfcDtoTest extends TestCase
{
    public function testProductOutputDto(): void
    {
        $dto = new KfcProductOutputDto(
            id: 42,
            name: 'Tričko modré 2026',
            price: 250,
            remaining: 15,
        );

        $this->assertSame(42, $dto->id);
        $this->assertSame('Tričko modré 2026', $dto->name);
        $this->assertSame(250, $dto->price);
        $this->assertSame(15, $dto->remaining);
    }

    public function testProductOutputDtoWithUnlimitedStock(): void
    {
        $dto = new KfcProductOutputDto(
            id: 1,
            name: 'Vstupné',
            price: 100,
            remaining: null,
        );

        $this->assertNull($dto->remaining);
    }

    public function testGridOutputDto(): void
    {
        $cell = new KfcGridCellOutputDto(
            id: 1,
            typ: 0,
            text: 'Tričko',
            barva: '#f4bb57',
            barvaText: '#000000',
            cilId: 42,
        );

        $grid = new KfcGridOutputDto(
            id: 1,
            text: 'Úvod',
            bunky: [$cell],
        );

        $this->assertSame(1, $grid->id);
        $this->assertSame('Úvod', $grid->text);
        $this->assertCount(1, $grid->bunky);
        $this->assertSame(0, $grid->bunky[0]->typ);
        $this->assertSame(42, $grid->bunky[0]->cilId);
        $this->assertSame('#f4bb57', $grid->bunky[0]->barva);
    }

    public function testGridCellTypes(): void
    {
        $product = new KfcGridCellOutputDto(id: 1, typ: 0, text: null, barva: null, barvaText: null, cilId: 42);
        $page = new KfcGridCellOutputDto(id: 2, typ: 1, text: 'Trička', barva: '#ff0000', barvaText: null, cilId: 5);
        $back = new KfcGridCellOutputDto(id: 3, typ: 2, text: 'Zpět', barva: null, barvaText: null, cilId: null);
        $summary = new KfcGridCellOutputDto(id: 4, typ: 3, text: 'Shrnutí', barva: null, barvaText: null, cilId: null);

        $this->assertSame(0, $product->typ);
        $this->assertSame(1, $page->typ);
        $this->assertSame(2, $back->typ);
        $this->assertSame(3, $summary->typ);
    }

    public function testSaleOutputDto(): void
    {
        $dto = new KfcSaleOutputDto(
            soldItems: 5,
            totalPrice: '750',
        );

        $this->assertSame(5, $dto->soldItems);
        $this->assertSame('750', $dto->totalPrice);
    }
}
