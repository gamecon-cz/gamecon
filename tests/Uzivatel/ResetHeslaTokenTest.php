<?php

declare(strict_types=1);

namespace Tests\Uzivatel;

use Gamecon\Uzivatel\ResetHeslaToken;
use PHPUnit\Framework\TestCase;

class ResetHeslaTokenTest extends TestCase
{
    private const SECRET = 'test-secret-do-not-use-in-prod';
    private const HASH = '$2y$10$abcdefghijklmnopqrstuv'; // jako hodnota sloupce heslo_md5

    public function testPodepsanyTokenSeOveriZpetNaIdUzivatele(): void
    {
        $ted = 1_700_000_000;
        $token = ResetHeslaToken::podepis(42, self::HASH, self::SECRET, $ted);

        self::assertSame(42, ResetHeslaToken::over($token, self::HASH, self::SECRET, $ted));
    }

    public function testTokenMaTvarPayloadTeckaPodpisBezPaddingu(): void
    {
        $token = ResetHeslaToken::podepis(42, self::HASH, self::SECRET, 1_700_000_000);

        self::assertStringContainsString('.', $token);
        [$payloadPart, $podpisPart] = explode('.', $token, 2);
        self::assertNotSame('', $payloadPart);
        self::assertNotSame('', $podpisPart);
        // base64url: žádné +, /, ani = padding.
        self::assertDoesNotMatchRegularExpression('~[+/=]~', $token);
    }

    public function testExpirovanyTokenNeprojde(): void
    {
        $ted = 1_700_000_000;
        $token = ResetHeslaToken::podepis(42, self::HASH, self::SECRET, $ted);

        $poExpiraci = $ted + ResetHeslaToken::TTL_SEKUND + 1;
        self::assertNull(ResetHeslaToken::over($token, self::HASH, self::SECRET, $poExpiraci));
    }

    public function testTokenTesneNeexpirovanyProjde(): void
    {
        $ted = 1_700_000_000;
        $token = ResetHeslaToken::podepis(42, self::HASH, self::SECRET, $ted);

        $tesnePredExpiraci = $ted + ResetHeslaToken::TTL_SEKUND - 1;
        self::assertSame(42, ResetHeslaToken::over($token, self::HASH, self::SECRET, $tesnePredExpiraci));
    }

    public function testPozmenenyPodpisNeprojde(): void
    {
        $ted = 1_700_000_000;
        $token = ResetHeslaToken::podepis(42, self::HASH, self::SECRET, $ted);

        // Změníme poslední znak podpisu.
        $pozmeneny = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');
        self::assertNull(ResetHeslaToken::over($pozmeneny, self::HASH, self::SECRET, $ted));
    }

    public function testZmenaHeslaInvalidujeDrivVydanyToken(): void
    {
        $ted = 1_700_000_000;
        $token = ResetHeslaToken::podepis(42, self::HASH, self::SECRET, $ted);

        // Uživatel si mezitím heslo změnil → heslo_md5 je jiné → otisk nesedí.
        $jinyHash = '$2y$10$ZZZZZZZZZZZZZZZZZZZZZZ';
        self::assertNull(ResetHeslaToken::over($token, $jinyHash, self::SECRET, $ted));
    }

    public function testRuznySecretNeoveriToken(): void
    {
        $ted = 1_700_000_000;
        $token = ResetHeslaToken::podepis(42, self::HASH, 'secret-a', $ted);

        self::assertNull(ResetHeslaToken::over($token, self::HASH, 'secret-b', $ted));
    }

    public function testNesmyslnyTokenNeprojde(): void
    {
        self::assertNull(ResetHeslaToken::over('uplny.nesmysl', self::HASH, self::SECRET, 1_700_000_000));
        self::assertNull(ResetHeslaToken::over('beztecky', self::HASH, self::SECRET, 1_700_000_000));
        self::assertNull(ResetHeslaToken::over('', self::HASH, self::SECRET, 1_700_000_000));
    }
}
