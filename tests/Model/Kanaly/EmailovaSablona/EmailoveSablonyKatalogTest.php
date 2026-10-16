<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaRepository;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaValidator;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaVykreslovac;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablonyKatalog;
use Gamecon\Kanaly\EmailovaSablona\SystemoveHodnotyProSablony;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * The catalog in code and the default wording seeded by the migration must match, otherwise
 * an e-mail would either be missing in the admin or could not be sent.
 */
class EmailoveSablonyKatalogTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function kazdaSablonaZKataloguMaVDatabaziVyplnenyRadek(): void
    {
        $ulozene = (new EmailovaSablonaRepository())->vsechny();

        foreach (array_keys(EmailoveSablonyKatalog::vsechny()) as $kod) {
            self::assertArrayHasKey($kod, $ulozene, "Migrace nezaložila výchozí znění šablony '{$kod}'");
            self::assertTrue($ulozene[$kod]->jeVyplnena(), "Šablona '{$kod}' je prázdná");
        }
    }

    /**
     * @test
     */
    public function vDatabaziNeniSablonaKteraNeniVKatalogu(): void
    {
        $ulozene = array_keys((new EmailovaSablonaRepository())->vsechny());

        self::assertSame([], array_values(array_diff($ulozene, array_keys(EmailoveSablonyKatalog::vsechny()))));
    }

    /**
     * @test
     */
    public function vychoziZneniProjdeStejnouValidaciJakoUlozeniVAdminu(): void
    {
        $ulozene = (new EmailovaSablonaRepository())->vsechny();
        $validator = new EmailovaSablonaValidator(new SystemoveHodnotyProSablony(SystemoveNastaveni::zGlobals()));

        foreach (EmailoveSablonyKatalog::vsechny() as $kod => $definice) {
            self::assertSame(
                [],
                $validator->validuj($definice, $ulozene[$kod]->predmet, $ulozene[$kod]->text),
                "Výchozí znění šablony '{$kod}' by se v adminu nedalo uložit",
            );
        }
    }

    /**
     * @test
     */
    public function vychoziZneniSeUmiVykreslitSUkazkovymiHodnotamiBezZbytkuSymbolu(): void
    {
        $ulozene = (new EmailovaSablonaRepository())->vsechny();
        $vykreslovac = new EmailovaSablonaVykreslovac(new SystemoveHodnotyProSablony(SystemoveNastaveni::zGlobals()));

        foreach (EmailoveSablonyKatalog::vsechny() as $kod => $definice) {
            $ukazky = [];
            foreach ($definice->promenne as $promenna) {
                $ukazky[$promenna->nazev] = $promenna->ukazka;
            }

            $vykresleno = $vykreslovac->vykresli($definice, $ulozene[$kod]->predmet, $ulozene[$kod]->text, $ukazky);

            self::assertDoesNotMatchRegularExpression(
                '~%[A-Za-z_]+%~',
                $vykresleno->predmet . $vykresleno->text,
                "Po vykreslení šablony '{$kod}' zbyl nedosazený symbol",
            );
        }
    }

    /**
     * @test
     */
    public function kazdaDeklarovanaPromennaSeVSablonePouzije(): void
    {
        $ulozene = (new EmailovaSablonaRepository())->vsechny();

        foreach (EmailoveSablonyKatalog::vsechny() as $kod => $definice) {
            foreach ($definice->promenne as $promenna) {
                if ($promenna->volitelna) {
                    continue;
                }
                self::assertStringContainsString(
                    $promenna->symbol(),
                    $ulozene[$kod]->predmet . "\n" . $ulozene[$kod]->text,
                    "Proměnná {$promenna->symbol()} šablony '{$kod}' se ve výchozím znění nikde nepoužije - buď ji z katalogu odeber, nebo ji použij",
                );
            }
        }
    }
}
