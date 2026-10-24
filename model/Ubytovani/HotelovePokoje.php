<?php

declare(strict_types=1);

namespace Gamecon\Ubytovani;

use Gamecon\Shop\PodtypPredmetu;
use Gamecon\Shop\Shop;
use Gamecon\Shop\TypPredmetu;

/**
 * Hotel rooms are scarce, so a roommate group on a hotel takes the whole room as soon as it is founded:
 * the beds of the room that no member has ordered yet are held for those who join by code, nobody else gets them.
 * There are thus never more groups on a hotel than rooms.
 *
 * Capacity of an item is still in beds (`kusu_vyrobeno`), a room has as many beds as the number before "L"
 * in the type says. A group holds its room on every night one of its members sleeps there.
 * People without a group take one bed and are paired by hand, as on the dormitory.
 * The dormitory keeps counting beds only, it has enough of them.
 */
class HotelovePokoje
{
    public function __construct(private readonly int $rok = ROCNIK)
    {
    }

    /**
     * Beds the roommate groups hold on hotels for those who have not joined yet.
     *
     * @param string|null $kodSkupinyNepocitat group whose beds are left out, its member can take them
     * @return array<int, int> by item id, only the items where a group holds a bed
     */
    public function drzenePostele(?string $kodSkupinyNepocitat = null): array
    {
        $drzenePostele = [];
        foreach ($this->nacti(zamknout: false, kodSkupinyNepocitat: $kodSkupinyNepocitat) as $idPredmetu => $predmet) {
            if ($predmet['drzeno'] > 0) {
                $drzenePostele[$idPredmetu] = $predmet['drzeno'];
            }
        }

        return $drzenePostele;
    }

    /**
     * Beds sold and held on every hotel night with a limited capacity.
     * With the lock the hotel items stay locked until the end of the transaction, so that two people
     * cannot take the last room at once, and the counts are read as committed, not from the snapshot
     * of the transaction.
     *
     * @return array<int, array{typ: string, nazev: string, den: int, kapacita: int, prodano: int, drzeno: int}> by item id
     */
    public function obsazenost(bool $zamknout = false): array
    {
        return $this->nacti($zamknout, null);
    }

    /**
     * Hotel nights a change made fuller than they can hold. A night that was overbooked before
     * (by hand in the admin) does not count, unless the change added to it.
     *
     * @param array<int, array{prodano: int, drzeno: int}> $predZmenou {@see obsazenost()} before the change
     * @return array<int, array{typ: string, nazev: string, den: int, kapacita: int, prodano: int, drzeno: int}> by item id
     */
    public function preplnenePoZmene(array $predZmenou): array
    {
        $preplnene = [];
        foreach ($this->obsazenost(zamknout: true) as $idPredmetu => $predmet) {
            $obsazeno = $predmet['prodano'] + $predmet['drzeno'];
            $obsazenoPredtim = isset($predZmenou[$idPredmetu])
                ? $predZmenou[$idPredmetu]['prodano'] + $predZmenou[$idPredmetu]['drzeno']
                : 0;
            if ($obsazeno > $predmet['kapacita'] && $obsazeno > $obsazenoPredtim) {
                $preplnene[$idPredmetu] = $predmet;
            }
        }

        return $preplnene;
    }

    /**
     * @return array<int, array{typ: string, nazev: string, den: int, kapacita: int, prodano: int, drzeno: int}>
     */
    private function nacti(bool $zamknout, ?string $kodSkupinyNepocitat): array
    {
        $zamekNakupu = $zamknout
            ? 'LOCK IN SHARE MODE'
            : '';

        $predmety = dbFetchAll(<<<'SQL'
SELECT id_predmetu, kod_predmetu, nazev, ubytovani_den, kusu_vyrobeno
FROM shop_predmety
WHERE typ = $0
  AND podtyp = $1
  AND model_rok = $2
ORDER BY id_predmetu
SQL,
            [
                0 => TypPredmetu::UBYTOVANI,
                1 => PodtypPredmetu::HOTEL,
                2 => $this->rok,
            ],
        );
        if ($zamknout && $predmety !== []) {
            // No index fits the conditions above, a locking read of them would lock every item of every year.
            // By primary key only the hotel items are locked, and read as committed.
            $predmety = dbFetchAll(<<<'SQL'
SELECT id_predmetu, kod_predmetu, nazev, ubytovani_den, kusu_vyrobeno
FROM shop_predmety
WHERE id_predmetu IN ($0)
ORDER BY id_predmetu
FOR UPDATE
SQL,
                [
                    0 => array_column($predmety, 'id_predmetu'),
                ],
            );
        }
        $obsazenost = [];
        foreach ($predmety as $predmet) {
            if ($predmet['kusu_vyrobeno'] === null) {
                continue;
            }
            $kod = (string)$predmet['kod_predmetu'];
            $obsazenost[(int)$predmet['id_predmetu']] = [
                'typ'      => substr($kod, 0, -3),
                'nazev'    => trim(Shop::bezDne(trim((string)$predmet['nazev']))),
                'den'      => (int)$predmet['ubytovani_den'],
                'kapacita' => (int)$predmet['kusu_vyrobeno'],
                'prodano'  => 0,
                'drzeno'   => 0,
            ];
        }
        if ($obsazenost === []) {
            return [];
        }
        $idsPredmetu = array_keys($obsazenost);

        // A locking read locks every row it walks through. So the purchases are reached by item only (the year
        // in WHERE could make it walk all purchases of the year and block every registration) and the memberships
        // by user.
        $prodano = dbFetchAll(<<<SQL
SELECT id_predmetu, SUM(rok = $0) AS prodano
FROM shop_nakupy
WHERE id_predmetu IN ($1)
GROUP BY id_predmetu
{$zamekNakupu}
SQL,
            [
                0 => $this->rok,
                1 => $idsPredmetu,
            ],
        );
        foreach ($prodano as $radek) {
            $obsazenost[(int)$radek['id_predmetu']]['prodano'] = (int)$radek['prodano'];
        }

        $clenuSkupinNaNoc = dbFetchAll(<<<SQL
SELECT shop_nakupy.id_predmetu, ubytovani_skupiny_clenove.kod_skupiny, COUNT(*) AS clenu
FROM shop_nakupy
STRAIGHT_JOIN ubytovani_skupiny_clenove FORCE INDEX (PRIMARY)
    ON ubytovani_skupiny_clenove.rok = $0
    AND ubytovani_skupiny_clenove.rok = shop_nakupy.rok
    AND ubytovani_skupiny_clenove.id_uzivatele = shop_nakupy.id_uzivatele
WHERE shop_nakupy.id_predmetu IN ($1)
GROUP BY shop_nakupy.id_predmetu, ubytovani_skupiny_clenove.kod_skupiny
{$zamekNakupu}
SQL,
            [
                0 => $this->rok,
                1 => $idsPredmetu,
            ],
        );
        foreach ($clenuSkupinNaNoc as $radek) {
            if ($kodSkupinyNepocitat !== null && $radek['kod_skupiny'] === $kodSkupinyNepocitat) {
                continue;
            }
            $idPredmetu = (int)$radek['id_predmetu'];
            $posteliNaPokoji = UbytovaciSkupiny::maxClenu($obsazenost[$idPredmetu]['typ']);
            if ($posteliNaPokoji === null) {
                continue;
            }
            $obsazenost[$idPredmetu]['drzeno'] += max(0, $posteliNaPokoji - (int)$radek['clenu']);
        }

        return $obsazenost;
    }
}
