<?php

/**
 * @var Uzivatel|void $u
 */

// Init our repositories
use Defuse\Crypto\Key;
use Gamecon\OAuth2\GCOAAccessTokenRepository;
use Gamecon\OAuth2\GCOAAuthCodeRepository;
use Gamecon\OAuth2\GCOAClientRepository;
use Gamecon\OAuth2\GCOARefreshTokenRepository;
use Gamecon\OAuth2\GCOAScopeRepository;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;

$clientRepository = new GCOAClientRepository(); // instance of ClientRepositoryInterface
$scopeRepository = new GCOAScopeRepository(); // instance of ScopeRepositoryInterface
$accessTokenRepository = new GCOAAccessTokenRepository(); // instance of AccessTokenRepositoryInterface
$authCodeRepository = new GCOAAuthCodeRepository(); // instance of AuthCodeRepositoryInterface
$refreshTokenRepository = new GCOARefreshTokenRepository(); // instance of RefreshTokenRepositoryInterface

$privateKey = 'file://' . PROJECT_ROOT_DIR . '/nastaveni/testing-private.key';

// Setup the authorization server
$server = new AuthorizationServer(
    $clientRepository,
    $accessTokenRepository,
    $scopeRepository,
    $privateKey,
    Key::loadFromAsciiSafeString(OPENID_SECURITY_KEY)
);

$grant = new AuthCodeGrant(
    $authCodeRepository,
    $refreshTokenRepository,
    new DateInterval('PT10M') // authorization codes will expire after 10 minutes
);

$grant->setRefreshTokenTTL(new DateInterval('P1M')); // refresh tokens will expire after 1 month

// Enable the authentication code grant on the server
$server->enableGrantType(
    $grant,
    new DateInterval('PT1H') // access tokens will expire after 1 hour
);

try {
    $response = new Response();
    $server->respondToAccessTokenRequest(ServerRequest::fromGlobals(), $response);
    foreach ($response->getHeaders() as $k => $values) {
        foreach ($values as $v) {
            header(sprintf('%s: %s', $k, $v), false);
        }
    }
    echo $response->getBody();
} catch (OAuthServerException $exception) {
    chyba($exception->getMessage());

} catch (\Exception $exception) {
    chyba($exception->getMessage());
}
