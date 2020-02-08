<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\FailedSavingGoogleApiTokens;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiTokenNotFound;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleSheetsException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\InvalidGoogleApiTokensStructure;

class GoogleApiTokenStorage
{
  /**
   * @var string
   */
  private $googleClientId;

  public function __construct(string $googleClientId) {
    $this->googleClientId = $googleClientId;
  }

  public function hasTokensFor(int $userId): bool {
    return (bool)dbOneCol(<<<'SQL'
SELECT 1 FROM google_api_user_tokens
WHERE user_id = $1
AND google_client_id = $2
SQL
      , [$userId, $this->googleClientId]
    );
  }

  /**
   * @param int $userId
   * @return array
   * @throws GoogleApiTokenNotFound
   * @throws InvalidGoogleApiTokensStructure
   */
  public function getTokensFor(int $userId): array {
    $encodedTokens = dbOneCol(<<<SQL
SELECT tokens FROM google_api_user_tokens
WHERE user_id = $1
AND google_client_id = $2
SQL
      , [$userId, $this->googleClientId]
    );
    if (!$encodedTokens) {
      throw new GoogleApiTokenNotFound(
        "No Google API tokens found for user '$userId' and Google client ID '{$this->googleClientId}'"
      );
    }
    return $this->tokensFromString((string)$encodedTokens);
  }

  /**
   * @param string $tokensAsString
   * @return array
   * @throws InvalidGoogleApiTokensStructure
   */
  private function tokensFromString(string $tokensAsString): array {
    $decoded = json_decode($tokensAsString, true);
    if ($decoded === null) {
      throw new InvalidGoogleApiTokensStructure(
        'Given tokens can not be decoded from string to JSON: ' . json_last_error_msg(),
        json_last_error()
      );
    }
    return $decoded;
  }

  /**
   * @param array $tokens
   * @param int $userId
   * @throws FailedSavingGoogleApiTokens
   * @throws InvalidGoogleApiTokensStructure
   */
  public function setTokensFor(array $tokens, int $userId) {
    try {
      dbQuery(<<<SQL
INSERT INTO google_api_user_tokens (tokens, user_id, google_client_id)
VALUES ($1, $2, $3)
ON DUPLICATE KEY UPDATE tokens = $1
SQL
        , [$this->tokensToString($tokens), $userId, $this->googleClientId]
      );
    } catch (\DbException $dbException) {
      throw new FailedSavingGoogleApiTokens(
        sprintf('Can not save Google API tokens for user %s: %s', $userId, $dbException->getMessage()),
        $dbException->getCode(),
        $dbException
      );
    }
  }

  /**
   * @param array $tokens
   * @return string
   * @throws InvalidGoogleApiTokensStructure
   */
  private function tokensToString(array $tokens): string {
    $encoded = json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
      throw new InvalidGoogleApiTokensStructure(
        'Given tokens can not be encoded as JSON string: ' . json_last_error_msg(),
        json_last_error()
      );
    }
    return $encoded;
  }

  /**
   * @param int $userId
   * @return bool
   * @throws GoogleSheetsException
   */
  public function deleteTokensFor(int $userId): bool {
    try {
      dbQuery(<<<SQL
DELETE FROM google_api_user_tokens
WHERE user_id = $1
AND google_client_id = $2
SQL
        , [$userId, $this->googleClientId]
      );
    } catch (\DbException $exception) {
      throw new GoogleSheetsException(
        "Can not delete Google API tokens for user $userId: {$exception->getMessage()}",
        $exception->getCode(),
        $exception
      );
    }
    return true;
  }

}
