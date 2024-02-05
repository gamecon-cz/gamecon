<?php

namespace Rikudou\QrPaymentQrCodeProvider;

use BaconQrCode\Renderer\Image\EpsImageBackEnd;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Imagick;
use XMLWriter;

final class BaconQrCodeProvider implements QrCodeProvider
{
    public function getQrCode(string $data): QrCode
    {
        if (class_exists(Imagick::class)) {
            $backend = new ImagickImageBackEnd();
        } elseif (class_exists(XMLWriter::class)) {
            $backend = new SvgImageBackEnd();
        } else {
            $backend = new EpsImageBackEnd();
        }
        $renderer = new ImageRenderer(
            new RendererStyle(400),
            $backend
        );

        $writer = new Writer($renderer);

        return new BaconQrCode($writer, $data, $backend);
    }

    public static function isInstalled(): bool
    {
        return class_exists(Writer::class);
    }
}
