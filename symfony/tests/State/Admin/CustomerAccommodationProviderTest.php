<?php

declare(strict_types=1);

namespace App\Tests\State\Admin;

use ApiPlatform\Metadata\Get;
use App\Dto\Cart\AccommodationOutputDto;
use App\Entity\User;
use App\Service\AccommodationDeskRights;
use App\Service\LegacySessionService;
use App\State\Admin\CustomerAccommodationProvider;
use App\State\Cart\AccommodationGridInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Pravo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Covers who may read a participant's accommodation and how the customer is identified. What
 * the grid then contains is covered in AccommodationProviderTest.
 */
class CustomerAccommodationProviderTest extends TestCase
{
    private MockObject $accommodationGrid;

    private MockObject $entityManager;

    private MockObject $legacySession;

    private CustomerAccommodationProvider $provider;

    protected function setUp(): void
    {
        $this->accommodationGrid = $this->createMock(AccommodationGridInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->legacySession = $this->createMock(LegacySessionService::class);

        $this->provider = new CustomerAccommodationProvider(
            $this->accommodationGrid,
            new AccommodationDeskRights($this->legacySession),
            $this->legacySession,
            $this->entityManager,
        );
    }

    private function signInOperator(bool $mayOrder = true): void
    {
        $operator = $this->createMock(\Uzivatel::class);
        $operator->method('maPravo')->willReturnCallback(
            static fn (int $permission): bool => $mayOrder && in_array(
                $permission,
                [Pravo::ADMINISTRACE_UBYTOVANI, Pravo::ADMINISTRACE_INFOPULT],
                true,
            ),
        );
        $this->legacySession->method('getCurrentUser')->willReturn($operator);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function read(array $filters): AccommodationOutputDto
    {
        return $this->provider->provide(new Get(), [], [
            'filters' => $filters,
        ]);
    }

    public function testSignedOutCallerIsRefused(): void
    {
        $this->legacySession->method('getCurrentUser')->willReturn(null);
        $this->entityManager->expects(self::never())->method('find');

        $this->expectException(AccessDeniedHttpException::class);

        $this->read([
            'customerId' => '4242',
        ]);
    }

    public function testOperatorWithoutTheRightIsRefused(): void
    {
        $this->signInOperator(mayOrder: false);
        $this->entityManager->expects(self::never())->method('find');

        $this->expectException(AccessDeniedHttpException::class);

        $this->read([
            'customerId' => '4242',
        ]);
    }

    public function testMissingCustomerIsRefused(): void
    {
        $this->signInOperator();

        $this->expectException(BadRequestHttpException::class);

        $this->read([]);
    }

    /**
     * A repeated ?customerId arrives as an array, which would cast to 1 and quietly answer for
     * whoever that is — so anything that is not a plain number is refused outright.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function nonsensicalCustomerIdProvider(): iterable
    {
        yield 'repeated parameter' => [['1', '2']];
        yield 'trailing garbage' => ['12abc'];
        yield 'exponent' => ['1e3'];
        yield 'negative' => ['-5'];
        yield 'zero' => ['0'];
        yield 'not a number' => ['abc'];
    }

    /**
     * @dataProvider nonsensicalCustomerIdProvider
     */
    public function testNonsensicalCustomerIdIsRefused(mixed $customerId): void
    {
        $this->signInOperator();
        $this->entityManager->expects(self::never())->method('find');

        $this->expectException(BadRequestHttpException::class);

        $this->read([
            'customerId' => $customerId,
        ]);
    }

    public function testUnknownCustomerIsRefused(): void
    {
        $this->signInOperator();
        $this->entityManager->method('find')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('~4242~');

        $this->read([
            'customerId' => '4242',
        ]);
    }

    public function testGridIsReadForTheNamedCustomer(): void
    {
        $customer = $this->createMock(User::class);
        $legacyCustomer = $this->createMock(\Uzivatel::class);
        $this->signInOperator();
        $this->entityManager->method('find')->willReturn($customer);
        $this->legacySession->method('getUserById')->with(self::identicalTo(4242))->willReturn($legacyCustomer);

        $grid = new AccommodationOutputDto();
        $this->accommodationGrid
            ->expects(self::once())
            ->method('forCustomer')
            ->with(self::identicalTo($customer), self::identicalTo($legacyCustomer))
            ->willReturn($grid);

        self::assertSame($grid, $this->read([
            'customerId' => '4242',
        ]));
    }
}
