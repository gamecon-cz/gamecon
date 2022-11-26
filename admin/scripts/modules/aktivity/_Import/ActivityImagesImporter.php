<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Vyjimkovac\Logovac;

class ActivityImagesImporter
{

    /** @var ImportValuesDescriber */
    private $importValuesDescriber;
    /** @var Logovac */
    private $logovac;

    public function __construct(ImportValuesDescriber $importValuesDescriber, Logovac $logovac) {
        $this->importValuesDescriber = $importValuesDescriber;
        $this->logovac = $logovac;
    }

    public function addImage(array $potentialImageUrls, Aktivita $activity): ImportStepResult {
        $fetchImageResult = $this->fetchImage($potentialImageUrls, $activity);
        if ($fetchImageResult->isError()) {
            return ImportStepResult::successWithErrorLikeWarnings(null, [$fetchImageResult->getError()]);
        }
        $fetchedImage = $fetchImageResult->getSuccess();
        if (!$fetchedImage) {
            return ImportStepResult::success(null);
        }
        try {
            $activity->obrazek($fetchedImage);
            return ImportStepResult::success(true);
        } catch (\Throwable $throwable) {
            $this->logovac->zaloguj($throwable);
            return ImportStepResult::successWithErrorLikeWarnings(
                null,
                [
                    sprintf(
                        'Obrázek k aktivitě %s se nepodařilo uložit: %s.',
                        $this->importValuesDescriber->describeActivity($activity),
                        $throwable->getMessage()
                    ),
                ]
            );
        }
    }

    private function fetchImage(array $potentialImageUrls, Aktivita $aktivita): ImportStepResult {
        $potentialImageUrls = array_unique($potentialImageUrls);
        $newImages = array_filter($potentialImageUrls, static function (string $potentialImageUrl) use ($aktivita) {
            return $potentialImageUrl !== $aktivita->urlObrazku();
        });
        if (!$newImages) {
            return ImportStepResult::success(null);
        }
        ['files' => $downloadedImages, 'errors' => $imagesDownloadErrors] = hromadneStazeni($potentialImageUrls, 10);
        if ($downloadedImages) {
            $imagePath = reset($downloadedImages);
            try {
                return ImportStepResult::success(\Obrazek::zSouboru($imagePath));
            } catch (\ObrazekException $obrazekException) {
                return ImportStepResult::error($obrazekException->getMessage());
            }
        }
        if ($imagesDownloadErrors) {
            return ImportStepResult::error(
                sprintf(
                    "Neporadřilo se stáhnout obrázek k aktivitě %s. Detail: %s",
                    $this->importValuesDescriber->describeActivity($aktivita),
                    var_export($imagesDownloadErrors, true)
                )
            );
        }
        return ImportStepResult::error(
            sprintf(
                "Neporadřilo se stáhnout obrázek k aktivitě %s. Důvod neznámý.",
                $this->importValuesDescriber->describeActivity($aktivita),
            )
        );
    }
}
