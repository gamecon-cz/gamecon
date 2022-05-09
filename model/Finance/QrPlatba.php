<?php declare(strict_types=1);

namespace Gamecon\Finance;

use Endroid\QrCode\Writer\Result\ResultInterface;
use Rikudou\CzQrPayment\Options\QrPaymentOptions as CzQrPaymentOptions;
use Rikudou\CzQrPayment\QrPayment as CzQrPayment;
use Rikudou\Iban\Iban\CzechIbanAdapter;
use Rikudou\Iban\Iban\IBAN;
use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\Payment\QrPaymentOptions as SkQrPaymentOptions;
use rikudou\SkQrPayment\QrPayment as SkQrPayment;

class QrPlatba
{

    /**
     * SEPA platba
     * Jednotná oblast pro platby v eurech v rámci EU
     * https://cs.wikipedia.org/wiki/Jednotn%C3%A1_oblast_pro_platby_v_eurech
     *
     * @param string $iban IBAN
     * @param float $variabilniSymbol
     * @param float $castka Bude zaokrouhlena na dve desetinna mista!
     * @return static
     */
    public static function dejQrProMezinarodniPlatbu(
        string             $iban,
        int                $variabilniSymbol,
        float              $castka,
        string             $kodMeny,
        \DateTimeInterface $datumSplatnosti = null
    ): self {
        return new static(
            new IBAN($iban),
            $variabilniSymbol,
            $castka,
            $kodMeny,
            $datumSplatnosti
        );
    }

    /**
     * @param string $cisloUctu
     * @param float $variabilniSymbol
     * @param float $castka Bude zaokrouhlena na dve desetinna mista!
     * @return static
     */
    public static function dejQrProTuzemskouPlatbu(
        string             $cisloUctu,
        int                $variabilniSymbol,
        float              $castka,
        string             $kodMeny,
        \DateTimeInterface $datumSplatnosti = null
    ): self {
        [$cisloUctuBezBanky, $kodBanky] = explode('/', $cisloUctu);

        return new static(
            new CzechIbanAdapter($cisloUctuBezBanky, $kodBanky),
            $variabilniSymbol,
            $castka,
            $kodMeny,
            $datumSplatnosti
        );
    }

    /**
     * @var IbanInterface
     */
    private $iban;
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
     * @var CzQrPayment|SkQrPayment|null
     */
    private $qrPayment;

    /**
     * @param IbanInterface $iban
     * @param int $variabilniSymbol
     * @param float $castka bude zaokrouhlena na dvě desetinná místa
     * @param string $kodMeny ISO 4217
     * @param \DateTimeInterface|null $datumSplatnosti
     */
    private function __construct(
        IbanInterface      $iban,
        int                $variabilniSymbol,
        float              $castka,
        string             $kodMeny,
        \DateTimeInterface $datumSplatnosti = null
    ) {
        $this->iban = $iban;
        $this->variabilniSymbol = $variabilniSymbol;
        $this->castka = round($castka, 2);
        $this->kodMeny = $kodMeny;
        $this->datumSplatnosti = $datumSplatnosti ?? new \DateTimeImmutable("+14 days");
    }

    /**
     * @return ResultInterface A ted uz na tom jenom zavolej getDataUri() a mas base64 obrazek
     */
    public function dejQrObrazek(): ResultInterface {
        /** @var ResultInterface $rawObject */
        $rawObject = $this->getQrPayment()->getQrCode()->getRawObject();

        return $rawObject;
    }

    /**
     * @return CzQrPayment|SkQrPayment
     */
    private function getQrPayment() {
        if (!$this->qrPayment) {
            if ($this->kodMeny === 'CZK') {
                $qrPayment = new CzQrPayment($this->iban);
                $qrPayment->setOptions([
                    CzQrPaymentOptions::VARIABLE_SYMBOL => $this->variabilniSymbol,
                    CzQrPaymentOptions::AMOUNT => $this->castka,
                    CzQrPaymentOptions::CURRENCY => $this->kodMeny,
                    CzQrPaymentOptions::DUE_DATE => $this->datumSplatnosti,
                ]);
            } else {
                $qrPayment = new SkQrPayment($this->iban);
                $qrPayment->setOptions([
                    SkQrPaymentOptions::VARIABLE_SYMBOL => $this->variabilniSymbol,
                    SkQrPaymentOptions::AMOUNT => $this->castka,
                    SkQrPaymentOptions::CURRENCY => $this->kodMeny,
                    SkQrPaymentOptions::DUE_DATE => $this->datumSplatnosti,
                ]);
            }
            $this->qrPayment = $qrPayment;
        }
        return $this->qrPayment;
    }

}
