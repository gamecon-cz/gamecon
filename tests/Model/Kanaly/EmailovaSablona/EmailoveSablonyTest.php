<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaRepository;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablony;
use Gamecon\Kanaly\EmailovaSablona\Exceptions\ChybiPromennaSablony;
use Gamecon\Kanaly\EmailovaSablona\Exceptions\EmailovaSablonaNeniVyplnena;
use Gamecon\Kanaly\EmailovaSablona\Exceptions\NeznamaEmailovaSablona;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class EmailoveSablonyTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function vytvorMailMaPredmetATextZDatabaze(): void
    {
        (new EmailovaSablonaRepository())->uloz(KodEmailoveSablonyEnum::UVOLNENE_MISTO, 'Volno na %aktivita%', 'Přijď na %aktivita% v %cas%.', \Uzivatel::SYSTEM);

        $mail = $this->sablony()->vytvorMail(KodEmailoveSablonyEnum::UVOLNENE_MISTO, [
            'aktivita' => 'Hrob',
            'cas'      => 'pátek 10–14',
        ]);

        self::assertSame('Volno na Hrob', $mail->dejPredmet());
        self::assertSame('<html><body>Přijď na Hrob v pátek 10–14.</body></html>', $mail->dejText());
    }

    /**
     * @test
     */
    public function textovyMailSeNeobalujeDoHtml(): void
    {
        $mail = $this->sablony()->vytvorMail(KodEmailoveSablonyEnum::ODHLASENI_Z_GAMECONU, [
            'a'          => '',
            'dalsi_vlna' => 'x',
        ]);

        self::assertStringStartsNotWith('<html>', $mail->dejText());
    }

    /**
     * @test
     */
    public function zmenaVDatabaziSeProjeviPriDalsimVytvoreniMailu(): void
    {
        $repository = new EmailovaSablonaRepository();
        $sablony = $this->sablony($repository);

        $repository->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Jedna', 'Text %odkaz%', \Uzivatel::SYSTEM);
        self::assertSame('Jedna', $sablony->vykresli(KodEmailoveSablonyEnum::OBNOVA_HESLA, [
            'odkaz' => 'x',
        ])->predmet);

        $repository->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Dvě', 'Text %odkaz%', \Uzivatel::SYSTEM);
        self::assertSame('Dvě', $sablony->vykresli(KodEmailoveSablonyEnum::OBNOVA_HESLA, [
            'odkaz' => 'x',
        ])->predmet);
    }

    /**
     * @test
     */
    public function predmetSeDaVykreslitSamBezHodnotProText(): void
    {
        self::assertSame(
            'GameCon ' . ROCNIK . ' - nedoplatky',
            $this->sablony()->vykresliPredmet(KodEmailoveSablonyEnum::UPOMINKA_TYDEN_PRITOMEN),
        );
    }

    /**
     * @test
     */
    public function predmetKterySiVyzadujePromenneJeBezNichChybouVolajiciho(): void
    {
        $this->expectException(ChybiPromennaSablony::class);

        $this->sablony()->vykresliPredmet(KodEmailoveSablonyEnum::UVOLNENE_MISTO);
    }

    /**
     * @test
     */
    public function predmetNesmiDostatPromennouKterouPredmetNepovoluje(): void
    {
        $this->expectException(ChybiPromennaSablony::class);

        $this->sablony()->vykresliPredmet(KodEmailoveSablonyEnum::UVOLNENE_MISTO, [
            'aktivita' => 'Hrob',
            'cas'      => 'pátek',
        ]);
    }

    /**
     * @test
     */
    public function chybejiciRadekVDatabaziMailNeodesleAniNenahradiTextemZKodu(): void
    {
        dbQuery("DELETE FROM emailova_sablona WHERE kod = 'obnova_hesla'");

        $this->expectException(EmailovaSablonaNeniVyplnena::class);

        $this->sablony()->vykresli(KodEmailoveSablonyEnum::OBNOVA_HESLA, [
            'odkaz' => 'x',
        ]);
    }

    /**
     * @test
     */
    public function sablonaMimoKatalogJeChybaVolajicihoKodu(): void
    {
        $this->expectException(NeznamaEmailovaSablona::class);

        $this->sablony()->vykresli(KodEmailoveSablonyEnum::UPOMINKA_VLASTNI, []);
    }

    /**
     * @test
     */
    public function mailZeSablonyPouzijeFormatSablony(): void
    {
        $mail = $this->sablony()->vytvorMail(KodEmailoveSablonyEnum::UVOLNENE_MISTO, [
            'aktivita' => 'x',
            'cas'      => 'y',
        ]);

        $format = new \ReflectionProperty($mail, 'format');

        self::assertSame(GcMail::FORMAT_HTML, $format->getValue($mail));
    }

    private function sablony(?EmailovaSablonaRepository $repository = null): EmailoveSablony
    {
        return new EmailoveSablony(SystemoveNastaveni::zGlobals(), $repository ?? new EmailovaSablonaRepository());
    }
}
