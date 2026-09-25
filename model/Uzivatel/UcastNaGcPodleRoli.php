<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Role\Role;
use Gamecon\Uzivatel\Enum\UcastNaGc;

/**
 * Účast na GC se čte z rolí účasti, jejichž ID kóduje ročník jako -((rok - 2000) * 100 + základ).
 * Načítá se hromadně jedním dotazem, protože upomínky se rozesílají všem dlužníkům v databázi.
 */
class UcastNaGcPodleRoli
{
    private const ZAKLAD_ROLE_PRITOMEN = 2;

    /** @var array<int, array<int, true>> ročník => ID přítomných */
    private array $pritomniVRocniku = [];

    /** @var array<int, array<int, true>> ročník => ID přihlášených */
    private array $prihlaseniVRocniku = [];

    /** @var array<int, int>|null */
    private ?array $rokyPosledniUcasti = null;

    public function ucast(
        int $idUzivatele,
        int $rocnik,
    ): UcastNaGc {
        // Cache je klíčovaná ročníkem, aby dotaz na jiný ročník nedostal data prvního.
        $this->pritomniVRocniku[$rocnik] ??= $this->idsSRoli(Role::pritomenNaRocniku($rocnik));
        $this->prihlaseniVRocniku[$rocnik] ??= $this->idsSRoli(Role::prihlasenNaRocnik($rocnik));

        return match (true) {
            isset($this->pritomniVRocniku[$rocnik][$idUzivatele])   => UcastNaGc::PRITOMEN,
            isset($this->prihlaseniVRocniku[$rocnik][$idUzivatele]) => UcastNaGc::JEN_PRIHLASEN,
            default                                                 => UcastNaGc::NEDORAZIL,
        };
    }

    /**
     * Poslední ročník, na kterém uživatel fyzicky byl, nebo null když nebyl nikdy.
     */
    public function rokPosledniUcasti(int $idUzivatele): ?int
    {
        $this->rokyPosledniUcasti ??= $this->nactiRokyPosledniUcasti();

        return $this->rokyPosledniUcasti[$idUzivatele] ?? null;
    }

    /**
     * @return array<int, true>
     */
    private function idsSRoli(int $idRole): array
    {
        $ids = dbFetchColumn(<<<SQL
SELECT id_uzivatele
FROM platne_role_uzivatelu
WHERE id_role = $0
SQL,
            [
                0 => $idRole,
            ],
        );

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * @return array<int, int>
     */
    private function nactiRokyPosledniUcasti(): array
    {
        $zaklad = self::ZAKLAD_ROLE_PRITOMEN;

        $radky = dbFetchAll(<<<SQL
SELECT id_uzivatele, MAX((-id_role - $zaklad) / 100 + 2000) AS rok
FROM platne_role_uzivatelu
WHERE id_role < 0
    AND (-id_role - $zaklad) % 100 = 0
GROUP BY id_uzivatele
SQL,
        );

        $roky = [];
        foreach ($radky as $radek) {
            $roky[(int) $radek['id_uzivatele']] = (int) $radek['rok'];
        }

        return $roky;
    }
}
