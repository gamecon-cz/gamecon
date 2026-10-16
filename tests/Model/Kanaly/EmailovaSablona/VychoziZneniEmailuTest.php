<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailoveSablony;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * The default wording seeded by the migration must produce exactly the e-mail that used to be assembled in code.
 * The expected texts are copied from before the switch to templates.
 */
class VychoziZneniEmailuTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function uvolneneMisto(): void
    {
        $mail = $this->sablony()->vytvorMail('uvolnene_misto', [
            'aktivita' => 'Hrob krále',
            'cas'      => 'čtvrtek 10–14',
        ]);

        self::assertSame('Gamecon: Volné místo na aktivitě Hrob krále', $mail->dejPredmet());
        self::assertSame(
            '<html><body>Na aktivitě Hrob krále, která se koná v čtvrtek 10–14, se uvolnilo místo. Tento e-mail dostáváš, protože máš nastavené sledování uvedené aktivity. Přihlaš se na aktivitu přes <a href="https://gamecon.cz/program">program</a> (pospěš si, ať ti místo nezabere někdo jiný).</body></html>',
            $mail->dejText(),
        );
    }

    /**
     * @test
     */
    public function nedostaveniSeNaAktivitu(): void
    {
        $mail = $this->sablony()->vytvorMail('nedostaveni_se_na_aktivitu', [
            'aktivita'         => 'Hrob krále',
            'lhuta_bez_pokuty' => '2 hodiny',
            'pokuta_procenta'  => 50,
        ]);

        self::assertSame('Nedostavení se na aktivitu', $mail->dejPredmet());
        self::assertSame(
            '<html><body>' . $this->naHtml(<<<'TEXT'
                Ahoj,

                vypadá to, že aktivita Hrob krále proběhla bez tebe!

                Chápeme, že může nastat situace, kvůli které se nemůžeš, nebo nechceš aktivity zúčastnit, pro příště tě ale moc prosíme

                VŽDY SE Z AKTIVITY ODHLAS.

                Žádáme tě o to z toho důvodu, aby na tebe vypravěč a ostatní účastníci nemuseli čekat a zjišťovat, jestli dorazíš. A taky to značně urychlí hledání náhradníka. Pokud se odhlásíš dříve než 2 hodiny před začátkem aktivity, vrátíme ti za ni všechny peníze. Pokud později, tak 50 %.

                Jedná se o omyl? Někde v systému pravděpodobně nastala chyba, za kterou se moc omlouváme. Dej nám o ní prosím vědět, abychom to opravili a znovu tě zbytečně neděsili.

                Děkujeme za spolupráci!

                Tvůj,
                Organizační tým GC
                TEXT) . '</body></html>',
            $mail->dejText(),
        );
    }

    /**
     * @test
     */
    public function obnovaHesla(): void
    {
        $vykresleno = $this->sablony()->vykresli('obnova_hesla', [
            'odkaz' => 'https://gamecon.cz/obnova-hesla?token=abc.def',
        ]);

        self::assertSame('Obnova hesla na GameConu', $vykresleno->predmet);
        self::assertSame(GcMail::FORMAT_HTML, $vykresleno->format);
        self::assertSame(
            $this->naHtml(<<<'TEXT'
                Ahoj,

                dostali jsme žádost o nastavení nového hesla na Gamecon.cz. Klikni na odkaz níže a nastav si nové heslo:

                <a href="https://gamecon.cz/obnova-hesla?token=abc.def">https://gamecon.cz/obnova-hesla?token=abc.def</a>

                Odkaz platí 1 hodinu. Pokud jsi o změnu hesla nežádal/a, tento e-mail ignoruj — heslo zůstane beze změny.

                Tvůj
                organizační tým GameConu
                TEXT),
            $vykresleno->text,
        );
    }

    /**
     * @test
     */
    public function rychlaRegistrace(): void
    {
        $mail = $this->sablony()->vytvorMail('rychla_registrace', [
            'odkaz_k_potvrzeni' => 'https://gamecon.cz/potvrzeni-registrace/kod123',
        ]);

        self::assertSame('Registrace na GameCon.cz', $mail->dejPredmet());
        self::assertSame(
            '<html><body>' . $this->naHtml(<<<'TEXT'
                Ahoj,

                děkujeme, že se letos účastníš GameConu! Kliknutím na odkaz níže potvrdíš registraci na web, kde si nastavíš přezdívku a heslo tak, ať můžeš používat web a přijet třeba i příští rok. V případě pozdější registrace na web by bylo nutné nechat si vygenerovat heslo znovu.

                <a href="https://gamecon.cz/potvrzeni-registrace/kod123">https://gamecon.cz/potvrzeni-registrace/kod123</a>
                TEXT) . '</body></html>',
            $mail->dejText(),
        );
    }

    /**
     * @test
     *
     * @dataProvider pohlavi
     */
    public function odhlaseniZGameconu(string $koncovka): void
    {
        $mail = $this->sablony()->vytvorMail('odhlaseni_z_gameconu', [
            'a'          => $koncovka,
            'dalsi_vlna' => '17. 7. 2026 20:00:00',
        ]);

        self::assertSame('Byl' . $koncovka . ' jsi odhlášen' . $koncovka . ' z Gameconu ' . ROCNIK, $mail->dejPredmet());
        self::assertSame(
            "Právě jsme tě odhlásili z letošního Gameconu.\n\n"
            . str_repeat('═', mb_strlen('Právě jsme tě odhlásili z letošního Gameconu.'))
            . "\n\nPokud jsi platbu zapomněl{$koncovka} poslat, přihlaš se zpět v další vlně aktivit, která bude 17. 7. 2026 20:00:00 a platbu ohlídej.",
            $mail->dejText(),
        );
    }

    /**
     * @test
     */
    public function odhlaseniZGameconuSeOdesilaJakoProstyText(): void
    {
        $mail = $this->sablony()->vytvorMail('odhlaseni_z_gameconu', [
            'a'          => '',
            'dalsi_vlna' => 'x',
        ]);

        $format = new \ReflectionProperty($mail, 'format');

        self::assertSame(GcMail::FORMAT_TEXT, $format->getValue($mail));
    }

    /**
     * @test
     *
     * @dataProvider upominkyZPuvodnihoKodu
     */
    public function upominkyJsouJakoVPuvodnimKodu(string $kod, string $ocekavanyPredmet, string $ocekavanyText): void
    {
        $promenne = [
            'dluh'  => 350,
            'vs'    => 1234,
            'jmeno' => 'Jana',
            'a'     => 'a',
        ];
        if (str_ends_with($kod, '_nedorazil_drive')) {
            $promenne['rok_posledni_ucasti'] = 2019;
        }
        $nahrady = [
            '%UCET_CZ%'             => UCET_CZ,
            '%IBAN%'                => IBAN,
            '%ROCNIK%'              => (string) ROCNIK,
            '%dluh%'                => '350',
            '%vs%'                  => '1234',
            '%a%'                   => 'a',
            '%rok_posledni_ucasti%' => '2019',
        ];

        $vykresleno = $this->sablony()->vykresli($kod, $promenne);

        self::assertSame(strtr($ocekavanyPredmet, $nahrady), $vykresleno->predmet);
        self::assertSame(strtr($ocekavanyText, $nahrady), $vykresleno->text);
    }

    /**
     * Texts generated from the original code UpominaniDluzniku::dejEmailZpravu() before the switch to templates,
     * for each combination of reminder type and debtor participation, with symbols in place of variables.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function upominkyZPuvodnihoKodu(): array
    {
        $zneni = json_decode(
            (string) file_get_contents(__DIR__ . '/upominky-puvodni-zneni.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $data = [];
        foreach ($zneni as $kod => $polozka) {
            $data[$kod] = [$kod, $polozka['predmet'], $polozka['text']];
        }

        return $data;
    }

    public static function pohlavi(): array
    {
        return [
            'muž'  => [''],
            'žena' => ['a'],
        ];
    }

    private function sablony(): EmailoveSablony
    {
        return new EmailoveSablony(SystemoveNastaveni::zGlobals());
    }

    /**
     * The way hlaska() used to do it: every new line of the template becomes a line break.
     */
    private function naHtml(string $text): string
    {
        return str_replace("\n", '<br />', $text);
    }
}
