<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;
use Gamecon\XTemplate\XTemplate;

/**
 * QR kód s odkazem na veřejnou stránku webu (blog, novinky, stránka) pro tisk na plakáty, letáky apod.
 */
final class QrOdkaz
{
    private const VELIKOST_PX = 1000;
    private const OKRAJ_PX = 40;

    public function __construct(
        private readonly string $url,
        private readonly string $nazevSouboru,
    ) {
    }

    public function url(): string
    {
        return $this->url;
    }

    public function pngDataUri(): string
    {
        return $this->vytvor(new PngWriter())->getDataUri();
    }

    public function svgDataUri(): string
    {
        return $this->vytvor(new SvgWriter())->getDataUri();
    }

    public function html(string $poznamka = ''): string
    {
        $t = new XTemplate(__DIR__ . '/qr-odkaz.xtpl');
        $t->assign([
            'url'          => htmlspecialchars($this->url),
            'png'          => $this->pngDataUri(),
            'svg'          => $this->svgDataUri(),
            'nazevSouboru' => htmlspecialchars($this->nazevSouboru),
        ]);
        if ($poznamka !== '') {
            $t->assign('poznamka', htmlspecialchars($poznamka));
            $t->parse('qrOdkaz.poznamka');
        }
        $t->parse('qrOdkaz');

        return $t->text('qrOdkaz');
    }

    private function vytvor(WriterInterface $writer): ResultInterface
    {
        return Builder::create()
            ->writer($writer)
            ->data($this->url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelMedium())
            ->size(self::VELIKOST_PX)
            ->margin(self::OKRAJ_PX)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->build();
    }
}
