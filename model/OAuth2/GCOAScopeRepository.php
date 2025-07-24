<?php

namespace Gamecon\OAuth2;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class GCOAScopeRepository implements ScopeRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        if ($identifier === "openid") {
            return GCOAScopeEntity::openIDScope();
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, ?string $userIdentifier = null, ?string $authCodeId = null): array
    {
        return $scopes;
    }
}
