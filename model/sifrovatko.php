<?php

use \Defuse\Crypto\Key,
    \Defuse\Crypto\Crypto,
    \Defuse\Crypto\Exception\CryptoException;

class Sifrovatko
{

    public static function zasifruj($text)
    {
        return Crypto::encrypt($text, self::vytvorKlic());
    }

    public static function desifruj($zasifrovanyText)
    {
        try {
            return Crypto::decrypt($zasifrovanyText, self::vytvorKlic());
        } catch (CryptoException $e) {

            // An attack! Either the wrong key was loaded, or the ciphertext has
            // changed since it was created -- either corrupted in the database or
            // intentionally modified by somebody trying to carry out an attack.
            return "Text se nepodařilo dešifrovat";

        }
    }

    protected static function vytvorKlic()
    {
        return Key::loadFromAsciiSafeString(SECRET_CRYPTO_KEY);
    }

}
