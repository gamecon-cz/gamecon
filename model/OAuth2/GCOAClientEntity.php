<?php
declare(strict_types=1);
namespace Gamecon\OAuth2;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Gamecon\OAuth2\SqlStruktura\GCOAClientEntitySqlStruktura as Sql;

class GCOAClientEntity extends \DbObject implements ClientEntityInterface
{
    protected static $tabulka = Sql::TABLE;
    protected static $pk = Sql::CLIENT_ID;

    /**
     * @inheritDoc
     */
    public function getIdentifier(): string
    {
        return $this->getSetR(Sql::IDENTIFIER);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->getSetR(Sql::NAME);
    }

    /**
     * @inheritDoc
     */
    public function getRedirectUri(): string
    {
        return $this->getSetR(Sql::REDIRECT_URI);
    }

    /**
     * @inheritDoc
     */
    public function isConfidential(): bool
    {
        return boolval($this->getSetR(Sql::CONFIDENTIAL));
    }

    public static function zIdentifier(string $identifier): ?GCOAClientEntity
    {
        return self::zWhereRadek(Sql::IDENTIFIER . '=' . $identifier);
    }
}
