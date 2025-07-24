<?php

namespace Gamecon\OAuth2;

use Gamecon\OAuth2\SqlStruktura\GCOAAuthCodeEntitySqlStruktura as Sql;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

class GCOAAuthCodeEntity extends \DbObject implements AuthCodeEntityInterface
{
    use TokenEntityTrait;
    protected static $tabulka = Sql::TABLE;
    protected static $pk = Sql::CODE_ID;

    public static function newAuthCode(): AuthCodeEntityInterface
    {
        return new self([Sql::REDIRECT_URI => null, Sql::IDENTIFIER => null, Sql::CLIENT_ID => null, Sql::USER_IDENTIFIER => null, Sql::CODE_ID => null]);
    }

    public function getRedirectUri(): string|null
    {
        return $this->getSetR(Sql::REDIRECT_URI);
    }

    public function setRedirectUri(string $uri): void
    {
        $this->getSetR(Sql::REDIRECT_URI, $uri);
    }

    public function getIdentifier(): string
    {
        return $this->getSetR(Sql::IDENTIFIER);
    }

    public function setIdentifier(string $identifier): void
    {
        $this->getSetR(Sql::IDENTIFIER, $identifier);
    }

    public function getClient(): ClientEntityInterface
    {
        return GCOAClientEntity::zId($this->getSetR(Sql::CLIENT_ID));
    }

    public function setClient(ClientEntityInterface $client): void
    {
        $this->getSetR(Sql::CLIENT_ID, $client->id());
    }

    public function setUserIdentifier(string $identifier): void
    {
        $this->getSetR(Sql::USER_IDENTIFIER, $identifier);
    }

    public function getUserIdentifier(): string|null
    {
        return $this->getSetR(Sql::USER_IDENTIFIER);
    }

    public static function zIdentifier(string $identifier): ?GCOAAuthCodeEntity
    {
        return self::zWhereRadek(Sql::IDENTIFIER . '=' . $identifier);
    }
}
