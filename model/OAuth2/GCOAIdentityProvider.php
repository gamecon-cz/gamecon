<?php
declare(strict_types=1);
namespace Gamecon\OAuth2;

use OpenIDConnectServer\Repositories\IdentityProviderInterface;

class GCOAIdentityProvider implements IdentityProviderInterface
{

    /**
     * @inheritDoc
     */
    public function getUserEntityByIdentifier($identifier) : ?\Uzivatel
    {
        return \Uzivatel::zId(intval($identifier));
    }
}
