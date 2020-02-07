<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleSheetsException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleDirReference;

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
   * @param string $dirForGoogle
   * @return \Google_Service_Drive_DriveFile[]]
   */
  public function getDirsByName(string $dirForGoogle): array {
    $parentIds = ['root'];
    $lastDirs = [];
    foreach ($this->getDirHierarchy($dirForGoogle) as $pathPart) {
      $lastDirs = $this->getDirsWithParents($pathPart, $parentIds);
      $parentIds = [];
      /** @var \Google_Service_Drive_DriveFile $dir */
      foreach ($lastDirs as $dir) {
        $parentIds[] = $dir->getId();
      }
    }
    return $lastDirs; // it should be just a single dir, but Google allows multiple dirs of same name, so there may be more of them
  }

  private function getDirHierarchy(string $dir): array {
    return explode('/', trim($dir, '/'));
  }

  private function getDirsWithParents(string $name, array $parentIds): \Google_Service_Drive_FileList {
    $parentsString = implode(',', $parentIds);
    return $this->getNativeDrive()->files->listFiles(
      ['q' => "mimeType='application/vnd.google-apps.folder' and '{$parentsString}' in parents and name='{$name}}' and trashed=false"]
    );
  }

  public function saveDirReference(\Google_Service_Drive_DriveFile $dir, int $userId, string $tag) {
    try {
      dbQuery(<<<SQL
REPLACE INTO google_drive_dirs(dir_id, original_name, user_id, tag)
VALUES ($1, $2, $3, $4)
SQL
        , [$dir->getId(), $dir->getName(), $userId, $tag]
      );
    } catch (\DbException $exception) {
      throw new GoogleSheetsException(
        "Can not save reference to a Google dir locally: {$exception->getMessage()}",
        $exception->getCode(),
        $exception
      );
    }
  }

  /**
   * @param int $userId
   * @param string $tag
   * @return array|GoogleDirReference[]
   */
  public function getDirsReferencesByUserIdAndTag(int $userId, string $tag): array {
    $dirValues = dbFetchAll(<<<SQL
SELECT id, user_id, dir_id, original_name, tag FROM google_drive_dirs
WHERE user_id = $1 AND tag = $2
SQL
      , [$userId, $tag]
    );
    return array_map(static function (array $values) {
      return new GoogleDirReference(
        $values['id'],
        $values['user_id'],
        $values['dir_id'],
        $values['original_name'],
        $values['tag']
      );
    }, $dirValues);
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

  public function dirExists(string $dir): bool {
    return count($this->getDirsByName($dir)) > 0;
  }

  public function createDir(string $dirForGoogle): \Google_Service_Drive_DriveFile {
    $parentId = 'root';
    $lastDir = null;
    foreach ($this->getDirHierarchy($dirForGoogle) as $pathPart) {
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

  public function folderByIdExists(string $folderId): bool {
    return (bool)$this->getNativeDrive()->files->get($folderId);
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

  public function getFileWeblink(string $fileId): string {
    $file = $this->getNativeDrive()->files->get($fileId, ['fields' => 'webViewLink']);
    return $file->getWebViewLink();
  }

  public function getAsXlsx(string $fileId): string {
    /** @var \GuzzleHttp\Psr7\Response $response */
    $response = $this->getNativeDrive()->files->export($fileId, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $body = $response->getBody();
    $body->rewind();
    $content = '';
    while (!$body->eof()) {
      $content .= $body->read(1024);
    }
    return $content;
  }

  public function importXlsx(string $xlsxFile, string $name): \Google_Service_Drive_DriveFile {
    $uploadFile = new \Google_Service_Drive_DriveFile();
    $uploadFile->setMimeType('application/vnd.google-apps.spreadsheet');
    // $uploadFile->setParents($spreadSheetDirParentId);
    $uploadFile->setName($name);
    return $this->getNativeDrive()->files->create(
      $uploadFile,
      [
        'data' => file_get_contents($xlsxFile),
        'uploadType' => 'multipart',
        'fields' => 'webViewLink,id',
      ]
    );
  }

}
