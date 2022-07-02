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
        $nastaveni = SystemoveNastaveni::vytvorZGlobalnich();

        $zaznamKurzuEuro = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])[0];
        /** viz migrace 2022-05-05_03-kurz-euro-do-systemoveho-nastaveni.php */
        self::assertSame('24', $zaznamKurzuEuro['hodnota']);
        self::assertNull($zaznamKurzuEuro['id_uzivatele']);

        $nastaveni->ulozZmenuHodnoty(123, 'KURZ_EURO', \Uzivatel::zId(48));

        $zaznamKurzuEuroPoZmene = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])[0];
        self::assertSame('123', $zaznamKurzuEuroPoZmene['hodnota']);
        self::assertSame('48', $zaznamKurzuEuroPoZmene['id_uzivatele']);
    }

    /**
     * @dataProvider provideVychoziHodnota
     */
    public function testVychoziHodnoty(int $rok, string $klic, string $ocekavanaHodnota) {
        $nastaveni = new SystemoveNastaveni($rok, new \DateTimeImmutable($rok . '-12-31 23:59:59'), false);

        self::assertSame($ocekavanaHodnota, $nastaveni->dejVychoziHodnotu($klic));
    }

    public function provideVychoziHodnota(): array {
        return [
            'GC_BEZI_OD' => [2022, 'GC_BEZI_OD', '2022-07-21 07:00:00'],
            'GC_BEZI_DO' => [2022, 'GC_BEZI_DO', '2022-07-24 21:00:00'],
            'REG_GC_OD' => [2022, 'REG_GC_OD', '2022-05-12 20:22:00'],
            'REG_AKTIVIT_OD' => [2022, 'REG_AKTIVIT_OD', '2022-05-19 20:22:00'],
            'HROMADNE_ODHLASOVANI' => [2022, 'HROMADNE_ODHLASOVANI', '2022-06-30 23:59:00'],
            'HROMADNE_ODHLASOVANI_2' => [2022, 'HROMADNE_ODHLASOVANI_2', '2022-07-17 23:59:00'],
        ];
    }
}
