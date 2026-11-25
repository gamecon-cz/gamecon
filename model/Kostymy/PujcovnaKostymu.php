<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Vyjimkovac\Vyjimkovac;

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

    /**
     * E-maily o rezervacích uvolněných během @see sOznamenimUvolneniPoZmene(), null = posílat hned.
     *
     * @var list<\Closure(): void>|null
     */
    private static ?array $odlozenaOznameniUvolneni = null;

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
            // Tentýž kostým znovu se pozná bez zámku rezervace: výdej drží rezervaci a čeká na její kostým, který
            // tady už je zamčený, takže by se zablokovaly. Kostým rezervace mění jen ten, kdo drží zámek přihlášky.
            $stavajici = $this->rezervaceUcastnika($aktivita->id(), $ucastnik->id());
            if ($stavajici?->getIdKostymu() === $idKostymu) {
                dbCommit();

                return VysledekRezervaceKostymuEnum::BEZE_ZMENY;
            }
            if ($stavajici !== null) {
                $stavajici = $this->rezervaceUcastnika($aktivita->id(), $ucastnik->id(), zamknout: true);
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

        // znovu z databáze kvůli názvu kostýmu; mezitím ji mohlo zrušit odhlášení z aktivity
        $rezervace = $this->rezervaceUcastnika($aktivita->id(), $ucastnik->id());
        if ($rezervace !== null) {
            $this->posliBezChyby(
                fn () => $this->emaily->posliPotvrzeni($rezervace, $aktivita, $ucastnik),
                sprintf('s potvrzením rezervace účastníkovi %d na aktivitu %d', $ucastnik->id(), $aktivita->id()),
            );
        }

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
        $posli = fn () => $this->posliBezChyby(
            fn () => $this->emaily->posliUvolneni($rezervace, $aktivita, $ucastnik),
            sprintf('o uvolnění rezervace účastníkovi %d na aktivitě %d', $ucastnik->id(), $aktivita->id()),
        );
        if (self::$odlozenaOznameniUvolneni !== null) {
            self::$odlozenaOznameniUvolneni[] = $posli;

            return;
        }
        $posli();
    }

    /**
     * Provede $zmena a e-maily o rezervacích, které při ní uvolní @see oznamUvolneni(), pošle až po ní. Pro změnu
     * v transakci, třeba smazání aktivity, které odhlásí všechny přihlášené: e-mail nesmí odejít o rezervaci, kterou
     * rollback vrátí. Když $zmena spadne, nepošle žádný.
     *
     * @template T
     *
     * @param callable(): T $zmena
     *
     * @return T
     */
    public static function sOznamenimUvolneniPoZmene(callable $zmena): mixed
    {
        if (self::$odlozenaOznameniUvolneni !== null) {
            // vnořená změna, e-maily pošle ta vnější
            return $zmena();
        }
        self::$odlozenaOznameniUvolneni = [];
        try {
            $vysledek = $zmena();
            $odlozenaOznameni = self::$odlozenaOznameniUvolneni;
        } finally {
            self::$odlozenaOznameniUvolneni = null;
        }
        foreach ($odlozenaOznameni as $posli) {
            $posli();
        }

        return $vysledek;
    }

    /**
     * E-mail půjčovny jde až po zapsané změně, a ta platí, i když ho nejde odeslat (pošta, nevyplněná šablona).
     * Chyba proto skončí ve výjimkovači, a když nejde zapsat ani tam, v logu PHP; ne u účastníka ani v transakci,
     * ve které změna proběhla.
     */
    private function posliBezChyby(callable $posli, string $coSePosila): void
    {
        try {
            $posli();
        } catch (\Throwable $chyba) {
            $zprava = "E-mail půjčovny kostýmů {$coSePosila} se nepodařilo odeslat";
            try {
                Vyjimkovac::vytvorZGlobals()->zaloguj(new \RuntimeException($zprava, previous: $chyba));
            } catch (\Throwable $chybaVyjimkovace) {
                error_log("{$zprava}: {$chyba->getMessage()} (výjimkovač: {$chybaVyjimkovace->getMessage()})");
            }
        }
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
            // Přihláška první, jako u rezervace: odhlášení ji maže, a rezervaci až kaskádou. Se zámkem jen rezervace
            // by zrušení a odhlášení zamykaly v opačném pořadí (deadlock) a snímek transakce by vznikl dřív,
            // než výměna, která na zrušení čeká, uvidí, že rezervace zmizela.
            $this->zamkniPrihlaskuKeZmene($aktivita, $ucastnik);
            $rezervace = $this->rezervaceUcastnika($aktivita->id(), $ucastnik->id(), zamknout: true);
            if ($rezervace === null) {
                dbCommit();

                return null;
            }
            if (! $jakoSpravce) {
                $this->overUzaverku($aktivita, 'zrušit');
                $this->overZeNeniVydany($rezervace, 'zrušit');
            }
            dbQuery(<<<'SQL'
DELETE FROM kostym_rezervace WHERE id = $0
SQL, [$rezervace->getId()]);
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        return $rezervace;
    }

    /**
     * @param bool $zamknout zamkne nalezenou rezervaci podle primárního klíče, a jen ji. Hledání podle aktivity
     *                       a účastníka nezamyká: zámek neexistujícího řádku v unikátním indexu je gap lock a dvě
     *                       první rezervace na tutéž aktivitu by na sebe při INSERT čekaly (deadlock). Rezervaci
     *                       téhož účastníka na téže aktivitě nikdo jiný nezmění, volající už drží zámek jeho přihlášky
     *                       (@see zamkniPrihlaskuKeZmene()), a snímek transakce tak vzniká až po něm.
     *                       Kostým rezervace se nezamyká: rezervace kostým zamyká jako první
     *                       (@see zamkniKostymKRezervaci()), a dvě výměny opačným směrem (K1 → K2 a K2 → K1) by tak
     *                       zamykaly oba kostýmy v opačném pořadí
     */
    public function rezervaceUcastnika(int $idAktivity, int $idUzivatele, bool $zamknout = false): ?RezervaceKostymu
    {
        $radek = dbOneLine(<<<'SQL'
SELECT kostym_rezervace.id, kostym_rezervace.id_kostymu, kostym_rezervace.id_akce, kostym_rezervace.id_uzivatele
FROM kostym_rezervace
WHERE kostym_rezervace.id_akce = $0 AND kostym_rezervace.id_uzivatele = $1
SQL, [$idAktivity, $idUzivatele]);
        if ($radek && $zamknout) {
            // co se s ní mezitím stalo (výměna, zrušení), ukáže až čtení se zámkem
            $radek = dbOneLine(<<<'SQL'
SELECT kostym_rezervace.id, kostym_rezervace.id_kostymu, kostym_rezervace.id_akce, kostym_rezervace.id_uzivatele
FROM kostym_rezervace
WHERE kostym_rezervace.id = $0
FOR UPDATE
SQL, [$radek['id']]);
        }
        if (! $radek) {
            return null;
        }
        $radek['nazev_kostymu'] = dbOneCol(<<<'SQL'
SELECT nazev FROM kostym WHERE id = $0
SQL, [$radek['id_kostymu']]);

        return RezervaceKostymu::zRadku($radek);
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
        $stav = dbOneCol(<<<'SQL'
SELECT stav FROM kostym WHERE id = $0 FOR UPDATE
SQL, [$idKostymu]);
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
        if ($this->zamkniPrihlaskuKeZmene($aktivita, $ucastnik) !== StavPrihlaseni::PRIHLASEN) {
            throw new ChybaPujcovny($ucastnik->id() === $rezervujici->id() ? 'Kostým jde rezervovat jen na aktivitu, na kterou máš přihlášku (ne jako sledující).' : 'Účastník nemá na aktivitu přihlášku, kostým mu rezervovat nejde.');
        }
    }

    /**
     * Zámek přihlášky drží každá změna rezervace i odhlášení (to přihlášku maže a rezervaci smaže kaskáda), takže
     * změny rezervací téhož účastníka na téže aktivitě jdou jedna po druhé, a ve stejném pořadí zámků.
     *
     * @return int|null stav přihlášky, null bez přihlášky
     */
    private function zamkniPrihlaskuKeZmene(Aktivita $aktivita, \Uzivatel $ucastnik): ?int
    {
        $stav = dbOneCol(<<<'SQL'
SELECT id_stavu_prihlaseni FROM akce_prihlaseni WHERE id_akce = $0 AND id_uzivatele = $1 FOR UPDATE
SQL, [$aktivita->id(), $ucastnik->id()]);

        return $stav === null
            ? null
            : (int) $stav;
    }

    /**
     * @throws ChybaPujcovny
     */
    private function overUzaverku(Aktivita $aktivita, string $coSeDela): void
    {
        if ($aktivita->zacatek() === null) {
            throw new ChybaPujcovny(sprintf('Aktivita nemá určený začátek, kostým na ni jde %s už jen v půjčovně.', $coSeDela));
        }
        if ($this->isPoUzaverce($aktivita)) {
            throw new ChybaPujcovny(sprintf('Kostým jde %s nejpozději %d h před začátkem aktivity. Potom už jen v půjčovně.', $coSeDela, $this->nastaveni->getUzaverkaHodinPredZacatkem()));
        }
    }

    /**
     * Rezervaci na aktivitu po uzávěrce zruší nebo vymění už jen správce půjčovny. Aktivita bez začátku uzávěrku
     * nemá: rezervaci na ni zruší jen on, a vyměnit ji nejde vůbec (@see overAktivitu() chce začátek i konec).
     */
    public function isPoUzaverce(Aktivita $aktivita): bool
    {
        $zacatek = $aktivita->zacatek();
        if ($zacatek === null) {
            return true;
        }
        $uzaverka = \DateTimeImmutable::createFromInterface($zacatek)
            ->modify(sprintf('-%d hours', $this->nastaveni->getUzaverkaHodinPredZacatkem()));

        return $this->systemoveNastaveni->ted() > $uzaverka;
    }

    /**
     * Čte poslední stav se zámkem, ne snímek transakce: ten vznikl hledáním rezervace ještě před čekáním na její
     * zámek, a výdej, na který se čekalo, by v něm chyběl.
     *
     * @throws ChybaPujcovny
     */
    private function overZeNeniVydany(RezervaceKostymu $rezervace, string $coSeDela): void
    {
        $vydany = dbOneCol(<<<'SQL'
SELECT 1 FROM kostym_vydej WHERE id_rezervace = $0 AND vraceno IS NULL LIMIT 1 LOCK IN SHARE MODE
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
            throw new ChybaPujcovny(sprintf('Na jeden ročník jde rezervovat nejvýš %s, víc už nejde.', FormatPujcovny::pocetKostymu($limit)));
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
        $kolizeKostymu = self::KOLIZE_KOSTYMU;
        $kolize = dbOneCol(<<<SQL
SELECT {$kolizeKostymu} FROM kostym WHERE kostym.id = $0
SQL, $this->parametryKolize($idKostymu, $aktivita, $idVlastniRezervace));
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
        $aktivity = Aktivita::zIds($this->idsAktivitKRezervaci($ucastnik), $this->systemoveNastaveni);
        usort($aktivity, static fn (Aktivita $prvni, Aktivita $druha): int => $prvni->zacatek() <=> $druha->zacatek());

        return array_values($aktivity);
    }

    /**
     * Pro program účastníka: kostým, který má na aktivitu rezervovaný (odkaz na vlastní rezervace), nebo u aktivity,
     * na kterou si ho ještě může rezervovat, odkaz do katalogu na kostýmy volné v jejím čase. Odkaz do katalogu
     * jen se zveřejněným katalogem.
     *
     * @return array<int, array{nazev: string|null, url: string}> podle ID aktivity
     */
    public function kostymyVProgramu(\Uzivatel $ucastnik, int $rok): array
    {
        $kostymy = [];
        if ($this->nastaveni->isKatalogZverejnen() && $rok === $this->systemoveNastaveni->rocnik()) {
            foreach ($this->idsAktivitKRezervaci($ucastnik) as $idAktivity) {
                $kostymy[$idAktivity] = [
                    'nazev' => null,
                    'url'   => URL_WEBU . '/kostymy?aktivita=' . $idAktivity,
                ];
            }
        }
        foreach (dbFetchAll(<<<'SQL'
SELECT kostym_rezervace.id_akce, kostym.nazev
FROM kostym_rezervace
JOIN kostym ON kostym.id = kostym_rezervace.id_kostymu
JOIN akce_seznam ON akce_seznam.id_akce = kostym_rezervace.id_akce
WHERE kostym_rezervace.id_uzivatele = $0
    AND akce_seznam.rok = $1
SQL, [$ucastnik->id(), $rok]) as $rezervace) {
            $kostymy[(int) $rezervace['id_akce']] = [
                'nazev' => (string) $rezervace['nazev'],
                'url'   => URL_WEBU . '/kostymy/moje',
            ];
        }

        return $kostymy;
    }

    /**
     * Rezervované kostýmy účastníků aktivity (pro online prezenci vypravěče).
     *
     * @return array<int, string> název kostýmu podle ID účastníka
     */
    public function kostymyUcastnikuAktivity(int $idAktivity): array
    {
        return array_map('strval', dbFetchPairs(<<<'SQL'
SELECT kostym_rezervace.id_uzivatele, kostym.nazev
FROM kostym_rezervace
JOIN kostym ON kostym.id = kostym_rezervace.id_kostymu
WHERE kostym_rezervace.id_akce = $0
SQL, [$idAktivity]));
    }

    /**
     * @return list<int>
     */
    private function idsAktivitKRezervaci(\Uzivatel $ucastnik): array
    {
        $typy = $this->nastaveni->getTypyAktivit();
        if ($typy === []) {
            return [];
        }

        return array_map('intval', dbFetchColumn(<<<'SQL'
SELECT akce_seznam.id_akce
FROM akce_prihlaseni
JOIN akce_seznam ON akce_seznam.id_akce = akce_prihlaseni.id_akce
WHERE akce_prihlaseni.id_uzivatele = $0
    AND akce_prihlaseni.id_stavu_prihlaseni = $1
    AND akce_seznam.typ IN ($2)
    AND akce_seznam.zacatek > $3
    AND akce_seznam.konec IS NOT NULL
    AND akce_seznam.rok = $4
SQL, [$ucastnik->id(), StavPrihlaseni::PRIHLASEN, $typy, $this->tedDb(), $this->systemoveNastaveni->rocnik()]));
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
        $kolizeKostymu = self::KOLIZE_KOSTYMU;

        return array_map('intval', dbFetchColumn(<<<SQL
SELECT kostym.id FROM kostym WHERE kostym.stav = $5 AND NOT {$kolizeKostymu}
SQL, $parametry));
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
