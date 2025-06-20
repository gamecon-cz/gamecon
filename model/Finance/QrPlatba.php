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
     * @param float  $castkaCzk         Bude zaokrouhlena na dvě desetinná místa!
     * @param int    $variabilniSymbol
     * @param float  $kurzCzkNaEur      Výchozí kurz pro převod
     * @param string $iban              IBAN
     * @param string $bic               BIC/SWIFT
     * @param string $jmenoPrijemcePlatby
     * @param string $zamereniFirmy     Zaměření firmy
     * @return static
     */
    public static function dejQrProSepaPlatbu(
        float  $castkaCzk,
        int    $variabilniSymbol,
        float  $kurzCzkNaEur         = KURZ_EURO,
        string $iban                 = IBAN,
        string $bic                  = BIC_SWIFT,
        string $jmenoPrijemcePlatby  = NAZEV_SPOLECNOSTI_GAMECON,
        string $zamereniFirmy        = ZAMERENI_FIRMY
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
     * @param float              $castka              Bude zaokrouhlena na dvě desetinná místa!
     * @param int                $variabilniSymbol
     * @param string             $cisloUctu           Číslo účtu ve formátu '12345678/0100'
     * @param string             $jmenoPrijemcePlatby
     * @param string             $zamereniFirmy       Zaměření firmy
     * @param \DateTimeInterface|null $datumSplatnosti Při nezadaném datu dnes
     * @return static
     */
    public static function dejQrProTuzemskouPlatbu(
        float              $castka,
        int                $variabilniSymbol,
        string             $cisloUctu             = UCET_CZ,
        string             $jmenoPrijemcePlatby   = NAZEV_SPOLECNOSTI_GAMECON,
        string             $zamereniFirmy         = ZAMERENI_FIRMY,
        \DateTimeInterface $datumSplatnosti       = null,
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
     * @param IbanInterface            $iban
     * @param string                   $bic
     * @param int                      $variabilniSymbol
     * @param float                    $castka              Bude zaokrouhlena na dvě desetinná místa
     * @param string                   $kodMeny             ISO 4217
     * @param string                   $jmenoPrijemcePlatby
     * @param string                   $zamereniFirmy       Zaměření firmy
     * @param \DateTimeInterface|null  $datumSplatnosti     Pouze pro tuzemské platby, SEPA platby jsou vždy splatné do jednoho dne
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
        $this->iban                = $iban;
        $this->bic                 = $bic;
        $this->variabilniSymbol    = $variabilniSymbol;
        $this->castka              = Finance::zaokouhli($castka);
        $this->kodMeny             = $kodMeny;
        $this->jmenoPrijemcePlatby = $jmenoPrijemcePlatby;
        $this->zamereniFirmy       = $zamereniFirmy;
        $this->datumSplatnosti     = $datumSplatnosti ?? new \DateTimeImmutable(); // dnes
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
            CzQrPaymentOptions::AMOUNT          => $this->castka,
            CzQrPaymentOptions::CURRENCY        => $this->kodMeny,
            CzQrPaymentOptions::DUE_DATE        => $this->datumSplatnosti,
            CzQrPaymentOptions::PAYEE_NAME      => $this->jmenoPrijemcePlatby,
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
            ->setInformation($this->zamereniFirmy)
            ->setVersion(1);

        return Builder::create()
            ->errorCorrectionLevel(new ErrorCorrectionLevelMedium())
            ->data($sepaQrData->__toString())
            ->build();
    }
}
