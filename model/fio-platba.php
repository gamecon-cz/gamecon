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
    public static function zPoslednichDni(int $pocetDniZpet): array
    {
        return self::zRozmezi(
            new DateTimeImmutable("-{$pocetDniZpet} days"),
            new DateTimeImmutable(),
        );
    }

    /**
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @return FioPlatba[]
     */
    public static function zRozmezi(DateTimeInterface $od, DateTimeInterface $do): array
    {
        $odString = $od->format('Y-m-d');
        $doString = $do->format('Y-m-d');
        $token    = FIO_TOKEN;
        $url      = "https://www.fio.cz/ib_api/rest/periods/$token/$odString/$doString/transactions.json";

        return self::zUrl($url);
    }

    /**
     * Vrátí platby načtené z jsonu na dané url
     * @return FioPlatba[]
     */
    private static function zUrl(string $url): array
    {
        $raw = self::cached($url);
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        if (!$decoded) {
            return [];
        }
        $platby    = $decoded->accountStatement->transactionList->transaction ?? [];
        $fioPlatby = [];
        foreach ($platby as $platba) {
            $fioPlatby[] = self::zPlatby($platba);
        }

        return $fioPlatby;
    }

    /** Cacheuje a zpracovává surovou rest odpověď (kvůli limitu 30s na straně FIO) */
    private static function cached(string $url)
    {
        $adresar = SPEC . '/fio';
        $soubor  = $adresar . '/' . md5($url) . '.json';
        if (!is_dir($adresar) && (!mkdir($adresar, 0777, true) || !is_dir($adresar))) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $adresar));
        }
        if (@filemtime($soubor) < (time() - 60)) {
            self::fetch($url, $soubor);
        }

        // konverze čísel na stringy kvůli IDs většími než PHP_MAX_INT
        return preg_replace('@"value":([\d.]+),@', '"value":"$1",', file_get_contents($soubor));
    }

    private static function fetch(string $url, string $soubor)
    {
        $errors = [];
        for ($odpoved = false, $pokus = 1; $odpoved === false && $pokus < 5; $pokus++, usleep(100)) {
            try {
                $odpoved = self::rawFetch($url);
            } catch (\RuntimeException $runtimeException) {
                // v prvních pokusech chyby maskovat
                $errors[] = $runtimeException->getMessage();
            }
        }
        if ($odpoved === false) {
            try {
                // v záverečném pokusu chybu reportovat
                $odpoved = self::rawFetch($url);
            } catch (\RuntimeException $runtimeException) {
                throw new \RuntimeException($runtimeException->getMessage() . '; ' . implode(',', $errors));
            }
        }
        file_put_contents($soubor, $odpoved);
    }

    private static function rawFetch(string $url): false | string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \RuntimeException(curl_error($ch));
        }

        return $response;
    }

    /** Vrátí platbu načtenou z předaného elementu z jsonového pole ...->transaction */
    private static function zPlatby(StdClass $platba): FioPlatba
    {
        $pole = [];
        foreach ($platba as $sloupec) {
            if ($sloupec) {
                $pole[$sloupec->name] = $sloupec->value;
            }
        }

        return new static($pole);
    }

    public static function existujePodleFioId($idFioPlatby): bool
    {
        return (bool)dbOneCol(<<<SQL
SELECT 1 FROM platby WHERE fio_id = $1
SQL,
            [$idFioPlatby],
        );
    }

    private $data;

    /**
     * Platba se vytváří z asociativního pole s klíči odpovídajícími názvům atributů v fio api
     * viz https://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /** Objem platby (kladný pro příchozí, záporný pro odchozí) */
    public function castka(): float
    {
        return (float)$this->data['Objem'];
    }

    /** Vrací ID jako string (64bitů int) */
    public function id(): string
    {
        return $this->data['ID pohybu'];
    }

    /** Vrací ID jako string (64bitů int) */
    public function datum(): \DateTimeImmutable
    {
        // '2021-06-10+0200' for example (despite documentation where timezone format mentioned is with colon as +02:00)
        return \DateTimeImmutable::createFromFormat('Y-m-dO', $this->data['Datum'])
            ->setTime(0, 0, 0);
    }

    /** Variabilní symbol */
    public function vs(): string
    {
        $vs = $this->data['VS'] ?? '';

        return $vs
            ?: $this->nactiVsZTextu($this->zpravaProPrijemce());
    }

    private function nactiVsZTextu(string $text): string
    {
        if (!preg_match('~(^|/)vs/(?<vs>\d+)~i', $text, $matches)) {
            return '';
        }

        return $matches['vs'];
    }

    /** Variabilní symbol */
    public function idUcastnika(): ?int
    {
        if ($this->castka() > 0) {
            return trim($this->vs()) === ''
                ? null
                : (int)trim($this->vs());
        }
        if ($this->castka() === 0.0) {
            return null;
        }

        return $this->nactiIdUcastnikaZeZpravyProPrijemce();
    }

    private function nactiIdUcastnikaZeZpravyProPrijemce(): ?int
    {
        $parovaciText = defined('TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY')
            ? trim(TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY)
            : '';
        if ($parovaciText === '') {
            return null;
        }
        $poznamkaProMe = trim($this->poznamkaProMne());
        if ($poznamkaProMe === '') {
            return null;
        }
        $parovaciTextBezDiakritiky  = $this->lowercaseBezMezerABezDiakritiky($parovaciText);
        $poznamkaProMeBezDiakritiky = $this->lowercaseBezMezerABezDiakritiky($poznamkaProMe);
        if (!preg_match(
            '~' . preg_quote($parovaciTextBezDiakritiky, '~') . '[^[:alnum:]]*(?<idUcastnika>\d+)~',
            $poznamkaProMeBezDiakritiky,
            $matches)
        ) {
            return null;
        }

        return (int)$matches['idUcastnika'];
    }

    private function lowercaseBezMezerABezDiakritiky(string $text): string
    {
        $bezMezer      = preg_replace('~\s~', '', $text);
        $bezDiakritiky = removeDiacritics($bezMezer);

        return strtolower($bezDiakritiky);
    }

    public function zpravaProPrijemce(): string
    {
        return $this->data['Zpráva pro příjemce'] ?? '';
    }

    public function poznamkaProMne(): string
    {
        return $this->data['Komentář'] ?? '';
    }

    public function jakoArray(): array
    {
        $array = [];
        $soucasnaMetoda  = explode('::', __METHOD__)[1];
        foreach ($this->seznamGetteru($soucasnaMetoda) as $getter) {
            $array[$getter] = $this->$getter();
        }

        return $array;
    }

    /**
     * @return array<string>
     */
    private function seznamGetteru(string $kromeMetody): array
    {
        static $gettery = null;
        if ($gettery === null) {
            $gettery         = [];
            $reflectionClass = new ReflectionClass($this);
            $publicMethods   = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            $soucasnaMetoda  = explode('::', __METHOD__)[1];
            $gettery         = [];
            foreach ($publicMethods as $method) {
                if ($method->getName() === $soucasnaMetoda || $method->isStatic() || $method->getNumberOfParameters() > 0) {
                    continue;
                }
                if ($method->hasReturnType()) {
                    $gettery[] = $method->getName();
                }
            }
        }

        return array_filter($gettery, fn($getter) => $getter !== $kromeMetody);
    }
}
