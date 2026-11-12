<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Přehledy pro správce půjčovny. Řádky jsou pole přímo z databáze; jména účastníků si stránka dočte
 * přes \Uzivatel::zIds().
 *
 * @phpstan-type RadekRezervace array{id_rezervace: string, id_kostymu: string, nazev_kostymu: string, stav_kostymu: string, id_akce: string, nazev_akce: string, zacatek: string|null, konec: string|null, id_uzivatele: string, id_vydeje: string|null, vydano: string|null}
 * @phpstan-type RadekVydeje array{id_vydeje: string, id_kostymu: string, nazev_kostymu: string, id_uzivatele: string, vydano: string, id_rezervace: string|null, id_akce: string|null, nazev_akce: string|null, zacatek: string|null, konec: string|null, vratit_do: string|null}
 */
final class PrehledyPujcovny
{
    private const string SELECT_REZERVACE = <<<'SQL'
SELECT kostym_rezervace.id AS id_rezervace, kostym.id AS id_kostymu, kostym.nazev AS nazev_kostymu,
       kostym.stav AS stav_kostymu, akce_seznam.id_akce, akce_seznam.nazev_akce, akce_seznam.zacatek,
       akce_seznam.konec, kostym_rezervace.id_uzivatele, kostym_vydej.id AS id_vydeje, kostym_vydej.vydano
FROM kostym_rezervace
JOIN kostym ON kostym.id = kostym_rezervace.id_kostymu
JOIN akce_seznam ON akce_seznam.id_akce = kostym_rezervace.id_akce
LEFT JOIN kostym_vydej ON kostym_vydej.id_rezervace = kostym_rezervace.id AND kostym_vydej.vraceno IS NULL
SQL;

    private const string SELECT_VYDEJE = <<<'SQL'
SELECT kostym_vydej.id AS id_vydeje, kostym.id AS id_kostymu, kostym.nazev AS nazev_kostymu,
       kostym_vydej.id_uzivatele, kostym_vydej.vydano, kostym_vydej.id_rezervace, akce_seznam.id_akce,
       akce_seznam.nazev_akce, akce_seznam.zacatek, akce_seznam.konec,
       akce_seznam.konec + INTERVAL $0 MINUTE AS vratit_do
FROM kostym_vydej
JOIN kostym ON kostym.id = kostym_vydej.id_kostymu
LEFT JOIN akce_seznam ON akce_seznam.id_akce = kostym_vydej.id_akce
SQL;

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly NastaveniPujcovny $nastaveni,
    ) {
    }

    public static function zGlobals(): self
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();

        return new self($systemoveNastaveni, NastaveniPujcovny::zSystemovehoNastaveni($systemoveNastaveni));
    }

    /**
     * Rezervace na aktivity, které začínají v příštích hodinách (a ty, které už začaly, ale kostým se nevydal).
     *
     * @return list<RadekRezervace>
     */
    public function vydejniList(int $hodin): array
    {
        $ted = $this->systemoveNastaveni->ted();

        return dbFetchAll(self::SELECT_REZERVACE . <<<'SQL'

WHERE akce_seznam.zacatek < $1
    AND akce_seznam.konec > $0
ORDER BY akce_seznam.zacatek, akce_seznam.nazev_akce, kostym.nazev
SQL, [
            $ted->format('Y-m-d H:i:s'),
            $ted->modify(sprintf('+%d hours', $hodin))->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Rezervace účastníka na aktivity ročníku, od nejbližší.
     *
     * @return list<RadekRezervace>
     */
    public function rezervaceUcastnika(int $idUzivatele): array
    {
        return dbFetchAll(self::SELECT_REZERVACE . <<<'SQL'

WHERE kostym_rezervace.id_uzivatele = $0
    AND akce_seznam.rok = $1
ORDER BY akce_seznam.zacatek, akce_seznam.nazev_akce
SQL, [$idUzivatele, $this->systemoveNastaveni->rocnik()]);
    }

    /**
     * Vydané a nevrácené kostýmy účastníka, i ty, jejichž rezervace mezitím zanikla.
     *
     * @return list<RadekVydeje>
     */
    public function nevraceneUcastnika(int $idUzivatele): array
    {
        return dbFetchAll(self::SELECT_VYDEJE . <<<'SQL'

WHERE kostym_vydej.vraceno IS NULL
    AND kostym_vydej.id_uzivatele = $1
ORDER BY kostym_vydej.vydano
SQL, [$this->nastaveni->getVraceniMinutPoKonci(), $idUzivatele]);
    }

    /**
     * Vydané kostýmy, které se nevrátily do konce aktivity a doby na vrácení. Kostým, jehož aktivita
     * už neexistuje nebo nemá čas, je po termínu vždy.
     *
     * @return list<RadekVydeje>
     */
    public function nevracenePoTerminu(): array
    {
        return dbFetchAll(self::SELECT_VYDEJE . <<<'SQL'

WHERE kostym_vydej.vraceno IS NULL
    AND (akce_seznam.konec IS NULL OR akce_seznam.konec + INTERVAL $0 MINUTE < $1)
ORDER BY vratit_do, kostym.nazev
SQL, [$this->nastaveni->getVraceniMinutPoKonci(), $this->systemoveNastaveni->ted()->format('Y-m-d H:i:s')]);
    }

    /**
     * Dvojice rezervací téhož kostýmu, jejichž aktivity se i s časem na výměnu překrývají. Při rezervaci
     * to hlídá @see PujcovnaKostymu, překryv ale vznikne přesunem aktivity, který se kvůli kostýmu neblokuje.
     * Jen dvojice, kde aspoň jedna aktivita ještě neskončila.
     *
     * @return list<array{prvni: RadekRezervace, druha: RadekRezervace}>
     */
    public function konfliktyRezervaci(): array
    {
        $dvojice = dbFetchAll(<<<'SQL'
SELECT prvni_rezervace.id AS id_prvni, druha_rezervace.id AS id_druhe
FROM kostym_rezervace AS prvni_rezervace
JOIN akce_seznam AS prvni_aktivita ON prvni_aktivita.id_akce = prvni_rezervace.id_akce
JOIN kostym_rezervace AS druha_rezervace
    ON druha_rezervace.id_kostymu = prvni_rezervace.id_kostymu
    AND druha_rezervace.id > prvni_rezervace.id
JOIN akce_seznam AS druha_aktivita ON druha_aktivita.id_akce = druha_rezervace.id_akce
WHERE prvni_aktivita.zacatek < druha_aktivita.konec + INTERVAL $0 MINUTE
    AND druha_aktivita.zacatek < prvni_aktivita.konec + INTERVAL $0 MINUTE
    AND (prvni_aktivita.konec > $1 OR druha_aktivita.konec > $1)
ORDER BY LEAST(prvni_aktivita.zacatek, druha_aktivita.zacatek)
SQL, [$this->nastaveni->getCasNaVymenuMinut(), $this->systemoveNastaveni->ted()->format('Y-m-d H:i:s')]);
        if ($dvojice === []) {
            return [];
        }
        $rezervace = [];
        foreach (dbFetchAll(self::SELECT_REZERVACE . <<<'SQL'

WHERE kostym_rezervace.id IN ($0)
SQL, [
            array_merge(array_column($dvojice, 'id_prvni'), array_column($dvojice, 'id_druhe')),
        ]) as $radek) {
            $rezervace[$radek['id_rezervace']] = $radek;
        }

        return array_map(
            static fn (array $par): array => [
                'prvni' => $rezervace[$par['id_prvni']],
                'druha' => $rezervace[$par['id_druhe']],
            ],
            $dvojice,
        );
    }

    /**
     * Rezervace kostýmů v opravě nebo vyřazených na aktivity, které ještě neskončily.
     *
     * @return list<RadekRezervace>
     */
    public function rezervaceKostymuMimoProvoz(): array
    {
        return dbFetchAll(self::SELECT_REZERVACE . <<<'SQL'

WHERE kostym.stav <> $0
    AND akce_seznam.konec > $1
ORDER BY akce_seznam.zacatek, kostym.nazev
SQL, [StavKostymuEnum::K_DISPOZICI->value, $this->systemoveNastaveni->ted()->format('Y-m-d H:i:s')]);
    }
}
