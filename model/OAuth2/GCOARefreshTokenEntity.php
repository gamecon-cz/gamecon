<?php

namespace Gamecon\OAuth2;

use Gamecon\OAuth2\SqlStruktura\GCOARefreshTokenEntitySqlStruktura as Sql;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

class GCOARefreshTokenEntity extends \DbObject implements RefreshTokenEntityInterface
{
    use RefreshTokenTrait;
    protected static $tabulka = Sql::TABLE;
    protected static $pk = Sql::TOKEN_ID;

    public function __construct()
    {
        parent::__construct([Sql::IDENTIFIER => ""]);
    }

    public function getIdentifier(): string
    {
        return $this->getSetR(Sql::IDENTIFIER);
    }

    public function setIdentifier(string $identifier): void
    {
        $this->getSetR(Sql::IDENTIFIER, $identifier);
    }

    public static function zIdentifier(string $identifier) : ?GCOARefreshTokenEntity
    {
        return self::zWhereRadek(Sql::IDENTIFIER . '=' . $identifier);
    }
    }
