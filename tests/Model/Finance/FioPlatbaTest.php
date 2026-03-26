<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Finance;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Finance\FioPlatba;
use PHPUnit\Framework\TestCase;

class FioPlatbaTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideTransactions
     */
    public function muzemeNacistVariabilniSymbolZeZpravyNeboPoznamky(array $transaction, string $expectedVs, ?int $ocekavaneIdUcastnika)
    {
        if (! defined('TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY')) {
            define('TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY', 'Příliš žluťoučký kůň úpěl ďábelské ódy');
        }

        $adresar = LOGY . '/fio';
        if (! is_dir($adresar)) {
            mkdir($adresar, 0777, true);
        }
        self::assertDirectoryExists($adresar);

        $pocetDniZpet = 1;
        $od = (new \DateTimeImmutable("-{$pocetDniZpet} days"))->format('Y-m-d');
        $do = date('Y-m-d');
        $url = 'https://fioapi.fio.cz/v1/rest/periods/' . FIO_TOKEN . "/{$od}/{$do}/transactions.json";
        $soubor = $adresar . '/' . md5($url) . '.json';
        $jsonData = json_encode([
            'accountStatement' => [
                'transactionList' => [
                    'transaction' => [$transaction],
                ],
            ],
        ], JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR);
        /* will be used as a less-than-minute-old "cache", @see \FioPlatba::cached */
        file_put_contents($soubor, $jsonData);

        $platby = FioPlatba::zPoslednichDni($pocetDniZpet);
        self::assertGreaterThan(0, count($platby), 'Platby nebyly načteny');
        $platba = reset($platby);
        self::assertSame($expectedVs, $platba->variabilniSymbol());
        self::assertSame($ocekavaneIdUcastnika, $platba->idUcastnika());
    }

    public static function provideTransactions(): array
    {
        return [
            'VS přímo' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 123.456,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775807',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '444555666',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => 'VS přímo',
                    ],
                ],
                '444555666',
                444555666,
            ],
            'VS v textu uppercase' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 0.1,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775806',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => '/VS/111222/Variabilní symbol velkými písmeny',
                    ],
                ],
                '111222',
                111222,
            ],
            'VS v textu lowercase' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 10.09,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775805',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => '/vs/9999/Variabilní symbol malými písmeny',
                    ],
                ],
                '9999',
                9999,
            ],
            'VS až někde v textu' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 10.09,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775804',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => '/DO2021-06-21/SP/VS/123465',
                    ],
                ],
                '123465',
                123465,
            ],
            'VS jako první ale bez úvodního lomítka' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 10.09,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775803',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => 'vs/887799/Variabilní symbol bez lomítka na začátku',
                    ],
                ],
                '887799',
                887799,
            ],
            'Bez VS' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 9999.99,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775802',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => 'Zabudol som ako sa dáva VS',
                    ],
                ],
                '',
                null,
            ],
            'VS v reference plátce jako čisté číslo' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 12.34,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775800',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Reference plátce',
                        'value' => '24680',
                    ],
                ],
                '24680',
                24680,
            ],
            'VS v reference plátce ve formátu /VS/' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 56.78,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775799',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Reference plátce',
                        'value' => '/VS/13579',
                    ],
                ],
                '13579',
                13579,
            ],
            'VS v reference plátce bez lomítek' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 43.21,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775798',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Reference plátce',
                        'value' => 'VS97531',
                    ],
                ],
                '97531',
                97531,
            ],
            'VS ve zprávě bez lomítek' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 65.43,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775797',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => 'VS86420',
                    ],
                ],
                '86420',
                86420,
            ],
            'VS ve zprávě s dvojtečkou' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 54.32,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775796',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => 'Platba VS:75319 za vstup',
                    ],
                ],
                '75319',
                75319,
            ],
            'VS v reference plátce s dvojtečkou' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 34.56,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775795',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Reference plátce',
                        'value' => 'VS:64208',
                    ],
                ],
                '64208',
                64208,
            ],
            'Dlouhý VS ve zprávě od zahraničního plátce se extrahuje celý' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 12.34,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775794',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => 'VS123456789012345',
                    ],
                ],
                '123456789012345',
                // VS se extrahuje celý; napárování na uživatele řeší jiná vrstva
                // (takové ID v DB neexistuje, ale to není věc tohoto testu).
                123456789012345,
            ],
            'VS následované suffixem se extrahuje bez suffixu' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 12.34,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775793',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => '/VS/4032 za GameCon',
                    ],
                ],
                '4032',
                4032,
            ],
            'Příliš dlouhá čistě číselná reference plátce není VS' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 12.34,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775792',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Reference plátce',
                        'value' => '12345678901234567890',
                    ],
                ],
                '',
                null,
            ],
            'Hraniční 11-místné čistě číselné reference není VS' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 12.34,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775791',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Reference plátce',
                        'value' => '12345678901',
                    ],
                ],
                '',
                null,
            ],
            'Hraniční 10-místná čistě číselná reference je VS' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 12.34,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775790',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Reference plátce',
                        'value' => '1234567890',
                    ],
                ],
                '1234567890',
                1234567890,
            ],
            'Dlouhý VS v reference plátce s oddělovačem se extrahuje celý' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 12.34,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775789',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Reference plátce',
                        'value' => 'VS:123456789012345',
                    ],
                ],
                '123456789012345',
                123456789012345,
            ],
            'Dlouhý VS s mezerou ve zprávě se extrahuje celý' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 12.34,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775788',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => 'Platba VS 123456789012 dekuji',
                    ],
                ],
                '123456789012',
                123456789012,
            ],
            'Sice s VS ale s nulovou částkou' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => 0.0,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775801',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '7654321',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => '/VS/7654321',
                    ],
                ],
                '7654321',
                null,
            ],
            'S VS v poznámce u odchozí platby' => [
                [
                    [
                        'name'  => 'Objem',
                        'value' => -0.1,
                    ],
                    [
                        'name'  => 'ID pohybu',
                        'value' => '9223372036854775801',
                    ],
                    [
                        'name'  => 'VS',
                        'value' => '7654321',
                    ],
                    [
                        'name'  => 'Zpráva pro příjemce',
                        'value' => '/VS/7654321',
                    ],
                    [
                        'name'  => 'Komentář',
                        'value' => 'PrIl  is Zluťouč kY kúŇ upěl ďabelskéOdy     90909090',
                    ],
                ],
                '7654321',
                90909090,
            ],
        ];
    }

    /**
     * @test
     */
    public function muzemeNacistVariabilniSymbolZeSkutecneOdpovedi()
    {
        $platbyMajiciVsVeZprave = array_filter(
            $this->dejAnonymizovanePlatby(),
            static function (FioPlatba $platba) {
                return $platba->zpravaProPrijemce() && preg_match('~^/VS/\d+$~', $platba->zpravaProPrijemce()) === 1;
            }
        );
        self::assertCount(2, $platbyMajiciVsVeZprave);
        foreach ($platbyMajiciVsVeZprave as $platbaMajiciVsVeZprave) {
            self::assertSame($platbaMajiciVsVeZprave->zpravaProPrijemce(), "/VS/{$platbaMajiciVsVeZprave->variabilniSymbol()}");
        }
    }

    /**
     * @return array|FioPlatba[]
     */
    private function dejAnonymizovanePlatby(): array
    {
        $adresar = LOGY . '/fio';
        if (! is_dir($adresar)) {
            mkdir($adresar, 0777, true);
        }
        self::assertDirectoryExists($adresar);

        $pocetDniZpet = 7;
        $od = (new \DateTimeImmutable("-{$pocetDniZpet} days"))->format('Y-m-d');
        $do = date('Y-m-d');
        $url = 'https://fioapi.fio.cz/v1/rest/periods/' . FIO_TOKEN . "/{$od}/{$do}/transactions.json";
        $soubor = $adresar . '/' . md5($url) . '.json';
        /* will be used as a less-than-minute-old "cache", @see \FioPlatba::cached */
        self::assertTrue(
            copy(__DIR__ . '/data/2021-06-10_to_2021-06-17_anonymised.json', $soubor),
            'Can not copy test file to destination'
        );

        return FioPlatba::zPoslednichDni($pocetDniZpet);
    }

    /**
     * @test
     */
    public function muzemeNacistDatumTransakce()
    {
        $konecGc2021 = DateTimeGamecon::spocitejKonecGameconu(2021);
        $platby = $this->dejAnonymizovanePlatby();
        foreach ($platby as $platba) {
            $datum = $platba->datum();
            self::assertInstanceOf(\DateTimeImmutable::class, $datum);
            self::assertLessThan($konecGc2021, $datum);
        }
    }
}
