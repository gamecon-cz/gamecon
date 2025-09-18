<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;

final class EndroidQrCode4Provider implements QrCodeProvider
{
    public function getQrCode(string $data): QrCode
    {
        $code = new EndroidQrCode($data);
        $writer = new PngWriter();

        return new EndroidQrCode4($writer->write($code));
    }

    public static function isInstalled(): bool
    {
        return interface_exists(ResultInterface::class);
    }
}
