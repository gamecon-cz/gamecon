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
INSERT INTO google_spreadsheets(spreadsheet_id, original_title, user_id)
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

  /**
   * @param string $spreadsheetId
   * @return array
   * @throws Exceptions\GoogleApiException
   * @throws Exceptions\UnauthorizedGoogleApiClient
   */
  public function getSpreadsheetValues(string $spreadsheetId): array {
    $valueRange = $this->getNativeSheets()->spreadsheets_values->get($spreadsheetId, new \Google_Service_Sheets_ValueRange());
    return $valueRange->getValues();
  }

  /**
   * @param array $values
   * @param string $spreadsheetId
   * @param bool $firstRowIsHeader
   * @throws Exceptions\GoogleApiException
   * @throws Exceptions\UnauthorizedGoogleApiClient
   */
  public function setValuesInSpreadsheet(array $values, string $spreadsheetId, bool $firstRowIsHeader) {
    $spreadsheet = $this->getSpreadsheet($spreadsheetId);
    $firstSheet = current($spreadsheet->getSheets());

    $sheetNameAsWholeSheetRange = $firstSheet->getProperties()->getTitle();

    $valueRange = new \Google_Service_Sheets_ValueRange();
    $valueRange->setValues($values);

    $this->getNativeSheets()->spreadsheets_values->update(
      $spreadsheetId,
      $sheetNameAsWholeSheetRange,
      $valueRange,
      ['valueInputOption' => 'USER_ENTERED']
    );
  }

  /**
   * @param string $spreadsheetId
   * @throws Exceptions\GoogleApiException
   * @throws Exceptions\UnauthorizedGoogleApiClient
   */
  public function setFirstRowAsHeader(string $spreadsheetId) {
    $requests = [];
    $requests[] = $this->createFirstRowCenteredAndBoldRequest();
    $requests[] = $this->createFreezeFirstRowRequest();

    $update = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
    $update->setRequests($requests);
    $this->getNativeSheets()->spreadsheets->batchUpdate($spreadsheetId, $update);
  }

  private function createFirstRowCenteredAndBoldRequest(): \Google_Service_Sheets_Request {
    $repeatCellRequest = new \Google_Service_Sheets_RepeatCellRequest();
    $repeatCellRequest->setFields("userEnteredFormat(textFormat,horizontalAlignment)");
    $repeatCellRequestRange = new \Google_Service_Sheets_GridRange();
    $repeatCellRequestRange->setStartRowIndex(0);
    $repeatCellRequestRange->setEndRowIndex(1);
    $repeatCellRequest->setRange($repeatCellRequestRange);

    $cell = new \Google_Service_Sheets_CellData();
    $cellFormat = new \Google_Service_Sheets_CellFormat();
    $cellFormat->setHorizontalAlignment("CENTER");
    $textFormat = new \Google_Service_Sheets_TextFormat();
    $textFormat->setBold(true);
    $cellFormat->setTextFormat($textFormat);
    $cell->setUserEnteredFormat($cellFormat);
    $repeatCellRequest->setCell($cell);

    $request = new \Google_Service_Sheets_Request();
    $request->setRepeatCell($repeatCellRequest);

    return $request;
  }

  private function createFreezeFirstRowRequest(): \Google_Service_Sheets_Request {
    $updateSheetPropertiesRequest = new \Google_Service_Sheets_UpdateSheetPropertiesRequest();
    $sheetProperties = new \Google_Service_Sheets_SheetProperties();
    $gridProperties = new \Google_Service_Sheets_GridProperties();
    $gridProperties->setFrozenRowCount(1);
    $sheetProperties->setGridProperties($gridProperties);
    $updateSheetPropertiesRequest->setFields("gridProperties.frozenRowCount");
    $updateSheetPropertiesRequest->setProperties($sheetProperties);

    $request = new \Google_Service_Sheets_Request();
    $request->setUpdateSheetProperties($updateSheetPropertiesRequest);

    return $request;
  }
}
