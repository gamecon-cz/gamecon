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
  public function createNewSpreadsheet(string $title): \Google_Service_Sheets_Spreadsheet {
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
        "Can not save reference to a Google spreadsheet locally: {$exception->getMessage()}",
        $exception->getCode(),
        $exception
      );
    }
  }

  /**
   * @param int $userId
   * @return array | \Google_Service_Sheets_Spreadsheet[]
   * @throws GoogleSheetsException
   */
  public function getUserSpreadsheets(int $userId): array {
    try {
      $spreadsheetIds = dbOneArray(<<<SQL
SELECT spreadsheet_id FROM google_spreadsheets
WHERE user_id = $1
SQL
        , [$userId]
      );
    } catch (\DbException $exception) {
      throw new GoogleSheetsException(
        "Can not get references of Google spreadsheets for user ${userId}: {$exception->getMessage()}",
        $exception->getCode(),
        $exception
      );
    }
    $spreadsheets = [];
    foreach ($spreadsheetIds as $spreadsheetId) {
      $spreadsheets[] = $this->getSpreadsheet($spreadsheetId);
    }
    return $spreadsheets;
  }


  /**
   * @param string $spreadsheetId
   * @return \Google_Service_Sheets_Spreadsheet
   * @throws Exceptions\GoogleApiException
   */
  public function getSpreadsheet(string $spreadsheetId): \Google_Service_Sheets_Spreadsheet {
    return $this->getNativeSheets()->spreadsheets->get($spreadsheetId);
  }
}
