<?php declare(strict_types=0);

namespace Granam\Tools\Exceptions;

class FileUploadException extends \RuntimeException implements Runtime
{
    public function __construct(string $message, int $fileErrorCode = UPLOAD_ERR_OK, \Exception $previousException = null)
    {
        $fileErrorMessage = $this->codeToMessage($fileErrorCode);
        parent::__construct($message . " ($fileErrorMessage)", $fileErrorCode, $previousException);
    }

    private function codeToMessage(int $fileErrorCode): string
    {
        switch ($fileErrorCode) {
            case UPLOAD_ERR_OK : // 0
                return 'Upload itself was OK';
            case UPLOAD_ERR_INI_SIZE : // 1
                return
                    'The uploaded file exceeds the INI directive upload_max_filesize: '
                    . \ini_get('upload_max_filesize');
            case UPLOAD_ERR_FORM_SIZE : // 2
                return
                    'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form: '
                    . ($_REQUEST['MAX_FILE_SIZE'] ?? '');
            case UPLOAD_ERR_PARTIAL : // 3
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE : // 4
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR : // 6
                return 'Missing a temporary directory';
            case UPLOAD_ERR_CANT_WRITE : // 7
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION : // 8
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}
