<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleSheetsPreview;

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
  /**
   * @var GoogleDriveService
   */
  private $googleDriveService;

  public function __construct(GoogleApiClient $googleApiClient, GoogleDriveService $googleDriveService) {
    $this->googleApiClient = $googleApiClient;
    $this->googleDriveService = $googleDriveService;
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
    $wholeFirstSheetRange = $this->getWholeFirstSheetRange($spreadsheetId);
    $valueRange = $this->getNativeSheets()->spreadsheets_values->get($spreadsheetId, $wholeFirstSheetRange);
    // empty spreadsheet gives null, so we have to convert it to an array
    return (array)$valueRange->getValues();
  }

  /**
   * @param array $values
   * @param string $spreadsheetId
   * @throws Exceptions\GoogleApiException
   * @throws Exceptions\UnauthorizedGoogleApiClient
   */
  public function setValuesInSpreadsheet(array $values, string $spreadsheetId) {
    $sheetNameAsWholeSheetRange = $this->getWholeFirstSheetRange($spreadsheetId);

    $valueRange = new \Google_Service_Sheets_ValueRange();
    $valueRange->setValues($values);

    $this->getNativeSheets()->spreadsheets_values->update(
      $spreadsheetId,
      $sheetNameAsWholeSheetRange,
      $valueRange,
      ['valueInputOption' => 'USER_ENTERED']
    );
  }

  private function getWholeFirstSheetRange(string $spreadsheetId): string {
    $spreadsheet = $this->getSpreadsheet($spreadsheetId);
    $firstSheet = current($spreadsheet->getSheets());

    // first sheet title, like "Sheet 1" is the range to target whole first sheet
    return $firstSheet->getProperties()->getTitle();
  }

  /**
   * @param string $spreadsheetId
   * @throws Exceptions\GoogleApiException
   * @throws Exceptions\UnauthorizedGoogleApiClient
   */
  public function setFirstRowAsHeader(string $spreadsheetId) {
    $requests = [];
    $requests[] = $this->createFirstRowBoldRequest();
    $requests[] = $this->createFreezeFirstRowRequest();

    $update = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
    $update->setRequests($requests);
    $this->getNativeSheets()->spreadsheets->batchUpdate($spreadsheetId, $update);
  }

  private function createFirstRowBoldRequest(): \Google_Service_Sheets_Request {
    $repeatCellRequest = new \Google_Service_Sheets_RepeatCellRequest();
    $repeatCellRequest->setFields("userEnteredFormat(textFormat,horizontalAlignment)");
    $repeatCellRequestRange = new \Google_Service_Sheets_GridRange();
    $repeatCellRequestRange->setStartRowIndex(0);
    $repeatCellRequestRange->setEndRowIndex(1);
    $repeatCellRequest->setRange($repeatCellRequestRange);

    $cell = new \Google_Service_Sheets_CellData();
    $cellFormat = new \Google_Service_Sheets_CellFormat();
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

  private function createAutoResizeFirstRowRequest(string $spreadsheetId): \Google_Service_Sheets_Request {
    $autoResizeDimensionsRequest = new \Google_Service_Sheets_AutoResizeDimensionsRequest();
    $dimensionRange = new \Google_Service_Sheets_DimensionRange();
    $dimensionRange->setSheetId($spreadsheetId);
    $dimensionRange->setDimension('COLUMN');
    $autoResizeDimensionsRequest->setDimensions($dimensionRange);

    $request = new \Google_Service_Sheets_Request();
    $request->setAutoResizeDimensions($autoResizeDimensionsRequest);

    return $request;
  }

  public function getSheetWeblink(string $sheetId): string {
    return $this->googleDriveService->getFileWeblink($sheetId);
  }

  public function getAsXlsx(string $sheetId): string {
    return $this->googleDriveService->getAsXlsx($sheetId);
  }

  public function importXlsx(string $xlsxFile, string $name): \Google_Service_Drive_DriveFile {
    return $this->googleDriveService->importXlsx($xlsxFile, $name);
  }

  /**
   * @return array|GoogleSheetsPreview[]
   */
  public function getAllSpreadsheets(): array {
    $spreadsheetPreviews = [];
    /** @var \Google_Service_Drive_DriveFile $file */
    foreach ($this->googleDriveService->getAllSheetFiles() as $file) {
      $spreadsheetPreviews[] = new GoogleSheetsPreview(
        $file->getId(),
        $file->getName(),
        $file->getWebViewLink(),
        $file->getCreatedTime(),
        $file->getModifiedTime()
      );
    }
    return $spreadsheetPreviews;
  }
}
