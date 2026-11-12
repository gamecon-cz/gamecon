<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Rezervace kostýmů na aktivity, viz docs/generated/pujcovna-kostymu.md
 *
 * Běží na legacy spojení (dbQuery): rezervace stojí na přihlášce v akce_prihlaseni, kterou legacy kód mění
 * ve vlastních transakcích, a Doctrine spojení by na její řádky čekalo.
 */
final class PujcovnaKostymu
{
    /**
     * Má kostým (řádek `kostym`) v čase aktivity jinou rezervaci nebo nevrácený výdej? Intervaly
     * [začátek, konec + výměna) se nesmí protnout. Vydaný a nevrácený kostým, jehož aktivita už neexistuje,
     * koliduje vždy. Parametry: $1 ID vlastní rezervace (nepřekáží), $2 začátek, $3 konec, $4 minut na výměnu.
     */
    private const string KOLIZE_KOSTYMU = <<<'SQL'
(EXISTS (
    SELECT 1
    FROM kostym_rezervace
    JOIN akce_seznam ON akce_seznam.id_akce = kostym_rezervace.id_akce
    WHERE kostym_rezervace.id_kostymu = kostym.id
        AND kostym_rezervace.id <> $1
        AND akce_seznam.zacatek < $3 + INTERVAL $4 MINUTE
        AND $2 < akce_seznam.konec + INTERVAL $4 MINUTE
) OR EXISTS (
    SELECT 1
    FROM kostym_vydej
    LEFT JOIN akce_seznam ON akce_seznam.id_akce = kostym_vydej.id_akce
    WHERE kostym_vydej.id_kostymu = kostym.id
        AND kostym_vydej.vraceno IS NULL
        AND (akce_seznam.id_akce IS NULL
            OR (akce_seznam.zacatek < $3 + INTERVAL $4 MINUTE AND $2 < akce_seznam.konec + INTERVAL $4 MINUTE))
))
SQL;

    private readonly EmailyPujcovny $emaily;

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly NastaveniPujcovny $nastaveni,
        ?EmailyPujcovny $emaily = null,
    ) {
        $this->emaily = $emaily ?? new EmailyPujcovny($systemoveNastaveni, $nastaveni);
    }

    public static function zGlobals(): self
    {
        return self::zSystemovehoNastaveni(SystemoveNastaveni::zGlobals());
    }

    public static function zSystemovehoNastaveni(SystemoveNastaveni $systemoveNastaveni): self
    {
        return new self($systemoveNastaveni, NastaveniPujcovny::zSystemovehoNastaveni($systemoveNastaveni));
    }

    public function getNastaveni(): NastaveniPujcovny
    {
        return $this->nastaveni;
    }

    /**
     * Rezervuje účastníkovi kostým na aktivitu, nebo mu vymění kostým, který už na ni má.
     *
     * @param bool $jakoSpravce správce půjčovny v adminu: neplatí pro něj uzávěrka, limit na osobu ani zveřejnění katalogu
     *
     * @throws ChybaPujcovny
     */
    public function rezervuj(
        int $idKostymu,
        Aktivita $aktivita,
        \Uzivatel $ucastnik,
        \Uzivatel $rezervujici,
        bool $jakoSpravce = false,
    ): VysledekRezervaceKostymuEnum {
        $this->overAktivitu($aktivita, $jakoSpravce);

        dbBegin();
        try {
            $this->zamkniKostymKRezervaci($idKostymu);
            $this->zamkniPrihlasku($aktivita, $ucastnik, $rezervujici);
            $stavajici = $this->rezervaceUcastnika($aktivita->id(), $ucastnik->id(), zamknout: true);

            if ($stavajici?->getIdKostymu() === $idKostymu) {
                dbCommit();

                return VysledekRezervaceKostymuEnum::BEZE_ZMENY;
            }
            if ($stavajici !== null) {
                if (! $jakoSpravce) {
                    $this->overUzaverku($aktivita, 'vyměnit');
                }
                $this->overZeNeniVydany($stavajici, 'vyměnit');
            } elseif (! $jakoSpravce) {
                $this->overLimit($ucastnik);
            }
            $this->overVolnoKostymu($idKostymu, $aktivita, $stavajici?->getId());

            if ($stavajici !== null) {
                dbQuery(<<<'SQL'
UPDATE kostym_rezervace SET id_kostymu = $0, rezervoval = $1, rezervovano = $2 WHERE id = $3
SQL, [$idKostymu, $rezervujici->id(), $this->tedDb(), $stavajici->getId()]);
            } else {
                dbQuery(<<<'SQL'
INSERT INTO kostym_rezervace SET id_kostymu = $0, id_akce = $1, id_uzivatele = $2, rezervoval = $3, rezervovano = $4
SQL, [$idKostymu, $aktivita->id(), $ucastnik->id(), $rezervujici->id(), $this->tedDb()]);
            }
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        $this->emaily->posliPotvrzeni(
            $this->rezervaceUcastnika($aktivita->id(), $ucastnik->id()),
            $aktivita,
            $ucastnik,
        );

        return $stavajici !== null
            ? VysledekRezervaceKostymuEnum::VYMENENA
            : VysledekRezervaceKostymuEnum::NOVA;
    }

    /**
     * Rezervaci smaže s přihláškou cizí klíč, sám nic neohlásí. @see Aktivita::odhlas() si proto rezervaci
     * přečte před smazáním přihlášky a po odhlášení pošle e-mail touto metodou.
     */
    public function oznamUvolneni(RezervaceKostymu $rezervace, Aktivita $aktivita, \Uzivatel $ucastnik): void
    {
        $this->emaily->posliUvolneni($rezervace, $aktivita, $ucastnik);
    }

    /**
     * @param bool $jakoSpravce správce půjčovny v adminu: neplatí pro něj uzávěrka a smí zrušit i rezervaci vydaného kostýmu
     *
     * @return RezervaceKostymu|null zrušená rezervace, null když žádná nebyla
     *
     * @throws ChybaPujcovny
     */
    public function zrus(Aktivita $aktivita, \Uzivatel $ucastnik, bool $jakoSpravce = false): ?RezervaceKostymu
    {
        dbBegin();
        try {
            $rezervace = $this->rezervaceUcastnika($aktivita->id(), $ucastnik->id(), zamknout: true);
            if ($rezervace === null) {
                dbCommit();

                return null;
            }
            if (! $jakoSpravce) {
                $this->overUzaverku($aktivita, 'zrušit');
                $this->overZeNeniVydany($rezervace, 'zrušit');
            }
            dbQuery('DELETE FROM kostym_rezervace WHERE id = $0', [$rezervace->getId()]);
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        return $rezervace;
    }

    public function rezervaceUcastnika(int $idAktivity, int $idUzivatele, bool $zamknout = false): ?RezervaceKostymu
    {
        $zamek = $zamknout
            ? 'FOR UPDATE'
            : '';
        $radek = dbOneLine(<<<SQL
SELECT kostym_rezervace.id, kostym_rezervace.id_kostymu, kostym.nazev AS nazev_kostymu,
       kostym_rezervace.id_akce, kostym_rezervace.id_uzivatele
FROM kostym_rezervace
JOIN kostym ON kostym.id = kostym_rezervace.id_kostymu
WHERE kostym_rezervace.id_akce = $0 AND kostym_rezervace.id_uzivatele = $1
{$zamek}
SQL, [$idAktivity, $idUzivatele]);

        return $radek
            ? RezervaceKostymu::zRadku($radek)
            : null;
    }

    /**
     * Pravidla, která nezávisí na kostýmu ani na účastníkovi. Od nejlevnějšího.
     *
     * @throws ChybaPujcovny
     */
    private function overAktivitu(Aktivita $aktivita, bool $jakoSpravce): void
    {
        if (! $jakoSpravce && ! $this->nastaveni->isKatalogZverejnen()) {
            throw new ChybaPujcovny('Půjčovna kostýmů zatím rezervace nepřijímá.');
        }
        $zacatek = $aktivita->zacatek();
        if ($zacatek === null || $aktivita->konec() === null) {
            throw new ChybaPujcovny('Aktivita nemá určený začátek a konec, kostým na ni rezervovat nejde.');
        }
        if (! $this->nastaveni->isTypAktivityPovolen($aktivita->typId())) {
            throw new ChybaPujcovny(sprintf('Na aktivity typu „%s“ se kostýmy nepůjčují.', $aktivita->typ()->nazev()));
        }
        if ($this->systemoveNastaveni->ted() >= $zacatek) {
            throw new ChybaPujcovny('Aktivita už začala, kostým na ni rezervovat nejde.');
        }
    }

    /**
     * Zamkne řádek kostýmu, takže rezervace téhož kostýmu se kontrolují a zapisují jedna po druhé.
     *
     * @throws ChybaPujcovny
     */
    private function zamkniKostymKRezervaci(int $idKostymu): void
    {
        $stav = dbOneCol('SELECT stav FROM kostym WHERE id = $0 FOR UPDATE', [$idKostymu]);
        if ($stav === null) {
            throw new ChybaPujcovny('Kostým neexistuje.');
        }
        $stav = StavKostymuEnum::from((string) $stav);
        if (! $stav->isRezervovatelny()) {
            throw new ChybaPujcovny(sprintf('Kostým je %s, rezervovat ho nejde.', $stav->getNazev()));
        }
    }

    /**
     * Rezervovat jde jen na přihlášku ve stavu „přihlášen“. Sledující přihlášku nemají vůbec (akce_prihlaseni_spec),
     * „dorazil“ a „dorazil jako náhradník“ jsou až po začátku aktivity.
     *
     * @throws ChybaPujcovny
     */
    private function zamkniPrihlasku(Aktivita $aktivita, \Uzivatel $ucastnik, \Uzivatel $rezervujici): void
    {
        $stav = dbOneCol(<<<'SQL'
SELECT id_stavu_prihlaseni FROM akce_prihlaseni WHERE id_akce = $0 AND id_uzivatele = $1 FOR UPDATE
SQL, [$aktivita->id(), $ucastnik->id()]);
        if ($stav === null || (int) $stav !== StavPrihlaseni::PRIHLASEN) {
            throw new ChybaPujcovny($ucastnik->id() === $rezervujici->id() ? 'Kostým jde rezervovat jen na aktivitu, na kterou máš přihlášku (ne jako sledující).' : 'Účastník nemá na aktivitu přihlášku, kostým mu rezervovat nejde.');
        }
    }

    /**
     * @throws ChybaPujcovny
     */
    private function overUzaverku(Aktivita $aktivita, string $coSeDela): void
    {
        $uzaverka = \DateTimeImmutable::createFromInterface($aktivita->zacatek())
            ->modify(sprintf('-%d hours', $this->nastaveni->getUzaverkaHodinPredZacatkem()));
        if ($this->systemoveNastaveni->ted() > $uzaverka) {
            throw new ChybaPujcovny(sprintf('Kostým jde %s nejpozději %d h před začátkem aktivity. Potom už jen v půjčovně.', $coSeDela, $this->nastaveni->getUzaverkaHodinPredZacatkem()));
        }
    }

    /**
     * @throws ChybaPujcovny
     */
    private function overZeNeniVydany(RezervaceKostymu $rezervace, string $coSeDela): void
    {
        $vydany = dbOneCol(<<<'SQL'
SELECT 1 FROM kostym_vydej WHERE id_rezervace = $0 AND vraceno IS NULL LIMIT 1
SQL, [$rezervace->getId()]);
        if ($vydany) {
            throw new ChybaPujcovny(sprintf('Kostým %s je už vydaný. Než ho půjde %s, musí se vrátit do půjčovny.', $rezervace->getNazevKostymu(), $coSeDela));
        }
    }

    /**
     * Souběžné rezervace téhož člověka na různé aktivity tu nic nezamyká, limit tak může o jednu přetéct.
     *
     * @throws ChybaPujcovny
     */
    private function overLimit(\Uzivatel $ucastnik): void
    {
        $limit = $this->nastaveni->getLimitNaOsobu();
        if ($limit <= 0) {
            return;
        }
        $pocet = (int) dbOneCol(<<<'SQL'
SELECT COUNT(*)
FROM kostym_rezervace
JOIN akce_seznam ON akce_seznam.id_akce = kostym_rezervace.id_akce
WHERE kostym_rezervace.id_uzivatele = $0 AND akce_seznam.rok = $1
SQL, [$ucastnik->id(), $this->systemoveNastaveni->rocnik()]);
        if ($pocet >= $limit) {
            throw new ChybaPujcovny(sprintf('Na jeden ročník jde rezervovat nejvýš %d %s, víc už nejde.', $limit, $limit === 1 ? 'kostým' : ($limit < 5 ? 'kostýmy' : 'kostýmů')));
        }
    }

    /**
     * Kostým musí být volný od začátku aktivity do jejího konce a ještě po dobu výměny, a stejně tak
     * po každé jiné jeho rezervaci a vydání: intervaly [začátek, konec + výměna) se nesmí protnout.
     * Vydaný a nevrácený kostým, jehož aktivita už neexistuje, blokuje vždy.
     *
     * @throws ChybaPujcovny
     */
    private function overVolnoKostymu(int $idKostymu, Aktivita $aktivita, ?int $idVlastniRezervace): void
    {
        $kolize = dbOneCol(
            'SELECT ' . self::KOLIZE_KOSTYMU . ' FROM kostym WHERE kostym.id = $0',
            $this->parametryKolize($idKostymu, $aktivita, $idVlastniRezervace),
        );
        if ($kolize) {
            throw new ChybaPujcovny(sprintf('Kostým je v čase aktivity už rezervovaný (počítá se i %d minut na výměnu mezi aktivitami).', $this->nastaveni->getCasNaVymenuMinut()));
        }
    }

    /**
     * Aktivity ročníku, na které účastník může mít kostým: je na ně přihlášený (ne jako sledující), jsou povoleného
     * typu, mají začátek i konec a ještě nezačaly. Od nejbližší.
     *
     * @return list<Aktivita>
     */
    public function aktivityKRezervaci(\Uzivatel $ucastnik): array
    {
        $typy = $this->nastaveni->getTypyAktivit();
        if ($typy === []) {
            return [];
        }
        $ids = dbFetchColumn(<<<'SQL'
SELECT akce_seznam.id_akce
FROM akce_prihlaseni
JOIN akce_seznam ON akce_seznam.id_akce = akce_prihlaseni.id_akce
WHERE akce_prihlaseni.id_uzivatele = $0
    AND akce_prihlaseni.id_stavu_prihlaseni = $1
    AND akce_seznam.typ IN ($2)
    AND akce_seznam.zacatek > $3
    AND akce_seznam.konec IS NOT NULL
    AND akce_seznam.rok = $4
SQL, [$ucastnik->id(), StavPrihlaseni::PRIHLASEN, $typy, $this->tedDb(), $this->systemoveNastaveni->rocnik()]);
        $aktivity = Aktivita::zIds($ids, $this->systemoveNastaveni);
        usort($aktivity, static fn (Aktivita $prvni, Aktivita $druha): int => $prvni->zacatek() <=> $druha->zacatek());

        return array_values($aktivity);
    }

    /**
     * Kostýmy k dispozici, které jdou na aktivitu rezervovat, protože v jejím čase (i s výměnou) nejsou
     * rezervované ani vydané. Vlastní rezervace účastníka na tutéž aktivitu nepřekáží (výměna kostýmu).
     *
     * @return list<int> ID kostýmů
     */
    public function volneKostymy(Aktivita $aktivita, ?\Uzivatel $ucastnik = null): array
    {
        if ($aktivita->zacatek() === null || $aktivita->konec() === null) {
            return [];
        }
        $vlastniRezervace = $ucastnik !== null
            ? $this->rezervaceUcastnika($aktivita->id(), $ucastnik->id())
            : null;
        $parametry = $this->parametryKolize(0, $aktivita, $vlastniRezervace?->getId());
        // výpis $0 (jeden kostým) nepoužívá a dbQueryAssemble() by bez něj číslování posunul o jedno
        unset($parametry[0]);
        $parametry[5] = StavKostymuEnum::K_DISPOZICI->value;

        return array_map('intval', dbFetchColumn(
            'SELECT kostym.id FROM kostym WHERE kostym.stav = $5 AND NOT ' . self::KOLIZE_KOSTYMU,
            $parametry,
        ));
    }

    /**
     * @return array<int, int|string> parametry pro @see KOLIZE_KOSTYMU
     */
    private function parametryKolize(int $idKostymu, Aktivita $aktivita, ?int $idVlastniRezervace): array
    {
        return [
            $idKostymu,
            $idVlastniRezervace ?? 0,
            $aktivita->zacatek()->format('Y-m-d H:i:s'),
            $aktivita->konec()->format('Y-m-d H:i:s'),
            $this->nastaveni->getCasNaVymenuMinut(),
        ];
    }

    private function tedDb(): string
    {
        return $this->systemoveNastaveni->ted()->format('Y-m-d H:i:s');
    }
}
