<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Enum\RoleMeaning;
use App\Repository\UserRepository;
use App\Service\PriceIncreaseNotifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PriceIncreaseNotifierTest extends TestCase
{
    /**
     * @var array<int, array{email: string, predmet: string, zprava: string}>
     */
    private array $odeslane = [];

    private MockObject $userRepository;

    private MockObject $logger;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function notifier(): PriceIncreaseNotifier
    {
        /** @var UserRepository $repository */
        $repository = $this->userRepository;
        /** @var LoggerInterface $logger */
        $logger = $this->logger;

        return new class($repository, $logger, $this->odeslane) extends PriceIncreaseNotifier {
            /**
             * @param array<int, array{email: string, predmet: string, zprava: string}> $odeslane
             */
            public function __construct(
                UserRepository $userRepository,
                LoggerInterface $logger,
                public array &$odeslane,
            ) {
                parent::__construct($userRepository, $logger);
            }

            public bool $selhat = false;

            public bool $drainPriOdeslani = false;

            public bool $selhatPriSestaveni = false;

            protected function dejZustatek(User $customer): string
            {
                if ($this->selhatPriSestaveni) {
                    throw new \RuntimeException('Legacy Finance selhalo');
                }

                return '0.00 Kč';
            }

            protected function odesli(string $email, string $predmet, string $zprava): void
            {
                if ($this->selhat) {
                    throw new \RuntimeException('SMTP je nedostupné');
                }
                if ($this->drainPriOdeslani) {
                    $this->drainPriOdeslani = false;
                    $this->odesliFrontu();
                }
                $this->odeslane[] = [
                    'email'   => $email,
                    'predmet' => $predmet,
                    'zprava'  => $zprava,
                ];
            }
        };
    }

    private function cfo(string $email): User
    {
        $cfo = $this->createMock(User::class);
        $cfo->method('getEmail')->willReturn($email);

        return $cfo;
    }

    private function zakaznik(): User
    {
        $zakaznik = $this->createMock(User::class);
        $zakaznik->method('getId')->willReturn(4242);
        $zakaznik->method('getJmeno')->willReturn('Testovací Zákazník');

        return $zakaznik;
    }

    public function testNothingIsSentWithoutAPriceIncrease(): void
    {
        $this->userRepository->expects(self::never())->method('findByRoleMeaning');

        $notifier = $this->notifier();
        $notifier->oznamZdrazeni($this->zakaznik(), 2026, []);
        $notifier->odesliFrontu();

        self::assertSame([], $this->odeslane);
    }

    public function testEveryCfoIsTold(): void
    {
        $this->userRepository->method('findByRoleMeaning')
            ->with(RoleMeaning::CFO)
            ->willReturn([$this->cfo('cfo1@example.invalid'), $this->cfo('cfo2@example.invalid')]);

        $notifier = $this->notifier();
        $notifier->oznamZdrazeni($this->zakaznik(), 2026, [
            7 => [
                'nazev' => 'Tričko',
                'pred'  => '0.00',
                'po'    => '250.00',
            ],
        ]);
        $notifier->odesliFrontu();

        self::assertCount(2, $this->odeslane);
        self::assertSame('cfo1@example.invalid', $this->odeslane[0]['email']);
        // The mail has to carry both prices, or the reader cannot judge the case.
        self::assertStringContainsString('0.00 → 250.00', $this->odeslane[0]['zprava']);
        self::assertStringContainsString('Testovací Zákazník', $this->odeslane[0]['predmet']);
    }

    /**
     * Doctrine fires postPersist before the commit, so a throwing mailer would roll back the
     * role change that triggered it.
     */
    public function testFailedMailDoesNotEscape(): void
    {
        $this->userRepository->method('findByRoleMeaning')
            ->willReturn([$this->cfo('cfo@example.invalid')]);
        $this->logger->expects(self::once())->method('error');

        $notifier = $this->notifier();
        $notifier->selhat = true;

        $notifier->oznamZdrazeni($this->zakaznik(), 2026, [
            7 => [
                'nazev' => 'Tričko',
                'pred'  => '0.00',
                'po'    => '250.00',
            ],
        ]);
        $notifier->odesliFrontu();

        self::assertSame([], $this->odeslane);
    }

    /**
     * The balance comes from a full legacy Finance recompute, which runs on another
     * connection inside the still-open transaction.
     */
    public function testFailedBalanceLookupDoesNotEscape(): void
    {
        $this->userRepository->method('findByRoleMeaning')
            ->willReturn([$this->cfo('cfo@example.invalid')]);
        $this->logger->expects(self::once())->method('error');

        $notifier = $this->notifier();
        $notifier->selhatPriSestaveni = true;

        $notifier->oznamZdrazeni($this->zakaznik(), 2026, [
            7 => [
                'nazev' => 'Tričko',
                'pred'  => '0.00',
                'po'    => '250.00',
            ],
        ]);
        $notifier->odesliFrontu();

        self::assertSame([], $this->odeslane);
    }

    public function testQueuingAloneSendsNothing(): void
    {
        $this->userRepository->method('findByRoleMeaning')
            ->willReturn([$this->cfo('cfo@example.invalid')]);

        $notifier = $this->notifier();
        // No drain: in production this is the still-open transaction, where a mail must not
        // go out yet — the price change may still roll back.
        $notifier->oznamZdrazeni($this->zakaznik(), 2026, [
            7 => [
                'nazev' => 'Tričko',
                'pred'  => '0.00',
                'po'    => '250.00',
            ],
        ]);

        self::assertSame([], $this->odeslane);
    }

    public function testReentrantDrainDoesNotSendTheSameBatchTwice(): void
    {
        $this->userRepository->method('findByRoleMeaning')
            ->willReturn([$this->cfo('cfo@example.invalid')]);

        $notifier = $this->notifier();
        // Sending can flush (logging, legacy reads), which re-enters odesliFrontu().
        $notifier->drainPriOdeslani = true;
        $notifier->oznamZdrazeni($this->zakaznik(), 2026, [
            7 => [
                'nazev' => 'Tričko',
                'pred'  => '0.00',
                'po'    => '250.00',
            ],
        ]);
        $notifier->odesliFrontu();

        self::assertCount(1, $this->odeslane);
    }

    public function testAFailedBatchIsNotResentOnTheNextFlush(): void
    {
        $this->userRepository->method('findByRoleMeaning')
            ->willReturn([$this->cfo('cfo@example.invalid')]);

        $notifier = $this->notifier();
        $notifier->selhat = true;
        $notifier->oznamZdrazeni($this->zakaznik(), 2026, [
            7 => [
                'nazev' => 'Tričko',
                'pred'  => '0.00',
                'po'    => '250.00',
            ],
        ]);
        $notifier->odesliFrontu();

        // An unrelated later flush must not resurrect the failed batch.
        $notifier->selhat = false;
        $notifier->odesliFrontu();

        self::assertSame([], $this->odeslane);
    }

    public function testMissingCfoIsLoggedRatherThanSwallowed(): void
    {
        $this->userRepository->method('findByRoleMeaning')->willReturn([]);
        $this->logger->expects(self::once())->method('error');

        $notifier = $this->notifier();
        $notifier->oznamZdrazeni($this->zakaznik(), 2026, [
            7 => [
                'nazev' => 'Tričko',
                'pred'  => '0.00',
                'po'    => '250.00',
            ],
        ]);
        $notifier->odesliFrontu();

        self::assertSame([], $this->odeslane);
    }
}
