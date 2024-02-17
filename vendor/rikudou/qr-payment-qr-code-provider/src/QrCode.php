<?php

namespace Rikudou\QrPaymentQrCodeProvider;

interface QrCode
{
    public function getRawString(): string;

    public function writeToFile(string $path): void;

    public function getDataUri(): string;

    public function getRawObject(): object;
}
