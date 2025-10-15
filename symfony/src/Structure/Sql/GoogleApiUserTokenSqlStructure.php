<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\GoogleApiUserToken
 */
class GoogleApiUserTokenSqlStructure
{
    /**
     * @see GoogleApiUserToken
     */
    public const _table = 'google_api_user_tokens';

    /**
     * @see GoogleApiUserToken::$id
     */
    public const id = 'id';

    /**
     * @see GoogleApiUserToken::$googleClientId
     */
    public const google_client_id = 'google_client_id';

    /**
     * @see GoogleApiUserToken::$tokens
     */
    public const tokens = 'tokens';

    /**
     * @see GoogleApiUserToken::$user
     */
    public const user_id = 'user_id';
}
