<?php

namespace Gamecon\OAuth2;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Gamecon\OAuth2\SqlStruktura\GCOAAuthCodeEntitySqlStruktura as Sql;

class GCOAAuthCodeRepository implements AuthCodeRepositoryInterface
{

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return GCOAAuthCodeEntity::newAuthCode();
    }

    /**
     * @inheritDoc
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $authCodeEntity->uloz();
    }

    public function revokeAuthCode(string $codeId): void
    {
        dbDelete(Sql::TABLE, [Sql::IDENTIFIER => $codeId]);
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        return GCOAAuthCodeEntity::zIdentifier($codeId) === null;
    }
}
