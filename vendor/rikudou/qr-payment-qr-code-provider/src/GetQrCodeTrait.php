<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use Rikudou\QrPayment\QrPaymentInterface;
use Rikudou\QrPaymentQrCodeProvider\Exception\InvalidTraitTargetException;

trait GetQrCodeTrait
{
    /**
     * @var QrCodeProvider|null
     */
    private $provider = null;

    public function getQrCode(): QrCode
    {
        if (!$this instanceof QrPaymentInterface) {
            throw new InvalidTraitTargetException(
                'This trait must be used on an instance of ' . QrPaymentInterface::class
            );
        }
        if ($this->provider === null) {
            $locator = new QrCodeProviderLocator();
            $this->provider = $locator->getProvider();
        }

        return $this->provider->getQrCode($this->getQrString());
    }
}
