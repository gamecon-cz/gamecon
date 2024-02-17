<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use Endroid\QrCode\QrCode as EndroidQrCode;

final class EndroidQrCode3 implements QrCode
{
    /**
     * @var EndroidQrCode
     */
    private $qrCode;

    public function __construct(EndroidQrCode $qrCode)
    {
        $this->qrCode = $qrCode;
    }

    public function getRawString(): string
    {
        return $this->qrCode->writeString();
    }

    public function writeToFile(string $path): void
    {
        $this->qrCode->writeFile($path);
    }

    public function getDataUri(): string
    {
        return $this->qrCode->writeDataUri();
    }

    public function getRawObject(): object
    {
        return $this->qrCode;
    }
}
