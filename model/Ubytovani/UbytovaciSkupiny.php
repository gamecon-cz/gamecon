<?php

declare(strict_types=1);

namespace Gamecon\Ubytovani;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Exceptions\ChybaUbytovaciSkupiny;
use Gamecon\Shop\Shop;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Ubytovani\SqlStruktura\UbytovaciSkupinaClenSqlStruktura as Sql;

/**
 * Roommate groups. People who want to share a room join one group: one of them founds it
 * and sends its code to the others. Everyone in a group has the same type of accommodation
 * and a group holds at most as many people as the room has beds.
 * Rooms are still assigned by hand (report of accommodation → import), a group only says
 * who belongs together.
 *
 * The type of accommodation is the item code without its three character day suffix
 * ("2L_ct" → "2L", "Hs-2L-ct" → "Hs-2L"), the same as in the report of accommodation.
 * A member without accommodation that year (cancelled outside the form) does not take a bed.
 */
class UbytovaciSkupiny
{
    // without characters that are easy to mix up (0/O, 1/I/L)
    private const ZNAKY_KODU = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const DELKA_KODU = 6;

    /** @var array<string, string> */
    private array $nazvyTypu = [];

    public function __construct(private readonly int $rok = ROCNIK)
    {
    }

    /**
     * How many people fit into a room of the type, by the number before "L" in the code.
     * Null for a type without beds counted that way (sleeping bag).
     */
    public static function maxClenu(string $typUbytovani): ?int
    {
        return preg_match('~(\d+)L~i', $typUbytovani, $shoda)
            ? (int)$shoda[1]
            : null;
    }

    public static function normalizujKod(string $kod): string
    {
        return mb_strtoupper(preg_replace('~\s+~u', '', $kod));
    }

    /**
     * Subquery for reports: the code of the group the user is in.
     */
    public static function sqlKodSkupiny(string $sloupecIdUzivatele, int $rok): string
    {
        return <<<SQL
(SELECT ubytovani_skupiny_clenove.kod_skupiny
 FROM ubytovani_skupiny_clenove
 WHERE ubytovani_skupiny_clenove.id_uzivatele = {$sloupecIdUzivatele}
   AND ubytovani_skupiny_clenove.rok = {$rok})
SQL;
    }

    /**
     * Subquery for reports: the other members of the group the user is in, as "Name Surname (ID)".
     * Members without accommodation that year are left out, they do not take a bed.
     */
    public static function sqlSpolubydliciVeSkupine(string $sloupecIdUzivatele, int $rok): string
    {
        $typUbytovani = TypPredmetu::UBYTOVANI;

        return <<<SQL
(SELECT GROUP_CONCAT(
            CONCAT(TRIM(CONCAT_WS(' ', spolubydlici.jmeno_uzivatele, spolubydlici.prijmeni_uzivatele)), ' (', spolubydlici.id_uzivatele, ')')
            ORDER BY spolubydlici.prijmeni_uzivatele, spolubydlici.jmeno_uzivatele
            SEPARATOR ', '
        )
 FROM ubytovani_skupiny_clenove AS clenstvi
 JOIN ubytovani_skupiny_clenove AS ostatni_clenove
     ON ostatni_clenove.rok = clenstvi.rok
     AND ostatni_clenove.kod_skupiny = clenstvi.kod_skupiny
     AND ostatni_clenove.id_uzivatele <> clenstvi.id_uzivatele
 JOIN uzivatele_hodnoty AS spolubydlici
     ON spolubydlici.id_uzivatele = ostatni_clenove.id_uzivatele
 WHERE clenstvi.id_uzivatele = {$sloupecIdUzivatele}
   AND clenstvi.rok = {$rok}
   AND EXISTS (
       SELECT 1
       FROM shop_nakupy
       JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
       WHERE shop_nakupy.id_uzivatele = spolubydlici.id_uzivatele
         AND shop_nakupy.rok = {$rok}
         AND shop_predmety.typ = {$typUbytovani}
   ))
SQL;
    }

    /**
     * Group of everyone who is in one this year, for a report that cannot use the subqueries.
     *
     * @return array<int, array{kod: string, spolubydlici: string}> by user id
     */
    public function clenstviProReport(): array
    {
        $sqlSpolubydlici = self::sqlSpolubydliciVeSkupine('ubytovani_skupiny_clenove.id_uzivatele', $this->rok);
        $radky           = dbFetchAll(<<<SQL
SELECT ubytovani_skupiny_clenove.id_uzivatele,
       ubytovani_skupiny_clenove.kod_skupiny,
       {$sqlSpolubydlici} AS spolubydlici
FROM ubytovani_skupiny_clenove
WHERE ubytovani_skupiny_clenove.rok = $0
SQL,
            [0 => $this->rok],
        );

        $clenstvi = [];
        foreach ($radky as $radek) {
            $clenstvi[(int)$radek[Sql::ID_UZIVATELE]] = [
                'kod'          => (string)$radek[Sql::KOD_SKUPINY],
                'spolubydlici' => (string)$radek['spolubydlici'],
            ];
        }

        return $clenstvi;
    }

    public function kodSkupiny(int $idUzivatele): ?string
    {
        $kod = dbOneCol(<<<'SQL'
SELECT kod_skupiny
FROM ubytovani_skupiny_clenove
WHERE rok = $0 AND id_uzivatele = $1
SQL,
            [0 => $this->rok, 1 => $idUzivatele],
        );

        return $kod !== null
            ? (string)$kod
            : null;
    }

    /**
     * @return int[] all members including those without accommodation, in the order they joined
     */
    public function idsClenu(string $kod, bool $zamknout = false): array
    {
        $zamek = $zamknout
            ? 'FOR UPDATE'
            : '';

        return array_map('intval', dbOneArray(<<<SQL
SELECT id_uzivatele
FROM ubytovani_skupiny_clenove
WHERE rok = $0 AND kod_skupiny = $1
ORDER BY pripojen, id_uzivatele
{$zamek}
SQL,
            [0 => $this->rok, 1 => self::normalizujKod($kod)],
        ));
    }

    /**
     * Founds a new group with the user as its only member. Leaves the group the user was in.
     *
     * @return string code of the new group
     * @throws ChybaUbytovaciSkupiny
     */
    public function zaloz(\Uzivatel $uzivatel): string
    {
        $typ      = $this->typProSkupinu((int)$uzivatel->id());
        $maxClenu = self::maxClenu($typ);
        if ($maxClenu !== null && $maxClenu < 2) {
            throw new ChybaUbytovaciSkupiny(sprintf(
                'Na pokoji typu „%s“ je jen jedna postel, skupinu spolubydlících proto založit nelze.',
                $this->nazevTypu($typ),
            ));
        }

        $kod = $this->vygenerujKod();
        $this->opust((int)$uzivatel->id());
        dbInsert(Sql::UBYTOVANI_SKUPINY_CLENOVE_TABULKA, [
            Sql::ROK          => $this->rok,
            Sql::ID_UZIVATELE => $uzivatel->id(),
            Sql::KOD_SKUPINY  => $kod,
        ]);

        return $kod;
    }

    /**
     * Puts the user into the group with the code. Leaves the group the user was in.
     *
     * @throws ChybaUbytovaciSkupiny
     */
    public function pripoj(\Uzivatel $uzivatel, string $kod): void
    {
        $kod         = self::normalizujKod($kod);
        $idUzivatele = (int)$uzivatel->id();
        $idsClenu    = $this->idsClenu($kod, zamknout: true);
        if ($idsClenu === []) {
            throw new ChybaUbytovaciSkupiny(sprintf(
                'Skupina spolubydlících s kódem %s neexistuje. Zkontroluj, že je kód opsaný správně.',
                $kod,
            ));
        }
        if (in_array($idUzivatele, $idsClenu, true)) {
            return;
        }

        $typ = $this->typProSkupinu($idUzivatele);
        foreach ($this->typySkupiny($idsClenu) as $typSkupiny) {
            if ($typSkupiny !== $typ) {
                throw new ChybaUbytovaciSkupiny(sprintf(
                    'Skupina %s má ubytování „%s“, zvolené ubytování je ale „%s“. Ve skupině musí mít všichni stejný typ ubytování.',
                    $kod,
                    $this->nazevTypu($typSkupiny),
                    $this->nazevTypu($typ),
                ));
            }
        }

        $maxClenu = self::maxClenu($typ);
        if ($maxClenu !== null && count($this->sUbytovanim($idsClenu)) >= $maxClenu) {
            throw new ChybaUbytovaciSkupiny(sprintf(
                'Skupina %s je plná, víc lidí se na pokoj typu „%s“ nevejde.',
                $kod,
                $this->nazevTypu($typ),
            ));
        }

        $this->opust($idUzivatele);
        dbInsert(Sql::UBYTOVANI_SKUPINY_CLENOVE_TABULKA, [
            Sql::ROK          => $this->rok,
            Sql::ID_UZIVATELE => $idUzivatele,
            Sql::KOD_SKUPINY  => $kod,
        ]);
    }

    /**
     * @return bool whether the user was in a group
     */
    public function opust(int $idUzivatele): bool
    {
        $result = dbQuery(<<<'SQL'
DELETE FROM ubytovani_skupiny_clenove
WHERE rok = $0 AND id_uzivatele = $1
SQL,
            [0 => $this->rok, 1 => $idUzivatele],
        );

        return dbAffectedOrNumRows($result) > 0;
    }

    /**
     * After the accommodation of the user changed: the user leaves the group when it no longer fits
     * (no accommodation, more types, another type than the others, no free bed).
     *
     * @return string|null why the user left the group, null when nothing changed
     */
    public function zkontrolujPoZmeneUbytovani(\Uzivatel $uzivatel): ?string
    {
        $idUzivatele = (int)$uzivatel->id();
        $kod         = $this->kodSkupiny($idUzivatele);
        if ($kod === null) {
            return null;
        }

        $duvod = null;
        $typy  = $this->ubytovaniClenu([$idUzivatele])[$idUzivatele]['typy'] ?? [];
        if ($typy === []) {
            $duvod = 'bez objednaného ubytování nelze být ve skupině';
        } elseif (count($typy) > 1) {
            $duvod = 'pro skupinu je potřeba mít na všechny noci stejný typ ubytování';
        } else {
            $typ      = reset($typy);
            $ostatni  = array_values(array_diff($this->idsClenu($kod), [$idUzivatele]));
            $jinyTyp  = array_values(array_diff($this->typySkupiny($ostatni), [$typ]));
            $maxClenu = self::maxClenu($typ);
            if ($jinyTyp !== []) {
                $duvod = sprintf(
                    'ostatní ve skupině mají ubytování „%s“, nově zvolené je „%s“',
                    $this->nazevTypu($jinyTyp[0]),
                    $this->nazevTypu($typ),
                );
            } elseif ($maxClenu !== null && count($this->sUbytovanim($ostatni)) + 1 > $maxClenu) {
                $duvod = sprintf('na pokoji typu „%s“ není pro všechny ze skupiny dost postelí', $this->nazevTypu($typ));
            }
        }

        if ($duvod === null) {
            return null;
        }
        $this->opust($idUzivatele);

        return sprintf('Členství ve skupině spolubydlících %s skončilo: %s.', $kod, $duvod);
    }

    /**
     * What to show about the group of the user, null when the user is in none.
     *
     * @return array{
     *     kod: string,
     *     maxClenu: int|null,
     *     clenove: list<array{id: int, jmeno: string, ubytovani: string, maUbytovani: bool}>,
     *     varovani: list<string>,
     * }|null
     */
    public function prehled(int $idUzivatele): ?array
    {
        $kod = $this->kodSkupiny($idUzivatele);
        if ($kod === null) {
            return null;
        }

        $idsClenu       = $this->idsClenu($kod);
        $ubytovaniClenu = $this->ubytovaniClenu($idsClenu);
        $uzivatele      = [];
        foreach (\Uzivatel::zIds($idsClenu) as $uzivatel) {
            $uzivatele[(int)$uzivatel->id()] = $uzivatel;
        }

        $clenove  = [];
        $varovani = [];
        // per member with accommodation, to compare them
        $typy = [];
        $noci = [];
        foreach ($idsClenu as $idClena) {
            $ubytovani = $ubytovaniClenu[$idClena] ?? null;
            $jmeno     = isset($uzivatele[$idClena])
                ? (string)$uzivatele[$idClena]->jmenoNick()
                : (string)$idClena;
            $clenove[] = [
                'id'          => $idClena,
                'jmeno'       => $jmeno,
                'ubytovani'   => $ubytovani !== null
                    ? implode(', ', array_map(fn(string $typ) => $this->nazevTypu($typ), $ubytovani['typy']))
                      . ', ' . $this->rozsahNoci($ubytovani['noci'])
                    : 'nemá objednané ubytování',
                'maUbytovani' => $ubytovani !== null,
            ];
            if ($ubytovani === null) {
                $varovani[] = sprintf('%s nemá objednané ubytování, a tak se do počtu na pokoji nepočítá.', $jmeno);
                continue;
            }
            $typy[] = implode(',', $ubytovani['typy']);
            $noci[] = implode(',', $ubytovani['noci']);
        }

        $pocetSUbytovanim = count($typy);
        $ruzneTypy        = array_values(array_unique($typy));
        $maxClenu         = count($ruzneTypy) === 1 && !str_contains($ruzneTypy[0], ',')
            ? self::maxClenu($ruzneTypy[0])
            : null;
        if (count($ruzneTypy) > 1) {
            $varovani[] = 'Členové skupiny nemají stejný typ ubytování, na jeden pokoj je proto dát nepůjde.';
        }
        if ($maxClenu !== null && $pocetSUbytovanim > $maxClenu) {
            $varovani[] = sprintf('Ve skupině je víc lidí, než má pokoj postelí (%d).', $maxClenu);
        }
        if (count(array_unique($noci)) > 1) {
            $varovani[] = 'Členové skupiny nemají objednané stejné noci. Postel, která některou noc zůstane volná, můžeme obsadit někým dalším.';
        }

        return [
            'kod'      => $kod,
            'maxClenu' => $maxClenu,
            'clenove'  => $clenove,
            'varovani' => $varovani,
        ];
    }

    /**
     * Name of the type for people, like "Postel na 2L koleji", the code when the year has no such item.
     */
    public function nazevTypu(string $typUbytovani): string
    {
        if (!isset($this->nazvyTypu[$typUbytovani])) {
            $nazev = dbOneCol(<<<'SQL'
SELECT nazev
FROM shop_predmety
WHERE typ = $0
  AND model_rok = $1
  AND LEFT(kod_predmetu, CHAR_LENGTH(kod_predmetu) - 3) = $2
ORDER BY ubytovani_den
LIMIT 1
SQL,
                [0 => TypPredmetu::UBYTOVANI, 1 => $this->rok, 2 => $typUbytovani],
            );
            $this->nazvyTypu[$typUbytovani] = $nazev !== null
                ? trim(Shop::bezDne(trim((string)$nazev)))
                : $typUbytovani;
        }

        return $this->nazvyTypu[$typUbytovani];
    }

    /**
     * @throws ChybaUbytovaciSkupiny
     */
    private function typProSkupinu(int $idUzivatele): string
    {
        $typy = $this->ubytovaniClenu([$idUzivatele])[$idUzivatele]['typy'] ?? [];
        if ($typy === []) {
            throw new ChybaUbytovaciSkupiny('Do skupiny spolubydlících se lze přidat jen s objednaným ubytováním.');
        }
        if (count($typy) > 1) {
            throw new ChybaUbytovaciSkupiny('Pro skupinu spolubydlících je potřeba mít na všechny noci stejný typ ubytování.');
        }

        return reset($typy);
    }

    /**
     * @param int[] $idsClenu
     * @return string[] types of accommodation of the members who have some
     */
    private function typySkupiny(array $idsClenu): array
    {
        $typy = [];
        foreach ($this->ubytovaniClenu($idsClenu) as $ubytovani) {
            foreach ($ubytovani['typy'] as $typ) {
                $typy[$typ] = $typ;
            }
        }

        return array_values($typy);
    }

    /**
     * @param int[] $idsClenu
     * @return int[] the members who have accommodation
     */
    private function sUbytovanim(array $idsClenu): array
    {
        return array_keys($this->ubytovaniClenu($idsClenu));
    }

    /**
     * @param int[] $idsUzivatelu
     * @return array<int, array{typy: list<string>, noci: list<int>}> only the users who have accommodation
     */
    private function ubytovaniClenu(array $idsUzivatelu): array
    {
        if ($idsUzivatelu === []) {
            return [];
        }
        $radky = dbFetchAll(<<<'SQL'
SELECT shop_nakupy.id_uzivatele,
       LEFT(shop_predmety.kod_predmetu, CHAR_LENGTH(shop_predmety.kod_predmetu) - 3) AS typ,
       shop_predmety.ubytovani_den
FROM shop_nakupy
JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu
WHERE shop_nakupy.id_uzivatele IN ($0)
  AND shop_nakupy.rok = $1
  AND shop_predmety.typ = $2
ORDER BY shop_predmety.ubytovani_den
SQL,
            [0 => $idsUzivatelu, 1 => $this->rok, 2 => TypPredmetu::UBYTOVANI],
        );

        $ubytovani = [];
        foreach ($radky as $radek) {
            $idUzivatele = (int)$radek['id_uzivatele'];
            $ubytovani[$idUzivatele] ??= ['typy' => [], 'noci' => []];
            if (!in_array($radek['typ'], $ubytovani[$idUzivatele]['typy'], true)) {
                $ubytovani[$idUzivatele]['typy'][] = (string)$radek['typ'];
            }
            $ubytovani[$idUzivatele]['noci'][] = (int)$radek['ubytovani_den'];
        }

        return $ubytovani;
    }

    /**
     * @param int[] $noci indexes of nights from the start of GameCon, sorted
     * @return string like "St–Ne"
     */
    private function rozsahNoci(array $noci): string
    {
        $prvniNoc    = DateTimeCz::poradiDneVTydnuNaPrelomDnuVeZkratkach(
            DateTimeGamecon::poradiDneVTydnuPodleIndexuOdZacatkuGameconu(min($noci), $this->rok),
            true,
        );
        $posledniNoc = DateTimeCz::poradiDneVTydnuNaPrelomDnuVeZkratkach(
            DateTimeGamecon::poradiDneVTydnuPodleIndexuOdZacatkuGameconu(max($noci), $this->rok),
            true,
        );

        return explode('–', $prvniNoc)[0] . '–' . explode('–', $posledniNoc)[1];
    }

    private function vygenerujKod(): string
    {
        $pocetZnaku = strlen(self::ZNAKY_KODU);
        for ($pokus = 0; $pokus < 20; $pokus++) {
            $kod = '';
            for ($i = 0; $i < self::DELKA_KODU; $i++) {
                $kod .= self::ZNAKY_KODU[random_int(0, $pocetZnaku - 1)];
            }
            // unique across years, so that last year's code does not lead to somebody else's group
            $obsazeny = dbOneCol(<<<'SQL'
SELECT 1 FROM ubytovani_skupiny_clenove WHERE kod_skupiny = $0 LIMIT 1
SQL,
                [0 => $kod],
            );
            if ($obsazeny === null) {
                return $kod;
            }
        }

        throw new \RuntimeException('Nepodařilo se vygenerovat volný kód skupiny spolubydlících');
    }
}
