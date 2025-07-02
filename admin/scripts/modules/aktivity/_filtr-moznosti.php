<?php

use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura;

class FiltrMoznosti
{
    public const FILTROVAT_PODLE_ROKU   = true;
    public const NEFILTROVAT_PODLE_ROKU = false;
    /**
     * @var int
     */
    private $filtrRoku;
    /**
     * @var int
     */
    private $letosniRok;
    /**
     * @var string
     */
    private $adminAktivityFiltr;
    /**
     * @var bool
     */
    private $filtrovatPodleRoku;
    private $posledniProExportPouzitelnyFiltrRoku;

    public static function vytvorZGlobals(bool $filtrovatPodleRoku): FiltrMoznosti {
        if (post('filtr')) {
            if (post('filtr') === 'vsechno') {
                unset($_SESSION['adminAktivityFiltr']);
            } else {
                $_SESSION['adminAktivityFiltr'] = post('filtr');
            }
        }

        //načtení aktivit a zpracování
        if (get('sort')) { //řazení
            setcookie('akceRazeni', get('sort'), (new \DateTimeImmutable('+1 year'))->getTimestamp());
            $_COOKIE['akceRazeni'] = get('sort');
        }

        if (post('filtrRoku')) {
            if (post('filtrRoku') === 'letos') {
                unset($_SESSION['adminAktivityFiltrRoku']);
            } else {
                $_SESSION['adminAktivityFiltrRoku'] = post('filtrRoku');
            }
        }

        $filtrRoku = $filtrovatPodleRoku && !empty($_SESSION['adminAktivityFiltrRoku']) && $_SESSION['adminAktivityFiltrRoku'] >= 2000 && $_SESSION['adminAktivityFiltrRoku'] <= ROCNIK
            ? $_SESSION['adminAktivityFiltrRoku']
            : ROCNIK;

        $adminAktivityFiltr = $_SESSION['adminAktivityFiltr'] ?? '';

        return new static($adminAktivityFiltr, ROCNIK, $filtrRoku, $filtrovatPodleRoku);
    }

    private function __construct(string $adminAktivityFiltr, int $letosniRok, int $filtrRoku, bool $filtrovatPodleRoku) {
        $this->adminAktivityFiltr = $adminAktivityFiltr;
        $this->letosniRok         = $letosniRok;
        $this->filtrRoku          = $filtrRoku;
        $this->filtrovatPodleRoku = $filtrovatPodleRoku;
    }

    public function zobraz() {
        $this->naplnTemplate()->out('filtr');
    }

    public function dejProTemplate() : string
    {
        return $this->naplnTemplate()->text('filtr');
    }

    private function naplnTemplate(): XTemplate {
        $tplFiltrMoznosti = new XTemplate(__DIR__ . '/_filtr-moznosti.xtpl');

        $posledniZobrazenyRok = null;
        if ($this->filtrovatPodleRoku) {
            $pocetAktivitVLetech  = $this->dejPoctyAktivitProTemplate();
            $posledniZobrazenyRok = $this->vyberPosledniZobrazenyRok($pocetAktivitVLetech);
            foreach ($pocetAktivitVLetech as $pocetAktivitVRoce) {
                $tplFiltrMoznosti->assign('rok', $pocetAktivitVRoce['rok']);
                $tplFiltrMoznosti->assign('nazevRoku', $pocetAktivitVRoce['nazevRoku']);
                $tplFiltrMoznosti->assign('pocetAktivit', $pocetAktivitVRoce['pocetAktivit']);
                $tplFiltrMoznosti->assign('selected', $this->filtrRoku == $pocetAktivitVRoce['rok']
                    ? 'selected="selected"'
                    : ''
                );
                $tplFiltrMoznosti->parse('filtr.roky.rok');
            }
            $tplFiltrMoznosti->parse('filtr.roky');
        }

        $proExportPouzitelnyFiltrRoku = $this->dejProExportPouzitelnyFiltrRoku($posledniZobrazenyRok);
        $programoveLinie              = $this->programoveLinie($proExportPouzitelnyFiltrRoku);
        foreach ($this->pocetAktivitProgramovychLinii($programoveLinie) as $idTypu => $varianta) {
            $tplFiltrMoznosti->assign('idTypu', $idTypu);
            $tplFiltrMoznosti->assign(
                'nazev_programove_linie',
                sprintf('%s (aktivit %d)', ucfirst($varianta['popis']) . ($this->filtrRoku != $this->letosniRok ? (' ' . $this->filtrRoku) : ''), $varianta['pocet_aktivit'])
            );
            $tplFiltrMoznosti->assign('selected', $this->adminAktivityFiltr == $idTypu
                ? 'selected="selected"'
                : ''
            );
            $tplFiltrMoznosti->parse('filtr.programoveLinie.programovaLinie');
        }
        $tplFiltrMoznosti->parse('filtr.programoveLinie');

        $tplFiltrMoznosti->parse('filtr');
        return $tplFiltrMoznosti;
    }

    private function dejProExportPouzitelnyFiltrRoku(?int $posledniZobrazenyRok) {
        $this->posledniProExportPouzitelnyFiltrRoku = $posledniZobrazenyRok
            ? min($posledniZobrazenyRok, $this->filtrRoku)
            : $this->filtrRoku;

        return $this->posledniProExportPouzitelnyFiltrRoku;
    }

    private function vyberPosledniZobrazenyRok(array $pocetAktivitVLetech): ?int {
        $roky = array_column($pocetAktivitVLetech, 'rok');
        return $roky ? (int)max($roky) : null;
    }

    private function dejPoctyAktivitProTemplate(): array {
        $posledniZobrazenyRok           = null;
        $poctyAktivitVLetechProTemplate = [];
        $poctyAktivitVLetech            = dbArrayCol('SELECT rok, COUNT(*) AS pocet FROM akce_seznam WHERE ROK > 2000 GROUP BY rok ORDER BY rok DESC');
        foreach ($poctyAktivitVLetech as $rok => $pocetAktivit) {
            $posledniZobrazenyRok             = max($posledniZobrazenyRok ?? 0, $rok);
            $nazevRoku                        = $rok == $this->letosniRok ? 'letos' : $rok;
            $poctyAktivitVLetechProTemplate[] = ['rok' => $rok, 'nazevRoku' => $nazevRoku, 'pocetAktivit' => $pocetAktivit];
        }
        return $poctyAktivitVLetechProTemplate;
    }

    private function programoveLinie(int $rok): array {
        return dbFetchAll(<<<SQL
SELECT *
FROM (
    SELECT akce_typy.id_typu, akce_typy.typ_1pmn AS nazev_typu, COUNT(*) AS pocet_aktivit, akce_typy.poradi AS poradi_typu
    FROM akce_seznam
    JOIN akce_typy ON akce_seznam.typ = akce_typy.id_typu
    WHERE akce_seznam.rok = $1
    GROUP BY akce_typy.id_typu
) AS seskupeno
ORDER BY poradi_typu
SQL
            , [$rok]
        );
    }

    private function pocetAktivitProgramovychLinii(array $typy): array {
        $varianty                             = ['vsechno' => ['popis' => '(všechno)']];
        $varianty['vsechno']['pocet_aktivit'] = $this->pocetAktivitCelkem($typy);
        foreach ($typy as $typ) {
            $varianty[$typ['id_typu']] = ['popis' => $typ['nazev_typu'], 'db' => $typ['id_typu'], 'pocet_aktivit' => $typ['pocet_aktivit']];
        }
        return $varianty;
    }

    private function pocetAktivitCelkem(array $typy): int {
        return (int)array_sum(array_column($typy, 'pocet_aktivit'));
    }

    public function dejFiltr(bool $pouzitFiltrRokuProExport = false): array {
        $razeni = [AkceSeznamSqlStruktura::NAZEV_AKCE, AkceSeznamSqlStruktura::ZACATEK];
        if (!empty($_COOKIE['akceRazeni'])) {
            array_unshift($razeni, $_COOKIE['akceRazeni']);
        }
        $razeni    = array_map(static function ($raditPodle) {
            return urldecode((string)$raditPodle); // například organizatori+ASC = organizatori ASC
        }, $razeni);
        $filtrRoku = $pouzitFiltrRokuProExport
            ? $this->posledniProExportPouzitelnyFiltrRoku
            : $this->filtrRoku;

        $programoveLinie = $this->programoveLinie($filtrRoku);
        $varianty        = $this->pocetAktivitProgramovychLinii($programoveLinie);
        $filtr           = empty($varianty[$this->adminAktivityFiltr]['db'])
            ? []
            : [FiltrAktivity::TYP => $varianty[$this->adminAktivityFiltr]['db']];
        $filtr[FiltrAktivity::ROK]    = $filtrRoku;

        return [$filtr, $razeni];
    }
}
