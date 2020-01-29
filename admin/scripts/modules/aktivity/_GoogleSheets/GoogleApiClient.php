<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\GoogleSheets;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiClientInvalidAuthorization;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\UnauthorizedGoogleApiClient;

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

  function __construct(
    GoogleApiCredentials $googleApiCredentials,
    GoogleApiTokenStorage $googleApiTokenStorage,
    int $userId
  ) {
    $this->googleApiCredentials = $googleApiCredentials;
    $this->googleApiTokenStorage = $googleApiTokenStorage;
    $this->userId = $userId;
  }

  public function isAuthorized(): bool {
    $nativeClient = $this->getNativeClient();
    $this->refreshAccessTokenIfNeeded($nativeClient);
    return !$nativeClient->isAccessTokenExpired();
  }

  private function getNativeClient(): \Google_Client {
    if (!$this->nativeClient) {
      $this->nativeClient = $this->createNativeClient();
    }
    return $this->nativeClient;
  }

  private function createNativeClient(): \Google_Client {
    $nativeClient = new \Google_Client();
    $nativeClient->setApplicationName('GameCon - Největší festival nepočítačových her');
    $nativeClient->setScopes([\Google_Service_Sheets::DRIVE_FILE]);
    $nativeClient->setAuthConfig($this->googleApiCredentials->getValues());
    $nativeClient->setAccessType('offline');
    $nativeClient->setPrompt('select_account consent');

    $this->restoreAccessToken($nativeClient);

    return $nativeClient;
  }

  private function restoreAccessToken(\Google_Client $nativeClient) {
    // Load previously authorized tokens, if they exists.
    // Tokens stores user's access and refresh tokens, and are
    // created automatically when the authorization flow completes for the first
    // time.
    if ($this->googleApiTokenStorage->hasTokensFor($this->userId)) {
      $tokens = $this->googleApiTokenStorage->getTokensFor($this->userId);
      $nativeClient->setAccessToken($tokens);
    }
  }

  public function do() {
    if (!$this->isAuthorized()) {
      throw new UnauthorizedGoogleApiClient("User {$this->userId} is not yet authorized");
    }
    // TODO
  }

  private function refreshAccessTokenIfNeeded(\Google_Client $nativeClient) {
    // If there is no previous token or it's expired.
    if ($nativeClient->isAccessTokenExpired()) {
      // Refresh the token if possible, else fetch a new one.
      $refreshToken = $nativeClient->getRefreshToken();
      if ($refreshToken) {
        $nativeClient->fetchAccessTokenWithRefreshToken($refreshToken);
        $this->googleApiTokenStorage->setTokensFor($nativeClient->getAccessToken(), $this->userId);
      }
    }
  }

  public function getAuthorizationUrl(): string {
    return $this->nativeClient->createAuthUrl();
  }

  public function validateAuthorizationByCode(string $authCode) {
    // Exchange authorization code for an access token.
    $tokens = $this->nativeClient->fetchAccessTokenWithAuthCode($authCode);
    // Check to see if there was an error.
    if (array_key_exists('error', $tokens)) {
      throw new GoogleApiClientInvalidAuthorization("Invalid authorization by code '{$authCode}': " . implode(', ', $tokens));
    }
    $this->nativeClient->setAccessToken($tokens);
    $this->googleApiTokenStorage->setTokensFor($this->nativeClient->getAccessToken(), $this->userId);
  }
}