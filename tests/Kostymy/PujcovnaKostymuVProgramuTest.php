<?php

declare(strict_types=1);

namespace Gamecon\Tests\Kostymy;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceUcastnikHtml;
use Gamecon\Aktivita\OnlinePrezence\PotvrzeniZobrazeniKontaktu;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Kostymy\PujcovnaKostymu;
use Gamecon\Logger\LogUdalosti;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractUzivatelTestDb;

/**
 * Rezervace v programu účastníka (aktivityUzivatel, aktivitaAkce) a v online prezenci vypravěče.
 */
class PujcovnaKostymuVProgramuTest extends AbstractUzivatelTestDb
{
    use KostymyTestTrait;

    private const string TED = '2099-01-01 06:00:00';

    protected static bool $disableStrictTransTables = true;

    private function pujcovna(bool $katalogZverejnen = true): PujcovnaKostymu
    {
        return new PujcovnaKostymu(self::systemoveNastaveniKCasu(self::TED), self::nastaveniPujcovny(katalogZverejnen: $katalogZverejnen));
    }

    public function testProgramUkazeRezervovanyKostymANabidneKatalog(): void
    {
        $ucastnik = self::prihlasenyUzivatel();
        $sKostymem = self::vlozAktivitu('2099-01-01 10:00:00', '2099-01-01 14:00:00');
        $bezKostymu = self::vlozAktivitu('2099-01-02 10:00:00', '2099-01-02 14:00:00');
        $rpg = self::vlozAktivitu('2099-01-03 10:00:00', '2099-01-03 14:00:00', TypAktivity::RPG);
        foreach ([$sKostymem, $bezKostymu, $rpg] as $idAktivity) {
            self::vlozPrihlasku($idAktivity, $ucastnik->id());
        }
        self::vlozRezervaci(self::vlozKostym('Elfí plášť'), $sKostymem, $ucastnik->id());

        $kostymy = $this->pujcovna()->kostymyVProgramu($ucastnik, ROCNIK);

        self::assertSame([
            'nazev' => 'Elfí plášť',
            'url'   => URL_WEBU . '/kostymy/moje',
        ], $kostymy[$sKostymem]);
        self::assertSame([
            'nazev' => null,
            'url'   => URL_WEBU . '/kostymy?aktivita=' . $bezKostymu,
        ], $kostymy[$bezKostymu]);
        self::assertArrayNotHasKey($rpg, $kostymy);

        $nezverejneny = $this->pujcovna(katalogZverejnen: false)->kostymyVProgramu($ucastnik, ROCNIK);
        self::assertSame([$sKostymem], array_keys($nezverejneny), 'Do nezveřejněného katalogu program neodkazuje');
        self::assertSame([], $this->pujcovna()->kostymyVProgramu($ucastnik, ROCNIK - 1), 'Minulý ročník bez rezervací a bez nabídky');
    }

    public function testOnlinePrezenceUkazeRezervovanyKostym(): void
    {
        $vypravec = self::prihlasenyUzivatel();
        $sKostymem = self::prihlasenyUzivatel();
        $bezKostymu = self::prihlasenyUzivatel();
        $idAktivity = self::vlozAktivitu();
        self::vlozPrihlasku($idAktivity, $sKostymem->id());
        self::vlozPrihlasku($idAktivity, $bezKostymu->id());
        self::vlozRezervaci(self::vlozKostym('Elfí <plášť>'), $idAktivity, $sKostymem->id());
        $systemoveNastaveni = self::nastaveniProPrezenci();
        $aktivita = Aktivita::zId($idAktivity, false, $systemoveNastaveni);
        $radky = new OnlinePrezenceUcastnikHtml($systemoveNastaveni, new PotvrzeniZobrazeniKontaktu(new LogUdalosti()));

        $sKostymemHtml = $radky->sestavHmlUcastnikaAktivity($sKostymem, $aktivita, $vypravec, StavPrihlaseni::PRIHLASEN, true, true);
        $bezKostymuHtml = $radky->sestavHmlUcastnikaAktivity($bezKostymu, $aktivita, $vypravec, StavPrihlaseni::PRIHLASEN, true, true);

        self::assertStringContainsString('fa-shirt', $sKostymemHtml);
        self::assertStringContainsString('Elfí &lt;plášť&gt;', $sKostymemHtml);
        self::assertStringNotContainsString('fa-shirt', $bezKostymuHtml);
    }

    /**
     * Testovací databáze nemá nastavení „na poslední chvíli“, které řádek prezence čte.
     */
    private static function nastaveniProPrezenci(): SystemoveNastaveni
    {
        $puvodni = self::systemoveNastaveniKCasu(self::TED);

        return new class($puvodni) extends SystemoveNastaveni {
            public function __construct(SystemoveNastaveni $puvodni)
            {
                parent::__construct(
                    rocnik: $puvodni->rocnik(),
                    ted: $puvodni->ted(),
                    prostredi: $puvodni->prostredi(),
                    databazoveNastaveni: $puvodni->databazoveNastaveni(),
                    rootAdresarProjektu: $puvodni->rootAdresarProjektu(),
                    privateCacheDir: $puvodni->privateCacheDir(),
                    kernel: $puvodni->kernel(),
                    publicCacheDir: $puvodni->publicCacheDir(),
                );
            }

            public function prihlaseniNaPosledniChviliXMinutPredZacatkemAktivity(): int
            {
                return 10;
            }
        };
    }
}
