<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

class ImagesImporter
{

  /**
   * @var string
   */
  private $baseUrl;
  /**
   * @var ImportValuesDescriber
   */
  private $importValuesDescriber;

  public function __construct(string $baseUrl, ImportValuesDescriber $importValuesDescriber) {
    $this->baseUrl = $baseUrl;
    $this->importValuesDescriber = $importValuesDescriber;
  }

  public function saveImages(array $potentialImageUrlsPerActivity): ImportStepResult {
    if (count($potentialImageUrlsPerActivity) === 0) {
      return ImportStepResult::success(null);
    }
    $errorLikeWarnings = [];
    $imageUrls = [];
    /** @var \Aktivita[] $imageUrlsToActivity */
    $imageUrlsToActivity = [];
    /** @var \Aktivita[] $activities */
    $activities = [];
    $activityIds = array_keys($potentialImageUrlsPerActivity);
    foreach (\Aktivita::zIds($activityIds) as $activity) {
      $activities[$activity->id()] = $activity;
    }
    foreach ($potentialImageUrlsPerActivity as $activityId => $potentialImageUrls) {
      $activity = $activities[$activityId];
      foreach ($potentialImageUrls as $potentialImageUrl) {
        // Image URL is same as current, therefore came from an export and there is no change from it
        if ($potentialImageUrl === $activity->urlObrazku($this->baseUrl)) {
          continue 2;
        }
        $imageUrls[] = $potentialImageUrl;
        $imageUrlsToActivity[$potentialImageUrl] = $activity;
      }
    }
    $imageUrls = array_unique($imageUrls);
    ['files' => $downloadedImages, 'errors' => $downloadingImagesErrors] = hromadneStazeni($imageUrls, 10);

    $successfulActivityIds = [];
    foreach ($downloadedImages as $imageUrl => $downloadedImage) {
      $activity = $imageUrlsToActivity[$imageUrl];
      $successfulActivityIds[] = $activity->id();
      try {
        $obrazek = \Obrazek::zSouboru($downloadedImage);
        $activity->obrazek($obrazek);
      } catch (\ObrazekException $obrazekException) {
        $errorLikeWarnings[] = sprintf(
          '%s: Nepodařilo se uložit obrázek %s k z důvodu: %s',
          $this->importValuesDescriber->describeActivity($activity),
          $imageUrl,
          $obrazekException->getMessage()
        );
        continue;
      }
    }
    $downloadingImagesErrorsPerActivity = [];
    foreach ($potentialImageUrlsPerActivity as $activityId => $potentialImageUrls) {
      if (in_array($activityId, $successfulActivityIds, true)) {
        foreach ($potentialImageUrls as $potentialImageUrl) {
          unset($downloadingImagesErrors[$potentialImageUrl]); // failures of other images are useless
        }
      } else {
        $downloadingImagesErrorsPerActivity[$activityId] = [];
        foreach ($potentialImageUrls as $potentialImageUrl) {
          $downloadingImagesErrorsPerActivity[$activityId][$potentialImageUrl] = $downloadingImagesErrors[$potentialImageUrl];
        }
      }
    }
    if (count($downloadingImagesErrorsPerActivity) > 0) {
      foreach ($downloadingImagesErrorsPerActivity as $activityId => $downloadingImagesErrorsOfActivity) {
        $errorLikeWarnings[$activityId] = sprintf(
          '%s: Nepodařilo se stáhnout %s: <ol>%s</ol>',
          $this->importValuesDescriber->describeActivityById($activityId),
          count($downloadingImagesErrorsOfActivity) > 1
            ? 'ani jeden z možných obrázků'
            : 'obrázek',
          implode(
            "\n",
            array_map(static function (string $downloadingImageError) {
              return "<li>$downloadingImageError</li>";
            }, $downloadingImagesErrorsOfActivity)
          )
        );
      }
    }
    return ImportStepResult::successWithErrorLikeWarnings(true, $errorLikeWarnings);
  }
}
