<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailovaPromenna;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaDefinice;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaValidator;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kanaly\EmailovaSablona\SystemoveHodnotyProSablony;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Gamecon\Tests\Db\AbstractTestDb;

class EmailovaSablonaValidatorTest extends AbstractTestDb
{
    use CerstveSystemoveNastaveniTrait;

    /**
     * @test
     *
     * @dataProvider pripadyZeSpolecnehoSouboru
     *
     * @param list<string> $ocekavaneChyby
     */
    public function validaceVraciOcekavaneChyby(string $predmet, string $text, array $ocekavaneChyby): void
    {
        self::assertSame($ocekavaneChyby, $this->validuj($predmet, $text));
    }

    /**
     * The same file is read by the editor's JS test, so both validators give the same answer for the same input.
     *
     * @see ../../../../ui/src/adminEditorSablony/editorSablonyLogika.test.ts
     */
    public static function pripadyZeSpolecnehoSouboru(): array
    {
        $pripady = json_decode(
            (string) file_get_contents(__DIR__ . '/validace-pripady.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        )['pripady'];

        $data = [];
        foreach ($pripady as $pripad) {
            $data[$pripad['nazev']] = [$pripad['predmet'], $pripad['text'], $pripad['chyby']];
        }

        return $data;
    }

    /**
     * @test
     */
    public function poZverejneniNastaveniUzProjde(): void
    {
        $klic = SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA;
        $this->cerstveSystemoveNastaveni()->ulozZmenuPriznakuVerejne(true, $klic, \Uzivatel::zId(\Uzivatel::SYSTEM));

        self::assertSame([], $this->validuj('Předmět', "Hranice: %{$klic}%", $this->cerstveSystemoveNastaveni()));
    }

    /**
     * @test
     */
    public function poZruseniVerejnostiSeUzNeprojde(): void
    {
        $nastaveni = $this->cerstveSystemoveNastaveni();
        $nastaveni->ulozZmenuPriznakuVerejne(false, 'GC_BEZI_OD', \Uzivatel::zId(\Uzivatel::SYSTEM));

        self::assertSame(
            ['Text: „%GC_BEZI_OD%“ je neveřejné nastavení a do e-mailu se nedosadí. Zveřejnit ho jde na stránce Nastavení.'],
            $this->validuj('Předmět', 'Začátek %GC_BEZI_OD%', $this->cerstveSystemoveNastaveni()),
        );
    }

    /**
     * @test
     */
    public function zpetnaKontrolaNajdeJenProblemoveSystemoveHodnoty(): void
    {
        $validator = new EmailovaSablonaValidator(new SystemoveHodnotyProSablony(SystemoveNastaveni::zGlobals()));

        self::assertSame(
            ['„%PODEZRELE_VYSOKA_PLATBA_UCASTNIKA%“ je neveřejné nastavení a do e-mailu se nedosadí. Zveřejnit ho jde na stránce Nastavení.'],
            $validator->chybySystemovychHodnot('Ahoj %jmeno% %ROCNIK% %PODEZRELE_VYSOKA_PLATBA_UCASTNIKA% %jmen'),
        );
    }

    /**
     * @return list<string>
     */
    private function validuj(string $predmet, string $text, ?SystemoveNastaveni $systemoveNastaveni = null): array
    {
        $definice = new EmailovaSablonaDefinice(
            kod: KodEmailoveSablonyEnum::UPOMINKA_VLASTNI,
            skupina: 'Test',
            nazev: 'Test',
            kdyOdchazi: '',
            prijemce: '',
            format: GcMail::FORMAT_TEXT,
            promenne: [
                new EmailovaPromenna('jmeno', 'jméno', 'Jan', vPredmetu: true),
                new EmailovaPromenna('odkaz', 'odkaz', 'https://example.test'),
            ],
        );

        return (new EmailovaSablonaValidator(
            new SystemoveHodnotyProSablony($systemoveNastaveni ?? SystemoveNastaveni::zGlobals()),
        ))->validuj($definice, $predmet, $text);
    }
}
