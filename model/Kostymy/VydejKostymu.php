<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Výdej rezervovaného kostýmu a jeho vrácení. Záznam o výdeji přežije zánik rezervace: kdo se odhlásí
 * s kostýmem u sebe, zůstane v přehledu nevrácených, dokud kostým nevrátí.
 */
final class VydejKostymu
{
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
     * @return int ID výdeje
     *
     * @throws ChybaPujcovny
     */
    public function vydej(int $idRezervace, \Uzivatel $vydal): int
    {
        dbBegin();
        try {
            $rezervace = dbOneLine(<<<'SQL'
SELECT kostym_rezervace.id_kostymu, kostym_rezervace.id_akce, kostym_rezervace.id_uzivatele,
       akce_seznam.zacatek, akce_seznam.konec
FROM kostym_rezervace
JOIN akce_seznam ON akce_seznam.id_akce = kostym_rezervace.id_akce
WHERE kostym_rezervace.id = $0
FOR UPDATE
SQL, [$idRezervace]);
            if (! $rezervace) {
                throw new ChybaPujcovny('Rezervace neexistuje, účastník se mezitím mohl z aktivity odhlásit.');
            }
            $idKostymu = (int) $rezervace['id_kostymu'];
            $stav = StavKostymuEnum::from((string) dbOneCol('SELECT stav FROM kostym WHERE id = $0 FOR UPDATE', [$idKostymu]));
            if (! $stav->isRezervovatelny()) {
                throw new ChybaPujcovny(sprintf('Kostým je %s, vydat ho nejde.', $stav->getNazev()));
            }
            $this->overCasVydeje($rezervace['zacatek'], $rezervace['konec']);
            $nevracenyVydej = dbOneLine(<<<'SQL'
SELECT id_rezervace FROM kostym_vydej WHERE id_kostymu = $0 AND vraceno IS NULL LIMIT 1
SQL, [$idKostymu]);
            if ($nevracenyVydej) {
                throw new ChybaPujcovny((int) $nevracenyVydej['id_rezervace'] === $idRezervace ? 'Kostým už je na tuhle rezervaci vydaný.' : 'Kostým je vydaný někomu jinému a ještě se nevrátil.');
            }
            dbQuery(<<<'SQL'
INSERT INTO kostym_vydej SET id_kostymu = $0, id_rezervace = $1, id_akce = $2, id_uzivatele = $3, vydano = $4, vydal = $5,
    poznamka = ''
SQL, [
                $idKostymu,
                $idRezervace,
                $rezervace['id_akce'],
                $rezervace['id_uzivatele'],
                $this->tedDb(),
                $vydal->id(),
            ]);
            $idVydeje = (int) dbInsertId();
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        return $idVydeje;
    }

    /**
     * Poškozený kostým přepne do stavu „v opravě“, takže ho nikdo nový nezarezervuje. Stávající rezervace
     * zůstanou, správce je uvidí na stránce Konflikty.
     *
     * @throws ChybaPujcovny
     */
    public function vrat(int $idVydeje, \Uzivatel $prijal, StavPriVraceniKostymuEnum $stavPriVraceni, string $poznamka = ''): void
    {
        dbBegin();
        try {
            $vydej = dbOneLine('SELECT id_kostymu, vraceno FROM kostym_vydej WHERE id = $0 FOR UPDATE', [$idVydeje]);
            if (! $vydej) {
                throw new ChybaPujcovny('Výdej neexistuje.');
            }
            if ($vydej['vraceno'] !== null) {
                throw new ChybaPujcovny('Kostým už je vrácený.');
            }
            dbQuery(<<<'SQL'
UPDATE kostym_vydej SET vraceno = $0, prijal = $1, stav_pri_vraceni = $2, poznamka = $3 WHERE id = $4
SQL, [$this->tedDb(), $prijal->id(), $stavPriVraceni->value, trim($poznamka), $idVydeje]);
            if ($stavPriVraceni === StavPriVraceniKostymuEnum::POSKOZENY) {
                dbQuery('UPDATE kostym SET stav = $0 WHERE id = $1', [StavKostymuEnum::V_OPRAVE->value, $vydej['id_kostymu']]);
            }
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }
    }

    /**
     * @throws ChybaPujcovny
     */
    private function overCasVydeje(?string $zacatek, ?string $konec): void
    {
        if ($zacatek === null || $konec === null) {
            throw new ChybaPujcovny('Aktivita nemá určený začátek a konec, kostým na ni vydat nejde.');
        }
        $ted = $this->systemoveNastaveni->ted();
        $vydejOd = (new \DateTimeImmutable($zacatek, $ted->getTimezone()))
            ->modify(sprintf('-%d minutes', $this->nastaveni->getVydejMinutPredZacatkem()));
        if ($ted < $vydejOd) {
            throw new ChybaPujcovny(sprintf('Kostým jde vydat nejdřív %d minut před začátkem aktivity, tedy od %s.', $this->nastaveni->getVydejMinutPredZacatkem(), $vydejOd->format('j. n. G:i')));
        }
        if ($ted >= new \DateTimeImmutable($konec, $ted->getTimezone())) {
            throw new ChybaPujcovny('Aktivita už skončila, kostým na ni vydat nejde.');
        }
    }

    private function tedDb(): string
    {
        return $this->systemoveNastaveni->ted()->format('Y-m-d H:i:s');
    }
}
