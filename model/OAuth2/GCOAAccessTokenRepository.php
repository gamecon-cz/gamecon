<?php

namespace Gamecon\OAuth2;

use Gamecon\OAuth2\SqlStruktura\GCOAAccessTokenEntitySqlStruktura as Sql;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class GCOAAccessTokenRepository implements AccessTokenRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        return new GCOAAccessTokenEntity($clientEntity, $userIdentifier);
    }

    /**
     * @inheritDoc
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $accessTokenEntity->uloz();
    }

    public function revokeAccessToken(string $tokenId): void
    {
        dbDelete(Sql::TABLE, [Sql::IDENTIFIER => $tokenId]);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        return GCOAAccessTokenEntity::zIdentifier($tokenId) === null;
    }
}
