<?php declare(strict_types=1);

namespace Granam\Tests\Tools\Exceptions;

use Granam\Tools\Exceptions\FileUploadException;
use PHPUnit\Framework\TestCase;

class FileUploadExceptionTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideUploadCodeAndDescription
     * @param int $uploadCode
     * @param string $contentRegexp
     */
    public function I_get_upload_codes_described(int $uploadCode, string $contentRegexp): void
    {
        $message = 'foo bar';
        foreach ([new \Exception(), null] as $previous) { // previous exception is optional
            try {
                throw new FileUploadException($message, $uploadCode, $previous); // can be thrown
            } catch (FileUploadException $fileUploadException) {
                self::assertSame(0, strpos($fileUploadException->getMessage(), $message));
                self::assertMatchesRegularExpression($contentRegexp, $fileUploadException->getMessage());
                self::assertSame($uploadCode, $fileUploadException->getCode());
                self::assertSame($previous, $fileUploadException->getPrevious());
            }
        }
    }

    /**
     * @codeCoverageIgnore
     * @return array
     */
    public function provideUploadCodeAndDescription(): array
    {
        return [
            [UPLOAD_ERR_OK, '~\s*OK\s*~'],
            [UPLOAD_ERR_INI_SIZE, '~exceed.+upload_max_filesize.+\d~'],
            [UPLOAD_ERR_FORM_SIZE, '~exceed.+MAX_FILE_SIZE~'],
            [UPLOAD_ERR_PARTIAL, '~partial~'],
            [UPLOAD_ERR_NO_FILE, '~no file~i'],
            [UPLOAD_ERR_NO_TMP_DIR, '~temp~'],
            [UPLOAD_ERR_NO_TMP_DIR, '~temp~'],
            [UPLOAD_ERR_CANT_WRITE, '~write~'],
            [UPLOAD_ERR_EXTENSION, '~extension~'],
            [PHP_INT_MAX, '~unknown~i'],
        ];
    }
}
