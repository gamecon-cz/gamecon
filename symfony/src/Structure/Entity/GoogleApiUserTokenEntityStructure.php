<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\GoogleApiUserToken
 */
class GoogleApiUserTokenEntityStructure
{
    /**
     * @see GoogleApiUserToken::$id
     */
    public const id = 'id';

    /**
     * @see GoogleApiUserToken::$googleClientId
     */
    public const googleClientId = 'googleClientId';

    /**
     * @see GoogleApiUserToken::$tokens
     */
    public const tokens = 'tokens';

    /**
     * @see GoogleApiUserToken::$user
     */
    public const user = 'user';
}
