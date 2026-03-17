<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use App\Structure\Entity\ActivityEntityStructure;
use App\Structure\Entity\ActivityInstanceEntityStructure;
use App\Structure\Entity\CategoryTagEntityStructure;
use App\Structure\Entity\TagEntityStructure;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\Tag;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\ActivityFactory;
use Gamecon\Tests\Factory\ActivityInstanceFactory;
use Gamecon\Tests\Factory\CategoryTagFactory;
use Gamecon\Tests\Factory\TagFactory;

class AktivitaTagyTest extends AbstractTestDb
{
    // Disable legacy mysqli transaction wrapping because this test uses Doctrine factories.
    // Doctrine uses a separate PDO connection, so legacy mysqli transactions would cause deadlocks.
    // Foundry (via the Factories trait) handles transaction management for Doctrine.
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            function () {
                $mainActivity = ActivityFactory::createOne();
                $activityInstances = ActivityInstanceFactory::createMany(2, [
                    ActivityInstanceEntityStructure::mainActivity => $mainActivity,
                ]);
                $firstInstance = $activityInstances[0];
                ActivityFactory::createMany(2, [
                    ActivityEntityStructure::activityInstance => $firstInstance,
                ]);
                $secondInstance = $activityInstances[1];
                ActivityFactory::createMany(2, [
                    ActivityEntityStructure::activityInstance => $secondInstance,
                ]);
                $categoryTag = CategoryTagFactory::createOne([
                    CategoryTagEntityStructure::nazev => 'Za co?',
                ]);
                TagFactory::createSequence([
                    [
                        TagEntityStructure::nazev       => 'První',
                        TagEntityStructure::categoryTag => $categoryTag,
                    ],
                    [
                        TagEntityStructure::nazev       => 'druhý',
                        TagEntityStructure::categoryTag => $categoryTag,
                    ],
                ]);
            },
        ];
    }

    /**
     * @dataProvider provideAktivity
     */
    public function testNastaveni(
        int $idNastavovaneAktivity,
        int $idCteneAktivity,
        array $nastaveneTagy,
    ) {
        $a = Aktivita::zId($idNastavovaneAktivity);
        $a->nastavTagy($nastaveneTagy);
        $b = Aktivita::zId($idCteneAktivity);
        self::assertEquals(self::getSortedCopy($nastaveneTagy), self::getSortedCopy($b->tagy()),
            "Tagy nastavené aktivitě {$idNastavovaneAktivity} musí odpovídat tagům přečteným z aktivity {$idCteneAktivity}.",
        );
    }

    public static function provideAktivity(): array
    {
        return [
            'obyčejná aktivita, nastavení více štítků'    => [1, 1, ['První', 'druhý']],
            'obyčejná aktivita, nastavení žádných štítků' => [1, 1, []],
            'skupina, nastavení více štítků'              => [2, 3, ['První', 'druhý']],
            'skupina, nastavení žádných štítků'           => [4, 5, []],
            'skupina, druhá aktivita'                     => [3, 2, ['První', 'druhý']],
        ];
    }

    /**
     * @dataProvider provideAktivity
     */
    public function testKopiePriInstanciaci(
        int $idAktivity,
        $_,
        array $tagy,
    ) {
        $a = Aktivita::zId($idAktivity);
        self::assertNotNull($a, "Aktivita pro ID {$idAktivity} musí existovat.");
        $a->nastavTagy($tagy);
        $b = $a->instancuj();
        self::assertEquals(
            self::getSortedCopy($tagy),
            self::getSortedCopy($b->tagy()),
            'Tagy se musí propsat i do nově vytvořené instance',
        );
    }

    /**
     * Tags must be ordered by category poradi, then alphabetically by tag name.
     */
    public function testRazeniTagu()
    {
        $kategorieC = CategoryTagFactory::createOne([
            CategoryTagEntityStructure::nazev  => 'Kategorie C',
            CategoryTagEntityStructure::poradi => 30,
        ]);
        $kategorieA = CategoryTagFactory::createOne([
            CategoryTagEntityStructure::nazev  => 'Kategorie A',
            CategoryTagEntityStructure::poradi => 10,
        ]);
        $kategorieB = CategoryTagFactory::createOne([
            CategoryTagEntityStructure::nazev  => 'Kategorie B',
            CategoryTagEntityStructure::poradi => 20,
        ]);

        $tagC2 = TagFactory::createOne([
            TagEntityStructure::nazev       => 'Zebra',
            TagEntityStructure::categoryTag => $kategorieC,
        ]);
        $tagA2 = TagFactory::createOne([
            TagEntityStructure::nazev       => 'Mango',
            TagEntityStructure::categoryTag => $kategorieA,
        ]);
        $tagB1 = TagFactory::createOne([
            TagEntityStructure::nazev       => 'Alfa',
            TagEntityStructure::categoryTag => $kategorieB,
        ]);
        $tagA1 = TagFactory::createOne([
            TagEntityStructure::nazev       => 'Banán',
            TagEntityStructure::categoryTag => $kategorieA,
        ]);
        $tagC1 = TagFactory::createOne([
            TagEntityStructure::nazev       => 'Citron',
            TagEntityStructure::categoryTag => $kategorieC,
        ]);

        $aktivita = Aktivita::zId(1);
        self::assertNotNull($aktivita);

        $aktivita->nastavTagy(['Zebra', 'Mango', 'Alfa', 'Banán', 'Citron']);

        $ocekavaneRazeni = ['Banán', 'Mango', 'Alfa', 'Citron', 'Zebra'];

        $tagy = $aktivita->tagy();
        self::assertSame($ocekavaneRazeni, $tagy, 'Tagy z tagy() musí být seřazené podle pořadí kategorie a pak podle názvu');

        $tagyIds = $aktivita->tagyId();
        $ocekavaneIds = [
            $tagA1->getId(),
            $tagA2->getId(),
            $tagB1->getId(),
            $tagC1->getId(),
            $tagC2->getId(),
        ];
        self::assertSame($ocekavaneIds, $tagyIds, 'ID tagů z tagyId() musí být seřazené podle pořadí kategorie a pak podle názvu');

        $tagyZeZIds = Tag::zIds($tagyIds);
        $nazvyZeZIds = array_map(static fn (
            Tag $tag) => $tag->nazev(), $tagyZeZIds);
        self::assertSame($ocekavaneRazeni, $nazvyZeZIds, 'Tag::zIds() musí vracet tagy seřazené podle pořadí kategorie a pak podle názvu');
    }

    /**
     * Vrátí seřazenou kopii pole bez modifikace původního pole.
     */
    private static function getSortedCopy(array $pole): array
    {
        $serazene = $pole;
        sort($serazene);

        return $serazene;
    }
}
