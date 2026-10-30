<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * The looks of the public web. All run the same module code, a module with its own template in the modern look
 * (sablony/moderni/<modul>.xtpl) shows it, any other keeps its classic content inside the modern frame.
 *
 * The modern look has variants A–D to present the design directions side by side (web/moduly/vzhledy.php),
 * D is the first modern look. A variant has its own homepage (sablony/moderni/varianty/<a>/titulka.xtpl) and
 * styles (soubory/moderni/varianty/<a>.css), anything else is shared. Once a variant is chosen, the others go.
 */
enum VzhledWebuEnum: string
{
    case KLASICKY = 'klasicky';
    case MODERNI_A = 'moderni-a';
    case MODERNI_B = 'moderni-b';
    case MODERNI_C = 'moderni-c';
    // the value stays from the time it was the only modern look, so the cookies of those who chose it still work
    case MODERNI = 'moderni';

    /**
     * @return list<self> the variants of the modern look, A to D
     */
    public static function getModerni(): array
    {
        return [self::MODERNI_A, self::MODERNI_B, self::MODERNI_C, self::MODERNI];
    }

    public function isModerni(): bool
    {
        return $this !== self::KLASICKY;
    }

    public function getNazev(): string
    {
        return match ($this) {
            self::KLASICKY => 'klasický',
            default        => 'moderní ' . $this->getPismenoVarianty(),
        };
    }

    /**
     * The directory with the templates of the look, relative to web/sablony.
     */
    public function getAdresarSablon(): string
    {
        return match ($this) {
            self::KLASICKY => 'blackarrow',
            default        => 'moderni',
        };
    }

    /**
     * A–D for a variant of the modern look, null for the classic one.
     */
    public function getPismenoVarianty(): ?string
    {
        return match ($this) {
            self::KLASICKY  => null,
            self::MODERNI_A => 'A',
            self::MODERNI_B => 'B',
            self::MODERNI_C => 'C',
            self::MODERNI   => 'D',
        };
    }

    public function getNazevVarianty(): ?string
    {
        return match ($this) {
            self::KLASICKY  => null,
            self::MODERNI_A => 'Inkoust a štětec',
            self::MODERNI_B => 'Krabice od hry',
            self::MODERNI_C => 'Ruka karet',
            self::MODERNI   => 'Současný moderní vzhled',
        };
    }

    /**
     * What makes the variant different, for the overview of the variants.
     */
    public function getPopisVarianty(): ?string
    {
        return match ($this) {
            self::KLASICKY  => null,
            self::MODERNI_A => 'Kresby linií a tah štětcem z klasického webu. Kresby stojí přímo v nadpisu, linie jsou kresba a název bez krabiček.',
            self::MODERNI_B => 'Hlavička jako víko krabice s lištou z jejího boku (hráči, doba hraní, věk, místo), pak „Co je v krabici“ a „Příprava hry“ ve čtyřech krocích.',
            self::MODERNI_C => 'Linie jako hrací karty 63 × 88 s kresbou v rohu. V hlavičce se při načtení rozdá vějíř pěti karet.',
            self::MODERNI   => 'Tmavá hlavička s videem, čísla v kartách a linie jako karty s fotkou. Moderní vzhled, jak je na webu teď.',
        };
    }

    /**
     * The directory of the variant's own templates, relative to web/sablony, null for the classic look.
     * It does not have to exist, a variant without its own template uses the shared modern one.
     */
    public function getAdresarSablonVarianty(): ?string
    {
        $pismeno = $this->getPismenoVarianty();

        return $pismeno === null
            ? null
            : 'moderni/varianty/' . strtolower($pismeno);
    }

    /**
     * The variant's own stylesheet or script relative to web/, null for the classic look. It does not have to exist.
     *
     * @param 'css'|'js' $pripona
     */
    public function getSouborVarianty(string $pripona): ?string
    {
        $pismeno = $this->getPismenoVarianty();

        return $pismeno === null
            ? null
            : 'soubory/moderni/varianty/' . strtolower($pismeno) . '.' . $pripona;
    }
}
