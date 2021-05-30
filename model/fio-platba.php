<?php

/**
 * Platba načtená z fio api (bez DB reprezentace)
 */
class FioPlatba
{

    private $data;

    /**
     * Platba se vytváří z asociativního pole s klíči odpovídajícími názvům atributů v fio api
     * viz http://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
     */
    protected function __construct(array $data) {
        $this->data = $data;
    }

    /** Cacheuje a zpracovává surovou rest odpověď (kvůli limitu 30s na straně FIO) */
    protected static function cached($url) {
        $adresar = SPEC . '/fio';
        $soubor = $adresar . '/' . md5($url) . '.json';
        if (!mkdir($adresar) && !is_dir($adresar)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $adresar));
        }
        if (@filemtime($soubor) < time() - 60) {
            $pokus = 0;
            do {
                $pokus++;
                $odpoved = @file_get_contents($url); // v prvních pokusech chyby maskovat
            } while ($odpoved === false && $pokus < 5); // opakovat načtení až 5x
            if ($odpoved === false) {
                $odpoved = file_get_contents($url); // v záverečném pokusu chybu reportovat
            }

            file_put_contents($soubor, $odpoved);
        }
        return preg_replace('@"value":([\d\.]+),@', '"value":"$1",', file_get_contents($soubor)); // konverze čísel na stringy kvůli velkým ID
    }

    /** Objem platby (kladný pro příchozí, záporný pro odchozí) */
    public function castka() {
        return $this->data['Objem'];
    }

    /** Vrací ID jako string (64bitů int) */
    public function id() {
        return $this->data['ID pohybu'];
    }

    /** Variabilní symbol */
    public function vs() {
        return $this->data['VS'] ?? '';
    }

    /** Zpráva pro příjemce */
    public function zprava() {
        return $this->data['Zpráva pro příjemce'] ?? '';
    }

    /** Vrátí platby za posledních $dni dní */
    public static function zPoslednichDni($dni) {
        return self::zRozmezi(
            (new DateTime())->sub(new DateInterval('P' . $dni . 'D')),
            new DateTime()
        );
    }

    protected static function zRozmezi(DateTime $od, DateTime $do) {
        $od = $od->format('Y-m-d');
        $do = $do->format('Y-m-d');
        $token = FIO_TOKEN;
        $url = "https://www.fio.cz/ib_api/rest/periods/$token/$od/$do/transactions.json";
        return self::zUrl($url);
    }

    /** Vrátí platby načtené z jsonu na dané url */
    protected static function zUrl($url) {
        $platby = json_decode(self::cached($url))->accountStatement->transactionList;
        $platby = $platby ? $platby->transaction : [];
        $fioPlatby = [];
        foreach ($platby as $platba) {
            $fioPlatby[] = self::zPlatby($platba);
            //$o[id]?
        }
        return $fioPlatby;
    }

    /** Vrátí platbu načtenou z předaného elementu z jsonového pole ...->transaction */
    protected static function zPlatby(StdClass $platba): FioPlatba {
        $pole = [];
        foreach ($platba as $sloupec) {
            if ($sloupec) {
                $pole[$sloupec->name] = $sloupec->value;
            }
        }
        return new static($pole);
    }

}
