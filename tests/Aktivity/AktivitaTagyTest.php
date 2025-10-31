<?php

namespace Gamecon\Tests\Aktivity;

use App\Structure\Entity\ActivityInstanceEntityStructure;
use App\Structure\Entity\CategoryTagEntityStructure;
use App\Structure\Entity\ActivityEntityStructure;
use App\Structure\Entity\TagEntityStructure;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Tests\Db\AbstractDoctrineTestDb;
use Gamecon\Tests\Factory\ActivityFactory;
use Gamecon\Tests\Factory\ActivityInstanceFactory;
use Gamecon\Tests\Factory\CategoryTagFactory;
use Gamecon\Tests\Factory\TagFactory;

class AktivitaTagyTest extends AbstractDoctrineTestDb
{
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
                    [TagEntityStructure::nazev => 'První', TagEntityStructure::categoryTag => $categoryTag],
                    [TagEntityStructure::nazev => 'druhý', TagEntityStructure::categoryTag => $categoryTag],
                ]);
            },
        ];
    }

    /**
     * @dataProvider provideAktivity
     */
    public function testNastaveni(
        int   $idNastavovaneAktivity,
        int   $idCteneAktivity,
        array $nastaveneTagy,
    ) {
        $a = Aktivita::zId($idNastavovaneAktivity);
        $a->nastavTagy($nastaveneTagy);
        $b = Aktivita::zId($idCteneAktivity);
        self::assertEquals(self::getSortedCopy($nastaveneTagy), self::getSortedCopy($b->tagy()),
            "Tagy nastavené aktivitě $idNastavovaneAktivity musí odpovídat tagům přečteným z aktivity $idCteneAktivity.",
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
        int   $idAktivity,
              $_,
        array $tagy,
    ) {
        $a = Aktivita::zId($idAktivity);
        self::assertNotNull($a, "Aktivita pro ID $idAktivity musí existovat.");
        $a->nastavTagy($tagy);
        $b = $a->instancuj();
        self::assertEquals(
            self::getSortedCopy($tagy),
            self::getSortedCopy($b->tagy()),
            "Tagy se musí propsat i do nově vytvořené instance",
        );
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
