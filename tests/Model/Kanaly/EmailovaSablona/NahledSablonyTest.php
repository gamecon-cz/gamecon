<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaVykreslovac;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablonyKatalog;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kanaly\EmailovaSablona\NahledSablony;
use Gamecon\Kanaly\EmailovaSablona\SystemoveHodnotyProSablony;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

class NahledSablonyTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function prazdnyPredmetNebraniVykreslenemuTextu(): void
    {
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO);
        $aktivita = $definice->promenna('aktivita')->ukazka;

        $nahled = $this->nahled()->vykresli($definice, '', 'Přijď na %aktivita%.', $definice->ukazky());

        self::assertSame(NahledSablony::NEVYPLNENO, $nahled->predmet);
        self::assertSame("Přijď na {$aktivita}.", $nahled->text);
    }

    /**
     * @test
     */
    public function prazdnyTextNebraniVykreslenemuPredmetu(): void
    {
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO);
        $aktivita = $definice->promenna('aktivita')->ukazka;

        $nahled = $this->nahled()->vykresli($definice, 'Volno na %aktivita%', '', $definice->ukazky());

        self::assertSame("Volno na {$aktivita}", $nahled->predmet);
        self::assertSame(NahledSablony::NEVYPLNENO, $nahled->text);
    }

    /**
     * @test
     */
    public function kdyzNicNeniVyplnenoUkazeObaZastupneZnaky(): void
    {
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO);

        $nahled = $this->nahled()->vykresli($definice, '', '', $definice->ukazky());

        self::assertSame(NahledSablony::NEVYPLNENO, $nahled->predmet);
        self::assertSame(NahledSablony::NEVYPLNENO, $nahled->text);
    }

    /**
     * @test
     */
    public function samaMezeraNebonovyRadekSeBereJakoNevyplneno(): void
    {
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO);

        $nahled = $this->nahled()->vykresli($definice, " \t ", "\n\n", $definice->ukazky());

        self::assertSame(NahledSablony::NEVYPLNENO, $nahled->predmet);
        self::assertSame(NahledSablony::NEVYPLNENO, $nahled->text);
    }

    /**
     * @test
     */
    public function vyplneneObeCastiSeVykresliBezZastupnychZnaku(): void
    {
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO);
        $systemoveHodnoty = new SystemoveHodnotyProSablony(SystemoveNastaveni::zGlobals());
        $ocekavane = (new EmailovaSablonaVykreslovac($systemoveHodnoty))
            ->vykresli($definice, 'Volno na %aktivita%', 'Přijď na %aktivita% v %cas%.', $definice->ukazky());

        $nahled = $this->nahled()->vykresli($definice, 'Volno na %aktivita%', 'Přijď na %aktivita% v %cas%.', $definice->ukazky());

        self::assertSame($ocekavane->predmet, $nahled->predmet);
        self::assertSame($ocekavane->text, $nahled->text);
        self::assertSame($ocekavane->format, $nahled->format);
    }

    /**
     * @test
     */
    public function symbolSChybouSeVNahleduNechaJakoZapsanyAOstatniSeVykresli(): void
    {
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::UVOLNENE_MISTO);
        $aktivita = $definice->promenna('aktivita')->ukazka;

        $nahled = $this->nahled()->vykresli($definice, '', '%aktivita% a %neexistuje%', $definice->ukazky());

        self::assertSame("{$aktivita} a %neexistuje%", $nahled->text, 'Chyba v symbolu nemá bránit náhledu zbytku textu');
    }

    /**
     * @test
     */
    public function zastupnyZnakVHtmlEmailuJdeDoTelaDokumentu(): void
    {
        $definice = EmailoveSablonyKatalog::dej(KodEmailoveSablonyEnum::OBNOVA_HESLA);
        self::assertTrue($definice->jeHtml(), 'Test potřebuje šablonu s HTML textem');

        $nahled = $this->nahled()->vykresli($definice, 'Obnova hesla', '', $definice->ukazky());

        self::assertSame('Obnova hesla', $nahled->predmet);
        self::assertSame(GcMail::FORMAT_HTML, $nahled->format);
        self::assertSame('<html><body>' . NahledSablony::NEVYPLNENO . '</body></html>', $nahled->textProEmail());
    }

    private function nahled(): NahledSablony
    {
        return new NahledSablony(new EmailovaSablonaVykreslovac(new SystemoveHodnotyProSablony(SystemoveNastaveni::zGlobals())));
    }
}
