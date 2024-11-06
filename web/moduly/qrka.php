<?php
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Logo\Logo;

echo __DIR__;
require __DIR__ . '/../soubory/blackarrow/paticka';

function generateQrCode($url, $logoSvgPath = null) {
    // Create a new QR code
    $qrCode = new QrCode($url);
    $qrCode->setSize(300);
    $qrCode->setEncoding(new Encoding('UTF-8'));
    $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
    $qrCode->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());
    
    // Set up the writer for generating the QR code
    $writer = new PngWriter();

    // Check if the SVG file exists before attempting conversion
    $logo = null;
    if ($logoSvgPath && file_exists($logoSvgPath)) {
        // Convert SVG to PNG using Imagick
        $imagick = new Imagick();
        $imagick->setBackgroundColor(new ImagickPixel('transparent'));
        $imagick->readImage($logoSvgPath);
        $imagick->setImageFormat("png");
        $imagick->resizeImage(50, 50, Imagick::FILTER_LANCZOS, 1);
        
        // Save to a temporary PNG file
        $tempPng = 'temp_logo.png';
        $imagick->writeImage($tempPng);
        $imagick->clear();
        $imagick->destroy();

        // Add the PNG logo to the QR code
        $logo = new Logo($tempPng, 50, null, null);
    } else {
        // If file does not exist, log a message (optional)
        error_log("Logo file does not exist at path: " . $logoSvgPath);
        echo "Logo file does not exist at path: ";
    }

    // Generate the QR code image with the optional logo
    $result = $writer->write($qrCode, $logo);

    // Capture the image data as a PNG string
    $imageData = $result->getString();

    // Encode the image data to Base64 for inline display
    $base64 = base64_encode($imageData);

    // Remove the temporary logo file if it was created
    if (isset($tempPng) && file_exists($tempPng)) {
        unlink($tempPng);
    }

    // Return the inline HTML img tag with the Base64 encoded image
    return "<img src='data:image/png;base64," . $base64 . "'>";
}

// Usage example
echo generateQrCode('https://www.instagram.com/gamecon_cz/', __DIR__ . '..' . DIRECTORY_SEPARATOR . 'soubory'. DIRECTORY_SEPARATOR . 'blackarrow' . DIRECTORY_SEPARATOR .'paticka' . DIRECTORY_SEPARATOR .'instagram.svg');
echo generateQrCode('https://www.facebook.com/groups/gamecon/', '../soubory/blackarrow/paticka/facebook.svg');
echo generateQrCode('https://discord.gg/wT6c6vcXez', '..\soubory\blackarrow\paticka\discord.svg');
echo generateQrCode('https://www.youtube.com/@GameconCz', '..\soubory\blackarrow\paticka\youtube.svg');
echo DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'soubory'. DIRECTORY_SEPARATOR . 'blackarrow' . DIRECTORY_SEPARATOR .'paticka' . DIRECTORY_SEPARATOR .'instagram.svg';

