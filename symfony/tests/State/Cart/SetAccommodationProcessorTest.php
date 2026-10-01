<?php

declare(strict_types=1);

namespace App\Tests\State\Cart;

use ApiPlatform\Metadata\Post;
use App\Dto\Cart\SetAccommodationInputDto;
use App\Entity\User;
use App\Exception\NoLongerAvailableException;
use App\Service\AccommodationRules;
use App\Service\AccommodationWriter;
use App\Service\BreakfastCanceller;
use App\Service\CurrentYearProviderInterface;
use App\Service\LegacySessionService;
use App\State\Cart\AccommodationProvider;
use App\State\Cart\SetAccommodationProcessor;
use App\Tests\AbstractDatabaseKernelTestCase;
use App\Tests\Support\ChybovePreklady;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Bundle\SecurityBundle\Security;

class SetAccommodationProcessorTest extends AbstractDatabaseKernelTestCase
{
    private ?SystemoveNastaveni $puvodniNastaveni = null;

    protected function tearDown(): void
    {
        if ($this->puvodniNastaveni !== null) {
            $GLOBALS['systemoveNastaveni'] = $this->puvodniNastaveni;
        }

        parent::tearDown();
    }

    /**
     * Same answer as a meal or merch past its deadline: the request was fine, it just came too late.
     */
    public function testPoTerminuOdmitneJakoUzNedostupne(): void
    {
        $this->puvodniNastaveni = $GLOBALS['systemoveNastaveni'] ?? null;
        $GLOBALS['systemoveNastaveni'] = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict((ROCNIK + 1) . '-01-01 00:00:00'),
        );

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->createMock(User::class));
        $legacySession = $this->createMock(LegacySessionService::class);
        $legacySession->method('getCurrentUser')->willReturn($this->createMock(\Uzivatel::class));
        $writer = $this->createMock(AccommodationWriter::class);
        $writer->expects(self::never())->method('save');

        $processor = new SetAccommodationProcessor(
            $writer,
            $this->createMock(BreakfastCanceller::class),
            static::getContainer()->get(AccommodationRules::class),
            static::getContainer()->get(AccommodationProvider::class),
            static::getContainer()->get(CurrentYearProviderInterface::class),
            $legacySession,
            $security,
            ChybovePreklady::translator(),
        );

        $this->expectException(NoLongerAvailableException::class);
        $this->expectExceptionMessage('Prodej ubytování už skončil.');

        $processor->process(new SetAccommodationInputDto(), new Post());
    }
}
