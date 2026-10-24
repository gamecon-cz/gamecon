<?php

declare(strict_types=1);

namespace Gamecon\Ubytovani;

use Gamecon\Uzivatel\Pohlavi;

/**
 * Puts the participants who came alone ({@see UbytovaciSkupiny::prihlasSamotare()}) into rooms. Run by hand
 * in the admin after the accommodation sales end: nobody can join a group by code then, so the beds a group
 * has not used are really free.
 *
 * Strangers share a room only with the same gender. First they fill the free beds of existing groups,
 * on nights the group sleeps there anyway, then the rest are put together into new groups.
 * Who fits nowhere keeps waiting, the rooms are assigned by hand after all.
 */
class DoplneniSamotaru
{
    private readonly UbytovaciSkupiny $skupiny;
    private readonly HotelovePokoje $hotel;

    public function __construct(private readonly int $rok = ROCNIK)
    {
        $this->skupiny = new UbytovaciSkupiny($rok);
        $this->hotel   = new HotelovePokoje($rok);
    }

    /**
     * @return array{doSkupin: int, doNovychSkupin: int, novychSkupin: int}
     */
    public function dopln(): array
    {
        $vysledek = [
            'doSkupin'       => 0,
            'doNovychSkupin' => 0,
            'novychSkupin'   => 0,
        ];

        dbBegin();
        try {
            $zbyli = $this->doplnDoSkupin($this->nactiSamotare(), $this->nactiSkupiny(), $vysledek);
            $this->dejDohromady($zbyli, $vysledek);
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        return $vysledek;
    }

    /**
     * A participant fits into a group whose members are of the same gender and sleep there on every night
     * of the participant, with a bed free on each. On a hotel the bed was held for the group, so the hotel
     * does not get fuller. Of more such groups the one with the fewest nights the participant misses.
     *
     * @param list<array{id: int, typ: string, noci: list<int>, pohlavi: string}>                    $samotari
     * @param list<array{kod: string, typ: string, pohlavi: string|null, obsazeno: array<int, int>}> $skupiny
     * @param array{doSkupin: int, doNovychSkupin: int, novychSkupin: int}                           $vysledek
     * @return list<array{id: int, typ: string, noci: list<int>, pohlavi: string}> who got into no group
     */
    private function doplnDoSkupin(array $samotari, array $skupiny, array &$vysledek): array
    {
        // longer stays fit into fewer groups, they go first
        usort($samotari, static fn (array $a, array $b): int => count($b['noci']) <=> count($a['noci']));

        $zbyli = [];
        foreach ($samotari as $samotar) {
            $maxClenu = (int)UbytovaciSkupiny::maxClenu($samotar['typ']);
            $nejlepsi = null;
            $nejmeneNoci = PHP_INT_MAX;
            foreach ($skupiny as $index => $skupina) {
                if ($skupina['typ'] !== $samotar['typ'] || $skupina['pohlavi'] !== $samotar['pohlavi']) {
                    continue;
                }
                foreach ($samotar['noci'] as $noc) {
                    $obsazeno = $skupina['obsazeno'][$noc] ?? 0;
                    if ($obsazeno < 1 || $obsazeno >= $maxClenu) {
                        continue 2;
                    }
                }
                $nociBezNej = count($skupina['obsazeno']) - count($samotar['noci']);
                if ($nociBezNej < $nejmeneNoci) {
                    $nejlepsi = $index;
                    $nejmeneNoci = $nociBezNej;
                }
            }

            if ($nejlepsi === null) {
                $zbyli[] = $samotar;
                continue;
            }
            $this->skupiny->doplnDoSkupiny($samotar['id'], $skupiny[$nejlepsi]['kod']);
            foreach ($samotar['noci'] as $noc) {
                ++$skupiny[$nejlepsi]['obsazeno'][$noc];
            }
            ++$vysledek['doSkupin'];
        }

        return $zbyli;
    }

    /**
     * The rest of the same type and gender share new rooms, those with the most nights in common together.
     * Nobody gets a room alone, nor with someone who sleeps there on no night of theirs.
     *
     * @param list<array{id: int, typ: string, noci: list<int>, pohlavi: string}> $samotari
     * @param array{doSkupin: int, doNovychSkupin: int, novychSkupin: int}        $vysledek
     */
    private function dejDohromady(array $samotari, array &$vysledek): void
    {
        $podleTypuAPohlavi = [];
        foreach ($samotari as $samotar) {
            $podleTypuAPohlavi[$samotar['typ'] . "\0" . $samotar['pohlavi']][] = $samotar;
        }

        foreach ($podleTypuAPohlavi as $cekajici) {
            $maxClenu = (int)UbytovaciSkupiny::maxClenu($cekajici[0]['typ']);
            usort($cekajici, static fn (array $a, array $b): int => [min($a['noci']), -count($a['noci'])]
                                                                      <=> [min($b['noci']), -count($b['noci'])]);
            while ($cekajici !== []) {
                $pokoj = [array_shift($cekajici)];
                $noci = $pokoj[0]['noci'];
                while (count($pokoj) < $maxClenu) {
                    $index = $this->nejlepsiSpolubydlici($cekajici, $pokoj, $noci);
                    if ($index === null) {
                        break;
                    }
                    $pokoj[] = $cekajici[$index];
                    $noci = array_values(array_unique([...$noci, ...$cekajici[$index]['noci']]));
                    unset($cekajici[$index]);
                    $cekajici = array_values($cekajici);
                }
                if (count($pokoj) < 2) {
                    continue;
                }
                if (!$this->zalozPokoj(array_column($pokoj, 'id'))) {
                    // the hotel has no room left for them, the others may still pair up differently
                    array_push($cekajici, ...array_slice($pokoj, 1));
                    continue;
                }
                ++$vysledek['novychSkupin'];
                $vysledek['doNovychSkupin'] += count($pokoj);
            }
        }
    }

    /**
     * @param list<array{noci: list<int>}> $cekajici
     * @param list<array{noci: list<int>}> $pokoj    who is in the room so far
     * @param list<int>                    $noci     nights of the room so far
     * @return int|null index of the one with the most nights in common and the fewest others,
     *                  of those who share a night with everyone in the room
     */
    private function nejlepsiSpolubydlici(array $cekajici, array $pokoj, array $noci): ?int
    {
        $nejlepsi = null;
        $nejlepsiShoda = [0, PHP_INT_MIN];
        foreach ($cekajici as $index => $kandidat) {
            foreach ($pokoj as $clen) {
                if (array_intersect($kandidat['noci'], $clen['noci']) === []) {
                    continue 2;
                }
            }
            $spolecne = count(array_intersect($kandidat['noci'], $noci));
            $shoda = [$spolecne, -count(array_diff($kandidat['noci'], $noci)) - count(array_diff($noci, $kandidat['noci']))];
            if ($shoda > $nejlepsiShoda) {
                $nejlepsi = $index;
                $nejlepsiShoda = $shoda;
            }
        }

        return $nejlepsi;
    }

    /**
     * On a hotel a new group takes a whole room, which may not be left, see {@see HotelovePokoje}.
     *
     * @param int[] $idsUzivatelu
     */
    private function zalozPokoj(array $idsUzivatelu): bool
    {
        dbBegin();
        try {
            $predtim = $this->hotel->obsazenost(zamknout: true);
            $this->skupiny->zalozSkupinuZ($idsUzivatelu);
            if ($this->hotel->preplnenePoZmene($predtim) !== []) {
                dbRollback();

                return false;
            }
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        return true;
    }

    /**
     * Who waits for roommates and can get some: one type of accommodation with rooms for more people
     * on all nights and a known gender. The others wait for the rooms assigned by hand.
     *
     * @return list<array{id: int, typ: string, noci: list<int>, pohlavi: string}> in the order they asked
     */
    private function nactiSamotare(): array
    {
        $idsSamotaru = $this->skupiny->idsSamotaru();
        $ubytovani = $this->skupiny->ubytovaniClenu($idsSamotaru);
        $pohlavi = $this->pohlavi($idsSamotaru);

        $samotari = [];
        foreach ($idsSamotaru as $idSamotare) {
            $typy = $ubytovani[$idSamotare]['typy'] ?? [];
            if (count($typy) !== 1
                || (UbytovaciSkupiny::maxClenu($typy[0]) ?? 0) < 2
                || !in_array($pohlavi[$idSamotare] ?? null, [Pohlavi::MUZ_KOD, Pohlavi::ZENA_KOD], true)
                || $this->skupiny->kodSkupiny($idSamotare) !== null
            ) {
                continue;
            }
            $samotari[] = [
                'id'      => $idSamotare,
                'typ'     => $typy[0],
                'noci'    => $ubytovani[$idSamotare]['noci'],
                'pohlavi' => $pohlavi[$idSamotare],
            ];
        }

        return $samotari;
    }

    /**
     * Groups a stranger may get into: all members with accommodation have one type, of rooms for more
     * people. Gender is null when the members differ in it (or it is unknown), nobody gets into such a group.
     *
     * @return list<array{kod: string, typ: string, pohlavi: string|null, obsazeno: array<int, int>}>
     *                                                                                                obsazeno = how many members sleep there by night, in the order the groups were founded
     */
    private function nactiSkupiny(): array
    {
        $clenstvi = dbFetchAll(<<<'SQL'
SELECT kod_skupiny, id_uzivatele
FROM ubytovani_skupiny_clenove
WHERE rok = $0
ORDER BY pripojen, id_uzivatele
SQL,
            [
                0 => $this->rok,
            ],
        );
        $idsClenu = array_map(static fn (array $radek): int => (int)$radek['id_uzivatele'], $clenstvi);
        $ubytovani = $this->skupiny->ubytovaniClenu($idsClenu);
        $pohlavi = $this->pohlavi($idsClenu);

        $skupiny = [];
        foreach ($clenstvi as $radek) {
            $idClena = (int)$radek['id_uzivatele'];
            if (!isset($ubytovani[$idClena])) {
                continue; // takes no bed
            }
            $kod = (string)$radek['kod_skupiny'];
            $skupiny[$kod] ??= [
                'kod'      => $kod,
                'typy'     => [],
                'pohlavi'  => [],
                'obsazeno' => [],
            ];
            foreach ($ubytovani[$idClena]['typy'] as $typ) {
                $skupiny[$kod]['typy'][$typ] = true;
            }
            $skupiny[$kod]['pohlavi'][$pohlavi[$idClena] ?? ''] = true;
            foreach ($ubytovani[$idClena]['noci'] as $noc) {
                $skupiny[$kod]['obsazeno'][$noc] = ($skupiny[$kod]['obsazeno'][$noc] ?? 0) + 1;
            }
        }

        $doplnitelne = [];
        foreach ($skupiny as $skupina) {
            $typy = array_keys($skupina['typy']);
            if (count($typy) !== 1 || (UbytovaciSkupiny::maxClenu((string)$typy[0]) ?? 0) < 2) {
                continue;
            }
            $pohlaviClenu = array_keys($skupina['pohlavi']);
            $doplnitelne[] = [
                'kod'     => $skupina['kod'],
                'typ'     => (string)$typy[0],
                'pohlavi' => count($pohlaviClenu) === 1 && $pohlaviClenu[0] !== ''
                    ? (string)$pohlaviClenu[0]
                    : null,
                'obsazeno' => $skupina['obsazeno'],
            ];
        }

        return $doplnitelne;
    }

    /**
     * @param int[] $idsUzivatelu
     * @return array<int, string>
     */
    private function pohlavi(array $idsUzivatelu): array
    {
        if ($idsUzivatelu === []) {
            return [];
        }
        $pohlavi = [];
        foreach (dbFetchAll(<<<'SQL'
SELECT id_uzivatele, pohlavi FROM uzivatele_hodnoty WHERE id_uzivatele IN ($0)
SQL,
            [
                0 => $idsUzivatelu,
            ],
        ) as $radek) {
            $pohlavi[(int)$radek['id_uzivatele']] = (string)$radek['pohlavi'];
        }

        return $pohlavi;
    }
}
