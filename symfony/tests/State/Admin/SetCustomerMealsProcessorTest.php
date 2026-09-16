<?php

declare(strict_types=1);

namespace App\Tests\State\Admin;

use ApiPlatform\Metadata\Post;
use App\Dto\Admin\SetCustomerMealsInputDto;
use App\Entity\User;
use App\Service\CurrentYearProviderInterface;
use App\Service\CustomerDeskRights;
use App\Service\LegacySessionService;
use App\Service\MealWriter;
use App\State\Admin\SetCustomerMealsProcessor;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Pravo;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Covers who may save a participant's meals and what reaches the caller when the writer
 * refuses. What the writer then does with the selection is covered in AccommodationWriterTest.
 */
class SetCustomerMealsProcessorTest extends AbstractDatabaseKernelTestCase
{
    private MockObject $entityManager;

    private MockObject $legacySession;

    private MockObject $mealWriter;

    private SetCustomerMealsProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->legacySession = $this->createMock(LegacySessionService::class);
        $this->mealWriter = $this->createMock(MealWriter::class);

        $this->processor = new SetCustomerMealsProcessor(
            $this->mealWriter,
            // Real rights over the mocked session, so the tests exercise the actual rule.
            new CustomerDeskRights($this->legacySession),
            static::getContainer()->get(CurrentYearProviderInterface::class),
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

    private function input(int $customerId = 4242): SetCustomerMealsInputDto
    {
        $input = new SetCustomerMealsInputDto();
        $input->customerId = $customerId;

        return $input;
    }

    public function testSignedOutCallerIsRefused(): void
    {
        $this->legacySession->method('getCurrentUser')->willReturn(null);
        $this->entityManager->expects(self::never())->method('find');

        $this->expectException(AccessDeniedHttpException::class);

        $this->processor->process($this->input(), new Post());
    }

    /**
     * Checked before the customer is looked up, so a caller without the right cannot use the
     * differing error to find out which participant ids exist.
     */
    public function testOperatorWithoutTheRightIsRefusedBeforeAnyLookup(): void
    {
        $this->signInOperator(mayOrder: false);
        $this->entityManager->expects(self::never())->method('find');
        $this->mealWriter->expects(self::never())->method('save');

        $this->expectException(AccessDeniedHttpException::class);

        $this->processor->process($this->input(), new Post());
    }

    public function testUnknownCustomerIsRefused(): void
    {
        $this->signInOperator();
        $this->entityManager->method('find')->willReturn(null);
        $this->mealWriter->expects(self::never())->method('save');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageMatches('~123456789~');

        $this->processor->process($this->input(123456789), new Post());
    }

    public function testMealsAreSavedForThePayloadCustomer(): void
    {
        $customer = $this->createMock(User::class);
        $this->signInOperator();
        $this->entityManager->method('find')->willReturn($customer);

        $this->mealWriter
            ->expects(self::once())
            ->method('save')
            ->with(self::identicalTo($customer), self::identicalTo([11, 12]), self::anything());

        $input = $this->input();
        $input->variantIds = [11, 12];

        $this->processor->process($input, new Post());
    }

    /**
     * A sold-out meal has to reach the desk as its message, not as a 500 with nothing useful.
     */
    public function testRefusedMealBecomesBadRequest(): void
    {
        $customer = $this->createMock(User::class);
        $this->signInOperator();
        $this->entityManager->method('find')->willReturn($customer);
        $this->mealWriter
            ->method('save')
            ->willThrowException(new \RuntimeException('Jídlo „Snídaně" je bohužel vyprodané.'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('vyprodané');

        $this->processor->process($this->input(), new Post());
    }
}
