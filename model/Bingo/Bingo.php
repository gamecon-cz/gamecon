<?php

declare(strict_types=1);

namespace Gamecon\Bingo;

use Psr\Clock\ClockInterface;
use Random\Randomizer;

/**
 * Bingo z jednání rady. Fráze jsou společné všem soutěžím, soutěž je jeden termín jednání (`bingo_jednani`).
 *
 * Před startem si org vygeneruje kartu 5×5 s volným středem (uloží se mu) a může ji měnit. Soutěž začne, když ji
 * někdo spustí, nebo sama v 19:00 v den jednání. Pak jsou karty zamčené, kdo kartu nemá, dostane jen jednu, a dokud
 * ji nemá, nevidí, co už zaznělo. Kdo odškrtne, že fráze zazněla, odškrtne ji všem: zaznění patří k jednání a k frázi,
 * ne ke kartě. První bingo soutěž ukončí a vítěze uloží u karty (`bingo_karta.vyhra`).
 *
 * Co kdo smí, rozhodují predikáty is…Povoleno() a getZrusitelneFraze(): podle nich getStav() nastaví tlačítka a změny
 * je kontrolují na serveru.
 */
final class Bingo
{
    public const int VELIKOST_KARTY = 5;

    /** Prostřední pole karty, nemá frázi a platí jako odškrtnuté. */
    public const int VOLNE_POLE = 12;

    public const int FRAZI_NA_KARTE = self::VELIKOST_KARTY * self::VELIKOST_KARTY - 1;

    public const int MAX_DELKA_FRAZE = 255;

    /** Kdy soutěž začne sama, když ji nikdo nespustí dřív. */
    public const string AUTOMATICKY_START = '19:00';

    /** Jednání může skončit po půlnoci, soutěž proto běží do rána dalšího dne. */
    private const int JEDNANI_KONCI_HODIN_PO_PULNOCI = 6;

    /** Za kolik sekund se stránka znovu zeptá na stav: za běhu soutěže, v den jednání před startem a po bingu, jinak. */
    private const int SLEDOVAT_ZA_BEHU = 3;

    private const int SLEDOVAT_V_DEN_JEDNANI = 10;

    private const int SLEDOVAT_JINAK = 60;

    /** Čas, podle kterého se právě rozhoduje, @see sJednimCasem(); jinak platí hodiny. */
    private ?\DateTimeImmutable $tedRozhodnuti = null;

    public function __construct(
        private readonly ClockInterface $hodiny,
    ) {
    }

    /**
     * Řádky, sloupce a obě úhlopříčky karty jako seznamy pozic, pozice jdou po řádcích od nuly.
     *
     * @return list<list<int>>
     */
    public static function getRady(): array
    {
        $rady = [];
        $uhlopricka = [];
        $protiuhlopricka = [];
        for ($i = 0; $i < self::VELIKOST_KARTY; $i++) {
            $radek = [];
            $sloupec = [];
            for ($j = 0; $j < self::VELIKOST_KARTY; $j++) {
                $radek[] = $i * self::VELIKOST_KARTY + $j;
                $sloupec[] = $j * self::VELIKOST_KARTY + $i;
            }
            $rady[] = $radek;
            $rady[] = $sloupec;
            $uhlopricka[] = $i * self::VELIKOST_KARTY + $i;
            $protiuhlopricka[] = $i * self::VELIKOST_KARTY + self::VELIKOST_KARTY - 1 - $i;
        }
        $rady[] = $uhlopricka;
        $rady[] = $protiuhlopricka;

        return $rady;
    }

    /**
     * Nejbližší jednání, které ještě neskončilo.
     */
    public function getAktualniJednani(): ?Jednani
    {
        $radek = dbOneLine(<<<'SQL'
            SELECT id, datum, spusteno
            FROM bingo_jednani
            WHERE datum >= $0
            ORDER BY datum
            LIMIT 1
            SQL, [0 => $this->getPrvniNeskonceneDatum()]);

        return $radek
            ? $this->jednaniZRadku($radek)
            : null;
    }

    public function getJednani(int $idJednani): ?Jednani
    {
        $radek = dbOneLine(<<<'SQL'
            SELECT id, datum, spusteno
            FROM bingo_jednani
            WHERE id = $0
            SQL, [0 => $idJednani]);

        return $radek
            ? $this->jednaniZRadku($radek)
            : null;
    }

    /**
     * Všechna jednání od nejbližšího budoucího po nejstarší s počtem karet, stavem soutěže a vítězi.
     *
     * @return list<array{jednani: Jednani, karet: int, odskrtnuto: int, stav: StavSouteze, probehle: bool, vitezove: list<array{jmeno: string, kdy: string}>}>
     */
    public function getPrehledJednani(): array
    {
        return $this->sJednimCasem(function (): array {
            $vitezovePodleJednani = [];
            foreach (dbFetchAll(<<<'SQL'
                SELECT bingo_karta.id_jednani,
                       bingo_karta.vyhra,
                       uzivatele_hodnoty.login_uzivatele,
                       uzivatele_hodnoty.jmeno_uzivatele,
                       uzivatele_hodnoty.prijmeni_uzivatele
                FROM bingo_karta
                    JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = bingo_karta.id_uzivatele
                WHERE bingo_karta.vyhra IS NOT NULL
                SQL) as $radek) {
                $vitezovePodleJednani[(int)$radek['id_jednani']][] = [
                    'jmeno' => (string)self::jmeno($radek),
                    'kdy'   => self::cas($radek['vyhra']),
                ];
            }

            $prehled = [];
            foreach (dbFetchAll(<<<'SQL'
                SELECT bingo_jednani.id,
                       bingo_jednani.datum,
                       bingo_jednani.spusteno,
                       (SELECT COUNT(*) FROM bingo_karta WHERE bingo_karta.id_jednani = bingo_jednani.id) AS karet,
                       (SELECT COUNT(*) FROM bingo_zazneni WHERE bingo_zazneni.id_jednani = bingo_jednani.id) AS odskrtnuto
                FROM bingo_jednani
                ORDER BY bingo_jednani.datum DESC
                SQL) as $radek) {
                $jednani = $this->jednaniZRadku($radek);
                $vitezove = $vitezovePodleJednani[$jednani->id] ?? [];
                $prehled[] = [
                    'jednani'    => $jednani,
                    'karet'      => (int)$radek['karet'],
                    'odskrtnuto' => (int)$radek['odskrtnuto'],
                    'stav'       => $this->stavSouteze($jednani, $vitezove !== []),
                    'probehle'   => $this->isProbehle($jednani),
                    'vitezove'   => $vitezove,
                ];
            }

            return $prehled;
        });
    }

    public function getStavSouteze(Jednani $jednani): StavSouteze
    {
        return $this->stavSouteze($jednani, $this->isVyhrano($jednani));
    }

    /**
     * Kdy soutěž začala nebo začne: když ji někdo spustil, tak tehdy, jinak v 19:00 v den jednání.
     */
    public function getZacatek(Jednani $jednani): \DateTimeImmutable
    {
        return $jednani->spusteno
            ?? $this->naDatum($jednani, self::AUTOMATICKY_START);
    }

    /**
     * Jednání i se soutěží skončilo, je jen v historii.
     */
    public function isProbehle(Jednani $jednani): bool
    {
        return $jednani->datum->format('Y-m-d') < $this->getPrvniNeskonceneDatum();
    }

    /**
     * Od půlnoci dne jednání do rána po něm.
     */
    public function isDenJednani(Jednani $jednani): bool
    {
        return $this->getTed() >= $this->naDatum($jednani, '00:00') && ! $this->isProbehle($jednani);
    }

    public function spust(Jednani $jednani): void
    {
        $this->vTransakci($jednani, function (Jednani $jednani): void {
            $stav = $this->getStavSouteze($jednani);
            if (! $this->isSpusteniPovoleno($jednani, $stav)) {
                throw new BingoException($stav === StavSouteze::PRIPRAVA
                    ? 'Soutěž jde spustit až v den jednání.'
                    : 'Soutěž už běží nebo skončila.');
            }
            dbQuery(<<<'SQL'
                UPDATE bingo_jednani SET spusteno = $0 WHERE id = $1
                SQL, [0 => $this->getTedDb(), 1 => $jednani->id]);
        });
    }

    public function pridejJednani(string $datum): void
    {
        $den = \DateTimeImmutable::createFromFormat('!Y-m-d', $datum);
        if ($den === false || $den->format('Y-m-d') !== $datum) {
            throw new BingoException('Zadej datum jednání.');
        }
        if ($datum < $this->getTed()->format('Y-m-d')) {
            throw new BingoException('Jednání nejde přidat do minulosti.');
        }
        try {
            dbInsert('bingo_jednani', ['datum' => $datum]);
        } catch (\DbDuplicateEntryException) {
            throw new BingoException(sprintf('Jednání %s už v seznamu je.', $den->format('j. n. Y')));
        }
    }

    /**
     * Smaže jednání i s kartami. Jednání, na kterém se odškrtávalo, zůstává v historii. Pod zámkem jednání, aby se mezi
     * kontrolou a smazáním nestihlo nic odškrtnout.
     */
    public function smazJednani(Jednani $jednani): void
    {
        $this->vTransakci($jednani, function (Jednani $jednani): void {
            if ($this->isNecoOdskrtnuto($jednani)) {
                throw new BingoException('Na jednání se už odškrtávalo, zůstane v historii.');
            }
            dbQuery(<<<'SQL'
                DELETE FROM bingo_jednani WHERE id = $0
                SQL, [0 => $jednani->id]);
        });
    }

    /**
     * Fráze v poolu, ze kterých se generují nové karty.
     *
     * @return list<array{id: int, text: string, autor: string|null}>
     */
    public function getFraze(): array
    {
        $radky = dbFetchAll(<<<'SQL'
            SELECT bingo_fraze.id,
                   bingo_fraze.text,
                   uzivatele_hodnoty.login_uzivatele,
                   uzivatele_hodnoty.jmeno_uzivatele,
                   uzivatele_hodnoty.prijmeni_uzivatele
            FROM bingo_fraze
                LEFT JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = bingo_fraze.id_autora
            WHERE bingo_fraze.smazano IS NULL
            ORDER BY bingo_fraze.text
            SQL);

        return array_map(
            static fn (array $radek): array => [
                'id'    => (int)$radek['id'],
                'text'  => $radek['text'],
                'autor' => self::jmeno($radek),
            ],
            $radky,
        );
    }

    public function pridejFrazi(string $text, int $idAutora): void
    {
        $text = trim((string)preg_replace('~\s+~u', ' ', $text));
        if ($text === '') {
            throw new BingoException('Napiš frázi.');
        }
        if (mb_strlen($text) > self::MAX_DELKA_FRAZE) {
            throw new BingoException(sprintf('Fráze může mít nejvýš %d znaků.', self::MAX_DELKA_FRAZE));
        }
        $existuje = (bool)dbFetchSingle(<<<'SQL'
            SELECT EXISTS(SELECT 1 FROM bingo_fraze WHERE text = $0 AND smazano IS NULL)
            SQL, [0 => $text]);
        if ($existuje) {
            // zpráva je text, HTML z ní dělá až stránka
            throw new BingoException(sprintf('Fráze „%s“ už v poolu je.', $text));
        }
        dbInsert('bingo_fraze', [
            'text'      => $text,
            'id_autora' => $idAutora,
            'pridano'   => $this->getTedDb(),
        ]);
    }

    /**
     * Fráze zmizí z poolu, na kartách, které ji už mají, zůstane.
     */
    public function smazFrazi(int $idFraze): void
    {
        dbQuery(<<<'SQL'
            UPDATE bingo_fraze SET smazano = $0 WHERE id = $1 AND smazano IS NULL
            SQL, [0 => $this->getTedDb(), 1 => $idFraze]);
    }

    /**
     * @return array<int, array{id: int, text: string}>|null pozice => fráze, bez volného pole; null, když kartu nemá
     */
    public function getKarta(Jednani $jednani, int $idUzivatele): ?array
    {
        return $this->getKarty($jednani)[$idUzivatele]['pole'] ?? null;
    }

    /**
     * Náhodná karta z poolu. Před startem nahradí předchozí kartu, za běhu soutěže ji dostane jen ten, kdo žádnou nemá.
     */
    public function vygenerujKartu(Jednani $jednani, int $idUzivatele): void
    {
        $this->vTransakci($jednani, function (Jednani $jednani) use ($idUzivatele): void {
            $stav = $this->getStavSouteze($jednani);
            if (! self::isGenerovaniPovoleno($stav, $this->isHrac($jednani, $idUzivatele))) {
                throw new BingoException($stav === StavSouteze::BEZI
                    ? 'Soutěž běží, kartu už vyměnit nejde.'
                    : 'Soutěž už skončila.');
            }
            $idsFrazi = array_map('intval', dbFetchColumn(<<<'SQL'
                SELECT id FROM bingo_fraze WHERE smazano IS NULL
                SQL));
            if (count($idsFrazi) < self::FRAZI_NA_KARTE) {
                throw new BingoException(sprintf(
                    'Na kartu je potřeba %d frází, v poolu jich je %d.',
                    self::FRAZI_NA_KARTE,
                    count($idsFrazi),
                ));
            }
            $vybraneFraze = array_slice((new Randomizer())->shuffleArray($idsFrazi), 0, self::FRAZI_NA_KARTE);
            $pozice = array_values(array_diff(range(0, self::FRAZI_NA_KARTE), [self::VOLNE_POLE]));

            dbQuery(<<<'SQL'
                DELETE FROM bingo_karta WHERE id_jednani = $0 AND id_uzivatele = $1
                SQL, [0 => $jednani->id, 1 => $idUzivatele]);
            dbInsert('bingo_karta', [
                'id_jednani'   => $jednani->id,
                'id_uzivatele' => $idUzivatele,
                'vygenerovano' => $this->getTedDb(),
            ]);
            $idKarty = (int)dbInsertId();
            $radky = [];
            $hodnoty = [];
            foreach ($vybraneFraze as $index => $idFraze) {
                $radky[] = '(?, ?, ?)';
                array_push($hodnoty, $idKarty, $pozice[$index], $idFraze);
            }
            $radkySql = implode(', ', $radky);
            dbQuery(<<<SQL
                INSERT INTO bingo_karta_pole (id_karty, pozice, id_fraze) VALUES {$radkySql}
                SQL, $hodnoty);
            if ($stav === StavSouteze::BEZI) {
                // pozdě příchozímu se počítá, co už zaznělo, a může mít bingo hned
                $this->prepocitejVyhru($jednani);
            }
        });
    }

    /**
     * Odškrtne frázi (nebo odškrtnutí zruší) všem, kdo ji mají na kartě. Odškrtává jen ten, kdo má kartu.
     *
     * Zrušit odškrtnutí jde i po bingu, dokud jednání běží, ale jen ve vítězné řadě: kdo se překlikl, zruší tím falešné
     * bingo a vrátí soutěž do hry. Takové zrušení zůstane zapsané a odškrtnutí jde vrátit i s původním časem, i když
     * mezitím vyhrál někdo jiný; omylem zrušené bingo tak nepřijde o svůj čas.
     */
    public function odskrtni(Jednani $jednani, int $idFraze, int $idUzivatele, bool $zaznelo): void
    {
        $this->vTransakci($jednani, function (Jednani $jednani) use ($idFraze, $idUzivatele, $zaznelo): void {
            if (! $this->isHrac($jednani, $idUzivatele)) {
                throw new BingoException('Odškrtávat může jen ten, kdo má kartu.');
            }
            $zazneni = dbOneLine(<<<'SQL'
                SELECT zruseno FROM bingo_zazneni WHERE id_jednani = $0 AND id_fraze = $1
                SQL, [0 => $jednani->id, 1 => $idFraze]);
            $jeOdskrtnuta = $zazneni !== [] && $zazneni['zruseno'] === null;
            if ($jeOdskrtnuta === $zaznelo) {
                // stihl to někdo dřív, nic se nemění a vítěz se nepřepočítává
                return;
            }

            $karty = $this->getKarty($jednani);
            $vitezneKarty = self::getVitezneKarty($karty);
            $stav = $this->stavSouteze($jednani, $vitezneKarty !== []);
            $probehle = $this->isProbehle($jednani);
            if ($zaznelo && $zazneni !== []) {
                if (! self::isVraceniPovoleno($stav, true, $probehle)) {
                    throw new BingoException(self::getDuvodZakazu($stav, $probehle));
                }
                // vrátí odškrtnutí zrušené po bingu, i s tím, kdo a kdy odškrtl
                dbQuery(<<<'SQL'
                    UPDATE bingo_zazneni SET zruseno = NULL, id_zrusil = NULL WHERE id_jednani = $0 AND id_fraze = $1
                    SQL, [0 => $jednani->id, 1 => $idFraze]);
            } elseif ($zaznelo) {
                if (! self::isOdskrtnutiPovoleno($stav, true)) {
                    throw new BingoException(self::getDuvodZakazu($stav, $probehle));
                }
                if (! self::isNaKarte($karty, $idFraze)) {
                    throw new BingoException('Tahle fráze není na žádné kartě.');
                }
                dbQuery(<<<'SQL'
                    INSERT INTO bingo_zazneni (id_jednani, id_fraze, id_uzivatele, kdy)
                    VALUES ($0, $1, $2, $3)
                    SQL, [0 => $jednani->id, 1 => $idFraze, 2 => $idUzivatele, 3 => $this->getTedDb()]);
            } else {
                $zrusitelne = self::getZrusitelneFraze($stav, true, $probehle, $this->getZaznelaKdy($jednani), $vitezneKarty);
                if (! in_array($idFraze, $zrusitelne, true)) {
                    throw new BingoException($stav === StavSouteze::VYHRANO && ! $probehle
                        ? 'Po bingu jde zrušit jen odškrtnutí z vítězné řady.'
                        : self::getDuvodZakazu($stav, $probehle));
                }
                if ($stav === StavSouteze::BEZI) {
                    // před bingem jde o překliknutí: až fráze opravdu zazní, platí čas nového odškrtnutí
                    dbQuery(<<<'SQL'
                        DELETE FROM bingo_zazneni WHERE id_jednani = $0 AND id_fraze = $1
                        SQL, [0 => $jednani->id, 1 => $idFraze]);
                } else {
                    dbQuery(<<<'SQL'
                        UPDATE bingo_zazneni SET zruseno = $2, id_zrusil = $3 WHERE id_jednani = $0 AND id_fraze = $1
                        SQL, [0 => $jednani->id, 1 => $idFraze, 2 => $this->getTedDb(), 3 => $idUzivatele]);
                }
            }
            $this->prepocitejVyhru($jednani, $karty);
        });
    }

    /**
     * Stav soutěže, jak ho vidí uživatel. Kdo za běhu soutěže nemá kartu, nevidí, co už zaznělo, ani fráze ve hře.
     * Příznaky lze… dávají tytéž predikáty, kterými změny kontroluje server.
     *
     * @return array{
     *     stav: string,
     *     popis: string,
     *     sledovatZa: int|null,
     *     skryto: bool,
     *     idKarty: int|null,
     *     karta: array<int, array{id: int, text: string}>|null,
     *     lzeOdskrtnout: bool,
     *     lzeVratit: bool,
     *     lzeZrusit: list<int>,
     *     lzeGenerovat: bool,
     *     lzeSpustit: bool,
     *     zaznele: array<int, array{kdo: string|null, kdy: string}>,
     *     zrusene: array<int, array{kdo: string|null, kdy: string, zrusil: string|null, zruseno: string}>,
     *     frazeVeHre: list<array{id: int, text: string}>,
     *     vitezove: list<array{idUzivatele: int, jmeno: string, kdy: string, pole: array<int, array{id: int, text: string}>, rady: list<int>}>,
     * }
     */
    public function getStav(Jednani $jednani, int $idDivaka): array
    {
        return $this->sJednimCasem(function () use ($jednani, $idDivaka): array {
            $karty = $this->getKarty($jednani);
            $karta = $karty[$idDivaka] ?? null;
            $maKartu = $karta !== null;
            $vitezneKarty = self::getVitezneKarty($karty);
            $stav = $this->stavSouteze($jednani, $vitezneKarty !== []);
            $probehle = $this->isProbehle($jednani);
            $skryto = $stav === StavSouteze::BEZI && ! $maKartu;
            ['zaznele' => $zaznele, 'zrusene' => $zrusene] = $skryto
                ? ['zaznele' => [], 'zrusene' => []]
                : $this->getZazneni($jednani);

            $frazeVeHre = [];
            if (! $skryto) {
                foreach ($karty as $jednaKarta) {
                    foreach ($jednaKarta['pole'] as $fraze) {
                        $frazeVeHre[$fraze['id']] = $fraze['text'];
                    }
                }
                (new \Collator('cs'))->asort($frazeVeHre);
            }

            $vitezove = [];
            foreach ($vitezneKarty as $idUzivatele => $vitezna) {
                $vitezove[] = [
                    'idUzivatele' => $idUzivatele,
                    'jmeno'       => $vitezna['jmeno'],
                    'kdy'         => self::cas($vitezna['vyhra']),
                    'pole'        => $vitezna['pole'],
                    'rady'        => self::getPoziceVHotovychRadach($vitezna['pole'], $zaznele),
                ];
            }
            usort($vitezove, static fn (array $a, array $b): int => strcmp($a['jmeno'], $b['jmeno']));

            return [
                'stav'          => $stav->value,
                'popis'         => $this->popisStavu($jednani, $stav, $maKartu, $vitezove),
                'sledovatZa'    => $this->getSledovatZa($jednani, $stav),
                'skryto'        => $skryto,
                'idKarty'       => $karta['idKarty'] ?? null,
                'karta'         => $karta['pole'] ?? null,
                'lzeOdskrtnout' => self::isOdskrtnutiPovoleno($stav, $maKartu),
                'lzeVratit'     => self::isVraceniPovoleno($stav, $maKartu, $probehle),
                'lzeZrusit'     => self::getZrusitelneFraze($stav, $maKartu, $probehle, $zaznele, $vitezneKarty),
                'lzeGenerovat'  => self::isGenerovaniPovoleno($stav, $maKartu),
                'lzeSpustit'    => $this->isSpusteniPovoleno($jednani, $stav),
                'zaznele'       => $zaznele,
                'zrusene'       => $zrusene,
                'frazeVeHre'    => array_map(
                    static fn (int $id, string $text): array => ['id' => $id, 'text' => $text],
                    array_keys($frazeVeHre),
                    array_values($frazeVeHre),
                ),
                'vitezove'      => $vitezove,
            ];
        });
    }

    /**
     * Před startem jde kartu generovat a měnit, za běhu soutěže jen dostat první.
     */
    private static function isGenerovaniPovoleno(StavSouteze $stav, bool $maKartu): bool
    {
        return $stav === StavSouteze::PRIPRAVA || ($stav === StavSouteze::BEZI && ! $maKartu);
    }

    /**
     * Spustit soutěž dřív než v 19:00 jde jen v den jednání.
     */
    private function isSpusteniPovoleno(Jednani $jednani, StavSouteze $stav): bool
    {
        return $stav === StavSouteze::PRIPRAVA && $this->isDenJednani($jednani);
    }

    /**
     * Nově odškrtnout jde jen za běhu soutěže.
     */
    private static function isOdskrtnutiPovoleno(StavSouteze $stav, bool $maKartu): bool
    {
        return $maKartu && $stav === StavSouteze::BEZI;
    }

    /**
     * Odškrtnutí zrušené po bingu jde vrátit, dokud jednání běží, i když už zase někdo vyhrál.
     */
    private static function isVraceniPovoleno(StavSouteze $stav, bool $maKartu, bool $probehle): bool
    {
        return $maKartu && ! $probehle && ($stav === StavSouteze::BEZI || $stav === StavSouteze::VYHRANO);
    }

    /**
     * Odškrtnuté fráze, kterým jde odškrtnutí zrušit: za běhu soutěže všem, po bingu jen těm z vítězných řad (jen
     * překliknutí v nich mohlo bingo dát neprávem), po jednání žádné.
     *
     * @param array<int, mixed> $zaznele id odškrtnuté fráze => cokoli
     * @param array<int, array{pole: array<int, array{id: int, text: string}>}> $vitezneKarty
     * @return list<int>
     */
    private static function getZrusitelneFraze(
        StavSouteze $stav,
        bool $maKartu,
        bool $probehle,
        array $zaznele,
        array $vitezneKarty,
    ): array {
        if (! $maKartu || $probehle) {
            return [];
        }
        if ($stav === StavSouteze::BEZI) {
            return array_keys($zaznele);
        }
        if ($stav !== StavSouteze::VYHRANO) {
            return [];
        }
        $idsFrazi = [];
        foreach ($vitezneKarty as $vitezna) {
            foreach (self::getPoziceVHotovychRadach($vitezna['pole'], $zaznele) as $pozice) {
                if ($pozice !== self::VOLNE_POLE) {
                    $idsFrazi[] = $vitezna['pole'][$pozice]['id'];
                }
            }
        }

        return array_values(array_unique($idsFrazi));
    }

    private static function getDuvodZakazu(StavSouteze $stav, bool $probehle): string
    {
        return match (true) {
            $stav === StavSouteze::PRIPRAVA => 'Soutěž ještě nezačala.',
            $probehle                       => 'Jednání už skončilo.',
            $stav === StavSouteze::VYHRANO  => 'Soutěž už skončila bingem.',
            default                         => 'Jednání už skončilo.',
        };
    }

    private function stavSouteze(Jednani $jednani, bool $isVyhrano): StavSouteze
    {
        return match (true) {
            $isVyhrano                                     => StavSouteze::VYHRANO,
            $this->isProbehle($jednani)                    => StavSouteze::BEZ_VITEZE,
            $this->getTed() >= $this->getZacatek($jednani) => StavSouteze::BEZI,
            default                                        => StavSouteze::PRIPRAVA,
        };
    }

    /**
     * Za kolik sekund se má stránka znovu zeptat na stav, null = už se nic nezmění. Často jen za běhu soutěže, před
     * startem nejpozději v čase automatického startu.
     */
    private function getSledovatZa(Jednani $jednani, StavSouteze $stav): ?int
    {
        if ($this->isProbehle($jednani)) {
            return null;
        }

        return match ($stav) {
            StavSouteze::BEZI     => self::SLEDOVAT_ZA_BEHU,
            StavSouteze::PRIPRAVA => min(
                $this->isDenJednani($jednani)
                    ? self::SLEDOVAT_V_DEN_JEDNANI
                    : self::SLEDOVAT_JINAK,
                max(1, $this->getZacatek($jednani)->getTimestamp() - $this->getTed()->getTimestamp()),
            ),
            // po bingu: kdo ho zruší, vrátí soutěž do hry
            default               => self::SLEDOVAT_V_DEN_JEDNANI,
        };
    }

    /**
     * @param list<array{jmeno: string, kdy: string}> $vitezove
     */
    private function popisStavu(Jednani $jednani, StavSouteze $stav, bool $maKartu, array $vitezove): string
    {
        $zacatek = $this->getZacatek($jednani)->format('G:i');

        return match ($stav) {
            StavSouteze::PRIPRAVA   => $this->isDenJednani($jednani)
                ? "Soutěž začne v {$zacatek}, nebo dřív, když ji někdo spustí. Do té doby si kartu můžeš měnit."
                : "Soutěž začne v den jednání v {$zacatek}, nebo dřív, když ji někdo spustí. Kartu si můžeš vygenerovat a měnit už teď.",
            StavSouteze::BEZI       => $maKartu
                ? "Soutěž běží od {$zacatek}. Co zazní, odškrtni: odškrtne se všem, kdo to mají na kartě. Kartu už vyměnit nejde."
                : "Soutěž běží od {$zacatek}. Zapojit se můžeš i teď: co už zaznělo, uvidíš, až si vygeneruješ kartu, a počítá se ti. Kartu dostaneš jen jednu.",
            StavSouteze::VYHRANO    => sprintf(
                'Soutěž skončila v %s. %s: %s.',
                $vitezove[0]['kdy'],
                count($vitezove) === 1
                    ? 'Vítěz'
                    : 'Vítězové',
                implode(', ', array_column($vitezove, 'jmeno')),
            ),
            StavSouteze::BEZ_VITEZE => 'Jednání skončilo a bingo nikdo neměl.',
        };
    }

    /**
     * Uloží vítěze: karty s nejdřívějším bingem. Bingo má karta s celou řadou odškrtnutou, jeho čas je čas fráze,
     * která řadu dokončila, nejdřív ale čas vygenerování karty (pozdě příchozí nevyhraje zpětně).
     *
     * @param array<int, array{idKarty: int, vygenerovano: string, pole: array<int, array{id: int, text: string}>}>|null $karty
     */
    private function prepocitejVyhru(Jednani $jednani, ?array $karty = null): void
    {
        $zaznelaKdy = $this->getZaznelaKdy($jednani);
        $bingaKaret = [];
        foreach ($karty ?? $this->getKarty($jednani) as $karta) {
            $kdy = self::kdyBingo(
                array_map(static fn (array $fraze): int => $fraze['id'], $karta['pole']),
                $zaznelaKdy,
                $karta['vygenerovano'],
            );
            if ($kdy !== null) {
                $bingaKaret[$karta['idKarty']] = $kdy;
            }
        }
        dbQuery(<<<'SQL'
            UPDATE bingo_karta SET vyhra = NULL WHERE id_jednani = $0 AND vyhra IS NOT NULL
            SQL, [0 => $jednani->id]);
        if ($bingaKaret !== []) {
            $prvniBingo = min($bingaKaret);
            dbQuery(<<<'SQL'
                UPDATE bingo_karta SET vyhra = $0 WHERE id IN ($1)
                SQL, [0 => $prvniBingo, 1 => array_keys($bingaKaret, $prvniBingo, true)]);
        }
    }

    /**
     * @param array<int, int> $pole pozice => id fráze
     * @param array<int, string> $zaznelaKdy id fráze => kdy zazněla (Y-m-d H:i:s)
     * @return string|null kdy karta dosáhla binga (Y-m-d H:i:s)
     */
    private static function kdyBingo(array $pole, array $zaznelaKdy, string $vygenerovano): ?string
    {
        $prvni = null;
        foreach (self::getRady() as $rada) {
            $dokoncena = $vygenerovano;
            foreach ($rada as $pozice) {
                if ($pozice === self::VOLNE_POLE) {
                    continue;
                }
                $kdy = $zaznelaKdy[$pole[$pozice] ?? 0] ?? null;
                if ($kdy === null) {
                    continue 2;
                }
                $dokoncena = max($dokoncena, $kdy);
            }
            if ($prvni === null || $dokoncena < $prvni) {
                $prvni = $dokoncena;
            }
        }

        return $prvni;
    }

    /**
     * @param array<int, array{id: int, text: string}> $pole pozice => fráze
     * @param array<int, mixed> $zaznele id fráze => zaznění
     * @return list<int> pozice v celých odškrtnutých řadách
     */
    private static function getPoziceVHotovychRadach(array $pole, array $zaznele): array
    {
        $pozice = [];
        foreach (self::getRady() as $rada) {
            foreach ($rada as $poziceVRade) {
                if ($poziceVRade !== self::VOLNE_POLE && ! isset($zaznele[$pole[$poziceVRade]['id'] ?? 0])) {
                    continue 2;
                }
            }
            $pozice = [...$pozice, ...$rada];
        }

        return array_values(array_unique($pozice));
    }

    /**
     * Karty jednání podle uživatele.
     *
     * @return array<int, array{idKarty: int, jmeno: string, vygenerovano: string, vyhra: string|null, pole: array<int, array{id: int, text: string}>}>
     */
    private function getKarty(Jednani $jednani): array
    {
        $karty = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT bingo_karta.id AS id_karty,
                   bingo_karta.id_uzivatele,
                   bingo_karta.vygenerovano,
                   bingo_karta.vyhra,
                   bingo_karta_pole.pozice,
                   bingo_fraze.id AS id_fraze,
                   bingo_fraze.text,
                   uzivatele_hodnoty.login_uzivatele,
                   uzivatele_hodnoty.jmeno_uzivatele,
                   uzivatele_hodnoty.prijmeni_uzivatele
            FROM bingo_karta
                JOIN bingo_karta_pole ON bingo_karta_pole.id_karty = bingo_karta.id
                JOIN bingo_fraze ON bingo_fraze.id = bingo_karta_pole.id_fraze
                JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = bingo_karta.id_uzivatele
            WHERE bingo_karta.id_jednani = $0
            ORDER BY bingo_karta_pole.pozice
            SQL, [0 => $jednani->id]) as $radek) {
            $idUzivatele = (int)$radek['id_uzivatele'];
            $karty[$idUzivatele] ??= [
                'idKarty'      => (int)$radek['id_karty'],
                'jmeno'        => (string)self::jmeno($radek),
                'vygenerovano' => $radek['vygenerovano'],
                'vyhra'        => $radek['vyhra'],
                'pole'         => [],
            ];
            $karty[$idUzivatele]['pole'][(int)$radek['pozice']] = [
                'id'   => (int)$radek['id_fraze'],
                'text' => $radek['text'],
            ];
        }

        return $karty;
    }

    /**
     * @template K of array-key
     * @template V of array{vyhra: string|null}
     * @param array<K, V> $karty
     * @return array<K, V>
     */
    private static function getVitezneKarty(array $karty): array
    {
        return array_filter($karty, static fn (array $karta): bool => $karta['vyhra'] !== null);
    }

    /**
     * @param array<int, array{pole: array<int, array{id: int, text: string}>}> $karty
     */
    private static function isNaKarte(array $karty, int $idFraze): bool
    {
        foreach ($karty as $karta) {
            foreach ($karta['pole'] as $fraze) {
                if ($fraze['id'] === $idFraze) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Odškrtnuté fráze a odškrtnutí zrušená po bingu, obojí s tím, kdo a kdy odškrtl.
     *
     * @return array{
     *     zaznele: array<int, array{kdo: string|null, kdy: string}>,
     *     zrusene: array<int, array{kdo: string|null, kdy: string, zrusil: string|null, zruseno: string}>,
     * }
     */
    private function getZazneni(Jednani $jednani): array
    {
        $zazneni = ['zaznele' => [], 'zrusene' => []];
        foreach (dbFetchAll(<<<'SQL'
            SELECT bingo_zazneni.id_fraze,
                   bingo_zazneni.kdy,
                   bingo_zazneni.zruseno,
                   odskrtl.login_uzivatele,
                   odskrtl.jmeno_uzivatele,
                   odskrtl.prijmeni_uzivatele,
                   zrusil.login_uzivatele AS zrusil_login_uzivatele,
                   zrusil.jmeno_uzivatele AS zrusil_jmeno_uzivatele,
                   zrusil.prijmeni_uzivatele AS zrusil_prijmeni_uzivatele
            FROM bingo_zazneni
                LEFT JOIN uzivatele_hodnoty AS odskrtl ON odskrtl.id_uzivatele = bingo_zazneni.id_uzivatele
                LEFT JOIN uzivatele_hodnoty AS zrusil ON zrusil.id_uzivatele = bingo_zazneni.id_zrusil
            WHERE bingo_zazneni.id_jednani = $0
            SQL, [0 => $jednani->id]) as $radek) {
            $idFraze = (int)$radek['id_fraze'];
            $odskrtnuti = [
                'kdo' => self::jmeno($radek),
                'kdy' => self::cas($radek['kdy']),
            ];
            if (isset($radek['zruseno'])) {
                $zazneni['zrusene'][$idFraze] = $odskrtnuti + [
                    'zrusil'  => self::jmeno([
                        'login_uzivatele'    => $radek['zrusil_login_uzivatele'],
                        'jmeno_uzivatele'    => $radek['zrusil_jmeno_uzivatele'],
                        'prijmeni_uzivatele' => $radek['zrusil_prijmeni_uzivatele'],
                    ]),
                    'zruseno' => self::cas($radek['zruseno']),
                ];
            } else {
                $zazneni['zaznele'][$idFraze] = $odskrtnuti;
            }
        }

        return $zazneni;
    }

    /**
     * @return array<int, string> id odškrtnuté fráze => kdy zazněla (Y-m-d H:i:s), bez zrušených
     */
    private function getZaznelaKdy(Jednani $jednani): array
    {
        $zaznelaKdy = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT id_fraze, kdy FROM bingo_zazneni WHERE id_jednani = $0 AND zruseno IS NULL
            SQL, [0 => $jednani->id]) as $radek) {
            $zaznelaKdy[(int)$radek['id_fraze']] = $radek['kdy'];
        }

        return $zaznelaKdy;
    }

    /**
     * Má uživatel na jednání kartu, tedy hraje?
     */
    private function isHrac(Jednani $jednani, int $idUzivatele): bool
    {
        return (bool)dbFetchSingle(<<<'SQL'
            SELECT EXISTS(SELECT 1 FROM bingo_karta WHERE id_jednani = $0 AND id_uzivatele = $1)
            SQL, [0 => $jednani->id, 1 => $idUzivatele]);
    }

    private function isVyhrano(Jednani $jednani): bool
    {
        return (bool)dbFetchSingle(<<<'SQL'
            SELECT EXISTS(SELECT 1 FROM bingo_karta WHERE id_jednani = $0 AND vyhra IS NOT NULL)
            SQL, [0 => $jednani->id]);
    }

    /**
     * I zrušené odškrtnutí je historie.
     */
    private function isNecoOdskrtnuto(Jednani $jednani): bool
    {
        return (bool)dbFetchSingle(<<<'SQL'
            SELECT EXISTS(SELECT 1 FROM bingo_zazneni WHERE id_jednani = $0)
            SQL, [0 => $jednani->id]);
    }

    /**
     * Změny soutěže jednoho jednání jdou po sobě: kdo odškrtne současně s bingem jiného, uvidí už konec soutěže.
     * Změna dostane jednání načtené pod zámkem a rozhoduje podle času, kdy zámek dostala, ne kdy začal požadavek:
     * kdo čekal ve frontě přes 19:00, už kartu nevymění.
     *
     * @param callable(Jednani): void $zmena
     */
    private function vTransakci(Jednani $jednani, callable $zmena): void
    {
        dbBegin();
        try {
            $radek = dbOneLine(<<<'SQL'
                SELECT id, datum, spusteno FROM bingo_jednani WHERE id = $0 FOR UPDATE
                SQL, [0 => $jednani->id]);
            if ($radek === []) {
                throw new BingoException('Jednání už neexistuje.');
            }
            $this->sJednimCasem(fn () => $zmena($this->jednaniZRadku($radek)));
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }
    }

    /**
     * Rozhodne celé podle jednoho času, aby se stav soutěže a zapsané časy nerozešly, když mezitím uběhne chvíle.
     *
     * @template T
     * @param callable(): T $rozhodnuti
     * @return T
     */
    private function sJednimCasem(callable $rozhodnuti): mixed
    {
        if ($this->tedRozhodnuti !== null) {
            return $rozhodnuti();
        }
        $this->tedRozhodnuti = $this->hodiny->now();
        try {
            return $rozhodnuti();
        } finally {
            $this->tedRozhodnuti = null;
        }
    }

    /**
     * Den, kterým začínají neskončená jednání: včerejší jednání běží do rána.
     */
    private function getPrvniNeskonceneDatum(): string
    {
        return $this->getTed()
            ->modify(sprintf('-%d hours', self::JEDNANI_KONCI_HODIN_PO_PULNOCI))
            ->format('Y-m-d');
    }

    private function naDatum(Jednani $jednani, string $cas): \DateTimeImmutable
    {
        return new \DateTimeImmutable($jednani->datum->format('Y-m-d') . ' ' . $cas, $this->getTed()->getTimezone());
    }

    private function getTed(): \DateTimeImmutable
    {
        return $this->tedRozhodnuti ?? $this->hodiny->now();
    }

    private function getTedDb(): string
    {
        return $this->getTed()->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $radek
     */
    private function jednaniZRadku(array $radek): Jednani
    {
        return new Jednani(
            (int)$radek['id'],
            new \DateTimeImmutable($radek['datum']),
            $radek['spusteno'] === null
                ? null
                : new \DateTimeImmutable($radek['spusteno'], $this->getTed()->getTimezone()),
        );
    }

    /**
     * @param array<string, mixed> $radek s login_uzivatele, jmeno_uzivatele a prijmeni_uzivatele
     */
    private static function jmeno(array $radek): ?string
    {
        if ($radek['login_uzivatele'] === null) {
            return null;
        }

        return \Uzivatel::jmenoNickZjisti($radek);
    }

    private static function cas(string $kdy): string
    {
        return (new \DateTimeImmutable($kdy))->format('G:i');
    }
}
