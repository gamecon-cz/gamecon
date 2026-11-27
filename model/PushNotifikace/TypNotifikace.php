<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

/**
 * Druh notifikace a jeho výchozí doručení: jak dlouho má zprávu push služba držet pro nedostupné zařízení (TTL),
 * jak moc spěchá a kterou starší notifikaci na zařízení nahradí (tag).
 *
 * Dnes se posílá jen ručně a testovací sadou z adminu ({@see TestovaciNotifikace}). Druhy jsou tu proto, aby je
 * později mohly posílat události systému (připomínka před aktivitou, zrušení aktivity) a uživatel si je mohl
 * jednotlivě vypnout. Hodnota se zapisuje do `push_notifikace_odeslani.typ`.
 */
enum TypNotifikace: string
{
    case OBECNA = 'obecna';
    case PRIPOMINKA_AKTIVITY = 'pripominka_aktivity';
    case ZRUSENI_AKTIVITY = 'zruseni_aktivity';
    case ZMENA_AKTIVITY = 'zmena_aktivity';
    case OZNAMENI_PORADATELU = 'oznameni_poradatelu';
    case UVOLNENE_MISTO = 'uvolnene_misto';

    public function getNazev(): string
    {
        return match ($this) {
            self::OBECNA              => 'Obecná zpráva',
            self::PRIPOMINKA_AKTIVITY => 'Připomínka aktivity',
            self::ZRUSENI_AKTIVITY    => 'Zrušení aktivity',
            self::ZMENA_AKTIVITY      => 'Změna aktivity',
            self::OZNAMENI_PORADATELU => 'Oznámení pořadatelů',
            self::UVOLNENE_MISTO      => 'Uvolněné místo na sledované aktivitě',
        };
    }

    /**
     * Kolik sekund push služba zprávu drží, když je zařízení nedostupné. Připomínka „za 15 minut“ doručená
     * za hodinu už jen mate, proto po čtvrthodině propadne.
     */
    public function getTtl(): int
    {
        return match ($this) {
            self::PRIPOMINKA_AKTIVITY => 15 * 60,
            self::ZRUSENI_AKTIVITY,
            self::ZMENA_AKTIVITY,
            self::UVOLNENE_MISTO => 60 * 60,
            self::OBECNA,
            self::OZNAMENI_PORADATELU => 24 * 60 * 60,
        };
    }

    public function getUrgence(): Urgence
    {
        return match ($this) {
            self::PRIPOMINKA_AKTIVITY,
            self::ZRUSENI_AKTIVITY,
            self::ZMENA_AKTIVITY => Urgence::VYSOKA,
            default              => Urgence::NORMALNI,
        };
    }

    /**
     * Notifikace se stejným tagem se na zařízení nahrazují: změna času aktivity přepíše její připomínku, místo aby
     * vedle ní visela druhá. Až budou notifikace o skutečných aktivitách, patří do tagu i id aktivity.
     */
    public function getTag(): ?string
    {
        return match ($this) {
            self::PRIPOMINKA_AKTIVITY,
            self::ZRUSENI_AKTIVITY,
            self::ZMENA_AKTIVITY => 'aktivita',
            default              => null,
        };
    }
}
