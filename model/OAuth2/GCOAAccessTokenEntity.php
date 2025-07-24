<?php

namespace Gamecon\OAuth2;

use DateTimeImmutable;
use Gamecon\OAuth2\SqlStruktura\GCOAAccessTokenEntitySqlStruktura as Sql;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

class GCOAAccessTokenEntity extends \DbObject implements AccessTokenEntityInterface
{
    use AccessTokenTrait;
    protected static $tabulka = Sql::TABLE;
    protected static $pk = Sql::TOKEN_ID;
    private DateTimeImmutable $expiryDateTime;

    public function __construct(ClientEntityInterface $client, ?string $userIdentifier = null)
    {
        parent::__construct([Sql::CLIENT_ID => $client->id(), Sql::USER_ID => $userIdentifier]);
    }

    public static function zIdentifier(string $identifier): GCOAAccessTokenEntity
    {
        return self::zWhereRadek(Sql::IDENTIFIER . '=' . $identifier);
    }

    public function getClient(): ClientEntityInterface
    {
        return GCOAClientEntity::zId($this->getSetR(Sql::CLIENT_ID));
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        return $this->expiryDateTime;
    }

    public function getUserIdentifier(): string|null
    {
        return $this->getSetR(Sql::USER_ID);
    }

    public function getScopes(): array
    {
        return [GCOAScopeEntity::openIDScope()];
    }

    public function getIdentifier(): string
    {
        return $this->getSetR(Sql::IDENTIFIER);
    }

    public function setIdentifier(string $identifier): void
    {
        $this->getSetR(Sql::IDENTIFIER, $identifier);
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $this->expiryDateTime = $dateTime;
    }

    public function setUserIdentifier(string $identifier): void
    {
        $this->getSetR(Sql::USER_ID, $identifier);
    }

    public function setClient(ClientEntityInterface $client): void
    {
        $this->getSetR(Sql::CLIENT_ID, $client->id());
    }

    public function addScope(ScopeEntityInterface $scope): void
    {
        //All issued access tokens are assumed to have openid scope
    }
}
