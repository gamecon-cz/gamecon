<?php

declare(strict_types=1);

namespace App\Structure;

/**
 * Structure for @see \App\Entity\ActivityType
 * SQL table `akce_typy`
 */
class ActivityTypeSqlStructure
{
    public const ID = 'id_typu';
    public const TYP1P = 'typ_1p';
    public const TYP1PMN = 'typ_1pmn';
    public const URL_TYPU_MN = 'url_typu_mn';
    public const STRANKA_O = 'stranka_o';
    public const PORADI = 'poradi';
    public const MAIL_NEUCAST = 'mail_neucast';
    public const POPIS_KRATKY = 'popis_kratky';
    public const AKTIVNI = 'aktivni';
    public const ZOBRAZIT_V_MENU = 'zobrazit_v_menu';
    public const KOD_TYPU = 'kod_typu';
}
