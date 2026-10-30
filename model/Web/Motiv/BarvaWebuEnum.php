<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

/**
 * Colours of the modern look the graphic designer may change for the year. Each is a CSS variable from :root in
 * web/soubory/moderni/moderni.css, the default here must match it (BarvaWebuEnumTest checks it).
 * The variables left out are not a colour of the brand: --mw-inkoust-2 is unused, --mw-fokus is the outline
 * of the focused element, which keyboard users need to stay the same.
 */
enum BarvaWebuEnum: string
{
    case CERVENA = 'cervena';
    case CERVENA_TMAVA = 'cervena-tmava';
    case INKOUST = 'inkoust';
    case KREMOVA = 'kremova';
    case KREMOVA_TMAVA = 'kremova-tmava';
    case ORANZOVA = 'oranzova';
    case PLOCHA = 'plocha';
    case TEXT = 'text';
    case TEXT_TLUMENY = 'text-tlumeny';
    case OKRAJ = 'okraj';

    public function getCssPromenna(): string
    {
        return '--mw-' . $this->value;
    }

    public function getNazev(): string
    {
        return match ($this) {
            self::CERVENA       => 'Hlavní barva',
            self::CERVENA_TMAVA => 'Hlavní barva, tmavší',
            self::INKOUST       => 'Tmavá',
            self::KREMOVA       => 'Světlé pozadí',
            self::KREMOVA_TMAVA => 'Světlé pozadí, tmavší',
            self::ORANZOVA      => 'Doplňková',
            self::PLOCHA        => 'Pozadí stránky',
            self::TEXT          => 'Text',
            self::TEXT_TLUMENY  => 'Tlumený text',
            self::OKRAJ         => 'Okraje',
        };
    }

    public function getPouziti(): string
    {
        return match ($this) {
            self::CERVENA       => 'Tlačítka, zvýrazněná slova v nadpisech, naléhavé oznámení. Je na ní bílý text.',
            self::CERVENA_TMAVA => 'Tlačítka po najetí myší, přihlašování na aktivity. Je na ní bílý text.',
            self::INKOUST       => 'Hlavička s menu, úvod titulky, patička. Je na ní bílý text.',
            self::KREMOVA       => 'Pozadí sekcí a hlaviček linií, běžné oznámení.',
            self::KREMOVA_TMAVA => 'Místo obrázku, dokud se nenačte, rámečky v detailu aktivity.',
            self::ORANZOVA      => 'Záře v úvodu titulky, upozornění v liště oznámení.',
            self::PLOCHA        => 'Pozadí stránky a karet.',
            self::TEXT          => 'Běžný text na světlém pozadí.',
            self::TEXT_TLUMENY  => 'Popisky, perexy a méně důležitý text.',
            self::OKRAJ         => 'Rámečky karet a oddělovací čáry.',
        };
    }

    public function getVychoziHodnota(): string
    {
        return match ($this) {
            self::CERVENA       => '#e22630',
            self::CERVENA_TMAVA => '#c01c25',
            self::INKOUST       => '#10111a',
            self::KREMOVA       => '#f6f1ea',
            self::KREMOVA_TMAVA => '#ece3d6',
            self::ORANZOVA      => '#fbc584',
            self::PLOCHA        => '#ffffff',
            self::TEXT          => '#10111a',
            self::TEXT_TLUMENY  => '#5c5f6e',
            self::OKRAJ         => '#e8e2d9',
        };
    }
}
