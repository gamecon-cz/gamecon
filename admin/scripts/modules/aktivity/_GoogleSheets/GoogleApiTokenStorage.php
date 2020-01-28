<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\FailedSavingGoogleApiToken;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiTokenNotFound;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\InvalidGoogleApiTokenStructure;

class GoogleApiTokenStorage
{
  public function hasTokenFor(int $userId): bool {
    return (bool)dbOneCol(<<<'SQL'
SELECT 1 FROM google_api_user_tokens WHERE user_id=$0
SQL
      , [$userId]
    );
  }

  /**
   * @param int $userId
   * @return array
   * @throws GoogleApiTokenNotFound
   * @throws InvalidGoogleApiTokenStructure
   */
  public function getTokenFor(int $userId): array {
    $encodedToken = dbOneCol(<<<SQL
SELECT token FROM google_api_user_tokens WHERE user_id=$0
SQL
      , [0 => $userId]
    );
    if (!$encodedToken) {
      throw new GoogleApiTokenNotFound("No Google API token found for user $userId");
    }
    return $this->tokenFromString((string)$encodedToken);
  }

  /**
   * @param string $tokenAsString
   * @return array
   * @throws InvalidGoogleApiTokenStructure
   */
  private function tokenFromString(string $tokenAsString): array {
    $decoded = json_decode($tokenAsString, true);
    if ($decoded === null) {
      throw new InvalidGoogleApiTokenStructure(
        'Given token can not be decoded from string to JSON: ' . json_last_error_msg(),
        json_last_error()
      );
    }
    return $decoded;
  }

  /**
   * @param array $token
   * @param int $userId
   * @throws FailedSavingGoogleApiToken
   * @throws InvalidGoogleApiTokenStructure
   */
  public function setTokenFor(array $token, int $userId) {
    try {
      dbQuery(<<<SQL
INSERT INTO google_api_user_tokens (token, user_id)
VALUES ($0, $1)
ON DUPLICATE KEY UPDATE token = $0
SQL
        , [0 => $this->tokenToString($token), 1 => $userId]
      );
    } catch (\DbException $dbException) {
      throw new FailedSavingGoogleApiToken(
        sprintf('Can not save Google API token for user %s: %s', $userId, $dbException->getMessage()),
        $dbException->getCode(),
        $dbException
      );
    }
  }

  /**
   * @param array $token
   * @return string
   * @throws InvalidGoogleApiTokenStructure
   */
  private function tokenToString(array $token): string {
    $encoded = json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
      throw new InvalidGoogleApiTokenStructure(
        'Given token can not be encoded as JSON string: ' . json_last_error_msg(),
        json_last_error()
      );
    }
    return $encoded;
  }

  /**
   * @param int $userId
   * @return bool
   * @throws GoogleApiException
   */
  public function deleteTokenFor(int $userId): bool {
    try {
      dbQuery(<<<SQL
DELETE FROM google_api_user_tokens WHERE user_id=$0
SQL
        , [0 => $userId]
      );
    } catch (\DbException $exception) {
      throw new GoogleApiException(
        "Can not delete Google API token for user $userId: {$exception->getMessage()}",
        $exception->getCode(),
        $exception
      );
    }
    return true;
  }

}
