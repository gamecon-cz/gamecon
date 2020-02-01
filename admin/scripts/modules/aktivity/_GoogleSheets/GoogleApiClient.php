<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiClientInvalidAuthorization;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\UnauthorizedGoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;

class GoogleApiClient
{
  /**
   * @var int
   */
  private $userId;
  /**
   * @var GoogleApiCredentials
   */
  private $googleApiCredentials;
  /**
   * @var GoogleApiTokenStorage
   */
  private $googleApiTokenStorage;
  /**
   * @var \Google_Client
   */
  private $nativeClient;

  public function __construct(
    GoogleApiCredentials $googleApiCredentials,
    GoogleApiTokenStorage $googleApiTokenStorage,
    int $userId
  ) {
    $this->googleApiCredentials = $googleApiCredentials;
    $this->googleApiTokenStorage = $googleApiTokenStorage;
    $this->userId = $userId;
  }

  /**
   * @return bool
   * @throws GoogleApiException
   */
  public function isAuthorized(): bool {
    $nativeClient = $this->getNativeClient();
    $this->refreshAccessTokenIfNeeded($nativeClient);
    return !$nativeClient->isAccessTokenExpired();
  }

  /**
   * @return \Google_Client
   * @throws GoogleApiException
   */
  private function getNativeClient(): \Google_Client {
    if (!$this->nativeClient) {
      $this->nativeClient = $this->createNativeClient();
    }
    return $this->nativeClient;
  }

  /**
   * @return \Google_Client
   * @throws GoogleApiException
   */
  private function createNativeClient(): \Google_Client {
    try {
      $nativeClient = new \Google_Client();
      $nativeClient->setApplicationName('GameCon - Největší festival nepočítačových her');
      $nativeClient->setScopes([\Google_Service_Sheets::DRIVE_FILE]);
      $nativeClient->setAuthConfig($this->googleApiCredentials->getValues());
      $nativeClient->setAccessType('offline');
      $nativeClient->setPrompt('select_account consent');

      $this->restoreAccessToken($nativeClient);
    } catch (\Google_Exception $google_Exception) {
      throw new GoogleApiException(
        "Can not create native Google client: {$google_Exception->getMessage()}",
        $google_Exception->getCode(),
        $google_Exception
      );
    }

    return $nativeClient;
  }

  /**
   * @param \Google_Client $nativeClient
   * @throws Exceptions\GoogleApiTokenNotFound
   * @throws Exceptions\InvalidGoogleApiTokensStructure
   */
  private function restoreAccessToken(\Google_Client $nativeClient): void {
    // Load previously authorized tokens, if they exists.
    // Tokens stores user's access and refresh tokens, and are
    // created automatically when the authorization flow completes for the first
    // time.
    if ($this->googleApiTokenStorage->hasTokensFor($this->userId)) {
      $tokens = $this->googleApiTokenStorage->getTokensFor($this->userId);
      $nativeClient->setAccessToken($tokens);
    }
  }

  /**
   * @param \Google_Client $nativeClient
   * @throws Exceptions\FailedSavingGoogleApiTokens
   * @throws Exceptions\InvalidGoogleApiTokensStructure
   */
  private function refreshAccessTokenIfNeeded(\Google_Client $nativeClient): void {
    // If there is no previous token or it's expired.
    if (!$nativeClient->isAccessTokenExpired()) {
      // Refresh the token if possible, else fetch a new one.
      $refreshToken = $nativeClient->getRefreshToken();
      if ($refreshToken) {
        $nativeClient->fetchAccessTokenWithRefreshToken($refreshToken);
        $this->googleApiTokenStorage->setTokensFor($nativeClient->getAccessToken(), $this->userId);
      }
    }
  }

  /**
   * @return string
   * @throws GoogleApiException
   */
  public function getAuthorizationUrl(): string {
    return $this->getNativeClient()->createAuthUrl();
  }

  /**
   * @param string $authCode
   * @throws Exceptions\FailedSavingGoogleApiTokens
   * @throws Exceptions\InvalidGoogleApiTokensStructure
   * @throws GoogleApiClientInvalidAuthorization
   * @throws GoogleApiException
   */
  public function authorizeByCode(string $authCode): void {
    // Exchange authorization code for an access token.
    $tokens = $this->getNativeClient()->fetchAccessTokenWithAuthCode($authCode);
    // Check to see if there was an error.
    if (array_key_exists('error', $tokens)) {
      throw new GoogleApiClientInvalidAuthorization("Invalid authorization by code '{$authCode}': " . implode(', ', $tokens));
    }
    $this->getNativeClient()->setAccessToken($tokens);
    $this->googleApiTokenStorage->setTokensFor($this->getNativeClient()->getAccessToken(), $this->userId);
  }

  /**
   * @return \Google_Client
   * @throws GoogleApiException
   * @throws UnauthorizedGoogleApiClient
   */
  public function getAuthorizedNativeClient(): \Google_Client {
    if (!$this->isAuthorized()) {
      throw new UnauthorizedGoogleApiClient("User {$this->userId} is not yet authorized");
    }
    return $this->getNativeClient();
  }
}
