<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Role\Role;

/**
 * Vypravěči ročníku pro veřejnou stránku /vypravaci.
 *
 * Vypravěčem je, kdo má ročníkovou roli vypravěče, i když ještě nevede žádnou aktivitu,
 * a kdo vede netechnickou aktivitu viditelnou na webu, i když roli nemá.
 * Jeho sekce jsou linie z menu webu, ve kterých vede aktivitu viditelnou na webu.
 */
class PrehledVypravecu
{
    /** @var list<\Uzivatel>|null */
    private ?array $vypraveci = null;

    /** @var array<int, list<int>>|null ID vedoucího => ID typů jeho aktivit */
    private ?array $idTypuPodleVedoucich = null;

    /** @var list<TypAktivity>|null */
    private ?array $linieZMenu = null;

    public function __construct(private readonly int $rocnik)
    {
    }

    /**
     * @return list<\Uzivatel> podle jména na webu
     */
    public function vypraveci(?TypAktivity $sekce = null): array
    {
        if ($this->vypraveci === null) {
            // technickou a brigádnickou aktivitu vedou i lidé, kteří nic nevyprávějí
            $idVedoucichNetechnickych = array_keys(array_filter(
                $this->idTypuPodleVedoucich(),
                static fn (array $idTypu) => array_diff($idTypu, TypAktivity::interniTypy()) !== [],
            ));
            $vypraveci = [];
            foreach ([
                ...\Uzivatel::zRole(Role::LETOSNI_VYPRAVEC($this->rocnik)),
                ...\Uzivatel::zIds($idVedoucichNetechnickych),
            ] as $vypravec) {
                $vypraveci[(int)$vypravec->id()] = $vypravec;
            }
            $this->vypraveci = serazenePodle(
                array_values($vypraveci),
                static fn (\Uzivatel $vypravec) => $vypravec->jmenoNaWebu(),
            );
        }
        if ($sekce === null) {
            return $this->vypraveci;
        }

        return array_values(array_filter(
            $this->vypraveci,
            fn (\Uzivatel $vypravec) => in_array((int)$sekce->id(), $this->idTypu($vypravec), true),
        ));
    }

    /**
     * @return list<TypAktivity> v pořadí menu
     */
    public function sekceVypravece(\Uzivatel $vypravec): array
    {
        $idTypu = $this->idTypu($vypravec);

        return array_values(array_filter(
            $this->linieZMenu(),
            static fn (TypAktivity $linie) => in_array((int)$linie->id(), $idTypu, true),
        ));
    }

    /**
     * Sekce, ve kterých vede aktivitu aspoň jeden vypravěč.
     *
     * @return list<TypAktivity> v pořadí menu
     */
    public function sekce(): array
    {
        $idTypu = [];
        foreach ($this->vypraveci() as $vypravec) {
            foreach ($this->idTypu($vypravec) as $idTypuVypravece) {
                $idTypu[$idTypuVypravece] = true;
            }
        }

        return array_values(array_filter(
            $this->linieZMenu(),
            static fn (TypAktivity $linie) => isset($idTypu[(int)$linie->id()]),
        ));
    }

    /**
     * @return list<int>
     */
    private function idTypu(\Uzivatel $vypravec): array
    {
        return $this->idTypuPodleVedoucich()[(int)$vypravec->id()] ?? [];
    }

    /**
     * @return array<int, list<int>>
     */
    private function idTypuPodleVedoucich(): array
    {
        if ($this->idTypuPodleVedoucich === null) {
            $this->idTypuPodleVedoucich = [];
            $radky = dbFetchAll(<<<'SQL'
SELECT DISTINCT akce_organizatori.id_uzivatele, akce_seznam.typ
FROM akce_organizatori
JOIN akce_seznam ON akce_seznam.id_akce = akce_organizatori.id_akce
WHERE akce_seznam.rok = $0
    AND akce_seznam.stav IN ($1)
SQL, [0 => $this->rocnik, 1 => StavAktivity::bezneViditelneStavy()]);
            foreach ($radky as $radek) {
                $this->idTypuPodleVedoucich[(int)$radek['id_uzivatele']][] = (int)$radek['typ'];
            }
        }

        return $this->idTypuPodleVedoucich;
    }

    /**
     * @return list<TypAktivity>
     */
    private function linieZMenu(): array
    {
        return $this->linieZMenu ??= serazenePodle(TypAktivity::zViditelnych(), 'poradi');
    }
}
