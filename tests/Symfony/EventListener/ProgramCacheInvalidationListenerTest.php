<?php

declare(strict_types=1);

namespace Gamecon\Tests\Symfony\EventListener;

use App\Entity\ActivityOrganizer;
use App\Entity\ActivityRegistration;
use App\Entity\ActivityRegistrationState;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\ActivityFactory;
use Gamecon\Tests\Factory\CategoryTagFactory;
use Gamecon\Tests\Factory\TagFactory;
use Gamecon\Tests\Factory\UserFactory;
use Symfony\Component\Filesystem\Filesystem;
use Zenstruck\Foundry\Test\Factories;

/**
 * Integrační testy ověřující, že Doctrine listener invalidates program cache
 * při relevantních změnách entit.
 *
 * Listener {@see \App\EventListener\ProgramCacheInvalidationListener} je v
 * této vrstvě "defense in depth" — zachytí cokoli, co projde Symfony/Doctrine
 * vrstvou, i kdyby legacy invalidace někde chyběla.
 */
class ProgramCacheInvalidationListenerTest extends AbstractTestDb
{
    use Factories;

    protected static bool $disableStrictTransTables = true;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return true;
    }

    private const ROK = ROCNIK;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->smazVsechnyDirtyFlagy();
    }

    protected function tearDown(): void
    {
        $this->smazVsechnyDirtyFlagy();
        parent::tearDown();
    }

    private function cestaKDirtyFlagu(ProgramStaticFileType $typ): string
    {
        return SPEC . '/program/dirty-' . $typ->value . '-' . self::ROK;
    }

    private function smazVsechnyDirtyFlagy(): void
    {
        foreach (ProgramStaticFileType::cases() as $typ) {
            $soubor = $this->cestaKDirtyFlagu($typ);
            if (file_exists($soubor)) {
                unlink($soubor);
            }
        }
    }

    private function assertDirtyFlagNastaven(ProgramStaticFileType $typ, string $kontext): void
    {
        self::assertFileExists(
            $this->cestaKDirtyFlagu($typ),
            "Po akci \"{$kontext}\" musí Doctrine listener nastavit dirty flag pro {$typ->value}",
        );
    }

    private function assertDirtyFlagNenastaven(ProgramStaticFileType $typ, string $kontext): void
    {
        self::assertFileDoesNotExist(
            $this->cestaKDirtyFlagu($typ),
            "Po akci \"{$kontext}\" NESMÍ být nastaven dirty flag pro {$typ->value}",
        );
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @test
     */
    public function persistTaguNastaviTagyFlag(): void
    {
        TagFactory::createOne();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::TAGY,
            'persist nového tagu přes Doctrine',
        );
    }

    /**
     * @test
     */
    public function persistCategoryTaguNastaviTagyFlag(): void
    {
        CategoryTagFactory::createOne();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::TAGY,
            'persist nové kategorie tagu přes Doctrine',
        );
    }

    /**
     * @test
     */
    public function persistAktivityNastaviVsechnyFlagyAktivit(): void
    {
        ActivityFactory::createOne([
            'urlAkce' => 'lst-' . uniqid('', true),
        ]);

        $this->assertDirtyFlagNastaven(ProgramStaticFileType::AKTIVITY, 'persist aktivity');
        $this->assertDirtyFlagNastaven(ProgramStaticFileType::POPISY, 'persist aktivity');
        $this->assertDirtyFlagNastaven(ProgramStaticFileType::OBSAZENOSTI, 'persist aktivity');
    }

    /**
     * @test
     */
    public function persistOrganizatoraNastaviAktivityFlag(): void
    {
        $activity = ActivityFactory::createOne([
            'urlAkce' => 'lst-' . uniqid('', true),
        ])->_real();
        $user = UserFactory::createOne([
            'login' => 'lst-' . uniqid('', true),
            'email' => 'lst-' . uniqid('', true) . '@example.test',
        ])->_real();
        $this->smazVsechnyDirtyFlagy();

        $em = $this->getEntityManager();
        $organizer = (new ActivityOrganizer())
            ->setActivity($activity)
            ->setUser($user);
        $em->persist($organizer);
        $em->flush();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'persist organizátora aktivity přes Doctrine',
        );
    }

    /**
     * @test
     */
    public function persistRegistraceNastaviObsazenostiFlag(): void
    {
        $activity = ActivityFactory::createOne([
            'urlAkce' => 'lst-' . uniqid('', true),
        ])->_real();
        $user = UserFactory::createOne([
            'login' => 'lst-' . uniqid('', true),
            'email' => 'lst-' . uniqid('', true) . '@example.test',
        ])->_real();
        $em = $this->getEntityManager();
        $stav = $em->getReference(ActivityRegistrationState::class, 0)
            ?? $em->find(ActivityRegistrationState::class, 0);
        if (! $stav) {
            self::markTestSkipped('Stav přihlášení 0 nenalezen v testovací DB.');
        }
        $this->smazVsechnyDirtyFlagy();

        $registrace = (new ActivityRegistration())
            ->setActivity($activity)
            ->setRegisteredUser($user)
            ->setActivityRegistrationState($stav);
        $em->persist($registrace);
        $em->flush();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::OBSAZENOSTI,
            'persist registrace na aktivitu',
        );
    }

    /**
     * @test
     */
    public function zmenaNickuVypraveceNastaviAktivityFlag(): void
    {
        // Aktivita musí být v aktuálním ročníku, jinak EXISTS dotaz vrátí false a
        // listener neoznačí cache jako špinavou — to je očekávané chování (vypravěč
        // staré aktivity nemění zobrazení v aktuálním programu).
        $activity = ActivityFactory::createOne([
            'rok'     => self::ROK,
            'urlAkce' => 'lst-' . uniqid('', true),
        ])->_real();
        $user = UserFactory::createOne([
            'login' => 'lst-' . uniqid('', true),
            'email' => 'lst-' . uniqid('', true) . '@example.test',
        ])->_real();
        $em = $this->getEntityManager();
        $em->persist((new ActivityOrganizer())->setActivity($activity)->setUser($user));
        $em->flush();
        $this->smazVsechnyDirtyFlagy();

        $user->setLogin('novyNick' . uniqid());
        $em->flush();

        $this->assertDirtyFlagNastaven(
            ProgramStaticFileType::AKTIVITY,
            'změna nicku vypravěče přes Doctrine',
        );
    }

    /**
     * @test
     */
    public function zmenaNickuNevypraveceNenastaviFlag(): void
    {
        $user = UserFactory::createOne([
            'login' => 'lst-' . uniqid('', true),
            'email' => 'lst-' . uniqid('', true) . '@example.test',
        ])->_real();
        // user is NOT an organizer
        $this->smazVsechnyDirtyFlagy();

        $user->setLogin('novyNick' . uniqid());
        $this->getEntityManager()->flush();

        $this->assertDirtyFlagNenastaven(
            ProgramStaticFileType::AKTIVITY,
            'změna nicku obyčejného uživatele',
        );
    }

    /**
     * @test
     */
    public function zmenaTelefonuVypraveceNenastaviFlag(): void
    {
        $activity = ActivityFactory::createOne([
            'urlAkce' => 'lst-' . uniqid('', true),
        ])->_real();
        $user = UserFactory::createOne([
            'login' => 'lst-' . uniqid('', true),
            'email' => 'lst-' . uniqid('', true) . '@example.test',
        ])->_real();
        $em = $this->getEntityManager();
        $em->persist((new ActivityOrganizer())->setActivity($activity)->setUser($user));
        $em->flush();
        $this->smazVsechnyDirtyFlagy();

        // Telefon se nezobrazuje v programu — listener musí přeskočit check vypravěče.
        $user->setTelefon('+420999999999');
        $em->flush();

        $this->assertDirtyFlagNenastaven(
            ProgramStaticFileType::AKTIVITY,
            'změna telefonu vypravěče (žádné z polí jméno/příjmení/login)',
        );
    }
}
