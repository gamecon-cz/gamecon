<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use BaconQrCode\Renderer\Image\EpsImageBackEnd;
use BaconQrCode\Renderer\Image\ImageBackEndInterface;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Writer;
use RuntimeException;

final class BaconQrCode implements QrCode
{
    /**
     * @var Writer
     */
    private $writer;

    /**
     * @var string
     */
    private $data;

    /**
     * @var ImageBackEndInterface
     */
    private $backend;

    public function __construct(Writer $writer, string $data, ImageBackEndInterface $backend)
    {
        $this->writer = $writer;
        $this->data = $data;
        $this->backend = $backend;
    }

    public function getRawString(): string
    {
        return $this->writer->writeString($this->data);
    }

    public function writeToFile(string $path): void
    {
        $this->writer->writeFile($this->data, $path);
    }

    public function getDataUri(): string
    {
        return 'data:' . $this->getMimeType() . ';base64,' . base64_encode($this->getRawString());
    }

    public function getRawObject(): object
    {
        return $this->writer;
    }

    private function getMimeType(): string
    {
        if ($this->backend instanceof ImagickImageBackEnd) {
            return 'image/png';
        }
        if ($this->backend instanceof EpsImageBackEnd) {
            return 'image/eps';
        }
        if ($this->backend instanceof SvgImageBackEnd) {
            return 'image/svg+xml';
        }

        throw new RuntimeException('Unknown mime type for image');
    }
}
