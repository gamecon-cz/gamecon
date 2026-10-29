<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

/**
 * Colours of the modern look for the year. Holds only the colours that differ from the default, so a colour nobody
 * changed follows moderni.css even when its default changes there later.
 */
final readonly class BarvyWebu
{
    /**
     * @param array<string, string> $zmenene '#rrggbb' by the value of BarvaWebuEnum, never the default
     */
    private function __construct(
        private array $zmenene,
    ) {
    }

    public static function vychozi(): self
    {
        return new self([]);
    }

    /**
     * Unknown colours (left by a newer version) and values that are not a colour are skipped, as is the default.
     *
     * @param array<array-key, mixed> $hodnoty by the value of BarvaWebuEnum
     */
    public static function zPole(array $hodnoty): self
    {
        $zmenene = [];
        foreach (BarvaWebuEnum::cases() as $barva) {
            $hodnota = self::normalizujHodnotu((string) ($hodnoty[$barva->value] ?? ''));
            if ($hodnota !== null && $hodnota !== $barva->getVychoziHodnota()) {
                $zmenene[$barva->value] = $hodnota;
            }
        }

        return new self($zmenene);
    }

    public static function zJson(string $json): self
    {
        $hodnoty = json_decode($json, true);

        return self::zPole(is_array($hodnoty)
            ? $hodnoty
            : []);
    }

    /**
     * Accepts what a person types or pastes: with or without #, in any case, the short #abc form too.
     *
     * @return string|null '#rrggbb', null when it is not a colour
     */
    public static function normalizujHodnotu(string $hodnota): ?string
    {
        $hodnota = strtolower(ltrim(trim($hodnota), '#'));
        if (preg_match('~^[0-9a-f]{3}$~', $hodnota)) {
            $hodnota = $hodnota[0] . $hodnota[0] . $hodnota[1] . $hodnota[1] . $hodnota[2] . $hodnota[2];
        }

        return preg_match('~^[0-9a-f]{6}$~', $hodnota)
            ? '#' . $hodnota
            : null;
    }

    public function getHodnota(BarvaWebuEnum $barva): string
    {
        return $this->zmenene[$barva->value] ?? $barva->getVychoziHodnota();
    }

    public function jeZmenena(BarvaWebuEnum $barva): bool
    {
        return isset($this->zmenene[$barva->value]);
    }

    public function jsouVychozi(): bool
    {
        return $this->zmenene === [];
    }

    public function jeShodnaS(self $jine): bool
    {
        return $this->zmenene === $jine->zmenene;
    }

    public function toJson(): string
    {
        return json_encode((object) $this->zmenene, JSON_THROW_ON_ERROR);
    }

    /**
     * Overrides the variables of moderni.css, so it has to come after it. Empty when nothing is changed.
     * Values are only ever '#rrggbb', nothing else gets into the page.
     */
    public function getCss(): string
    {
        if ($this->zmenene === []) {
            return '';
        }
        $promenne = '';
        foreach (BarvaWebuEnum::cases() as $barva) {
            if ($this->jeZmenena($barva)) {
                $promenne .= $barva->getCssPromenna() . ':' . $this->getHodnota($barva) . ';';
            }
        }

        return ':root{' . $promenne . '}';
    }
}
