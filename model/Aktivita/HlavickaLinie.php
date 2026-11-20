<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;

/**
 * Hlavička stránky linie (`akce_typy_hlavicky`): kdo linii vede („šéf RPG“), jeho jméno a kontakt.
 * Upravuje se v adminu (Web → Hlavičky linií, `admin/scripts/modules/web/hlavicky-linii.php`).
 *
 * @see web/moduly/aktivity.php
 */
final readonly class HlavickaLinie
{
    public static function zTypu(int $idTypu): ?self
    {
        $radek = dbOneLine(<<<'SQL'
SELECT sekce, sekce_en, jmeno, email FROM akce_typy_hlavicky WHERE id_typu = $0
SQL,
            [0 => $idTypu],
        );

        return $radek === []
            ? null
            : new self(
                sekce: (string) $radek['sekce'],
                sekceEn: (string) $radek['sekce_en'],
                jmeno: (string) $radek['jmeno'],
                email: (string) $radek['email'],
            );
    }

    public function __construct(
        private string $sekce,
        private string $sekceEn,
        private string $jmeno,
        private string $email,
    ) {
    }

    /**
     * Kdo linii vede, v jazyce stránky (nebo v předaném); bez anglického textu (NULL, prázdný) česky.
     */
    public function getSekceVJazyce(?Jazyk $jazyk = null): string
    {
        if (($jazyk ?? AktualniJazyk::jazyk()) === Jazyk::ANGLICTINA) {
            $anglicky = trim($this->sekceEn);
            if ($anglicky !== '') {
                return $anglicky;
            }
        }

        return $this->sekce;
    }

    public function getJmeno(): string
    {
        return $this->jmeno;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
