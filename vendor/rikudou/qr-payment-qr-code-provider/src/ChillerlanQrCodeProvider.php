<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use chillerlan\QRCode\QRCode as LibraryQrCode;

final class ChillerlanQrCodeProvider implements QrCodeProvider
{
    public function getQrCode(string $data): QrCode
    {
        return new ChillerlanQrCode($data);
    }

    public static function isInstalled(): bool
    {
        return class_exists(LibraryQrCode::class);
    }
}
