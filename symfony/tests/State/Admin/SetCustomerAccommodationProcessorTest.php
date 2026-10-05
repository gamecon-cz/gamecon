<?php

declare(strict_types=1);

namespace App\Tests\State\Admin;

use ApiPlatform\Metadata\Post;
use App\Dto\Admin\SetCustomerAccommodationInputDto;
use App\Dto\Cart\AccommodationOutputDto;
use App\Entity\User;
use App\Exception\InsufficientPermissionsException;
use App\Service\AccommodationRules;
use App\Service\AccommodationWriter;
use App\Service\CurrentYearProviderInterface;
use App\Service\LegacySessionService;
use App\State\Admin\SetCustomerAccommodationProcessor;
use App\State\Cart\AccommodationGridInterface;
use App\Tests\AbstractDatabaseKernelTestCase;
use App\Tests\Support\ChybovePreklady;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Pravo;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Covers whose booking it becomes, who decides overbooking, and what is handed to the
 * writer. The rules the writer then applies are covered in AccommodationWriterTest.
 */
class SetCustomerAccommodationProcessorTest extends AbstractDatabaseKernelTestCase
{
    private const OPERATOR_ID = 7;

    private MockObject $accommodationGrid;

    private MockObject $accommodationWriter;

    private MockObject $entityManager;

    private MockObject $legacySession;

    private MockObject $security;

    /**
     * @var array<int, \Uzivatel> by id, so the operator and the customer cannot be mistaken for each other
     */
    private array $legacyUsers = [];

    private SetCustomerAccommodationProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accommodationGrid = $this->createMock(AccommodationGridInterface::class);
        $this->accommodationWriter = $this->createMock(AccommodationWriter::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->legacySession = $this->createMock(LegacySessionService::class);
        $this->legacySession->method('getUserById')->willReturnCallback(
            fn (int $id): ?\Uzivatel => $this->legacyUsers[$id] ?? null,
        );
        $this->security = $this->createMock(Security::class);

        $container = static::getContainer();

        $this->processor = new SetCustomerAccommodationProcessor(
            $this->accommodationWriter,
            $container->get(AccommodationRules::class),
            $container->get(CurrentYearProviderInterface::class),
            $this->security,
            $this->legacySession,
            $this->accommodationGrid,
            $this->entityManager,
            ChybovePreklady::translator(),
        );
    }

    private function input(int $customerId = 4242): SetCustomerAccommodationInputDto
    {
        $input = new SetCustomerAccommodationInputDto();
        $input->customerId = $customerId;

        return $input;
    }

    private function signInOperator(bool $isInfopultChief = false): void
    {
        $operator = $this->createMock(User::class);
        $operator->method('getId')->willReturn(self::OPERATOR_ID);
        $this->security->method('getUser')->willReturn($operator);

        $legacyOperator = $this->createMock(\Uzivatel::class);
        $legacyOperator->method('jeSefInfopultu')->willReturn($isInfopultChief);
        $this->legacyUsers[self::OPERATOR_ID] = $legacyOperator;
    }

    private function customer(int $id = 4242): MockObject
    {
        $customer = $this->createMock(User::class);
        $customer->method('getId')->willReturn($id);
        $this->entityManager->method('find')->willReturn($customer);

        return $customer;
    }

    private function legacyCustomer(string $roommate = '', bool $maySingleNight = false, bool $isInfopultChief = false): MockObject
    {
        $legacyCustomer = $this->createMock(\Uzivatel::class);
        $legacyCustomer->method('ubytovanS')->willReturn($roommate);
        $legacyCustomer->method('jeSefInfopultu')->willReturn($isInfopultChief);
        // Answers for one right only: a blanket stub would pass even if the processor read
        // some other permission off the customer.
        $legacyCustomer->method('maPravo')->willReturnCallback(
            static fn (int $permission): bool => $maySingleNight && $permission === Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC,
        );
        $this->legacyUsers[4242] = $legacyCustomer;

        return $legacyCustomer;
    }

    public function testNightsAreSavedForTheCustomer(): void
    {
        $this->signInOperator();
        $customer = $this->customer();
        $this->legacyCustomer();

        $this->accommodationWriter
            ->expects(self::once())
            ->method('save')
            ->with(
                self::identicalTo($customer),
                self::identicalTo([11, 12]),
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo(false),
                self::anything(),
            );

        $input = $this->input();
        $input->variantIds = [11, 12];

        $this->processor->process($input, new Post());
    }

    /**
     * The infopult screen sends no roommate at all, and the writer would read that as "clear
     * it" — so an omitted one has to arrive as whatever the participant already has.
     */
    public function testOmittedRoommateKeepsTheStoredOne(): void
    {
        $this->signInOperator();
        $this->customer();
        $this->legacyCustomer(roommate: 'Už tam bydlí');

        $this->accommodationWriter
            ->expects(self::once())
            ->method('save')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo('Už tam bydlí'),
                self::anything(),
                self::anything(),
            );

        $this->processor->process($this->input(), new Post());
    }

    public function testSentRoommateReplacesTheStoredOne(): void
    {
        $this->signInOperator();
        $this->customer();
        $this->legacyCustomer(roommate: 'Už tam bydlí');

        $this->accommodationWriter
            ->expects(self::once())
            ->method('save')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo('Někdo jiný'),
                self::anything(),
                self::anything(),
            );

        $input = $this->input();
        $input->roommate = 'Někdo jiný';

        $this->processor->process($input, new Post());
    }

    /**
     * The single-night permission belongs to the customer; reading it off the operator would
     * let anyone book one night just because the person at the desk may.
     */
    public function testSingleNightPermissionComesFromTheCustomer(): void
    {
        $this->signInOperator();
        $this->customer();
        $this->legacyCustomer(maySingleNight: true);

        $this->accommodationWriter
            ->expects(self::once())
            ->method('save')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo(true),
                self::anything(),
                self::anything(),
                self::anything(),
            );

        $this->processor->process($this->input(), new Post());
    }

    /**
     * Legacy showed the "over capacity" button to the infopult chief but never checked it on
     * write, so anyone reaching the screen could overbook. The server decides now.
     */
    public function testOnlyTheInfopultChiefMayOverbook(): void
    {
        $this->signInOperator(isInfopultChief: true);
        $this->customer();
        $this->legacyCustomer();

        $this->accommodationWriter
            ->expects(self::once())
            ->method('save')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo(true),
            );

        $this->processor->process($this->input(), new Post());
    }

    /**
     * Both are on the legacy side and both looked up by id, so a mix-up would let a customer's
     * own role decide what the desk may do for them.
     */
    public function testOverbookingFollowsTheOperatorNotTheCustomer(): void
    {
        $this->signInOperator();
        $this->customer();
        $this->legacyCustomer(isInfopultChief: true);

        $this->accommodationWriter
            ->expects(self::once())
            ->method('save')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo(false),
            );

        $this->processor->process($this->input(), new Post());
    }

    public function testOrdinaryOperatorMayNotOverbook(): void
    {
        $this->signInOperator();
        $this->customer();
        $this->legacyCustomer();

        $this->accommodationWriter
            ->expects(self::once())
            ->method('save')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::identicalTo(false),
            );

        $this->processor->process($this->input(), new Post());
    }

    /**
     * save() clears the entity manager, so the customer resolved before the write is detached
     * by the time the grid is read. Reading it off that object would query a stale entity.
     */
    public function testRefusalReachesTheClientAsItIs(): void
    {
        $refusal = new InsufficientPermissionsException('Ubytování je plné; přeplnit ho smí jen šéf infopultu.');

        self::assertSame($refusal, $this->exceptionFromSaveThrowing($refusal));
    }

    /**
     * Its message was not written for the desk, and turning it into a 400 would also keep it out of the log.
     */
    public function testFaultIsNotPassedOffAsARefusal(): void
    {
        $fault = new \RuntimeException('SQLSTATE[HY000]: General error');

        self::assertSame($fault, $this->exceptionFromSaveThrowing($fault));
    }

    private function exceptionFromSaveThrowing(\Throwable $exception): \Throwable
    {
        $this->signInOperator();
        $this->customer();
        $this->legacyCustomer();
        $this->accommodationWriter->method('save')->willThrowException($exception);

        try {
            $this->processor->process($this->input(), new Post());
        } catch (\Throwable $caught) {
            return $caught;
        }

        self::fail('The processor was expected to throw.');
    }

    public function testCustomerIsResolvedAgainBeforeTheGridIsRead(): void
    {
        $customer = $this->createMock(User::class);
        $customer->method('getId')->willReturn(4242);
        $this->signInOperator();
        $this->legacyCustomer();

        // Once before the write, once after it — a single lookup means a detached entity.
        $this->entityManager
            ->expects(self::exactly(2))
            ->method('find')
            ->willReturn($customer);

        $this->accommodationGrid
            ->expects(self::once())
            ->method('forCustomer')
            ->with(self::identicalTo($customer), self::anything());

        $this->processor->process($this->input(), new Post());
    }

    public function testTheSavedGridIsReturned(): void
    {
        $this->signInOperator();
        $this->customer();
        $this->legacyCustomer();

        $grid = new AccommodationOutputDto();
        $this->accommodationGrid->method('forCustomer')->willReturn($grid);

        self::assertSame($grid, $this->processor->process($this->input(), new Post()));
    }

    /**
     * The grid redraws from this response, so built for a participant it would show the desk
     * a closed sale and locked cells after the deadline, until the page is reloaded.
     */
    public function testTheSavedGridIsTheDesksView(): void
    {
        $this->signInOperator();
        $this->customer();
        $this->legacyCustomer();

        $this->accommodationGrid
            ->expects(self::once())
            ->method('forCustomer')
            ->with(self::anything(), self::anything(), true);

        $this->processor->process($this->input(), new Post());
    }

    public function testUnknownCustomerIsRefused(): void
    {
        $this->signInOperator();
        $this->entityManager->method('find')->willReturn(null);
        $this->legacySession->expects(self::never())->method('getUserById');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('~123456789~');

        $this->processor->process($this->input(123456789), new Post());
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
        $this->signInOperator();
        $this->entityManager->method('find')->willReturn($customer);

        $this->expectException(BadRequestHttpException::class);

        $this->processor->process($this->input(), new Post());
    }

    /**
     * The customer comes from the payload, not from the security token — the operator sends the
     * request but the booking is the customer's.
     */
    public function testLegacyRightsAreReadForThePayloadCustomerNotTheOperator(): void
    {
        $customer = $this->createMock(User::class);
        $customer->method('getId')->willReturn(4242);
        $this->signInOperator();
        $this->entityManager->method('find')->willReturn($customer);

        $this->legacySession
            ->expects(self::once())
            ->method('getUserById')
            ->with(self::identicalTo(4242))
            ->willReturn(null);

        $this->expectException(BadRequestHttpException::class);

        $this->processor->process($this->input(), new Post());
    }
}
