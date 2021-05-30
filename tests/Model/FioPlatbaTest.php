<?php

namespace Gamecon\Tests;

use FioPlatba;
use PHPUnit\Framework\TestCase;

class FioPlatbaTest extends TestCase
{
    /**
     * @test
     */
    public function Muzeme_nacist_variabilni_symbol_ze_zpravy() {
        $adresar = SPEC . '/fio';
        if (!is_dir($adresar)) {
            mkdir($adresar, 0777, true);
        }
        self::assertDirectoryExists($adresar);
        $od = (new \DateTimeImmutable('-1 day'));
        $odString = $od->format('Y-m-d');
        $doString = date('Y-m-d');
        $url = "https://www.fio.cz/ib_api/rest/periods/" . FIO_TOKEN . "/$odString/$doString/transactions.json";
        $soubor = $adresar . '/' . md5($url) . '.json';
        $jsonData = json_encode([
            'accountStatement' => [
                'transactionList' => [
                    'transaction' => [
                        [
                            ['name' => 'Objem', 'value' => 123.456],
                            ['name' => 'ID pohybu', 'value' => '9223372036854775807'],
                            ['name' => 'VS', 'value' => '444555666'],
                            ['name' => 'Zpráva pro příjemce', 'value' => 'VS přímo'],
                        ],
                        [
                            ['name' => 'Objem', 'value' => 10.09],
                            ['name' => 'ID pohybu', 'value' => '9223372036854775806'],
                            ['name' => 'Zpráva pro příjemce', 'value' => '/VS/111222/Variabilní symbol velkými písmeny'],
                        ],
                        [
                            ['name' => 'Objem', 'value' => 10.09],
                            ['name' => 'ID pohybu', 'value' => '9223372036854775805'],
                            ['name' => 'VS', 'value' => ''],
                            ['name' => 'Zpráva pro příjemce', 'value' => '/vs/9999/Variabilní symbol malými písmeny'],
                        ],
                    ],
                ],
            ],
        ], JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR);
        file_put_contents($soubor, $jsonData);

        $platby = FioPlatba::zPoslednichDni(1);
        self::assertSame('444555666', $platby[0]->vs());
        self::assertSame('111222', $platby[1]->vs());
        self::assertSame('9999', $platby[2]->vs());
    }
}
