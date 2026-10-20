<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaRepository;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Tests\Db\AbstractTestDb;

class EmailovaSablonaRepositoryTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function ulozenaSablonaSeNacteZpet(): void
    {
        $repository = new EmailovaSablonaRepository();
        $repository->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Nový předmět', 'Nový text %odkaz%', \Uzivatel::SYSTEM);

        $sablona = (new EmailovaSablonaRepository())->dej(KodEmailoveSablonyEnum::OBNOVA_HESLA);

        self::assertNotNull($sablona);
        self::assertSame('Nový předmět', $sablona->predmet);
        self::assertSame('Nový text %odkaz%', $sablona->text);
        self::assertSame(\Uzivatel::SYSTEM, $sablona->zmenil);
        self::assertNotNull($sablona->zmenenoKdy);
    }

    /**
     * @test
     */
    public function ulozeniOrezeMezeryAJednotiNoveRadky(): void
    {
        $repository = new EmailovaSablonaRepository();
        $repository->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, "  Předmět \t", "\n Řádek 1\r\nŘádek 2 \n\n", \Uzivatel::SYSTEM);

        $sablona = $repository->dej(KodEmailoveSablonyEnum::OBNOVA_HESLA);

        self::assertSame('Předmět', $sablona->predmet);
        self::assertSame("Řádek 1\nŘádek 2", $sablona->text);
    }

    /**
     * @test
     *
     * @dataProvider prazdneHodnoty
     */
    public function prazdnouSablonuNejdeUlozit(string $predmet, string $text): void
    {
        $repository = new EmailovaSablonaRepository();
        $puvodniText = $repository->dej(KodEmailoveSablonyEnum::OBNOVA_HESLA)->text;

        try {
            $repository->uloz(KodEmailoveSablonyEnum::OBNOVA_HESLA, $predmet, $text, \Uzivatel::SYSTEM);
            self::fail('Prázdná šablona se neměla uložit.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        self::assertSame($puvodniText, (new EmailovaSablonaRepository())->dej(KodEmailoveSablonyEnum::OBNOVA_HESLA)->text);
    }

    public static function prazdneHodnoty(): array
    {
        return [
            'prázdný předmět' => ['', 'Text'],
            'prázdný text'    => ['Předmět', ''],
            'bílé znaky'      => [" \t", "\n  "],
        ];
    }

    /**
     * @test
     *
     * @dataProvider prazdneHodnotyProDatabazi
     */
    public function databazeOdmitnePrazdnouSablonuIKdyzObejdemeAplikaci(string $sloupec, string $hodnota): void
    {
        $this->expectException(\DbException::class);

        dbQuery(
            "UPDATE emailova_sablona SET {$sloupec} = $0 WHERE kod = 'obnova_hesla'",
            [
                0 => $hodnota,
            ],
        );
    }

    public static function prazdneHodnotyProDatabazi(): array
    {
        return [
            'předmět'             => ['predmet', ''],
            'text'                => ['text', ''],
            'předmět jen z mezer' => ['predmet', '   '],
            'text jen z mezer'    => ['text', '   '],
        ];
    }

    /**
     * @test
     */
    public function sablonaBezRadkuVDatabaziSeNenacte(): void
    {
        self::assertNull((new EmailovaSablonaRepository())->dej(KodEmailoveSablonyEnum::UPOMINKA_VLASTNI));
    }

    /**
     * @test
     */
    public function radekSNeznamymKodemSeVNacteniPreskoci(): void
    {
        dbQuery(
            <<<'SQL'
INSERT INTO emailova_sablona(kod, predmet, text, zmenil)
VALUES ($0, $1, $2, $3)
SQL,
            [
                0 => 'kod_z_novejsi_verze',
                1 => 'Předmět',
                2 => 'Text',
                3 => \Uzivatel::SYSTEM,
            ],
        );

        $sablony = (new EmailovaSablonaRepository())->vsechny();

        self::assertArrayNotHasKey('kod_z_novejsi_verze', $sablony);
        self::assertArrayHasKey(KodEmailoveSablonyEnum::OBNOVA_HESLA->value, $sablony);
    }
}
