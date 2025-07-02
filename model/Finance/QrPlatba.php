<?php declare(strict_types=1);

namespace Gamecon\Finance;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Rikudou\CzQrPayment\Options\QrPaymentOptions as CzQrPaymentOptions;
use Rikudou\CzQrPayment\QrPayment as CzQrPayment;
use Rikudou\Iban\Iban\CzechIbanAdapter;
use Rikudou\Iban\Iban\IBAN;
use Rikudou\Iban\Iban\IbanInterface;
use SepaQr\Data;
use Gamecon\Uzivatel\Finance;

class QrPlatba
{
    /**
     * SEPA platba
     * Jednotná oblast pro platby v eurech v rámci EU
     * https://cs.wikipedia.org/wiki/Jednotn%C3%A1_oblast_pro_platby_v_eurech
     *
     * @param float $castkaCzk Bude zaokrouhlena na dvě desetinná místa!
     * @param int $variabilniSymbol
     * @param float $kurzCzkNaEur Výchozí kurz pro převod
     * @param string $iban IBAN
     * @param string $bic BIC/SWIFT
     * @param string $jmenoPrijemcePlatby
     * @param string $zamereniFirmy Zaměření firmy
     * @return static
     */
    public static function dejQrProSepaPlatbu(
        float  $castkaCzk,
        int    $variabilniSymbol,
        float  $kurzCzkNaEur = KURZ_EURO,
        string $iban = IBAN,
        string $bic = BIC_SWIFT,
        string $jmenoPrijemcePlatby = NAZEV_SPOLECNOSTI_GAMECON,
        string $zamereniFirmy = ZAMERENI_FIRMY
    ): self
    {
        return new static(
            new IBAN($iban),
            $bic,
            $variabilniSymbol,
            $castkaCzk / $kurzCzkNaEur,
            'EUR',
            $jmenoPrijemcePlatby,
            $zamereniFirmy
        );
    }

    /**
     * Tuzemská platba
     *
     * @param float $castka Bude zaokrouhlena na dvě desetinná místa!
     * @param int $variabilniSymbol
     * @param string $cisloUctu Číslo účtu ve formátu '12345678/0100'
     * @param string $jmenoPrijemcePlatby
     * @param string $zamereniFirmy Zaměření firmy
     * @param \DateTimeInterface|null $datumSplatnosti Při nezadaném datu dnes
     * @return static
     */
    public static function dejQrProTuzemskouPlatbu(
        float              $castka,
        int                $variabilniSymbol,
        string             $cisloUctu = UCET_CZ,
        string             $jmenoPrijemcePlatby = NAZEV_SPOLECNOSTI_GAMECON,
        string             $zamereniFirmy = ZAMERENI_FIRMY,
        \DateTimeInterface $datumSplatnosti = null,
    ): self
    {
        [$cisloUctuBezBanky, $kodBanky] = array_map('trim', explode('/', $cisloUctu));

        return new static(
            new CzechIbanAdapter($cisloUctuBezBanky, $kodBanky),
            '', // BIC není potřeba pro tuzemské platby
            $variabilniSymbol,
            $castka,
            'CZK',
            $jmenoPrijemcePlatby,
            $zamereniFirmy,
            $datumSplatnosti
        );
    }

    /**
     * @var IbanInterface
     */
    private $iban;
    /**
     * @var string
     */
    private $bic;
    /**
     * @var int
     */
    private $variabilniSymbol;
    /**
     * @var float
     */
    private $castka;
    /**
     * @var string
     */
    private $kodMeny;
    /**
     * @var \DateTimeInterface
     */
    private $datumSplatnosti;
    /**
     * @var ResultInterface|null
     */
    private $qrImage;
    /**
     * @var string
     */
    private $jmenoPrijemcePlatby;
    /**
     * @var string
     */
    private $zamereniFirmy;

    /**
     * @param IbanInterface $iban
     * @param string $bic
     * @param int $variabilniSymbol
     * @param float $castka Bude zaokrouhlena na dvě desetinná místa
     * @param string $kodMeny ISO 4217
     * @param string $jmenoPrijemcePlatby
     * @param string $zamereniFirmy Zaměření firmy
     * @param \DateTimeInterface|null $datumSplatnosti Pouze pro tuzemské platby, SEPA platby jsou vždy splatné do jednoho dne
     */
    private function __construct(
        IbanInterface      $iban,
        string             $bic,
        int                $variabilniSymbol,
        float              $castka,
        string             $kodMeny,
        string             $jmenoPrijemcePlatby,
        string             $zamereniFirmy = ZAMERENI_FIRMY,
        \DateTimeInterface $datumSplatnosti = null,
    )
    {
        $this->iban = $iban;
        $this->bic = $bic;
        $this->variabilniSymbol = $variabilniSymbol;
        $this->castka = Finance::zaokouhli($castka);
        $this->kodMeny = $kodMeny;
        $this->jmenoPrijemcePlatby = $jmenoPrijemcePlatby;
        $this->zamereniFirmy = $zamereniFirmy;
        $this->datumSplatnosti = $datumSplatnosti ?? new \DateTimeImmutable(); // dnes
    }

    /**
     * @return ResultInterface A ted uz na tom jenom zavolej getDataUri() a mas base64 obrazek
     */
    public function dejQrObrazek(): ResultInterface
    {
        if (!$this->qrImage) {
            $this->qrImage = $this->getQrPayment();
        }
        return $this->qrImage;
    }

    private function getQrPayment(): ResultInterface
    {
        return $this->kodMeny === 'CZK'
            ? $this->createCzechQrPayment()
            : $this->createSepaPayment();
    }

    private function createCzechQrPayment(): ResultInterface
    {
        $qrPayment = new CzQrPayment($this->iban);
        $qrPayment->setOptions([
            CzQrPaymentOptions::VARIABLE_SYMBOL => $this->variabilniSymbol,
            CzQrPaymentOptions::AMOUNT => $this->castka,
            CzQrPaymentOptions::CURRENCY => $this->kodMeny,
            CzQrPaymentOptions::DUE_DATE => $this->datumSplatnosti,
            CzQrPaymentOptions::PAYEE_NAME => $this->jmenoPrijemcePlatby,
        ]);
        /** @var ResultInterface $qrImage */
        $qrImage = $qrPayment->getQrCode()->getRawObject();
        return $qrImage;
    }

    /**
     * Formát je hezky popsán na
     * https://en.wikipedia.org/wiki/EPC_QR_code
     */
    private function createSepaPayment(): ResultInterface
    {
        $sepaQrData = Data::create()
            ->setName($this->jmenoPrijemcePlatby)
            ->setIban($this->iban->asString())
            ->setBic($this->bic)
            ->setAmount($this->castka > 0
                ? $this->castka
                : 0.1,/** nejmenší povolená částka, @see \SepaQr\Data::setAmount */
            )
            ->setCurrency($this->kodMeny)
            ->setRemittanceText('/VS/' . $this->variabilniSymbol)
            ->setInformation($this->zamereniFirmy);

        return Builder::create()
            ->errorCorrectionLevel(new ErrorCorrectionLevelMedium())
            ->data($sepaQrData->__toString())
            ->build();
    }

    /**
     * Slovenská platba (SK QR standard, komprimovaný)
     *
     * @param float  $castka              Částka v EUR
     * @param int    $variabilniSymbol    Variabilní symbol
     * @param int    $konstantniSymbol    Konstantní symbol
     * @param int    $specifickySymbol    Specifický symbol
     * @param string $poznamka            Poznámka
     * @param string $iban                IBAN příjemce
     * @param string $swift               SWIFT/BIC příjemce
     * @param string $rozmer              Rozměr QR kódu (např. '320x320')
     * @return string                      Binární PNG data QR kódu
     */
    public static function dejQrProSkPlatbu(
        float  $castka,
        int    $variabilniSymbol,
        int    $konstantniSymbol,
        int    $specifickySymbol,
        string $poznamka,
        string $iban,
        string $swift,
        string $rozmer = '320x320'
    ): string {
        return self::createSkPayment(
            $castka,
            $variabilniSymbol,
            $konstantniSymbol,
            $specifickySymbol,
            $poznamka,
            $iban,
            $swift,
            $rozmer
        );
    }

    /**
     * Generuje binární data PNG QR kódu pro platbu (bez přímo vypisování).
     *
     * @param float $amount Částka (např. 123.45)
     * @param string $currency Měna (ISO kód, např. 'EUR')
     * @param string $date Datum ve formátu YYYYMMDD (např. '20170101')
     * @param int $variableSymbol Variabilní symbol
     * @param int $constantSymbol Konstantní symbol
     * @param int $specificSymbol Specifický symbol
     * @param string $note Poznámka
     * @param string $iban IBAN účtu (např. 'SK8011000000001234567890')
     * @param string $swift SWIFT/BIC (např. 'TATRSKBX')
     * @param string $size Rozměr QR (např. '200x200')
     * @param string $chartApiHost Host pro Chart API (default 'chart.googleapis.com')
     * @return string                  Binární data PNG QR kódu
     * @throws RuntimeException        Pokud selže komprese nebo připojení
     */
    function generatePaymentSkQr(
        float  $amount,
        string $currency,
        string $date,
        int    $variableSymbol,
        int    $constantSymbol,
        int    $specificSymbol,
        string $note,
        string $iban,
        string $swift,
        string $size = '320x320',
        string $chartApiHost = 'chart.googleapis.com'
    ): string
    {
        // 1) Sestavení dat oddělené tabulátory
        $fields = [
            true,
            $amount,
            $currency,
            $date,
            $variableSymbol,
            $constantSymbol,
            $specificSymbol,
            '',
            $note,
            '1',
            $iban,
            $swift,
            '0',
            '0'
        ];
        $dataPayload = implode("\t", $fields);

        // 2) CRC32b kontrolní součet
        $crcBin = strrev(hash('crc32b', "\t" . $dataPayload, true));
        $d = $crcBin . "\t" . $dataPayload;

        // 3) Komprese LZMA
        $proc = proc_open(
            '/usr/bin/xz --format=raw --lzma1=lc=3,lp=0,pb=2,dict=128KiB -c -',
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($proc)) {
            throw new RuntimeException('Nelze spustit xz');
        }
        fwrite($pipes[0], $d);
        fclose($pipes[0]);
        $compressed = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($proc);

        // 4) Base32-like kódování
        $hex = bin2hex("\x00\x00" . pack('v', strlen($d)) . $compressed);
        $binStr = '';
        for ($i = 0, $len = strlen($hex); $i < $len; $i++) {
            $binStr .= str_pad(base_convert($hex[$i], 16, 2), 4, '0', STR_PAD_LEFT);
        }
        $pad = (5 - strlen($binStr) % 5) % 5;
        $binStr .= str_repeat('0', $pad);
        $map = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
        $chunks = str_split($binStr, 5);
        $encoded = '';
        foreach ($chunks as $bits) {
            $encoded .= $map[bindec($bits)];
        }

        // 5) Sestavení HTTP GET
        $query = http_build_query([
            'chs' => $size,
            'cht' => 'qr',
            'chld' => 'L|0',
            'choe' => 'UTF-8',
            'chl' => $encoded,
        ]);
        $path = '/chart?' . $query;

        // 6) Odeslání požadavku
        $sock = @fsockopen($chartApiHost, 80, $errno, $errstr, 1);
        if (!$sock) {
            throw new RuntimeException("Nelze připojit k $chartApiHost: $errstr ($errno)");
        }
        $headers = "GET $path HTTP/1.0\r\n";
        $headers .= "Host: $chartApiHost\r\n";
        $headers .= "Connection: close\r\n\r\n";
        fwrite($sock, $headers);

        // Skip headers
        $response = '';
        while (strpos($response, "\r\n\r\n") === false && !feof($sock)) {
            $response .= fgets($sock, 128);
        }
        // Body
        $body = '';
        while (!feof($sock)) {
            $body .= fgets($sock, 4096);
        }
        fclose($sock);

        return $body;
    }
}
