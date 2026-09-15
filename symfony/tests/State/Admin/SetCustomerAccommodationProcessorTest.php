<?php

declare(strict_types=1);

namespace App\Tests\State\Admin;

use ApiPlatform\Metadata\Post;
use App\Dto\Admin\SetCustomerAccommodationInputDto;
use App\Entity\User;
use App\Service\AccommodationRules;
use App\Service\AccommodationWriter;
use App\Service\CurrentYearProviderInterface;
use App\Service\LegacySessionService;
use App\State\Admin\SetCustomerAccommodationProcessor;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Pravo;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Covers who may call this and whose booking it becomes. The writer is readonly and cannot be
 * doubled, so a successful booking is not exercised here — the rules it applies are covered in
 * AccommodationWriterTest.
 */
class SetCustomerAccommodationProcessorTest extends AbstractDatabaseKernelTestCase
{
    private MockObject $entityManager;

    private MockObject $legacySession;

    private SetCustomerAccommodationProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->legacySession = $this->createMock(LegacySessionService::class);

        $container = static::getContainer();

        $this->processor = new SetCustomerAccommodationProcessor(
            $container->get(AccommodationWriter::class),
            $container->get(AccommodationRules::class),
            $container->get(CurrentYearProviderInterface::class),
            $this->legacySession,
            $this->entityManager,
        );
    }

    private function operator(bool $smiObjednavat = true): MockObject
    {
        $operator = $this->createMock(\Uzivatel::class);
        $operator->method('maPravo')->willReturnCallback(
            static fn (int $pravo): bool => $smiObjednavat && in_array(
                $pravo,
                [Pravo::ADMINISTRACE_UBYTOVANI, Pravo::ADMINISTRACE_INFOPULT],
                true,
            ),
        );

        return $operator;
    }

    private function vstup(int $customerId = 4242): SetCustomerAccommodationInputDto
    {
        $input = new SetCustomerAccommodationInputDto();
        $input->customerId = $customerId;

        return $input;
    }

    public function testSignedOutCallerIsRefused(): void
    {
        $this->legacySession->method('getCurrentUser')->willReturn(null);
        $this->entityManager->expects(self::never())->method('find');

        $this->expectException(AccessDeniedHttpException::class);

        $this->processor->process($this->vstup(), new Post());
    }

    /**
     * ROLE_ADMIN cannot be the gate — it is granted by role code, and the codes carrying these
     * rights are per-year — so the operator's legacy right is what decides.
     */
    public function testOperatorWithoutTheRightIsRefused(): void
    {
        $this->legacySession->method('getCurrentUser')->willReturn($this->operator(smiObjednavat: false));
        $this->entityManager->expects(self::never())->method('find');

        $this->expectException(AccessDeniedHttpException::class);

        $this->processor->process($this->vstup(), new Post());
    }

    public function testUnknownCustomerIsRefused(): void
    {
        $this->legacySession->method('getCurrentUser')->willReturn($this->operator());
        $this->entityManager->method('find')->willReturn(null);
        $this->legacySession->expects(self::never())->method('getUserById');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('~123456789~');

        $this->processor->process($this->vstup(123456789), new Post());
    }

    /**
     * The rights that decide what may be booked live on the legacy user, so a customer the
     * legacy side cannot resolve must stop the booking instead of falling through to whatever
     * rights the operator happens to hold.
     */
    public function testCustomerMissingOnTheLegacySideIsRefused(): void
    {
        $customer = $this->createMock(User::class);
        $customer->method('getId')->willReturn(4242);
        $this->legacySession->method('getCurrentUser')->willReturn($this->operator());
        $this->entityManager->method('find')->willReturn($customer);
        $this->legacySession->method('getUserById')->willReturn(null);

        $this->expectException(BadRequestHttpException::class);

        $this->processor->process($this->vstup(), new Post());
    }

    /**
     * The customer comes from the payload, not from the security token — the operator sends the
     * request but the booking is the customer's.
     */
    public function testLegacyRightsAreReadForThePayloadCustomerNotTheOperator(): void
    {
        $customer = $this->createMock(User::class);
        $customer->method('getId')->willReturn(4242);
        $this->legacySession->method('getCurrentUser')->willReturn($this->operator());
        $this->entityManager->method('find')->willReturn($customer);

        $this->legacySession
            ->expects(self::once())
            ->method('getUserById')
            ->with(self::identicalTo(4242))
            ->willReturn(null);

        $this->expectException(BadRequestHttpException::class);

        $this->processor->process($this->vstup(), new Post());
    }
}
