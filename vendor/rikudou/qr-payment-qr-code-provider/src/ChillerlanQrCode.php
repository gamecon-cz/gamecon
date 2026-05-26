<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use chillerlan\QRCode\QRCode as LibraryQrCode;

final class ChillerlanQrCode implements QrCode
{
    /**
     * @var string
     */
    private $data;

    /**
     * @var LibraryQrCode
     */
    private $qrCode;

    public function __construct(string $data)
    {
        $this->data = $data;
        $this->qrCode = new LibraryQrCode();
    }

    public function getRawString(): string
    {
        try {
            $tempFile = $this->createTemporaryFilename();
            $this->qrCode->render($this->data, $tempFile);

            return file_get_contents($tempFile);
        } finally {
            if (isset($tempFile) && is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function writeToFile(string $path): void
    {
        $this->qrCode->render($this->data, $path);
    }

    public function getDataUri(): string
    {
        return $this->qrCode->render($this->data);
    }

    public function getRawObject(): object
    {
        return $this->qrCode;
    }

    private function createTemporaryFilename(): string
    {
        do {
            $file = sys_get_temp_dir() . '/qr-code-provider.chillerlan.' . bin2hex(random_bytes(10));
        } while (is_file($file));

        return $file;
    }
}
