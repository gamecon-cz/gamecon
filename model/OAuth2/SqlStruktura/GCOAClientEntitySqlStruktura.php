<?php

namespace Gamecon\OAuth2\SqlStruktura;

class GCOAClientEntitySqlStruktura
{
    public const TABLE = "gcoa_clients";

    public const CLIENT_ID = "id_client";
    public const IDENTIFIER = "identifier";
    public const NAME = "name";
    public const CONFIDENTIAL = "confidential";
    public const REDIRECT_URI = "redirect_uri";
}
