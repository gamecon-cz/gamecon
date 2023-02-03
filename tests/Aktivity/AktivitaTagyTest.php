<?php

namespace Gamecon\Tests\Aktivity;

use Gamecon\Tests\Db\DbTest;
use Gamecon\Aktivita\Aktivita;

class AktivitaTagyTest extends DbTest
{

    protected static string $initData = '
    # akce_seznam
    id_akce, patri_pod
    1,       null

    # akce_instance
    id_instance, id_hlavni_akce
    10,          1
    20,          1

    # akce_seznam
    id_akce, patri_pod
    2,       10
    3,       10
    4,       20
    5,       20

    # kategorie_sjednocenych_tagu
    id,   nazev
    2001, Za co?

    # sjednocene_tagy
    id,   nazev, id_kategorie_tagu
    1001, První, 2001
    1002, druhý, 2001
  ';

    public static function setUpBeforeClass(): void {
        static::$disableStrictTransTables = true;
        parent::setUpBeforeClass();
    }

    /**
     * @dataProvider aktivity
     */
    public function testNastaveni(int $idNastavovaneAktivity, int $idCteneAktivity, array $nastaveneTagy) {
        $a = Aktivita::zId($idNastavovaneAktivity);
        $a->nastavTagy($nastaveneTagy);
        $b = Aktivita::zId($idCteneAktivity);
        self::assertEquals(self::getSortedCopy($nastaveneTagy), self::getSortedCopy($b->tagy()),
            "Tagy nastavené aktivitě $idNastavovaneAktivity musí odpovídat tagům přečteným z aktivity $idCteneAktivity."
        );
    }

    public function aktivity(): array {
        return [
            'obyčejná aktivita, nastavení více štítků'    => [1, 1, ['První', 'druhý']],
            'obyčejná aktivita, nastavení žádných štítků' => [1, 1, []],
            'skupina, nastavení více štítků'              => [2, 3, ['První', 'druhý']],
            'skupina, nastavení žádných štítků'           => [4, 5, []],
            'skupina, druhá aktivita'                     => [3, 2, ['První', 'druhý']],
        ];
    }

    /**
     * @dataProvider aktivity
     */
    public function testKopiePriInstanciaci(int $idAktivity, $_, array $tagy) {
        $a = Aktivita::zId($idAktivity);
        $a->nastavTagy($tagy);
        $b = $a->instancuj();
        self::assertEquals(
            self::getSortedCopy($tagy),
            self::getSortedCopy($b->tagy()),
            "Tagy se musí propsat i do nově vytvořené instance"
        );
    }

    /**
     * Vrátí seřazenou kopii pole bez modifikace původního pole.
     */
    private static function getSortedCopy(array $pole): array {
        $serazene = $pole;
        sort($serazene);
        return $serazene;
    }
}
