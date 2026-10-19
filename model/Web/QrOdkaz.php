<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;
use Gamecon\XTemplate\XTemplate;

/**
 * QR kód s odkazem (blog, novinky, stránka, sociální sítě) pro tisk na plakáty, letáky apod.
 */
final class QrOdkaz
{
    private const VELIKOST_PX = 1000;
    private const OKRAJ_PX = 40;
    private const LOGO_MAX_PX = 300;

    /**
     * @param string|null $logoPng cesta k PNG logu, které se vloží doprostřed kódu
     */
    public function __construct(
        private readonly string $url,
        private readonly string $nazevSouboru,
        private readonly ?string $logoPng = null,
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
        $builder = Builder::create()
            ->writer($writer)
            ->data($this->url)
            ->encoding(new Encoding('UTF-8'))
            // logo zakryje střed kódu, chybějící moduly dopočítá vysoká úroveň korekce chyb
            ->errorCorrectionLevel($this->logoPng !== null ? new ErrorCorrectionLevelHigh() : new ErrorCorrectionLevelMedium())
            ->size(self::VELIKOST_PX)
            ->margin(self::OKRAJ_PX)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin());

        if ($this->logoPng !== null) {
            $builder->logoPath($this->logoPng);
            // logo se vejde do čtverce LOGO_MAX_PX, druhý rozměr dopočítá knihovna podle poměru stran
            [$sirka, $vyska] = getimagesize($this->logoPng);
            if ($sirka >= $vyska) {
                $builder->logoResizeToWidth(self::LOGO_MAX_PX);
            } else {
                $builder->logoResizeToHeight(self::LOGO_MAX_PX);
            }
        }

        return $builder->build();
    }
}
