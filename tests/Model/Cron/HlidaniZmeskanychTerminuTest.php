<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Cron;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Cron\HlidaniZmeskanychTerminu;
use Gamecon\Cron\HlidanyTermin;
use Gamecon\Kanaly\MailLogger;
use Gamecon\Logger\JobResultLoggerInterface;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\Enum\TypVarovaniPromlceni;
use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Uzivatel\UpominaniDluzniku;

class HlidaniZmeskanychTerminuTest extends AbstractTestDb
{
    private const string PREDMET_UPOZORNENI = 'cron zmeškal termín';

    protected static bool $disableStrictTransTables = true;

    /**
     * @test
     */
    public function predKoncemPrvnihoOknaNeupozorniNaNic(): void
    {
        $mailuPred = $this->pocetUpozorneni();

        $zmeskane = $this->dejHlidani($this->dejRegistraceOd()->modifyStrict('-2 months'))->zkontroluj();

        self::assertSame([], $zmeskane);
        self::assertSame($mailuPred, $this->pocetUpozorneni());
    }

    /**
     * @test
     */
    public function vOknuJobuJesteNeupozorni(): void
    {
        $vOknuTydenniUpominky = $this->dejKonecGc()->modifyStrict('+1 week +12 hours');

        $klice = $this->klice($this->dejHlidani($vOknuTydenniUpominky)->zkontroluj());

        self::assertNotContains('upominani-tyden-' . ROCNIK, $klice, 'Job může v okně ještě proběhnout');
        self::assertNotContains('upominani-mesic-' . ROCNIK, $klice);
    }

    /**
     * @test
     */
    public function poKonciOkenUpozorniNaVsechnyZmeskaneTerminy(): void
    {
        $mailuPred = $this->pocetUpozorneni();
        $ted = $this->dejKonecGc()->modifyStrict('+1 week +2 days');

        $zmeskane = $this->dejHlidani($ted)->zkontroluj();

        $rocnik = ROCNIK;
        self::assertSame(
            [
                "upominani-tyden-{$rocnik}",
                "varovani-promlceni-mesic-{$rocnik}",
                "varovani-promlceni-tyden-{$rocnik}",
                "automaticke-promlceni-{$rocnik}",
            ],
            $this->klice($zmeskane),
            'Měsíční upomínka má okno až měsíc po GC, ostatní termíny už uplynuly',
        );
        self::assertSame($mailuPred + 1, $this->pocetUpozorneni(), 'Všechny zmeškané termíny jdou v jednom mailu');

        $mailLogger = new MailLogger(LOGY . '/maily.sqlite');
        $mail = $mailLogger->najdi(self::PREDMET_UPOZORNENI, limit: 1)[0];
        self::assertStringContainsString("upomínka dlužníkům týden po GameConu {$rocnik}", $mail['predmet']);
        self::assertStringContainsString('it@gamecon.cz', $mail['adresati']);

        // Log stores the whole message with headers, the body of a text e-mail is in it as quoted-printable
        $telo = quoted_printable_decode(explode("\r\n\r\n", $mailLogger->detail((int) $mail['id'])['telo'], 2)[1]);
        self::assertStringContainsString('Finance → Upomínky dlužníkům', $telo);
        self::assertStringContainsString(
            'logy/cron/cron-dluznici-' . $this->dejKonecGc()->modifyStrict('+1 week')->format('Y-m') . '.log',
            $telo,
            'Text mail jde přes strip_tags(), název logu se v něm nesmí ztratit',
        );

        self::assertSame(
            [
                "zmeskano-automaticke-promlceni-{$rocnik}",
                "zmeskano-upominani-tyden-{$rocnik}",
                "zmeskano-varovani-promlceni-mesic-{$rocnik}",
                "zmeskano-varovani-promlceni-tyden-{$rocnik}",
            ],
            $this->zaznamyUpozorneni(),
        );
    }

    /**
     * @test
     */
    public function naKazdyTerminUpozorniJenJednou(): void
    {
        $tydenPoGc = $this->dejKonecGc()->modifyStrict('+1 week +2 days');
        $this->dejHlidani($tydenPoGc)->zkontroluj();
        $mailuPoPrvnimUpozorneni = $this->pocetUpozorneni();

        self::assertSame(
            [],
            $this->dejHlidani($tydenPoGc->modifyStrict('+1 hour'))->zkontroluj(),
            'Další hodinový běh už na stejné termíny neupozorní',
        );
        self::assertSame($mailuPoPrvnimUpozorneni, $this->pocetUpozorneni());

        self::assertSame(
            ['upominani-mesic-' . ROCNIK],
            $this->klice($this->dejHlidani($this->dejKonecGc()->modifyStrict('+1 month +2 days'))->zkontroluj()),
            'Po měsíci přibude jen měsíční upomínka',
        );
    }

    /**
     * @test
     */
    public function terminSeZaznamemBehuNehlasi(): void
    {
        $upominani = $this->dejUpominani($this->dejKonecGc());
        $upominani->zalogujUpominaniTyden(ROCNIK, 12);
        $upominani->zalogujUpominaniMesic(ROCNIK, 3);
        $promlceni = $this->dejPromlceni($this->dejKonecGc());
        $promlceni->zalogujVarovaniMesic(ROCNIK, 5);
        $promlceni->zalogujVarovaniTyden(ROCNIK, 4);
        $promlceni->zalogujAutomatickePromlceni(ROCNIK, 2, 300.0);
        $mailuPred = $this->pocetUpozorneni();

        $zmeskane = $this->dejHlidani($this->dejKonecGc()->modifyStrict('+2 months'))->zkontroluj();

        self::assertSame([], $zmeskane);
        self::assertSame($mailuPred, $this->pocetUpozorneni());
        self::assertSame([], $this->zaznamyUpozorneni());
    }

    /**
     * @test
     */
    public function zaznamMinulehoRocnikuTerminNesplni(): void
    {
        $this->dejUpominani($this->dejKonecGc())->zalogujUpominaniTyden(ROCNIK - 1, 12);

        $klice = $this->klice($this->dejHlidani($this->dejKonecGc()->modifyStrict('+1 week +2 days'))->zkontroluj());

        self::assertContains('upominani-tyden-' . ROCNIK, $klice);
    }

    /**
     * Upomínka bez dlužníků dřív skončila bez záznamu, takže by se od upomínky, která vůbec neběžela, nedala odlišit.
     *
     * @test
     */
    public function upominkaBezDluznikuSeNehlasiJakoZmeskana(): void
    {
        $this->odstranDluzniky();
        $vOknu = $this->dejUpominani($this->dejKonecGc()->modifyStrict('+1 week +1 hour'));
        self::assertSame([], $vOknu->najdiDluzniky(), 'Test potřebuje databázi bez dlužníků');

        self::assertSame(0, $vOknu->odesliUpominkyDluznikum(TypUpominky::TYDEN));

        $klice = $this->klice($this->dejHlidani($this->dejKonecGc()->modifyStrict('+1 week +2 days'))->zkontroluj());
        self::assertNotContains('upominani-tyden-' . ROCNIK, $klice);
    }

    /**
     * @test
     */
    public function varovaniBezUzivateluSeNehlasiJakoZmeskane(): void
    {
        dbQuery(<<<'SQL'
UPDATE uzivatele_hodnoty SET zustatek = 0 WHERE zustatek > 0
SQL);
        $vOknu = $this->dejPromlceni($this->dejRegistraceOd()->modifyStrict('-1 week +1 hour'));
        self::assertSame([], $vOknu->najdiUzivateleKPromlceni(), 'Test potřebuje databázi bez kladných zůstatků');

        self::assertSame(0, $vOknu->odesliVarovneEmaily(TypVarovaniPromlceni::TYDEN));

        $klice = $this->klice($this->dejHlidani($this->dejRegistraceOd()->modifyStrict('+1 day'))->zkontroluj());
        self::assertNotContains('varovani-promlceni-tyden-' . ROCNIK, $klice);
        self::assertContains('varovani-promlceni-mesic-' . ROCNIK, $klice, 'Měsíční varování neproběhlo');
    }

    private function dejHlidani(DateTimeImmutableStrict $ted): HlidaniZmeskanychTerminu
    {
        return new HlidaniZmeskanychTerminu(
            $this->dejNastaveni($ted),
            $this->dejUpominani($ted),
            $this->dejPromlceni($ted),
        );
    }

    private function dejUpominani(DateTimeImmutableStrict $ted): UpominaniDluzniku
    {
        // Fio se v testu nestahuje
        return new UpominaniDluzniku(
            $this->dejNastaveni($ted),
            $this->createMock(JobResultLoggerInterface::class),
            $this->createMock(FioStazeniNovychPlateb::class),
        );
    }

    private function dejPromlceni(DateTimeImmutableStrict $ted): PromlceniZustatku
    {
        return new PromlceniZustatku(
            $this->dejNastaveni($ted),
            $this->createMock(JobResultLoggerInterface::class),
        );
    }

    private function dejNastaveni(DateTimeImmutableStrict $ted): SystemoveNastaveni
    {
        return SystemoveNastaveni::zGlobals(rocnik: ROCNIK, ted: $ted);
    }

    private function dejKonecGc(): DateTimeImmutableStrict
    {
        return SystemoveNastaveni::zGlobals()->spocitanyKonecLetosnihoGameconu();
    }

    private function dejRegistraceOd(): DateTimeImmutableStrict
    {
        return DateTimeImmutableStrict::createFromInterface(SystemoveNastaveni::zGlobals()->prihlasovaniUcastnikuOd(ROCNIK));
    }

    /**
     * @param list<HlidanyTermin> $terminy
     *
     * @return list<string>
     */
    private function klice(array $terminy): array
    {
        return array_map(static fn (HlidanyTermin $termin) => $termin->klic, $terminy);
    }

    private function pocetUpozorneni(): int
    {
        return (new MailLogger(LOGY . '/maily.sqlite'))->spocitej(self::PREDMET_UPOZORNENI);
    }

    /**
     * @return list<string>
     */
    private function zaznamyUpozorneni(): array
    {
        return dbFetchColumn(<<<'SQL'
SELECT akce
FROM hromadne_akce_log
WHERE skupina = 'hlidani-zmeskanych-terminu'
ORDER BY akce
SQL);
    }

    private function odstranDluzniky(): void
    {
        dbQuery(<<<'SQL'
UPDATE uzivatele_hodnoty SET zustatek = 0 WHERE zustatek < 0
SQL);
        dbQuery(<<<'SQL'
DELETE FROM platby WHERE castka < 0
SQL);
        dbQuery(<<<'SQL'
DELETE FROM uzivatele_role WHERE id_role = $0
SQL, [
            0 => Role::prihlasenNaRocnik(ROCNIK),
        ]);
    }
}
