<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Jazyk\Jazyk;

/**
 * V jakém jazyce aktivita probíhá. Říká, jestli se jí může zúčastnit hráč, který neumí česky,
 * nezávisle na tom, v jakém jazyce je napsaný její popis.
 *
 * Hodnoty backingu se ukládají do `akce_seznam.jazyk` a posílají se do programu (Preact),
 * proto je neměň bez migrace a úpravy `ui/src/api/program`.
 */
enum JazykAktivity: string
{
    case CESKY = 'cs';

    case ANGLICKY = 'en';

    /** Vypravěč ji povede česky, nebo anglicky podle toho, kdo přijde. */
    case CESKY_I_ANGLICKY = 'cs-en';

    /** Jazyk nehraje roli, třeba deskovka bez textu nebo wargaming. */
    case NEZAVISLA = 'nezavisla';

    public static function vychozi(): self
    {
        return self::CESKY;
    }

    /**
     * Jazyk z formuláře editoru: neznámou nebo prázdnou hodnotu ukládáme jako výchozí jazyk.
     */
    public static function zFormulare(mixed $hodnota): self
    {
        return self::tryFrom((string)$hodnota) ?? self::vychozi();
    }

    /**
     * Název pro návštěvníka webu v jazyce stránky. {@see nazev()} zůstává česky, podle něj páruje import.
     */
    public function popisek(?Jazyk $jazyk = null): string
    {
        return match ($this) {
            self::CESKY            => \t('jazyk_aktivity.cesky', [], $jazyk),
            self::ANGLICKY         => \t('jazyk_aktivity.anglicky', [], $jazyk),
            self::CESKY_I_ANGLICKY => \t('jazyk_aktivity.cesky_i_anglicky', [], $jazyk),
            self::NEZAVISLA        => \t('jazyk_aktivity.nezavisla', [], $jazyk),
        };
    }

    public function nazev(): string
    {
        return match ($this) {
            self::CESKY            => 'česky',
            self::ANGLICKY         => 'anglicky',
            self::CESKY_I_ANGLICKY => 'česky i anglicky',
            self::NEZAVISLA        => 'jazykově nezávislá',
        };
    }

    /**
     * Pozná jazyk podle kódu (`en`), českého názvu (`anglicky`) i názvu bez diakritiky.
     * Používá import aktivit, kde se jazyk zapisuje ručně.
     */
    public static function zTextu(string $text): ?self
    {
        $normalizovany = mb_strtolower(trim(removeDiacritics($text)));
        $normalizovany = (string)preg_replace('~\s+~', ' ', $normalizovany);
        if ($normalizovany === '') {
            return null;
        }
        foreach (self::cases() as $jazyk) {
            if ($normalizovany === $jazyk->value
                || $normalizovany === mb_strtolower(removeDiacritics($jazyk->nazev()))
            ) {
                return $jazyk;
            }
        }

        return match ($normalizovany) {
            'cesky a anglicky', 'cs+en', 'cs/en', 'cz-en', 'cz+en', 'cz/en' => self::CESKY_I_ANGLICKY,
            'cz', 'cestina'                                                  => self::CESKY,
            'anglictina', 'english'                                          => self::ANGLICKY,
            'nezavisla', 'nezavisle', 'bez jazyka'                           => self::NEZAVISLA,
            default                                                          => null,
        };
    }

    /**
     * Názvy všech jazyků pro nápovědu v chybových hláškách.
     */
    public static function vsechnyNazvy(): string
    {
        return implode(', ', array_map(static fn (self $jazyk) => "'{$jazyk->nazev()}'", self::cases()));
    }
}
