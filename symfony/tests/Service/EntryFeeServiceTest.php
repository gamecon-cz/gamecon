<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\EntryFeeService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EntryFeeServiceTest extends TestCase
{
    /**
     * The slider is not linear, so the marker for last year's average has to be placed with the
     * same gamma correction the frontend uses; a plain percentage would sit visibly wrong.
     *
     * @dataProvider averages
     */
    public function testLastYearAverageIsPlacedWithGammaCorrection(float $average, int $expectedPercent): void
    {
        $service = $this->serviceWithAverage($average);

        self::assertSame($expectedPercent, $service->lastYearAveragePercent());
    }

    /**
     * @return iterable<string, array{float, int}>
     */
    public static function averages(): iterable
    {
        // sqrt(185.69 / 1000) ≈ 0.4309 — the value actually stored for 2026.
        yield 'real 2026 value' => [185.69, 43];
        yield 'nothing paid' => [0.0, 0];
        yield 'quarter of the scale sits at half its width' => [250.0, 50];
        yield 'top of the scale' => [1000.0, 100];
        // Amounts above the slider maximum must not push the marker off the track.
        yield 'above the scale is clamped' => [5000.0, 100];
    }

    public function testMissingAverageIsNotTreatedAsZero(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $yearProvider->method('getCurrentYear')->willReturn(2026);

        $service = new EntryFeeService(
            $this->createMock(ProductRepository::class),
            $this->createMock(OrderItemRepository::class),
            $entityManager,
            $yearProvider,
        );

        // Chybějící nastavení není průměr 0 Kč — značka se nemá kreslit vlevo, ale vůbec.
        self::assertSame(EntryFeeService::AVERAGE_UNKNOWN, $service->lastYearAveragePercent());
    }

    private function serviceWithAverage(float $average): EntryFeeService
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn((string) $average);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $yearProvider = $this->createMock(CurrentYearProviderInterface::class);
        $yearProvider->method('getCurrentYear')->willReturn(2026);

        return new EntryFeeService(
            $this->createMock(ProductRepository::class),
            $this->createMock(OrderItemRepository::class),
            $entityManager,
            $yearProvider,
        );
    }
}
