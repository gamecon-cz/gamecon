<?php

declare(strict_types=1);

namespace Gamecon\Antibot;

use AltchaOrg\Altcha\V1\Altcha as AltchaLib;
use AltchaOrg\Altcha\V1\Challenge;
use AltchaOrg\Altcha\V1\ChallengeOptions;
use AltchaOrg\Altcha\V1\Hasher\Algorithm;

/**
 * Self-hostovaná ochrana veřejných formulářů proti botům (ALTCHA, proof-of-work).
 *
 * Žádná třetí strana ani externí služba — klient v prohlížeči vyřeší malou
 * výpočetní výzvu (najít číslo, jehož SHA-256 sedí na zadaný hash), čímž za
 * každý request zaplatí CPU cenou. To brzdí hromadné zneužití (enumerace
 * e-mailů, mail-bombing) bez per-IP tabulky a bez obtěžování běžného uživatele.
 *
 * Používáme klasický (V1) formát výzvy, který widget altcha-org/altcha řeší
 * nativně. Podpisový klíč se odvozuje z {@see APP_SECRET} (dorazí do všech
 * prostředí), takže není potřeba zavádět novou env proměnnou.
 */
class Altcha
{
    // Horní mez čísla, které klient hledá brute-force. ~50 tis. SHA-256 hashů
    // je ve WebCrypto zlomek sekundy, ale násobeno tisíci requesty bota to
    // dělá citelnou brzdu.
    private const MAX_NUMBER = 50000;
    private const PLATNOST_SEKUND = 600;

    private AltchaLib $altcha;

    public function __construct(string $appSecret)
    {
        $this->altcha = new AltchaLib(hash_hmac('sha256', 'altcha', $appSecret));
    }

    public static function zGlobals(): self
    {
        return new self(defined('APP_SECRET') ? APP_SECRET : (string) getenv('APP_SECRET'));
    }

    /**
     * JSON podepsané výzvy pro klientský widget (atribut challenge / endpoint).
     */
    public function challengeJson(): string
    {
        $challenge = $this->altcha->createChallenge(new ChallengeOptions(
            algorithm: Algorithm::SHA256,
            maxNumber: self::MAX_NUMBER,
            expires: (new \DateTimeImmutable())->modify('+' . self::PLATNOST_SEKUND . ' seconds'),
        ));

        return (string) json_encode($this->challengePole($challenge));
    }

    /**
     * Ověří řešení odeslané widgetem (skryté pole „altcha" ve formuláři).
     */
    public function overReseni(string $payloadBase64): bool
    {
        if ($payloadBase64 === '') {
            return false;
        }

        // verifySolution si base64 payload dekóduje sám.
        return $this->altcha->verifySolution($payloadBase64);
    }

    /**
     * @return array{algorithm: string, challenge: string, maxnumber: int, salt: string, signature: string}
     */
    private function challengePole(Challenge $challenge): array
    {
        return [
            'algorithm' => $challenge->algorithm,
            'challenge' => $challenge->challenge,
            'maxnumber' => $challenge->maxNumber,
            'salt'      => $challenge->salt,
            'signature' => $challenge->signature,
        ];
    }
}
