<?php declare(strict_types=1);

class GoogleApiTokenStorage
{
  /**
   * @var Storage
   */
  private $storage;

  public function __construct(Storage $storage) {
    $this->storage = $storage;
  }

  public function hasTokenFor(int $userId): bool {
    return $this->storage->has($userId);
  }

  /**
   * @param int $userId
   * @return array
   * @throws GoogleApiTokenNotFound
   */
  public function getTokenFor(int $userId): array {
    $token = $this->storage->get($userId);
    if (!$token) {
      throw new GoogleApiTokenNotFound();
    }
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
VALUES (:token, :userId)
ON DUPLICATE KEY UPDATE token = :token
SQL
        , ['token' => $this->tokenToString($token), $userId]
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
    try {
      return json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (\JsonException $jsonException) {
      throw new InvalidGoogleApiTokenStructure(
        'Given token can not be encoded as JSON string: ' . $jsonException->getMessage(),
        $jsonException->getCode(),
        $jsonException
      );
    }
  }
}
