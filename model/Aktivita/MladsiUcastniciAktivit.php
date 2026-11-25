<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSqlStruktura as PrihlaseniSql;

/**
 * Přihlášení účastníci, kterým v den aktivity ještě nebude její minimální věk (štítek „Věk: 15+“).
 * Pravidlo je stejné jako u upozornění před přihlášením (Aktivita::getNesplnenyMinimalniVek()), jen bez ohledu na to,
 * kdo účastníka přihlásil: výpisy v adminu i report ukazují každého, a jestli smí zůstat, rozhodne šéf sekce.
 * Sledující (akce_prihlaseni_spec) přihlášení nejsou, nepočítají se.
 */
class MladsiUcastniciAktivit
{
    /**
     * Jeden dotaz na přihlášené a jeden na jejich účty pro celou kolekci, štítky aktivit načte kolekce sama
     * (Aktivita::prednactiTagy()).
     *
     * @param iterable<Aktivita> $aktivity
     *
     * @return array<int, list<MladsiUcastnikAktivity>> podle ID aktivity, jen aktivity, na kterých někdo mladší je;
     *                                                  účastníci od nejmladšího
     */
    public static function najdi(iterable $aktivity): array
    {
        $aktivitySVekem = [];
        foreach ($aktivity as $aktivita) {
            if ($aktivita->zacatek() !== null && $aktivita->getMinimalniVek() !== null) {
                $aktivitySVekem[(int) $aktivita->id()] = $aktivita;
            }
        }
        if ($aktivitySVekem === []) {
            return [];
        }

        $prihlaseni = dbFetchAll(<<<'SQL'
            SELECT id_akce, id_uzivatele FROM akce_prihlaseni WHERE id_akce IN ($0)
            SQL, [
            0 => array_keys($aktivitySVekem),
        ]);
        $ucastniciPodleId = [];
        foreach (\Uzivatel::zIds(array_values(array_unique(array_map(
            static fn (array $radek) => (int) $radek[PrihlaseniSql::ID_UZIVATELE],
            $prihlaseni,
        )))) as $ucastnik) {
            $ucastniciPodleId[(int) $ucastnik->id()] = $ucastnik;
        }

        $mladsiPodleAktivity = [];
        foreach ($prihlaseni as $radek) {
            $aktivita = $aktivitySVekem[(int) $radek[PrihlaseniSql::ID_AKCE]];
            $ucastnik = $ucastniciPodleId[(int) $radek[PrihlaseniSql::ID_UZIVATELE]] ?? null;
            $mladsi = $ucastnik === null
                ? null
                : $aktivita->getMladsiUcastnik($ucastnik);
            if ($mladsi !== null) {
                $mladsiPodleAktivity[(int) $aktivita->id()][] = $mladsi;
            }
        }
        foreach ($mladsiPodleAktivity as &$mladsi) {
            usort($mladsi, static fn (
                MladsiUcastnikAktivity $prvni,
                MladsiUcastnikAktivity $druhy,
            ) => [$prvni->vek, $prvni->ucastnik->id()] <=> [$druhy->vek, $druhy->ucastnik->id()]);
        }
        unset($mladsi);

        return $mladsiPodleAktivity;
    }

    /**
     * Totéž pravidlo jako Aktivita::getNesplnenyMinimalniVek() bez přihlašujícího, jen z hodnot řádku: pro Seznam
     * přihlášených, který má účastníky, datum narození i štítky ve vlastním SQL a aktivity ani účty nenačítá.
     *
     * @param list<string> $stitkyVeku názvy štítků aktivity z kategorie omezení věku (KategorieTagu::OMEZENI_VEK)
     *
     * @return array{vek: int, minimalniVek: int}|null věk účastníka v den začátku aktivity a minimální věk aktivity,
     *                                                 když je mladší, jinak null
     */
    public static function getMladsiZHodnot(
        array $stitkyVeku,
        ?string $zacatek,
        ?string $datumNarozeni,
        ?string $registrovan,
    ): ?array {
        if (!(int) $zacatek
            || !\Uzivatel::isDatumNarozeniSkutecneKRegistraci((string) $datumNarozeni, (string) $registrovan)
        ) {
            return null;
        }
        $veky = array_filter(
            array_map(static fn (string $nazev) => Tag::getMinimalniVekZNazvu($nazev), $stitkyVeku),
            static fn (?int $vek) => $vek !== null,
        );
        if ($veky === []) {
            return null;
        }
        $minimalniVek = max($veky);
        $vek = (new \DateTimeImmutable($datumNarozeni))->diff(new \DateTimeImmutable($zacatek))->y;

        return $vek < $minimalniVek
            ? ['vek' => $vek, 'minimalniVek' => $minimalniVek]
            : null;
    }

    /**
     * Pro nápovědu ve výpisech v adminu: „Aktivita je od 15 let, v den aktivity bude míň: Jana (13), Petr (14)“.
     *
     * @param list<MladsiUcastnikAktivity> $mladsi z najdi(), všichni z jedné aktivity
     */
    public static function getPopis(array $mladsi): string
    {
        if ($mladsi === []) {
            return '';
        }

        return "Aktivita je od {$mladsi[0]->minimalniVek} let, v den aktivity bude míň: "
               . implode(', ', array_map(
                   static fn (MladsiUcastnikAktivity $mladsiUcastnik) => $mladsiUcastnik->ucastnik->jmenoNick()
                                                                        . " ({$mladsiUcastnik->vek})",
                   $mladsi,
               ));
    }
}
