<?php

namespace Gamecon\OAuth2\SqlStruktura;

class GCOAAuthCodeEntitySqlStruktura
{
    public const TABLE = "gcoa_auth_codes";

    public const CODE_ID = "id_auth_code";
    public const REDIRECT_URI = "redirect_uri";
    public const IDENTIFIER = "identifier";
    public const CLIENT_ID = "client_id";
    public const USER_IDENTIFIER = "user_identifier";
}
