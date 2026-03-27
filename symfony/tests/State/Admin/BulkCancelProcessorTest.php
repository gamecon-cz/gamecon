<?php

declare(strict_types=1);

namespace App\Tests\State\Admin;

use ApiPlatform\Metadata\Post;
use App\Dto\Admin\BulkCancelInputDto;
use App\Dto\Admin\BulkCancelOutputDto;
use App\Entity\User;
use App\Service\BulkCancelService;
use App\State\Admin\BulkCancelProcessor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class BulkCancelProcessorTest extends TestCase
{
    private MockObject $bulkCancelService;

    private MockObject $entityManager;

    private MockClock $clock;

    private BulkCancelProcessor $processor;

    protected function setUp(): void
    {
        $this->bulkCancelService = $this->createMock(BulkCancelService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->clock = new MockClock(new \DateTimeImmutable('2026-07-15 10:00:00'));

        $this->processor = new BulkCancelProcessor(
            $this->bulkCancelService,
            $this->entityManager,
            $this->clock,
        );
    }

    public function testBulkCancelByTag(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);

        $this->entityManager->method('find')
            ->willReturnCallback(fn (string $class, int $id) => match ($id) {
                1 => $user1,
                2 => $user2,
                default => null,
            });

        $this->bulkCancelService->expects($this->once())
            ->method('cancelByTagForUsers')
            ->with([$user1, $user2], 'ubytovani', 'hromadne-odhlaseni', $this->clock->now())
            ->willReturn(5);

        $input = new BulkCancelInputDto();
        $input->userIds = [1, 2];
        $input->tag = 'ubytovani';
        $input->reason = 'hromadne-odhlaseni';

        $result = $this->processor->process($input, new Post());

        $this->assertInstanceOf(BulkCancelOutputDto::class, $result);
        $this->assertSame(5, $result->cancelledCount);
        $this->assertSame(2, $result->usersAffected);
        $this->assertSame('hromadne-odhlaseni', $result->reason);
    }

    public function testBulkCancelAllForUsers(): void
    {
        $user = $this->createMock(User::class);

        $this->entityManager->method('find')
            ->with(User::class, 1)
            ->willReturn($user);

        $this->bulkCancelService->expects($this->once())
            ->method('cancelAllForUser')
            ->with($user, 'storno', $this->clock->now())
            ->willReturn(3);

        $input = new BulkCancelInputDto();
        $input->userIds = [1];
        $input->tag = null;
        $input->reason = 'storno';

        $result = $this->processor->process($input, new Post());

        $this->assertSame(3, $result->cancelledCount);
        $this->assertSame(1, $result->usersAffected);
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $this->entityManager->method('find')->willReturn(null);

        $input = new BulkCancelInputDto();
        $input->userIds = [999];
        $input->reason = 'test';

        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $this->expectExceptionMessage('999');

        $this->processor->process($input, new Post());
    }
}
