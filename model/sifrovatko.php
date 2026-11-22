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

    /**
     * Null instead of a message when the text cannot be decrypted (another key, a damaged text): for a secret
     * the code uses, where the message would go out as the secret itself.
     */
    public static function desifrujNeboNull(string $zasifrovanyText): ?string
    {
        try {
            return Crypto::decrypt($zasifrovanyText, self::vytvorKlic());
        } catch (CryptoException) {
            return null;
        }
    }

    protected static function vytvorKlic()
    {
        return Key::loadFromAsciiSafeString(SECRET_CRYPTO_KEY);
    }

}
