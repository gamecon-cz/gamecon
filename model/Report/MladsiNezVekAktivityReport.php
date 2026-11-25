<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezenceTyp;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\MladsiUcastniciAktivit;
use Gamecon\Aktivita\MladsiUcastnikAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Report zazemi-a-program-mladsi-nez-vek-aktivity („Zázemí & Program: Přihlášení mladší než věk aktivity“):
 * letošní přihlášení, kterým v den aktivity ještě nebude její minimální věk (štítek „Věk: 15+“), viz
 * MladsiUcastniciAktivit. Každý, i koho přihlásil admin (ten má výjimku nejspíš domluvenou, proto sloupec Přihlásil).
 * Skript reportu jen vypíše řádky, tady jsou kvůli testu: hlavička reportů přepíná na spojení jen pro čtení.
 */
class MladsiNezVekAktivityReport
{
    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    /**
     * @return list<string>
     */
    public function getHlavicky(): array
    {
        return [
            'Linie',
            'Šéf sekce',
            'ID aktivity',
            'Aktivita',
            'Začátek',
            'Od kolika let',
            'Vypravěči',
            'ID účastníka',
            'Login',
            'Jméno',
            'Příjmení',
            'E-mail',
            'Telefon',
            'Datum narození',
            'Věk v den aktivity',
            'Přihlášen',
            'Přihlásil',
        ];
    }

    /**
     * Po liniích jako v menu Seznamu přihlášených (pořadí linie), v linii podle času aktivity, na aktivitě od nejmladšího.
     *
     * @return list<list<string|int|null>> sloupce jako getHlavicky()
     */
    public function getRadky(): array
    {
        $mladsiPodleAktivity = MladsiUcastniciAktivit::najdi(Aktivita::zFiltru(
            systemoveNastaveni: $this->systemoveNastaveni,
            filtr: [
                FiltrAktivity::ROK => $this->systemoveNastaveni->rocnik(),
            ],
        ));
        /** @var list<MladsiUcastnikAktivity> $mladsiUcastnici */
        $mladsiUcastnici = array_merge([], ...array_values($mladsiPodleAktivity));
        $razeni = static fn (MladsiUcastnikAktivity $mladsi) => [
            (int) $mladsi->aktivita->typ()->poradi(),
            $mladsi->aktivita->typId(),
            $mladsi->aktivita->zacatek(),
            $mladsi->aktivita->id(),
            $mladsi->vek,
            $mladsi->ucastnik->id(),
        ];
        usort($mladsiUcastnici, static fn (
            MladsiUcastnikAktivity $prvni,
            MladsiUcastnikAktivity $druhy,
        ) => $razeni($prvni) <=> $razeni($druhy));

        $posledniPrihlaseni = $this->najdiPosledniPrihlaseni(array_keys($mladsiPodleAktivity));
        $prihlasujiciPodleId = [];
        foreach (\Uzivatel::zIds(array_values(array_unique(array_filter(array_map(
            static fn (array $zaznam) => (int) $zaznam['id_zmenil'],
            $posledniPrihlaseni,
        ))))) as $prihlasujici) {
            $prihlasujiciPodleId[(int) $prihlasujici->id()] = $prihlasujici;
        }

        $radky = [];
        foreach ($mladsiUcastnici as $mladsi) {
            $aktivita = $mladsi->aktivita;
            $ucastnik = $mladsi->ucastnik;
            $zaznam = $posledniPrihlaseni[$aktivita->id() . '-' . $ucastnik->id()] ?? null;
            $idZmenil = (int) ($zaznam['id_zmenil'] ?? 0);

            $radky[] = [
                $aktivita->typ()->nazev(),
                // KontaktSefaLinie si hlavičky linií pamatuje, dotaz je jeden
                $aktivita->getKontaktSefaSekce()->getEmail(),
                $aktivita->id(),
                $aktivita->nazev(),
                $aktivita->zacatek()?->format('Y-m-d H:i'),
                $mladsi->minimalniVek,
                implode(', ', array_map(
                    static fn (\Uzivatel $vypravec) => (string) $vypravec->jmenoNick(),
                    $aktivita->organizatori(),
                )),
                $ucastnik->id(),
                $ucastnik->login(),
                $ucastnik->krestniJmeno(),
                $ucastnik->prijmeni(),
                $ucastnik->mail(),
                $ucastnik->telefon(),
                $ucastnik->datumNarozeni()->format('Y-m-d'),
                $mladsi->vek,
                $zaznam['kdy'] ?? '',
                match (true) {
                    $idZmenil === 0                     => '',
                    $idZmenil === (int) $ucastnik->id() => 'sám',
                    default                             => (string) (($prihlasujiciPodleId[$idZmenil] ?? null)?->jmenoNick() ?? $idZmenil),
                },
            ];
        }

        return $radky;
    }

    /**
     * Kdo a kdy účastníka přihlásil: poslední přihlášení z logu (mohl se mezitím odhlásit a přihlásit znovu).
     *
     * @param list<int> $idAktivit
     *
     * @return array<string, array{id_akce: int|string, id_uzivatele: int|string, kdy: string, id_zmenil: int|string}>
     *     podle "<id aktivity>-<id účastníka>"
     */
    private function najdiPosledniPrihlaseni(array $idAktivit): array
    {
        if ($idAktivit === []) {
            return [];
        }
        $posledniPrihlaseni = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT id_akce, id_uzivatele, kdy, id_zmenil
            FROM akce_prihlaseni_log
            WHERE id_akce IN ($0) AND typ IN ($1)
            ORDER BY id_log
            SQL, [
            0 => $idAktivit,
            1 => [AktivitaPrezenceTyp::PRIHLASENI, AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK],
        ]) as $zaznam) {
            $posledniPrihlaseni[$zaznam['id_akce'] . '-' . $zaznam['id_uzivatele']] = $zaznam;
        }

        return $posledniPrihlaseni;
    }
}
