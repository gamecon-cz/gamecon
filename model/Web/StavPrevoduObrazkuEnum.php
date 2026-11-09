<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Co se stalo s jedním obrázkem při převodu do AVIF, @see PrevodObrazkuNaAvif
 */
enum StavPrevoduObrazkuEnum: string
{
    case PREVEDENO = 'prevedeno';
    case UZ_PREVEDENO = 'uz-prevedeno';
    case ODKAZ_KE_STAZENI = 'odkaz-ke-stazeni';
    case ODKAZ_Z_EMAILU = 'odkaz-z-emailu';
    case MIMO_ROZSAH = 'mimo-rozsah';
    case NELZE_ZPRACOVAT = 'nelze-zpracovat';
    case MALA_USPORA = 'mala-uspora';
    case JINY_AVIF_UZ_EXISTUJE = 'jiny-avif-uz-existuje';
    case NEPODOBNY_VYSLEDEK = 'nepodobny-vysledek';
    case SELHALO = 'selhalo';
    case PUVODNI_SMAZAN = 'puvodni-smazan';
    case PUVODNI_JESTE_POUZIVANY = 'puvodni-jeste-pouzivany';
    case PUVODNI_BEZ_STEJNEHO_AVIF = 'puvodni-bez-stejneho-avif';

    public function getPopis(): string
    {
        return match ($this) {
            self::PREVEDENO                => 'převedeno do AVIF',
            self::UZ_PREVEDENO             => 'AVIF už vedle originálu je',
            self::ODKAZ_KE_STAZENI         => 'nechat: text na obrázek odkazuje jako na soubor ke stažení',
            self::ODKAZ_Z_EMAILU           => 'nechat: odkazuje na něj text e-mailu, e-mailové programy AVIF nečtou',
            self::MIMO_ROZSAH              => 'nechat: spravuje ho jiný modul, nebo ho stahují lidé',
            self::NELZE_ZPRACOVAT          => 'nechat: nejde zpracovat',
            self::MALA_USPORA              => 'nechat: AVIF by ušetřil málo',
            self::JINY_AVIF_UZ_EXISTUJE    => 'nechat: stejnojmenný AVIF už existuje a je to jiný obrázek',
            self::NEPODOBNY_VYSLEDEK       => 'nechat: AVIF se od originálu příliš liší',
            self::SELHALO                  => 'nechat: převod selhal',
            self::PUVODNI_SMAZAN           => 'originál smazán',
            self::PUVODNI_JESTE_POUZIVANY  => 'originál nechán: ještě se na něj odkazuje',
            self::PUVODNI_BEZ_STEJNEHO_AVIF => 'originál nechán: AVIF vedle něj není stejný obrázek',
        };
    }

    /**
     * Obrázek je (nebo by byl) po běhu v AVIF, ať ho vyrobil tenhle běh, nebo už existoval.
     */
    public function isPrevedeny(): bool
    {
        return $this === self::PREVEDENO || $this === self::UZ_PREVEDENO;
    }
}
