<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use Endroid\QrCode\QrCode as EndroidQrCode;

final class EndroidQrCode3Provider implements QrCodeProvider
{
    public function getQrCode(string $data): QrCode
    {
        return new EndroidQrCode3(new EndroidQrCode($data));
    }

    public static function isInstalled(): bool
    {
        return class_exists(EndroidQrCode::class)
            && method_exists(EndroidQrCode::class, 'writeString');
    }
}
