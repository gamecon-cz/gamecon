<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

use Gamecon\Web\NahranyObrazek;
use Gamecon\Web\NahravaniObrazku;

/**
 * Katalog kostýmů a jejich fotky. Fotky ukládá @see NahravaniObrazku (typ z obsahu, zmenšení, AVIF/WebP, bez metadat)
 * do web/soubory/systemove/kostymy, název souboru je v kostym_foto.
 */
final class KatalogKostymu
{
    private ?NahravaniObrazku $nahravaniFotek;

    public function __construct(?NahravaniObrazku $nahravaniFotek = null)
    {
        $this->nahravaniFotek = $nahravaniFotek;
    }

    /**
     * @return list<Kostym> podle pořadí a názvu
     */
    public function vyber(FiltrKatalogu $filtr = new FiltrKatalogu()): array
    {
        if ($filtr->jenIds === []) {
            return [];
        }
        $podminky = [];
        $parametry = [];
        if ($filtr->velikost !== null) {
            $podminky[] = 'kostym.velikost = $' . count($parametry);
            $parametry[] = $filtr->velikost->value;
        }
        if ($filtr->strih !== null) {
            $podminky[] = 'kostym.strih = $' . count($parametry);
            $parametry[] = $filtr->strih->value;
        }
        if ($filtr->kategorie !== null) {
            $podminky[] = 'kostym.kategorie = $' . count($parametry);
            $parametry[] = $filtr->kategorie;
        }
        if ($filtr->jenIds !== null) {
            $podminky[] = 'kostym.id IN ($' . count($parametry) . ')';
            $parametry[] = $filtr->jenIds;
        }
        if ($filtr->jenKDispozici) {
            $podminky[] = 'kostym.stav = $' . count($parametry);
            $parametry[] = StavKostymuEnum::K_DISPOZICI->value;
        }
        $kde = $podminky !== []
            ? 'WHERE ' . implode(' AND ', $podminky)
            : '';

        return $this->sFotkami(dbFetchAll(<<<SQL
SELECT * FROM kostym {$kde} ORDER BY kostym.poradi, kostym.nazev, kostym.id
SQL, $parametry));
    }

    public function dej(int $idKostymu): ?Kostym
    {
        return $this->sFotkami(dbFetchAll('SELECT * FROM kostym WHERE id = $0', [$idKostymu]))[0] ?? null;
    }

    /**
     * @return list<string> kategorie, které už nějaký kostým má (nabídka do formuláře a do filtru)
     */
    public function kategorie(): array
    {
        return array_map('strval', dbFetchColumn(<<<'SQL'
SELECT DISTINCT kategorie FROM kostym WHERE kategorie <> '' ORDER BY kategorie
SQL));
    }

    /**
     * @param array<string, mixed> $hodnoty pole formuláře: nazev, popis, velikost, miry, strih, kategorie, stav,
     *                                      interni_poznamka, poradi
     *
     * @return int ID kostýmu
     *
     * @throws ChybaPujcovny
     */
    public function uloz(array $hodnoty, ?int $idKostymu = null): int
    {
        $nazev = trim((string) ($hodnoty['nazev'] ?? ''));
        if ($nazev === '') {
            throw new ChybaPujcovny('Kostým musí mít název.');
        }
        $velikost = VelikostKostymuEnum::tryFrom((string) ($hodnoty['velikost'] ?? ''))
            ?? throw new ChybaPujcovny('Vyber velikost kostýmu.');
        $strih = StrihKostymuEnum::tryFrom((string) ($hodnoty['strih'] ?? ''))
            ?? throw new ChybaPujcovny('Vyber střih kostýmu.');
        $stav = StavKostymuEnum::tryFrom((string) ($hodnoty['stav'] ?? StavKostymuEnum::K_DISPOZICI->value))
            ?? throw new ChybaPujcovny('Neznámý stav kostýmu.');
        $sloupce = [
            'nazev'            => mb_substr($nazev, 0, 255),
            'popis'            => trim((string) ($hodnoty['popis'] ?? '')),
            'velikost'         => $velikost->value,
            'miry'             => mb_substr(trim((string) ($hodnoty['miry'] ?? '')), 0, 255),
            'strih'            => $strih->value,
            'kategorie'        => mb_substr(trim((string) ($hodnoty['kategorie'] ?? '')), 0, 255),
            'stav'             => $stav->value,
            'interni_poznamka' => trim((string) ($hodnoty['interni_poznamka'] ?? '')),
            'poradi'           => (int) ($hodnoty['poradi'] ?? 0),
        ];
        if ($idKostymu === null) {
            dbInsert('kostym', $sloupce);

            return (int) dbInsertId();
        }
        if ($this->dej($idKostymu) === null) {
            throw new ChybaPujcovny('Kostým neexistuje.');
        }
        dbUpdate('kostym', $sloupce, [
            'id' => $idKostymu,
        ]);

        return $idKostymu;
    }

    /**
     * Smazat jde jen kostým, který nikdy nikdo nerezervoval ani si nepůjčil. Jinak se vyřadí (stav „vyřazený“),
     * aby zůstala historie výdejů.
     *
     * @throws ChybaPujcovny
     */
    public function smaz(int $idKostymu): void
    {
        $kostym = $this->dej($idKostymu) ?? throw new ChybaPujcovny('Kostým neexistuje.');
        $pouzity = dbOneCol(<<<'SQL'
SELECT EXISTS(SELECT 1 FROM kostym_rezervace WHERE id_kostymu = $0)
    OR EXISTS(SELECT 1 FROM kostym_vydej WHERE id_kostymu = $0)
SQL, [$idKostymu]);
        if ($pouzity) {
            throw new ChybaPujcovny('Kostým má rezervace nebo záznamy o výdeji. Místo smazání ho vyřaď.');
        }
        foreach ($kostym->getFotky() as $fotka) {
            $this->smazSouborFotky($fotka);
        }
        dbQuery('DELETE FROM kostym WHERE id = $0', [$idKostymu]);
    }

    /**
     * @param NahranyObrazek $obrazek ověřený službou z @see nahravaniFotek(), třeba přes overNahranySoubor($_FILES[…])
     *
     * @throws \Gamecon\Web\NahravaniObrazkuException
     * @throws ChybaPujcovny
     */
    public function pridejFotku(int $idKostymu, NahranyObrazek $obrazek): FotkaKostymu
    {
        if ($this->dej($idKostymu) === null) {
            throw new ChybaPujcovny('Kostým neexistuje.');
        }
        $cesta = $this->nahravaniFotek()->ulozNovy($obrazek, '', $idKostymu . '-' . bin2hex(random_bytes(8)));
        $soubor = basename($cesta);
        dbQuery(<<<'SQL'
INSERT INTO kostym_foto (id_kostymu, soubor, poradi)
SELECT $0, $1, COALESCE(MAX(poradi), 0) + 1 FROM kostym_foto WHERE id_kostymu = $0
SQL, [$idKostymu, $soubor]);

        return new FotkaKostymu((int) dbInsertId(), $soubor);
    }

    /**
     * @throws ChybaPujcovny
     */
    public function smazFotku(int $idKostymu, int $idFotky): void
    {
        $soubor = dbOneCol('SELECT soubor FROM kostym_foto WHERE id = $0 AND id_kostymu = $1', [$idFotky, $idKostymu]);
        if ($soubor === null) {
            throw new ChybaPujcovny('Fotka neexistuje.');
        }
        $this->smazSouborFotky(new FotkaKostymu($idFotky, (string) $soubor));
        dbQuery('DELETE FROM kostym_foto WHERE id = $0', [$idFotky]);
    }

    /**
     * Fotku dá na první místo, tedy jako hlavní.
     */
    public function nastavHlavniFotku(int $idKostymu, int $idFotky): void
    {
        dbQuery(<<<'SQL'
UPDATE kostym_foto
SET poradi = IF(id = $1, 0, poradi + 1)
WHERE id_kostymu = $0
SQL, [$idKostymu, $idFotky]);
    }

    public function nahravaniFotek(): NahravaniObrazku
    {
        return $this->nahravaniFotek ??= NahravaniObrazku::proFotkyKostymu();
    }

    private function smazSouborFotky(FotkaKostymu $fotka): void
    {
        $nahravani = $this->nahravaniFotek();
        if (is_file($nahravani->cestaKSouboru($fotka->getSoubor()))) {
            $nahravani->smaz($fotka->getSoubor());
        }
    }

    /**
     * @param list<array<string, mixed>> $radky
     *
     * @return list<Kostym>
     */
    private function sFotkami(array $radky): array
    {
        if ($radky === []) {
            return [];
        }
        $fotkyPodleKostymu = [];
        foreach (dbFetchAll(<<<'SQL'
SELECT id, id_kostymu, soubor FROM kostym_foto WHERE id_kostymu IN ($0) ORDER BY poradi, id
SQL, [array_column($radky, 'id')]) as $fotka) {
            $fotkyPodleKostymu[(int) $fotka['id_kostymu']][] = new FotkaKostymu((int) $fotka['id'], (string) $fotka['soubor']);
        }

        return array_map(
            static fn (array $radek): Kostym => Kostym::zRadku($radek, $fotkyPodleKostymu[(int) $radek['id']] ?? []),
            $radky,
        );
    }
}
