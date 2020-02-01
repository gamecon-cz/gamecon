<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\DirForGoogle;

class GoogleDriveService
{
  /**
   * @var \Google_Service_Drive
   */
  private $nativeDrive;
  /**
   * @var GoogleApiClient
   */
  private $googleApiClient;

  public function __construct(GoogleApiClient $googleApiClient) {

    $this->googleApiClient = $googleApiClient;
  }

  /**
   * @param DirForGoogle $dirForGoogle
   * @return \Google_Service_Drive_DriveFile[]]
   */
  public function getDirsByName(DirForGoogle $dirForGoogle): array {
    $parentIds = ['root'];
    $lastDirs = [];
    foreach ($dirForGoogle->getHierarchy() as $pathPart) {
      $lastDirs = $this->getDirsWithParents($pathPart, $parentIds);
      $parentIds = [];
      /** @var \Google_Service_Drive_DriveFile $dir */
      foreach ($lastDirs as $dir) {
        $parentIds[] = $dir->getId();
      }
    }
    return $lastDirs; // it should be just a single dir, but Google allows multiple dirs of same name, so there may be more of them
  }

  private function getDirsWithParents(string $name, array $parentIds): \Google_Service_Drive_FileList {
    $prarentsString = implode(',', $parentIds);
    return $this->getNativeDrive()->files->listFiles(
      ['q' => "mimeType='application/vnd.google-apps.folder' and '{$prarentsString}' in parents and name='{$name}}' and trashed=false"]
    );
  }

  public function dirExists(DirForGoogle $dir): bool {
    return count($this->getDirsByName($dir)) > 0;
  }

  public function createDir(DirForGoogle $dirForGoogle): \Google_Service_Drive_DriveFile {
    $parentId = 'root';
    $lastDir = null;
    foreach ($dirForGoogle as $pathPart) {
      $folder = new \Google_Service_Drive_DriveFile();
      $folder->setName($pathPart);
      $folder->setParents([$parentId]);
      $folder->setMimeType('application/vnd.google-apps.folder');
      $lastDir = $this->getNativeDrive()->files->create($folder);
    }
    if (!$lastDir) {
      throw new GoogleApiException("Can not create dir {$dirForGoogle}");
    }
    return $lastDir;
  }

  private function getNativeDrive(): \Google_Service_Drive {
    if ($this->nativeDrive === null) {
      $this->nativeDrive = new \Google_Service_Drive($this->googleApiClient->getAuthorizedNativeClient());
    }
    return $this->nativeDrive;
  }

  /**
   * @param \Google_Service_Sheets_Spreadsheet $spreadsheet
   * @param string $dirId
   */
  public function moveSpreadsheetToDir(
    \Google_Service_Sheets_Spreadsheet $spreadsheet,
    string $dirId
  ): void {
    $this->moveFileToDir($spreadsheet->getSpreadsheetId(), $dirId);
  }

  public function moveFileToDir(
    string $fileToMoveId,
    string $dirId
  ): void {
    $file = $this->getFolderbyId($fileToMoveId);
    $file->setParents($dirId);
    $this->getNativeDrive()->files->update($file->getId(), $file);
  }

  private function getFolderById(string $id): \Google_Service_Drive_DriveFile {
    return $this->getNativeDrive()->files->get(
      $id,
      ['fields' => 'parents']
    );
  }
}
