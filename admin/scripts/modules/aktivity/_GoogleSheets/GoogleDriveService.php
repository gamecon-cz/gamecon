<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleSheetsException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleDirReference;

class GoogleDriveService
{
  private const SPREADSHEET_MIME_TYPE = 'application/vnd.google-apps.spreadsheet';
  private const OPENOFFICE_SHEET_MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
  private const DIR_MIME_TYPE = 'application/vnd.google-apps.folder';

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
   * @return array|\Google_Service_Drive_DriveFile[]
   */
  public function getDirsByName(string $dirForGoogle): array {
    $parentIds = ['root'];
    $lastDirs = [];
    foreach ($this->getDirHierarchy($dirForGoogle) as $pathPart) {
      $lastDirs = $this->getDirsWithParents($pathPart, $parentIds);
      if (!$lastDirs || $lastDirs->count() === 0) {
        return [];
      }
      $parentIds = [];
      /** @var \Google_Service_Drive_DriveFile $dir */
      foreach ($lastDirs as $dir) {
        $parentIds[] = $dir->getId();
      }
    }
    return $lastDirs->getFiles(); // it should be just a single dir, but Google allows multiple dirs of same name, so there may be more of them
  }

  private function getDirHierarchy(string $dir): array {
    return explode('/', trim($dir, '/'));
  }

  private function getDirsWithParents(string $name, array $parentIds): ?\Google_Service_Drive_FileList {
    $parentsString = implode(',', $parentIds);
    try {
      return $this->getNativeDrive()->files->listFiles(
        [
          'q' => sprintf(
            "mimeType='%s' and '%s' in parents and name='%s' and trashed=false",
            self::DIR_MIME_TYPE,
            $parentsString,
            $name
          ),
        ]
      );
    } catch (\Google_Service_Exception $exception) {
      if ($exception->getCode() === 404) {
        return null;
      }
      throw $exception;
    }
  }

  public function saveDirReferenceLocally(\Google_Service_Drive_DriveFile $dir, int $userId, string $tag) {
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
  public function getLocalDirsReferencesByUserIdAndTag(int $userId, string $tag): array {
    $dirValues = dbFetchAll(<<<SQL
SELECT id, user_id, dir_id, original_name, tag FROM google_drive_dirs
WHERE user_id = $1 AND tag = $2
SQL
      , [$userId, $tag]
    );
    return array_map(static function (array $values) {
      return new GoogleDirReference(
        (int)$values['id'],
        (int)$values['user_id'],
        $values['dir_id'],
        $values['original_name'],
        $values['tag']
      );
    }, $dirValues);
  }

  public function deleteLocalDirReferenceByDirId(string $dirId): void {
    dbQuery(<<<SQL
DELETE FROM google_drive_dirs
WHERE dir_id = $1
SQL
      , [$dirId]
    );
  }

  public function dirExists(string $dir): bool {
    return count($this->getDirsByName($dir)) > 0;
  }

  public function createDir(string $dirForGoogle): \Google_Service_Drive_DriveFile {
    /** @var null|string $parentId */
    $parentId = null;
    $lastDir = null;
    foreach ($this->getDirHierarchy($dirForGoogle) as $pathPart) {
      $folder = new \Google_Service_Drive_DriveFile();
      $folder->setName($pathPart);
      $folder->setMimeType(self::DIR_MIME_TYPE);
      $lastDir = $this->getNativeDrive()->files->create($folder);
      if ($parentId) {
        $this->moveFileToDir($lastDir->getId(), $parentId);
      }
      $parentId = $lastDir->getId();
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

  public function moveFileToDir(
    string $fileToMoveId,
    string $dirId
  ): void {
    $file = $this->getFileById($fileToMoveId);
    if (!$file) {
      throw new GoogleSheetsException("No file to move has been found by id $fileToMoveId");
    }
    $this->getNativeDrive()->files->update($fileToMoveId, new \Google_Service_Drive_DriveFile(), ['removeParents' => $file->getParents(), 'addParents' => $dirId]);
  }

  private function getFileById(string $id): \Google_Service_Drive_DriveFile {
    return $this->getNativeDrive()->files->get(
      $id,
      ['fields' => 'parents']
    );
  }

  public function dirByIdExists(string $folderId): bool {
    try {
      $file = $this->getNativeDrive()->files->get($folderId);
    } catch (\Google_Service_Exception $exception) {
      if ($exception->getCode() !== 404) {
        throw $exception;
      }
      $error = $exception->getErrors()[0] ?? null;
      if (!$error) {
        throw $exception;
      }
      if ($error['reason'] !== 'notFound' || $error['location'] !== 'fileId') {
        throw $exception;
      }
      return false; // simply not found
    }
    if ($file->getTrashed() || $file->getExplicitlyTrashed()) { // sadly this does not mean the user itself does not deleted the dir
      return false;
    }
    $list = $this->getNativeDrive()->files->listFiles(
      [
        'q' => sprintf("mimeType='%s' and name='%s' and trashed=false", self::DIR_MIME_TYPE, $file->getName()),
      ]
    );
    if ($list->count() === 0) {
      return false;
    }
    /** @var \Google_Service_Drive_DriveFile $sameNamedFile */
    foreach ($list->getFiles() as $sameNamedFile) {
      if ($sameNamedFile->getId() === $folderId) {
        return true;
      }
    }
    return false;
  }

  /**
   * @param string $fileId
   * @return string
   * @throws GoogleSheetsException
   */
  public function getFilePath(string $fileId): string {
    $file = $this->getFileById($fileId);
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
    $response = $this->getNativeDrive()->files->export($fileId, self::OPENOFFICE_SHEET_MIME_TYPE);
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
    $uploadFile->setMimeType(self::SPREADSHEET_MIME_TYPE);
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

  public function getAllSheetFiles(): \Google_Service_Drive_FileList {
    return $this->getNativeDrive()->files->listFiles(
      [
        'q' => sprintf("mimeType='%s' and trashed=false", self::SPREADSHEET_MIME_TYPE),
        'fields' => 'files(id,name,createdTime,modifiedTime,webViewLink)',
        'orderBy' => 'modifiedTime desc',
      ]
    );
  }

}
