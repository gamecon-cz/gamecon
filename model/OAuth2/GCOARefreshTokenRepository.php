<?php

namespace Gamecon\OAuth2;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Gamecon\OAuth2\SqlStruktura\GCOARefreshTokenEntitySqlStruktura as Sql;

class GCOARefreshTokenRepository implements RefreshTokenRepositoryInterface
{

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new GCOARefreshTokenEntity();
    }

    /**
     * @inheritDoc
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $refreshTokenEntity->uloz();
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        dbDelete(Sql::TABLE, [Sql::IDENTIFIER => $tokenId]);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        return GCOARefreshTokenEntity::zIdentifier($tokenId) === null;
    }
}
