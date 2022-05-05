<?php declare(strict_types=1);

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\DbTest;

class SystemoveNastaveniTest extends DbTest
{
    protected static $initData = '
    # uzivatele_hodnoty
    id_uzivatele,login_uzivatele,jmeno_uzivatele,prijmeni_uzivatele
    48,Elden,Jakub,Jandák
  ';

    public function testZmenyKurzuEura() {
        $nastaveni = new SystemoveNastaveni();

        $zaznamKurzuEuro = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])[0];
        /** viz migrace 2022-05-05_03-kurz-euro-do-systemoveho-nastaveni.php */
        self::assertSame('24', $zaznamKurzuEuro['hodnota']);
        self::assertNull($zaznamKurzuEuro['id_uzivatele']);

        $nastaveni->ulozZmeny(['KURZ_EURO' => 123], \Uzivatel::zId(48));

        $zaznamKurzuEuroPoZmene = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])[0];
        self::assertSame('123', $zaznamKurzuEuroPoZmene['hodnota']);
        self::assertSame('48', $zaznamKurzuEuroPoZmene['id_uzivatele']);
    }
}
