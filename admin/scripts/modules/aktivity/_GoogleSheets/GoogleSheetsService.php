<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleSheetsException;

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

  public function saveSpreadsheetReference(\Google_Service_Sheets_Spreadsheet $spreadsheet, int $userId) {
    try {
      dbQuery(<<<SQL
INSERT INTO google_spreadsheets(spreadsheet_id, title, user_id)
VALUES ($1, $2, $3)
SQL
        , [$spreadsheet->getSpreadsheetId(), $spreadsheet->getProperties()->getTitle(), $userId]
      );
    } catch (\DbException $exception) {
      throw new GoogleSheetsException(
        "Can not save reference to a Google spreadsheet localy: {$exception->getMessage()}",
        $exception->getCode(),
        $exception
      );
    }
  }
}
