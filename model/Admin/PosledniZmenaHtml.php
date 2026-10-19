<?php

declare(strict_types=1);

namespace Gamecon\Admin;

use Gamecon\Cas\DateTimeCz;

/**
 * The last-change column of admin tables: how long ago it was and, on hover, who changed it and when exactly.
 * Shared by the Settings page and the e-mail texts so both look and read the same.
 */
class PosledniZmenaHtml
{
    public const NAZEV_SLOUPCE = 'Poslední změna';

    /**
     * The two HTML fragments of the cell, kept apart because the Settings page swaps each one in place by script.
     * Whoever is not known is the System user, as admins know it.
     *
     * @return array{relativne: string, napoveda: string}
     */
    public static function dej(\DateTimeInterface $kdy, ?\Uzivatel $kdo): array
    {
        $kdyCz = DateTimeCz::createFromInterface($kdy);
        $jmeno = ($kdo ?? \Uzivatel::zId(\Uzivatel::SYSTEM, true))?->jmenoNick() ?? 'System';

        return [
            'relativne' => htmlspecialchars($kdyCz->relativni(), ENT_QUOTES),
            'napoveda'  => '<strong>' . htmlspecialchars($jmeno, ENT_QUOTES) . '</strong><br>' . $kdyCz->formatCasStandard(),
        ];
    }

    /**
     * The whole content of a `<td class="hinted">` cell.
     */
    public static function bunka(\DateTimeInterface $kdy, ?\Uzivatel $kdo): string
    {
        ['relativne' => $relativne, 'napoveda' => $napoveda] = self::dej($kdy, $kdo);

        return $relativne . '<span class="hint">' . $napoveda . '</span>';
    }
}
