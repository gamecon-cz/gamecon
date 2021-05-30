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
        if (!is_dir($adresar) && (!mkdir($adresar, 0777, true) || !is_dir($adresar))) {
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
        $vs = $this->data['VS'] ?? '';
        return $vs ?: $this->nactiVsZTextu($this->zprava());
    }

    protected function nactiVsZTextu(string $text): string {
        if (!preg_match('~/vs/(?<vs>\d+)~i', $text, $matches)) {
            return '';
        }
        return $matches['vs'];
    }

    /** Zpráva pro příjemce */
    public function zprava() {
        return $this->data['Zpráva pro příjemce'] ?? '';
    }

    /**
     * Vrátí platby za posledních X dní
     * @return FioPlatba[]
     */
    public static function zPoslednichDni(int $pocetDniZpet) {
        return self::zRozmezi(
            (new DateTime())->sub(new DateInterval('P' . $pocetDniZpet . 'D')),
            new DateTime()
        );
    }

    /**
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @return FioPlatba[]
     */
    protected static function zRozmezi(DateTimeInterface $od, DateTimeInterface $do): array {
        $od = $od->format('Y-m-d');
        $do = $do->format('Y-m-d');
        $token = FIO_TOKEN;
        $url = "https://www.fio.cz/ib_api/rest/periods/$token/$od/$do/transactions.json";
        return self::zUrl($url);
    }

    /**
     * Vrátí platby načtené z jsonu na dané url
     * @return FioPlatba[]
     */
    protected static function zUrl($url): array {
        $raw = self::cached($url);
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        if (!$decoded) {
            return [];
        }
        $platby = $decoded->accountStatement->transactionList->transaction ?? [];
        $fioPlatby = [];
        foreach ($platby as $platba) {
            $fioPlatby[] = self::zPlatby($platba);
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
