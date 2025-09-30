<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum SymfonyPohlaviEnum: string
{
    case ZENA = 'f';
    case MUZ = 'm';

    public function getLabel(): string
    {
        return match ($this) {
            self::ZENA => 'žena',
            self::MUZ => 'muž',
        };
    }

    public function getKoncovka(string $koncovkaProZeny = 'a'): string
    {
        return match ($this) {
            self::ZENA => $koncovkaProZeny,
            self::MUZ => '',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function seznamProSelect(): array
    {
        return [
            self::ZENA->value => self::ZENA->getLabel(),
            self::MUZ->value => self::MUZ->getLabel(),
        ];
    }
}
