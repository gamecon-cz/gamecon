<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use FioPlatba;
use Gamecon\Cas\DateTimeGamecon;
use PHPUnit\Framework\TestCase;

class FioPlatbaTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideTransactions
     */
    public function Muzeme_nacist_variabilni_symbol_ze_zpravy_nebo_poznamky(array $transaction, string $expectedVs, ?int $ocekavaneIdUcastnika) {
        $adresar = LOGY . '/fio';
        if (!is_dir($adresar)) {
            mkdir($adresar, 0777, true);
        }
        self::assertDirectoryExists($adresar);

        $pocetDniZpet = 1;
        $od = (new \DateTimeImmutable("-{$pocetDniZpet} days"))->format('Y-m-d');
        $do = date('Y-m-d');
        $url = "https://fioapi.fio.cz/v1/rest/periods/" . FIO_TOKEN . "/$od/$do/transactions.json";
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
        self::assertGreaterThan(0, count($platby), 'Platby nebyly načteny');
        $platba = reset($platby);
        self::assertSame($expectedVs, $platba->variabilniSymbol());
        self::assertSame($ocekavaneIdUcastnika, $platba->idUcastnika());
    }

    public static function provideTransactions(): array {
        return [
            'VS přímo' => [
                [
                    ['name' => 'Objem', 'value' => 123.456],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775807'],
                    ['name' => 'VS', 'value' => '444555666'],
                    ['name' => 'Zpráva pro příjemce', 'value' => 'VS přímo'],
                ],
                '444555666',
                444555666,
            ],
            'VS v textu uppercase' => [
                [
                    ['name' => 'Objem', 'value' => 0.1],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775806'],
                    ['name' => 'Zpráva pro příjemce', 'value' => '/VS/111222/Variabilní symbol velkými písmeny'],
                ],
                '111222',
                111222,
            ],
            'VS v textu lowercase' => [
                [
                    ['name' => 'Objem', 'value' => 10.09],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775805'],
                    ['name' => 'VS', 'value' => ''],
                    ['name' => 'Zpráva pro příjemce', 'value' => '/vs/9999/Variabilní symbol malými písmeny'],
                ],
                '9999',
                9999,
            ],
            'VS až někde v textu' => [
                [
                    ['name' => 'Objem', 'value' => 10.09],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775804'],
                    ['name' => 'VS', 'value' => ''],
                    ['name' => 'Zpráva pro příjemce', 'value' => '/DO2021-06-21/SP/VS/123465'],
                ],
                '123465',
                123465,
            ],
            'VS jako první ale bez úvodního lomítka' => [
                [
                    ['name' => 'Objem', 'value' => 10.09],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775803'],
                    ['name' => 'VS', 'value' => ''],
                    ['name' => 'Zpráva pro příjemce', 'value' => 'vs/887799/Variabilní symbol bez lomítka na začátku'],
                ],
                '887799',
                887799,
            ],
            'Bez VS' => [
                [
                    ['name' => 'Objem', 'value' => 9999.99],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775802'],
                    ['name' => 'VS', 'value' => ''],
                    ['name' => 'Zpráva pro příjemce', 'value' => 'Zabudol som ako sa dáva VS'],
                ],
                '',
                null,
            ],
            'Sice s VS ale s nulovou částkou' => [
                [
                    ['name' => 'Objem', 'value' => 0.0],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775801'],
                    ['name' => 'VS', 'value' => '7654321'],
                    ['name' => 'Zpráva pro příjemce', 'value' => '/VS/7654321'],
                ],
                '7654321',
                null,
            ],
            'S VS v poznámce u odchozí platby' => [
                [
                    ['name' => 'Objem', 'value' => -0.1],
                    ['name' => 'ID pohybu', 'value' => '9223372036854775801'],
                    ['name' => 'VS', 'value' => '7654321'],
                    ['name' => 'Zpráva pro příjemce', 'value' => '/VS/7654321'],
                    [
                        'name' => 'Komentář',
                        'value' => (define('TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY', 'Příliš žluťoučký kůň úpěl ďábelské ódy')
                                ? 'PrIl  is Zluťouč kY kúŇ upěl ďabelskéOdy     '
                                : ''
                            ) . '90909090',
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
    public function Muzeme_nacist_variabilni_symbol_ze_skutecne_odpovedi() {
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
    private function dejAnonymizovanePlatby(): array {
        $adresar = LOGY . '/fio';
        if (!is_dir($adresar)) {
            mkdir($adresar, 0777, true);
        }
        self::assertDirectoryExists($adresar);

        $pocetDniZpet = 7;
        $od = (new \DateTimeImmutable("-{$pocetDniZpet} days"))->format('Y-m-d');
        $do = date('Y-m-d');
        $url = "https://fioapi.fio.cz/v1/rest/periods/" . FIO_TOKEN . "/$od/$do/transactions.json";
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
