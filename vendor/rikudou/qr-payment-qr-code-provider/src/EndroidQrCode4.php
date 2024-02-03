<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use Endroid\QrCode\Writer\Result\ResultInterface;

final class EndroidQrCode4 implements QrCode
{
    /**
     * @var ResultInterface
     */
    private $qrCode;

    public function __construct(ResultInterface $qrCode)
    {
        $this->qrCode = $qrCode;
    }

    public function getRawString(): string
    {
        return $this->qrCode->getString();
    }

    public function writeToFile(string $path): void
    {
        $this->qrCode->saveToFile($path);
    }

    public function getDataUri(): string
    {
        return $this->qrCode->getDataUri();
    }

    public function getRawObject(): object
    {
        return $this->qrCode;
    }
}
