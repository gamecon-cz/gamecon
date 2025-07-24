<?php

namespace Gamecon\OAuth2;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class GCOAClientRepository implements ClientRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        return GCOAClientEntity::zIdentifier($clientIdentifier);
    }

    /**
     * @inheritDoc
     */
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        return $this->getClientEntity($clientIdentifier) !== null;
    }
}
