<?php
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Logo\Logo;

function generateQrCode($url, $logoPngPath) {
    // Create a new QR code
    $qrCode = new QrCode($url);
    $qrCode->setSize(300);
    $qrCode->setEncoding(new Encoding('UTF-8'));
    $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
    $qrCode->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());
    
    // Set up the writer for generating the QR code
    $writer = new PngWriter();

    // Check if the PNG logo file exists
    $logo = null;
    if ($logoPngPath && file_exists($logoPngPath)) {
        // Add the PNG logo to the QR code
        $logo = new Logo($logoPngPath, 100, null, false);
    } else {
        // If file does not exist, log a message (optional)
        error_log("Logo file does not exist at path: " . $logoPngPath);
    }

    // Generate the QR code image with the optional logo
    $result = $writer->write($qrCode, $logo);

    // Capture the image data as a PNG string
    $imageData = $result->getString();

    // Encode the image data to Base64 for inline display
    $base64 = base64_encode($imageData);

    // Return the inline HTML img tag with the Base64 encoded image
    return "<img src='data:image/png;base64," . $base64 . "'>";
}

// Usage example
echo generateQrCode('https://www.instagram.com/gamecon_cz/', realpath('soubory' . DIRECTORY_SEPARATOR . 'blackarrow' . DIRECTORY_SEPARATOR .'paticka' . DIRECTORY_SEPARATOR .'instagram.png'));
echo generateQrCode('https://www.facebook.com/groups/gamecon/', realpath('soubory' . DIRECTORY_SEPARATOR . 'blackarrow' . DIRECTORY_SEPARATOR . 'paticka' . DIRECTORY_SEPARATOR . 'facebook.png'));
echo generateQrCode('https://discord.gg/wT6c6vcXez', realpath('soubory' . DIRECTORY_SEPARATOR . 'blackarrow' . DIRECTORY_SEPARATOR . 'paticka' . DIRECTORY_SEPARATOR . 'discord.png'));
echo generateQrCode('https://www.youtube.com/@GameconCz', realpath('soubory' . DIRECTORY_SEPARATOR . 'blackarrow' . DIRECTORY_SEPARATOR . 'paticka' . DIRECTORY_SEPARATOR . 'youtube.png'));
?>
