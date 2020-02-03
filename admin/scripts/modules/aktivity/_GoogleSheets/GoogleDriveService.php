<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleSheetsException;
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
    $parentsString = implode(',', $parentIds);
    return $this->getNativeDrive()->files->listFiles(
      ['q' => "mimeType='application/vnd.google-apps.folder' and '{$parentsString}' in parents and name='{$name}}' and trashed=false"]
    );
  }

  public function saveDirReference(\Google_Service_Drive_DriveFile $dir, int $userId) {
    try {
      dbQuery(<<<SQL
REPLACE INTO google_drive_dirs(dir_id, original_name, user_id)
VALUES ($1, $2, $3)
SQL
        , [$dir->getId(), $dir->getName(), $userId]
      );
    } catch (\DbException $exception) {
      throw new GoogleSheetsException(
        "Can not save reference to a Google dir locally: {$exception->getMessage()}",
        $exception->getCode(),
        $exception
      );
    }
  }

  public function getDirIdByName(string $name, int $userId) {
    try {
      dbQuery(<<<SQL
SELECT dir_id FROM google_drive_dirs
WHERE user_id = $1 AND original_name = $2
SQL
        , [$userId, $name]
      );
    } catch (\DbException $exception) {
      throw new GoogleSheetsException(
        "Can not save reference to a Google dir locally: {$exception->getMessage()}",
        $exception->getCode(),
        $exception
      );
    }
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

  /**
   * @param string $folderId
   * @return string
   * @throws GoogleSheetsException
   */
  public function getFolderPath(string $folderId): string {
    $file = $this->getFolderById($folderId);
    $dirsChainFromRoot = $this->getParentDirsChain($file);
    $dirsPath = '/' . implode('/', $dirsChainFromRoot);
    return $dirsPath . '/' . $file->getName();
  }

  /**
   * @param \Google_Service_Drive_DriveFile $file
   * @return array
   * @throws GoogleSheetsException
   */
  private function getParentDirsChain(\Google_Service_Drive_DriveFile $file): array {
    $dirsChain = [];
    $topParent = $file;
    /** @var \Google_Service_Drive_DriveFile $parent */
    foreach ($file->getParents() as $parent) {
      $topParent = $parent;
      if (!$parent->getTrashed() && !$parent->getExplicitlyTrashed() && $parent->getId() !== 'root') {
        $dirsChain[] = $parent->getName();
        $dirsChain = array_merge($dirsChain, $this->getParentDirsChain($parent->getId()));
        break;
      }
    }
    if ($topParent && $topParent->getId() !== 'root') {
      return array_flip($dirsChain); // they are ordered from file to root and we need them reversed, from root to file
    }
    throw new GoogleSheetsException(
      sprintf(
        "Can not find out full path for file '%s' of ID '%s'. Find out only '%s'.",
        $file->getName(),
        $file->getId(),
        implode(',', $dirsChain)
      )
    );
  }
}
