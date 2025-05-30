<?php

/**
 * Platba načtená z fio api (bez DB reprezentace)
 */
class FioPlatba
{
    private const  ID_POHYBU                = 'ID pohybu';                                      // example 1158152824
    private const  DATUM                    = 'Datum';                                          // example '2012-07-27+02:00'
    private const  OBJEM                    = 'Objem';                                          // example 12225.25
    private const  MENA                     = 'Měna';                                           // ISO 4217
    private const  PROTIUCET                = 'Protiúčet';                                      // example '2212-2000000699'
    private const  NAZEV_PROTIUCTU          = 'Název protiúčtu';                                // example 'Béďa Trávníček'
    private const  KOD_BANKY                = 'Kód banky';                                      // example '2010'
    private const  NAZEV_BANKY              = 'Název banky';                                    // example 'Fio banka, a.s.'
    private const  KS                       = 'KS';                                             // example '0558'
    private const  VS                       = 'VS';                                             // example '1234567890'
    private const  SS                       = 'SS';                                             // example '1234567890'
    private const  UZIVATELSKA_IDENTIFIKACE = 'Uživatelská identifikace';                       // example 'Nákup: PENNY MARKET s.r.o., Jaromer, CZ'
    private const  ZPRAVA_PRO_PRIJEMCE      = 'Zpráva pro příjemce';
    private const  TYP                      = 'Typ';                           // example 'Platba převodem uvnitř banky'
    private const  PROVEDL                  = 'Provedl';                       // example 'Béďa Trávníček'
    private const  UPRESNENI                = 'Upřesnění';                     // example '15.90 EUR'
    private const  KOMENTAR                 = 'Komentář';                      // example 'Hračky pro děti v PENNY MARKET'
    private const  BIC                      = 'BIC';                           // ISO 9362
    private const  ID_POKUNU                = 'ID Pokynu';                     // example 2102382863
    private const  REFERENCE_PLATCE         = 'Reference plátce';              // example '2000000003'

    /**y
     * @link https://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
     *
     * Vrátí nové platby od posledního stažení. Pozor! Fio považuje za úspěšné odeslání dat, to že to padne u nás se Fio nedozví a v další odpovědi už ztracené platby nebudou.
     * @see \FioPlatba::zPoslednichDni je jistější
     *
     * @return FioPlatba[]
     */
    public static function posledniZmeny(): array
    {
        $token = FIO_TOKEN;
        $url   = "https://fioapi.fio.cz/v1/rest/last/$token/transactions.json";

        return self::zUrl($url);
    }

    /**
     * Vrátí platby za posledních X dní
     * Max 90 dní zpět (protože nemáme silnou autorizaci)
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
     * Max 90 dní zpět (protože nemáme silnou autorizaci)
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @return FioPlatba[]
     */
    public static function zRozmezi(
        DateTimeInterface $od,
        DateTimeInterface $do,
    ): array {
        $odString = $od->format('Y-m-d');
        $doString = $do->format('Y-m-d');
        $token    = FIO_TOKEN;
        $url      = "https://fioapi.fio.cz/v1/rest/periods/$token/$odString/$doString/transactions.json";

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
        if ($decoded === null) {
            throw new \RuntimeException(sprintf("Can not decode Fio data to JSON from URL %s and fetched data %s", $url, var_export($decoded, true)));
        }
        if (!$decoded) {
            return [];
        }
        $platby    = $decoded->accountStatement->transactionList->transaction ?? [];
        $fioPlatby = [];
        foreach ($platby as $platba) {
            $fioPlatby[] = self::zFioZaznamu($platba);
        }

        return $fioPlatby;
    }

    /** Cacheuje a zpracovává surovou rest odpověď (kvůli limitu 30s na straně FIO) */
    private static function cached(string $url): ?string
    {
        $adresar = LOGY . '/fio';
        $soubor  = $adresar . '/' . md5($url) . '.json';
        if (!is_dir($adresar) && (!mkdir($adresar, 0777, true) || !is_dir($adresar))) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $adresar));
        }
        if (!file_exists($soubor) || @filemtime($soubor) < (time() - 60)) {
            self::fetch($url, $soubor);
        }

        $obsah = file_get_contents($soubor);
        if ($obsah === false) {
            throw new \RuntimeException(sprintf('File "%s" was not read', $soubor));
        }
        if (!$obsah) {
            return null;
        }

        // konverze čísel na stringy kvůli IDs většími než PHP_MAX_INT
        return preg_replace('@"value":([\d.]+),@', '"value":"$1",', $obsah);
    }

    private static function fetch(
        string $url,
        string $soubor,
    ) {
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
    private static function zFioZaznamu(stdClass $platba): FioPlatba
    {
        $pole = [];
        foreach ($platba as $sloupec) {
            if ($sloupec) {
                $pole[$sloupec->name] = $sloupec->value;
            }
        }

        return new static($pole);
    }

    public static function zeZaznamu(array $pole): FioPlatba
    {
        return new static($pole);
    }

    public static function existujePodleFioId(string | int $idFioPlatby): bool
    {
        return (bool)dbOneCol(<<<SQL
SELECT 1 FROM platby WHERE fio_id = $1
SQL,
            [$idFioPlatby],
        );
    }

    /**
     * Platba se vytváří z asociativního pole s klíči odpovídajícími názvům atributů v fio api
     * viz https://www.fio.cz/docs/cz/API_Bankovnictvi.pdf
     * @param array<string, string|float|int> $data
     */
    private function __construct(private array $data)
    {
    }

    /** Vrací ID jako string (64bitů int) */
    public function id(): string
    {
        return $this->data[self::ID_POHYBU];
    }

    /** Vrací ID jako string (64bitů int) */
    public function datum(): \DateTimeImmutable
    {
        // '2021-06-10+0200' for example (despite documentation where timezone format mentioned is with colon as +02:00)
        return \DateTimeImmutable::createFromFormat('Y-m-dO', $this->data[self::DATUM])
            ->setTime(0, 0, 0);
    }

    /** Objem platby (kladný pro příchozí, záporný pro odchozí) */
    public function castka(): float
    {
        return (float)$this->data[self::OBJEM];
    }

    public function mena(): ?string
    {
        return $this->data[self::MENA] ?? null;
    }

    public function protiucet(): ?string
    {
        return $this->data[self::PROTIUCET] ?? null;
    }

    public function nazevProtiuctu(): ?string
    {
        return $this->data[self::NAZEV_PROTIUCTU] ?? null;
    }

    public function kodBanky(): ?string
    {
        return $this->data[self::KOD_BANKY] ?? null;
    }

    public function nazevBanky(): ?string
    {
        return $this->data[self::NAZEV_BANKY] ?? null;
    }

    public function konstantniSymbol(): ?string
    {
        return $this->data[self::KS] ?? null;
    }

    public function variabilniSymbol(): string
    {
        $vs = $this->data[self::VS] ?? '';

        return $vs
            ?: $this->nactiVsZTextu($this->zpravaProPrijemce());
    }

    public function specifickySymbol(): string
    {
        return $this->data[self::SS] ?? '';
    }

    public function uzivatelskaSpecifikace(): ?string
    {
        return $this->data[self::UZIVATELSKA_IDENTIFIKACE] ?? null;
    }

    public function zpravaProPrijemce(): string
    {
        return $this->data[self::ZPRAVA_PRO_PRIJEMCE] ?? '';
    }

    public function typ(): ?string
    {
        return $this->data[self::TYP] ?? null;
    }

    public function provedl(): ?string
    {
        return $this->data[self::PROVEDL] ?? null;
    }

    public function upresneni(): ?string
    {
        return $this->data[self::UPRESNENI] ?? null;
    }

    public function skrytaPoznamka(): string
    {
        return $this->data[self::KOMENTAR] ?? '';
    }

    public function bic(): ?string
    {
        return $this->data[self::BIC] ?? null;
    }

    public function idPokynu(): ?string
    {
        return $this->data[self::ID_POKUNU] ?? null;
    }

    public function referencePlatce(): ?string
    {
        return $this->data[self::REFERENCE_PLATCE] ?? null;
    }

    /**
     * @return array<string, string|float|int>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function jakoArray(): array
    {
        $array          = [];
        $soucasnaMetoda = explode('::', __METHOD__)[1];
        foreach ($this->seznamGetteru($soucasnaMetoda) as $getter) {
            $array[$getter] = $this->$getter();
        }

        return $array;
    }

    /** Variabilní symbol */
    public function idUcastnika(): ?int
    {
        if ($this->castka() > 0) {
            return trim($this->variabilniSymbol()) === ''
                ? null
                : (int)trim($this->variabilniSymbol());
        }
        if ($this->castka() === 0.0) {
            return null;
        }

        return $this->nactiIdUcastnikaZeZkrytePoznamky();
    }

    private function nactiVsZTextu(string $text): string
    {
        if (!preg_match('~(^|/)vs/(?<vs>\d+)~i', $text, $matches)) {
            return '';
        }

        return $matches['vs'];
    }

    private function nactiIdUcastnikaZeZkrytePoznamky(): ?int
    {
        $parovaciText = defined('TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY')
            ? trim(TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY)
            : '';
        if ($parovaciText === '') {
            return null;
        }
        $zkrytaPoznamka = trim($this->skrytaPoznamka());
        if ($zkrytaPoznamka === '') {
            return null;
        }
        $parovaciTextBezDiakritiky   = $this->lowercaseBezMezerABezDiakritiky($parovaciText);
        $zkrytaPoznamkaBezDiakritiky = $this->lowercaseBezMezerABezDiakritiky($zkrytaPoznamka);
        if (!preg_match(
            '~' . preg_quote($parovaciTextBezDiakritiky, '~') . '[^[:alnum:]]*(?<idUcastnika>\d+)~',
            $zkrytaPoznamkaBezDiakritiky,
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

        return array_filter($gettery, fn(
            $getter,
        ) => $getter !== $kromeMetody);
    }
}
