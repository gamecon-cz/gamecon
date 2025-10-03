<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum GenderEnum: string
{
    case FEMALE = 'f';
    case MALE = 'm';

    public function getLabel(): string
    {
        return match ($this) {
            self::FEMALE => 'žena',
            self::MALE   => 'muž',
        };
    }

    public function getEnding(string $endingForWomen = 'a'): string
    {
        return match ($this) {
            self::FEMALE => $endingForWomen,
            self::MALE   => '',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function listForSelect(): array
    {
        return [
            self::FEMALE->value => self::FEMALE->getLabel(),
            self::MALE->value   => self::MALE->getLabel(),
        ];
    }
}
