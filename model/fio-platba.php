<?php

/**
 * Platba načtená z fio api (bez DB reprezentace)
 */
class FioPlatba
{

    /**
     * Vrátí platby za posledních X dní
     * @return FioPlatba[]
     */
    public static function zPoslednichDni(int $pocetDniZpet) {
        return self::zRozmezi(
            new DateTimeImmutable("-{$pocetDniZpet} days"),
            new DateTimeImmutable()
        );
    }

    /**
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @return FioPlatba[]
     */
    private static function zRozmezi(DateTimeInterface $od, DateTimeInterface $do): array {
        $odString = $od->format('Y-m-d');
        $doString = $do->format('Y-m-d');
        $token = FIO_TOKEN;
        $url = "https://www.fio.cz/ib_api/rest/periods/$token/$odString/$doString/transactions.json";
        return self::zUrl($url);
    }

    /**
     * Vrátí platby načtené z jsonu na dané url
     * @return FioPlatba[]
     */
    private static function zUrl(string $url): array {
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

    /** Cacheuje a zpracovává surovou rest odpověď (kvůli limitu 30s na straně FIO) */
    private static function cached($url) {
        $adresar = SPEC . '/fio';
        $soubor = $adresar . '/' . md5($url) . '.json';
        if (!is_dir($adresar) && (!mkdir($adresar, 0777, true) || !is_dir($adresar))) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $adresar));
        }
        if (@filemtime($soubor) < (time() - 60)) {
            self::fetch($url, $soubor);
        }
        // konverze čísel na stringy kvůli IDs většími než PHP_MAX_INT
        return preg_replace('@"value":([\d.]+),@', '"value":"$1",', file_get_contents($soubor));
    }

    private static function fetch(string $url, string $soubor) {
        for ($odpoved = false, $pokus = 1; $odpoved === false && $pokus < 5; $pokus++, usleep(100)) {
            $odpoved = @file_get_contents($url); // v prvních pokusech chyby maskovat
        }
        if ($odpoved === false) {
            $odpoved = file_get_contents($url); // v záverečném pokusu chybu reportovat
        }
        file_put_contents($soubor, $odpoved);
    }

    /** Vrátí platbu načtenou z předaného elementu z jsonového pole ...->transaction */
    private static function zPlatby(StdClass $platba): FioPlatba {
        $pole = [];
        foreach ($platba as $sloupec) {
            if ($sloupec) {
                $pole[$sloupec->name] = $sloupec->value;
            }
        }
        return new static($pole);
    }

    public static function existujePodleFioId($idFioPlatby): bool {
        return (bool)dbOneCol(<<<SQL
SELECT 1 FROM platby WHERE fio_id = $1
SQL,
            [$idFioPlatby]
        );
    }

    private $data;

    /**
     * Platba se vytváří z asociativního pole s klíči odpovídajícími názvům atributů v fio api
     * viz https://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
     */
    private function __construct(array $data) {
        $this->data = $data;
    }

    /** Objem platby (kladný pro příchozí, záporný pro odchozí) */
    public function castka(): float {
        return (float)$this->data['Objem'];
    }

    /** Vrací ID jako string (64bitů int) */
    public function id(): string {
        return $this->data['ID pohybu'];
    }

    /** Vrací ID jako string (64bitů int) */
    public function datum(): \DateTimeImmutable {
        // '2021-06-10+0200' for example (despite documentation where timezone format mentioned is with colon as +02:00)
        return \DateTimeImmutable::createFromFormat('Y-m-dO', $this->data['Datum'])
            ->setTime(0, 0, 0);
    }

    /** Variabilní symbol */
    public function vs(): string {
        $vs = $this->data['VS'] ?? '';
        return $vs ?: $this->nactiVsZTextu($this->zprava());
    }

    private function nactiVsZTextu(string $text): string {
        if (!preg_match('~(^|/)vs/(?<vs>\d+)~i', $text, $matches)) {
            return '';
        }
        return $matches['vs'];
    }

    /** Zpráva pro příjemce */
    public function zprava(): string {
        return $this->data['Zpráva pro příjemce'] ?? '';
    }
}
