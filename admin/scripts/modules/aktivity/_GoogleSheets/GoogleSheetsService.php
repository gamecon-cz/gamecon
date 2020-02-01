<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

class GoogleSheetsService
{
  /**
   * @var GoogleApiClient
   */
  private $googleApiClient;
  /**
   * @var \Google_Service_Sheets
   */
  private $nativeSheets;

  public function __construct(GoogleApiClient $googleApiClient) {
    $this->googleApiClient = $googleApiClient;
  }

  /**
   * @return \Google_Service_Sheets
   * @throws Exceptions\GoogleApiException
   * @throws Exceptions\UnauthorizedGoogleApiClient
   */
  private function getNativeSheets(): \Google_Service_Sheets {
    if ($this->nativeSheets === null) {
      $this->nativeSheets = new \Google_Service_Sheets($this->googleApiClient->getAuthorizedNativeClient());
    }
    return $this->nativeSheets;
  }

  /**
   * @param string $title
   * @return \Google_Service_Sheets_Spreadsheet
   * @throws Exceptions\GoogleApiException
   * @throws Exceptions\UnauthorizedGoogleApiClient
   */
  public function createNewSheet(string $title): \Google_Service_Sheets_Spreadsheet {
    $spreadsheet = new \Google_Service_Sheets_Spreadsheet();

    $spreadsheetProperties = new \Google_Service_Sheets_SpreadsheetProperties();
    $spreadsheetProperties->setTitle($title);
    $spreadsheet->setProperties($spreadsheetProperties);

    return $this->getNativeSheets()->spreadsheets->create($spreadsheet);
  }
}
