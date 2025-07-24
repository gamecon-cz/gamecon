<?php

namespace Gamecon\OAuth2;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

class GCOAScopeEntity implements ScopeEntityInterface
{
    use ScopeTrait;

    protected string $identifier;
    private static ScopeEntityInterface $openIdScope;

    private function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public static function openIDScope(): ScopeEntityInterface
    {
        self::$openIdScope ??= new GCOAScopeEntity("openid");
        return self::$openIdScope;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
