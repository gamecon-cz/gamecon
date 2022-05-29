<?php

namespace Gamecon\Tests;

use FioPlatba;
use Gamecon\Cas\DateTimeGamecon;
use PHPUnit\Framework\TestCase;

class FioPlatbaTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideTransactions
     */
    public function Muzeme_nacist_variabilni_symbol_ze_zpravy(array $transaction, string $expectedVs) {
        $adresar = SPEC . '/fio';
        if (!is_dir($adresar)) {
            mkdir($adresar, 0777, true);
        }
        self::assertDirectoryExists($adresar);

        $pocetDniZpet = 1;
        $od = (new \DateTimeImmutable("-{$pocetDniZpet} days"))->format('Y-m-d');
        $do = date('Y-m-d');
        $url = "https://www.fio.cz/ib_api/rest/periods/" . FIO_TOKEN . "/$od/$do/transactions.json";
        $soubor = $adresar . '/' . md5($url) . '.json';
        $jsonData = json_encode([
            'accountStatement' => [
                'transactionList' => [
                    'transaction' => [$transaction],
                ],
            ],
        ], JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR);
        /** will be used as a less-than-minute-old "cache", @see \FioPlatba::cached */
        file_put_contents($soubor, $jsonData);

        $platby = FioPlatba::zPoslednichDni($pocetDniZpet);
        self::assertSame($expectedVs, $platby[0]->vs());
    }

    public function provideTransactions(): array {
        return [
            'VS directly' => [
                [
                    ['name' => 'Objem', 'value' => 123.456],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775807'],
                    ['name' => 'VS', 'value' => '444555666'],
                    ['name' => 'Zpráva pro příjemce', 'value' => 'VS přímo'],
                ],
                '444555666',
            ],
            'VS uppercase' => [
                [
                    ['name' => 'Objem', 'value' => 10.09],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775806'],
                    ['name' => 'Zpráva pro příjemce', 'value' => '/VS/111222/Variabilní symbol velkými písmeny'],
                ],
                '111222',
            ],
            'VS lowercase' => [
                [
                    ['name' => 'Objem', 'value' => 10.09],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775805'],
                    ['name' => 'VS', 'value' => ''],
                    ['name' => 'Zpráva pro příjemce', 'value' => '/vs/9999/Variabilní symbol malými písmeny'],
                ],
                '9999',
            ],
            'VS not first' => [
                [
                    ['name' => 'Objem', 'value' => 10.09],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775804'],
                    ['name' => 'VS', 'value' => ''],
                    ['name' => 'Zpráva pro příjemce', 'value' => '/DO2021-06-21/SP/VS/123465'],
                ],
                '123465',
            ],
            'VS first but without leading slash' => [
                [
                    ['name' => 'Objem', 'value' => 10.09],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775803'],
                    ['name' => 'VS', 'value' => ''],
                    ['name' => 'Zpráva pro příjemce', 'value' => 'vs/887799/Variabilní symbol bez lomítka na začátku'],
                ],
                '887799',
            ],
        ];
    }

    /**
     * @test
     */
    public function Muzeme_nacist_variabilni_symbol_ze_skutecne_odpovedi() {
        $platbyMajiciVsVeZprave = array_filter(
            $this->dejAnonymizovanePlatby(),
            static function (FioPlatba $platba) {
                return $platba->zprava() && preg_match('~^/VS/\d+$~', $platba->zprava()) === 1;
            }
        );
        self::assertCount(2, $platbyMajiciVsVeZprave);
        foreach ($platbyMajiciVsVeZprave as $platbaMajiciVsVeZprave) {
            self::assertSame($platbaMajiciVsVeZprave->zprava(), "/VS/{$platbaMajiciVsVeZprave->vs()}");
        }
    }

    /**
     * @return array|FioPlatba[]
     */
    private function dejAnonymizovanePlatby(): array {
        $adresar = SPEC . '/fio';
        if (!is_dir($adresar)) {
            mkdir($adresar, 0777, true);
        }
        self::assertDirectoryExists($adresar);

        $pocetDniZpet = 7;
        $od = (new \DateTimeImmutable("-{$pocetDniZpet} days"))->format('Y-m-d');
        $do = date('Y-m-d');
        $url = "https://www.fio.cz/ib_api/rest/periods/" . FIO_TOKEN . "/$od/$do/transactions.json";
        $soubor = $adresar . '/' . md5($url) . '.json';
        /** will be used as a less-than-minute-old "cache", @see \FioPlatba::cached */
        self::assertTrue(
            copy(__DIR__ . '/data/2021-06-10_to_2021-06-17_anonymised.json', $soubor),
            'Can not copy test file to destination'
        );

        return FioPlatba::zPoslednichDni($pocetDniZpet);
    }

    /**
     * @test
     */
    public function Muzeme_nacist_datum_transakce() {
        $konecGc2021 = DateTimeGamecon::spocitejKonecGameconu(2021);
        $platby = $this->dejAnonymizovanePlatby();
        foreach ($platby as $platba) {
            $datum = $platba->datum();
            self::assertInstanceOf(\DateTimeImmutable::class, $datum);
            self::assertLessThan($konecGc2021, $datum);
        }
    }
}
