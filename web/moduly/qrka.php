<?php
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Logo\Logo;

function generateQrCode($url, $logoPngPath, $id) {
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


        list($originalWidth, $originalHeight) = getimagesize($logoPngPath);

        // Desired width or height
        $newWidth = 100; // Example width
        $newHeight = 100; // Example height

        // Calculate the aspect ratio
        $aspectRatio = $originalWidth / $originalHeight;

        // Adjust width or height to maintain aspect ratio
        if ($newWidth / $newHeight > $aspectRatio) {
            $newWidth = $newHeight * $aspectRatio;
        }  else {
        $newHeight = $newWidth / $aspectRatio;
        }
        // Add the PNG logo to the QR code
       // $logo = new Logo($logoPngPath, 100, 0, punchoutBackground: false);
        $logo = new Logo($logoPngPath, (int)$newWidth, (int)$newHeight, punchoutBackground: false);
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
    return "
        <div>
            <img id='qrCode-$id' src='data:image/png;base64,$base64' alt='QR Code'>
            <button onclick='copyQrCode(\"qrCode-$id\")'>Zkopírovat</button>
            <a href='data:image/png;base64,$base64' download='qr-code-$id.png'>
                <button>Stáhnout</button>
            </a>
        </div>
    ";
}

$qrCodes = [
    'instagram' => 'https://gamecon.cz/instagram',
    'facebook' => 'https://gamecon.cz/facebook',
    'discord' => 'https://gamecon.cz/discord',
    'youtube' => 'https://gamecon.cz/youtube',
];

$basePath = realpath('soubory' . DIRECTORY_SEPARATOR . 'blackarrow' . DIRECTORY_SEPARATOR . 'qrka');

foreach ($qrCodes as $name => $url) {
    $filePath = $basePath . DIRECTORY_SEPARATOR . $name . '.png';
    echo generateQrCode($url, $filePath, $name);
}

?>

<script src="/../soubory/blackarrow/qrka/script.js"></script>